#!/usr/bin/env python3
"""
condense_metric_log.py

This script condenses/summarises topics from the MetricLog table for a given setID and date.
It assigns each topic to an existing CondensedTopic or creates a new one based on similarity.
When creating a new CondensedTopic, it assigns the category based on the majority category
of the merged topics. In case of a tie, it uses an NLP-based approach to determine the most applicable category.
The condensed data is then inserted into the MetricLogCondensed table.

Usage:
    python3 condense_metric_log.py --setID <set_id> --date <YYYY-MM-DD>
"""

import os
import mysql.connector
from mysql.connector import errorcode
import argparse
import logging
import sys
from datetime import datetime
from sentence_transformers import SentenceTransformer
from sklearn.metrics.pairwise import cosine_similarity
import numpy as np
from collections import Counter

from dotenv import load_dotenv  # NEW: Import dotenv to load .env variables
from pathlib import Path            # NEW: Import Path for path manipulations

# ---------------------------------------------------
# 0) Setup
# ---------------------------------------------------

# Determine the path to the parent directory
script_path = Path(__file__).resolve()
parent_dir = script_path.parent.parent  # One directory level up

# Define the path to the .env file
dotenv_path = parent_dir / '.env'

# Load environment variables from the .env file
load_dotenv(dotenv_path=dotenv_path)

# Fetch MySQL Database credentials from environment variables
DB_HOST = os.getenv("DB_HOST")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")
DB_NAME = os.getenv("DB_NAME")

# Validate that all required environment variables are set
required_vars = [
    "DB_HOST", "DB_USER", "DB_PASSWORD", "DB_NAME"
]

missing_vars = [var for var in required_vars if os.getenv(var) is None]
if missing_vars:
    raise EnvironmentError(f"Missing environment variables: {', '.join(missing_vars)}")

# Configure logging
logging.basicConfig(
    level=logging.INFO,  # Change to DEBUG for more detailed logs
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

# Similarity threshold for topic condensation
SIMILARITY_THRESHOLD = 0.5  # Adjust as needed

# Predefined priority list for categories in case of a tie
CATEGORY_PRIORITY = [
    "Customer Satisfaction",
    "Financial Performance",
    "Product Quality",
    "Environment",
    "Legislation",
    "Competition",
    "Workplace Conditions",
    "Supply Chain",
    "Technology",
    "Miscellaneous"  # Default fallback
]

def parse_arguments():
    """Parse command-line arguments."""
    parser = argparse.ArgumentParser(description="Condense MetricLog topics into MetricLogCondensed.")
    parser.add_argument('--setID', type=int, required=True, help='The setID of the TrackedEntity.')
    parser.add_argument('--date', type=str, required=True, help='The date in YYYY-MM-DD format.')
    return parser.parse_args()

def connect_to_db():
    """Establish a connection to the MySQL database."""
    try:
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME
        )
        logger.info("Successfully connected to the database.")
        return conn
    except mysql.connector.Error as err:
        if err.errno == errorcode.ER_ACCESS_DENIED_ERROR:
            logger.error("Invalid database credentials.")
        elif err.errno == errorcode.ER_BAD_DB_ERROR:
            logger.error("Database does not exist.")
        else:
            logger.error(f"Database connection error: {err}")
        sys.exit(1)

def check_existing_condensed(conn, set_id, date_str):
    """Check if MetricLogCondensed already has entries for the given setID and date."""
    query = """
        SELECT COUNT(*) FROM MetricLogCondensed
        WHERE setID = %s AND date = %s
    """
    cursor = conn.cursor()
    cursor.execute(query, (set_id, date_str))
    count = cursor.fetchone()[0]
    cursor.close()
    if count > 0:
        logger.info(f"MetricLogCondensed already has {count} entries for setID {set_id} on {date_str}. Exiting.")
        sys.exit(0)

def fetch_metric_logs(conn, set_id, date_str):
    """Fetch MetricLog entries for the given setID and date, including topic and category."""
    query = """
        SELECT ml.logID, t.topic, t.category, ml.adjectiveID, ml.impressions, ml.severity, ml.explanation
        FROM MetricLog ml
        JOIN Topic t ON ml.topicID = t.topicID
        WHERE ml.setID = %s AND ml.date = %s
    """
    cursor = conn.cursor(dictionary=True)
    cursor.execute(query, (set_id, date_str))
    rows = cursor.fetchall()
    cursor.close()
    for row in rows:
        # Normalise the topic by applying title() method
        row['topic'] = row['topic'].title()
    logger.info(f"Fetched {len(rows)} MetricLog entries for setID {set_id} on {date_str}.")
    return rows

