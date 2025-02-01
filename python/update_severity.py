#!/usr/bin/env python3
"""
update_severity.py

This script updates the severity in the MetricLog table for rows where severity == -1.
It uses the fine-tuned severity classification model from the folder "severity_model_v3" to predict severity
based solely on the topic title. The predicted severity (1-10) is then written back to the database.

Usage:
    python update_severity.py
"""

import os
import sys
import logging
from datetime import datetime
import mysql.connector
from mysql.connector import errorcode
from transformers import pipeline
from dotenv import load_dotenv
from pathlib import Path

############################
# CONFIGURATION & ENVIRONMENT
############################

# Load environment variables from .env (assumed to be one directory level up)
script_path = Path(__file__).resolve()
parent_dir = script_path.parent.parent
dotenv_path = parent_dir / '.env'
load_dotenv(dotenv_path=dotenv_path)

# Database credentials
DB_HOST = os.getenv("DB_HOST")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")
DB_NAME = os.getenv("DB_NAME")

required_vars = ["DB_HOST", "DB_USER", "DB_PASSWORD", "DB_NAME"]
missing_vars = [var for var in required_vars if not os.getenv(var)]
if missing_vars:
    raise EnvironmentError(f"Missing required environment variables: {', '.join(missing_vars)}")

# Configure logging
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger(__name__)

# Path to the fine-tuned model folder
MODEL_PATH = "./severity_model_v3"

# Confidence threshold (adjust as needed)
CONFIDENCE_THRESHOLD = 0.2

############################
# DATABASE FUNCTIONS
############################

def connect_to_db():
    """Connect to the MySQL database."""
    try:
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME
        )
        logger.info("Connected to database.")
        return conn
    except mysql.connector.Error as err:
        if err.errno == errorcode.ER_ACCESS_DENIED_ERROR:
            logger.error("Invalid credentials.")
        elif err.errno == errorcode.ER_BAD_DB_ERROR:
            logger.error("Database does not exist.")
        else:
            logger.error(err)
        sys.exit(1)

def fetch_unscored_entries(conn):
    """Fetch rows from MetricLog where severity = -1."""
    query = "SELECT logID, topicID FROM MetricLog WHERE severity = -1"
    cursor = conn.cursor(dictionary=True)
    cursor.execute(query)
    rows = cursor.fetchall()
    cursor.close()
    logger.info(f"Fetched {len(rows)} rows with severity == -1.")
    return rows

def fetch_topic_text(conn, topic_id):
    """Fetch the topic text from the Topic table given topicID."""
    query = "SELECT topic FROM Topic WHERE topicID = %s"
    cursor = conn.cursor()
    cursor.execute(query, (topic_id,))
    row = cursor.fetchone()
    cursor.close()
    if row:
        return row[0].strip()
    else:
        return ""

def update_severity(conn, log_id, new_severity):
    """Update the severity for a given logID in the MetricLog table."""
    query = "UPDATE MetricLog SET severity = %s WHERE logID = %s"
    cursor = conn.cursor()
    try:
        cursor.execute(query, (new_severity, log_id))
        conn.commit()
        logger.info(f"Updated logID {log_id} to severity {new_severity}.")
    except mysql.connector.Error as err:
        logger.error(f"Error updating logID {log_id}: {err}")
        conn.rollback()
    finally:
        cursor.close()

############################
# CLASSIFICATION FUNCTIONS
############################

def classify_topic(classifier, topic_text):
    """
    Use the fine-tuned classifier to predict severity based on the topic text.
    Expects the classifier to return a label in the format 'LABEL_X'.
    Returns (severity, predicted_label, confidence).
    """
    if not topic_text:
        return 5, "N/A", 0.0

    # Pass the raw topic text (without any additional prompt)
    result = classifier(topic_text)
    logger.debug(f"Raw classification output for '{topic_text}': {result}")
    predicted_label = result[0]['label']  # e.g., "LABEL_4"
    confidence = result[0]['score']
    try:
        label_index = int(predicted_label.split('_')[-1])
    except (ValueError, IndexError):
        logger.error(f"Unexpected label format: {predicted_label}. Defaulting severity to 5.")
        return 5, predicted_label, confidence

    severity = label_index + 1  # Map from class 0-9 to severity 1-10
    return severity, predicted_label, confidence

############################
# MAIN PROCESSING
############################

def main():
    conn = connect_to_db()
    try:
        entries = fetch_unscored_entries(conn)
        if not entries:
            logger.info("No rows to update. Exiting.")
            return

        logger.info(f"Loading fine-tuned classifier from {MODEL_PATH} ...")
        classifier = pipeline("text-classification", model=MODEL_PATH, tokenizer=MODEL_PATH)
        logger.info("Classifier loaded.")

        for row in entries:
            log_id = row["logID"]
            topic_id = row["topicID"]

            topic_text = fetch_topic_text(conn, topic_id)
            if not topic_text:
                logger.warning(f"logID {log_id}: No topic text found. Skipping.")
                continue

            severity, label, conf = classify_topic(classifier, topic_text)
            logger.info(f"logID {log_id}: Topic: '{topic_text}' => Predicted severity: {severity} (label: {label}, confidence: {conf:.2f})")

            # If confidence is low, fallback to a neutral severity (e.g., 5)
            if conf < CONFIDENCE_THRESHOLD:
                logger.warning(f"logID {log_id}: Confidence {conf:.2f} is below threshold. Using fallback severity 5.")
                severity = 5

            update_severity(conn, log_id, severity)

    finally:
        conn.close()
        logger.info("Database connection closed.")

if __name__ == "__main__":
    main()