def fetch_existing_condensed_topics(conn, model):
    """Fetch all existing CondensedTopics and compute their embeddings."""
    query = """
        SELECT condensedTopicID, condensedTopic FROM CondensedTopic
    """
    cursor = conn.cursor(dictionary=True)
    cursor.execute(query)
    rows = cursor.fetchall()
    cursor.close()
    topics = [row['condensedTopic'] for row in rows]
    ids = [row['condensedTopicID'] for row in rows]
    if topics:
        embeddings = model.encode(topics, convert_to_tensor=True).cpu().detach().numpy()
    else:
        embeddings = np.array([])
    logger.info(f"Fetched and embedded {len(topics)} existing CondensedTopics.")
    return dict(zip(ids, topics)), embeddings

def compute_embedding(model, text):
    """Compute the embedding for a given text."""
    return model.encode([text], convert_to_tensor=True).cpu().detach().numpy()

def find_best_match(topic_embedding, condensed_embeddings, condensed_ids, threshold=SIMILARITY_THRESHOLD):
    """Find the best matching condensedTopicID for a given topic embedding."""
    if len(condensed_embeddings) == 0:
        return None
    similarities = cosine_similarity(topic_embedding, condensed_embeddings)[0]
    best_idx = np.argmax(similarities)
    best_score = similarities[best_idx]
    if best_score >= threshold:
        return condensed_ids[best_idx], best_score
    return None

def create_condensed_topic(conn, topic, category):
    """Insert a new CondensedTopic and return its ID."""
    query = """
        INSERT INTO CondensedTopic (condensedTopic, category) VALUES (%s, %s)
    """
    cursor = conn.cursor()
    try:
        cursor.execute(query, (topic, category))
        conn.commit()
        condensed_topic_id = cursor.lastrowid
        logger.info(f"Created new CondensedTopic '{topic}' with ID {condensed_topic_id} and category '{category}'.")
        return condensed_topic_id
    except mysql.connector.Error as err:
        logger.error(f"Error creating CondensedTopic: {err}")
        conn.rollback()
        sys.exit(1)
    finally:
        cursor.close()

def aggregate_metric_logs(metric_logs, condensed_mapping):
    """
    Aggregate MetricLog entries by condensedTopicID and adjectiveID.

    Returns a dictionary with keys as (condensedTopicID, adjectiveID) and values as aggregated data.
    """
    aggregation = {}
    for log in metric_logs:
        condensed_id = condensed_mapping[log['logID']]['condensedTopicID']
        adjective_id = log['adjectiveID']
        key = (condensed_id, adjective_id)
        if key not in aggregation:
            aggregation[key] = {
                'impressions': 0,
                'severity_sum': 0,
                'severity_count': 0,
                'explanations': [],
                'categories': []
            }
        aggregation[key]['impressions'] += log['impressions']
        aggregation[key]['severity_sum'] += log['severity']
        aggregation[key]['severity_count'] += 1
        aggregation[key]['explanations'].append(log['explanation'])
        aggregation[key]['categories'].append(log['category'])
    logger.info(f"Aggregated MetricLog entries into {len(aggregation)} MetricLogCondensed entries.")
    return aggregation

def insert_metric_log_condensed(conn, set_id, date_str, aggregation):
    """Insert aggregated MetricLogCondensed entries into the database."""
    query = """
        INSERT INTO MetricLogCondensed
        (setID, condensedTopicID, adjectiveID, impressions, date, severity, explanation)
        VALUES (%s, %s, %s, %s, %s, %s, %s)
    """
    data = []
    for (condensed_id, adjective_id), agg in aggregation.items():
        # Determine majority category
        category_counter = Counter(agg['categories'])
        most_common = category_counter.most_common()
        if not most_common:
            majority_category = "Miscellaneous"
        else:
            top_count = most_common[0][1]
            top_categories = [cat for cat, cnt in most_common if cnt == top_count]
            if len(top_categories) == 1:
                majority_category = top_categories[0]
            else:
                # Tie-breaker using NLP-based semantic similarity
                majority_category = resolve_tie_with_nlp(conn, condensed_id, agg['explanations'], top_categories)
        
        # Calculate average severity
        avg_severity = int(round(agg['severity_sum'] / agg['severity_count']))  # Averaging severity
        
        # Combine explanations and include majority category
        combined_explanations = f"Category: {majority_category} | " + " | ".join(agg['explanations'])
        
        data.append((
            set_id,
            condensed_id,
            adjective_id,
            agg['impressions'],
            date_str,
            avg_severity,
            combined_explanations
        ))
    
    cursor = conn.cursor()
    try:
        cursor.executemany(query, data)
        conn.commit()
        logger.info(f"Inserted {len(data)} entries into MetricLogCondensed.")
    except mysql.connector.Error as err:
        logger.error(f"Error inserting into MetricLogCondensed: {err}")
        conn.rollback()
        sys.exit(1)
    finally:
        cursor.close()

def resolve_tie_with_nlp(conn, condensed_id, explanations, tied_categories):
    """
    Resolve category tie using NLP-based semantic similarity.

    Args:
        conn: Database connection.
        condensed_id: The CondensedTopicID.
        explanations: List of explanations from MetricLog entries.
        tied_categories: List of categories with the same top count.

    Returns:
        The resolved majority category.
    """
    # Fetch the condensedTopic text
    query = """
        SELECT condensedTopic FROM CondensedTopic WHERE condensedTopicID = %s
    """
    cursor = conn.cursor()
    cursor.execute(query, (condensed_id,))
    result = cursor.fetchone()
    cursor.close()
    if not result:
        logger.warning(f"CondensedTopicID {condensed_id} not found. Defaulting to first tied category.")
        return tied_categories[0]
    condensed_topic_text = result[0]

    # Initialise the NLP model
    try:
        model = SentenceTransformer('all-mpnet-base-v2')  # Ensure this model is loaded only once if possible
    except Exception as e:
        logger.error(f"Error loading NLP model for tie resolution: {e}")
        return tied_categories[0]

    # Compute embedding for the condensed topic
    topic_embedding = model.encode([condensed_topic_text], convert_to_tensor=True).cpu().detach().numpy()

    # Compute embeddings for tied categories
    category_embeddings = model.encode(tied_categories, convert_to_tensor=True).cpu().detach().numpy()

    # Compute similarities
    similarities = cosine_similarity(topic_embedding, category_embeddings)[0]

    # Find the category with the highest similarity
    best_idx = np.argmax(similarities)
    best_score = similarities[best_idx]
    resolved_category = tied_categories[best_idx]

    logger.info(f"Tie resolved using NLP: Selected category '{resolved_category}' with similarity score {best_score:.2f}.")

    return resolved_category

def main():
    # Parse arguments
    args = parse_arguments()
    set_id = args.setID
    date_str = args.date

    # Validate date format
    try:
        datetime.strptime(date_str, "%Y-%m-%d")
    except ValueError:
        logger.error("Invalid date format. Please use YYYY-MM-DD.")
        sys.exit(1)

    # Connect to database
    conn = connect_to_db()

    try:
        # Check for existing condensed entries
        check_existing_condensed(conn, set_id, date_str)

        # Fetch MetricLog entries
        metric_logs = fetch_metric_logs(conn, set_id, date_str)
        if not metric_logs:
            logger.info("No MetricLog entries found for the given setID and date. Exiting.")
            sys.exit(0)

        # Load NLP model
        logger.info("Loading NLP model for sentence embeddings...")
        model = SentenceTransformer('all-MiniLM-L6-v2')  # Lightweight and efficient
        logger.info("NLP model loaded.")

        # Fetch existing CondensedTopics and their embeddings
        condensed_topics_dict, condensed_embeddings = fetch_existing_condensed_topics(conn, model)
        condensed_ids = list(condensed_topics_dict.keys())

        # Mapping from logID to condensedTopicID
        condensed_mapping = {}

        # Process each MetricLog entry
        for log in metric_logs:
            log_id = log['logID']
            topic = log['topic']
            category = log['category']
            # Compute embedding for the topic
            topic_embedding = compute_embedding(model, topic)
            # Find best match
            match = find_best_match(topic_embedding, condensed_embeddings, condensed_ids)
            if match:
                matched_id, score = match
                condensed_mapping[log_id] = {'condensedTopicID': matched_id, 'score': score}
                logger.debug(f"LogID {log_id}: Matched with CondensedTopicID {matched_id} (Score: {score:.2f})")
            else:
                # Create new CondensedTopic with the topic's category
                new_condensed_id = create_condensed_topic(conn, topic, category)
                # Update condensed_topics_dict and condensed_embeddings
                condensed_topics_dict[new_condensed_id] = topic
                # Compute and append new embedding
                new_embedding = compute_embedding(model, topic)
                if condensed_embeddings.size == 0:
                    condensed_embeddings = new_embedding
                else:
                    condensed_embeddings = np.vstack([condensed_embeddings, new_embedding])
                condensed_ids.append(new_condensed_id)
                condensed_mapping[log_id] = {'condensedTopicID': new_condensed_id, 'score': None}

        # Aggregate MetricLog entries
        aggregation = aggregate_metric_logs(metric_logs, condensed_mapping)

        # Insert into MetricLogCondensed
        insert_metric_log_condensed(conn, set_id, date_str, aggregation)

    finally:
        # Close the database connection
        conn.close()
        logger.info("Database connection closed.")

if __name__ == "__main__":
    main()
