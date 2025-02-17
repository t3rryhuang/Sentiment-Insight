import os
import praw
import datetime
import time
import re
import string
import hashlib
import json
from rapidfuzz.fuzz import partial_ratio
from rapidfuzz.fuzz import token_sort_ratio
import torch
import ollama  # For interacting with DeepSeek-R1:8B

import nltk
nltk.download('punkt', quiet=True)
from nltk.tokenize import sent_tokenize

import spacy
from transformers import (
    AutoTokenizer, 
    AutoModelForSequenceClassification, 
    pipeline, 
    T5ForConditionalGeneration, 
    T5Tokenizer
)

import mysql.connector

from rake_nltk import Rake
from collections import defaultdict

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

# Fetch Reddit API credentials from environment variables
REDDIT_CLIENT_ID = os.getenv("REDDIT_CLIENT_ID")
REDDIT_CLIENT_SECRET = os.getenv("REDDIT_CLIENT_SECRET")
REDDIT_USER_AGENT = os.getenv("REDDIT_USER_AGENT")

# Fetch MySQL Database credentials from environment variables
DB_HOST = os.getenv("DB_HOST")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")
DB_NAME = os.getenv("DB_NAME")

# Validate that all required environment variables are set
required_vars = [
    "REDDIT_CLIENT_ID", "REDDIT_CLIENT_SECRET", "REDDIT_USER_AGENT",
    "DB_HOST", "DB_USER", "DB_PASSWORD", "DB_NAME"
]

missing_vars = [var for var in required_vars if os.getenv(var) is None]
if missing_vars:
    raise EnvironmentError(f"Missing environment variables: {', '.join(missing_vars)}")

# Load spaCy model
nlp = spacy.load("en_core_web_sm")

# Initialize RAKE with NLTK's default stopwords
rake_extractor = Rake()

# Load emotion analysis model
MODEL_NAME = "j-hartmann/emotion-english-distilroberta-base"
emotion_tokenizer = AutoTokenizer.from_pretrained(MODEL_NAME)
emotion_model = AutoModelForSequenceClassification.from_pretrained(MODEL_NAME)
emotion_pipeline = pipeline(
    "text-classification",
    model=emotion_model,
    tokenizer=emotion_tokenizer,
    top_k=None  # Get all classification scores
)

# Initialize T5 tokenizer and model for paraphrasing (if needed)
paraphrase_model_name = "t5-small"  # Change to "t5-base" or "t5-large" if needed
paraphrase_tokenizer = T5Tokenizer.from_pretrained(paraphrase_model_name)
paraphrase_model = T5ForConditionalGeneration.from_pretrained(paraphrase_model_name)
paraphrase_model.eval()  # Set to evaluation mode

# Initialize Ollama for DeepSeek-R1:8B
OLLAMA_MODEL = "deepseek-r1:8b"

# Predefined categories for topics
TOPIC_CATEGORIES = [
    "Environment", "Customer Satisfaction", "Legislation", "Competition",
    "Workplace Conditions", "Product Quality", "Supply Chain", "Technology",
    "Financial Performance", "Corporate Governance"
]

# Initialize Zero-Shot Classification pipeline
zero_shot_classifier = pipeline(
    "zero-shot-classification", 
    model="facebook/bart-large-mnli"
)

# Initialize a cache dictionary (optional if used)
topic_name_cache = {}

def load_topic_name_cache(filepath='topic_name_cache.json'):
    global topic_name_cache
    try:
        with open(filepath, 'r') as f:
            topic_name_cache = json.load(f)
    except FileNotFoundError:
        topic_name_cache = {}

def save_topic_name_cache(filepath='topic_name_cache.json'):
    with open(filepath, 'w') as f:
        json.dump(topic_name_cache, f)

# ---------------------------------------------------
# 1) Reddit Setup
# ---------------------------------------------------
reddit = praw.Reddit(
    client_id=REDDIT_CLIENT_ID,         # UPDATED: Use environment variable
    client_secret=REDDIT_CLIENT_SECRET, # UPDATED: Use environment variable
    user_agent=REDDIT_USER_AGENT,       # UPDATED: Use environment variable
    check_for_async=False
)

request_count = 0
last_checked_time = time.time()

def rate_limiter():
    global request_count, last_checked_time
    current_time = time.time()
    if current_time - last_checked_time > 60:
        request_count = 0
        last_checked_time = current_time

    if request_count >= 100:
        sleep_time = 60 - (current_time - last_checked_time)
        print(f"Rate limit approached, sleeping for {sleep_time:.2f} seconds.")
        time.sleep(sleep_time + 1)
        last_checked_time = time.time()
        request_count = 0

# ---------------------------------------------------
# 2) DB Setup
# ---------------------------------------------------
db_connection = mysql.connector.connect(
    host=DB_HOST,           # UPDATED: Use environment variable
    user=DB_USER,           # UPDATED: Use environment variable
    password=DB_PASSWORD,   # UPDATED: Use environment variable
    database=DB_NAME        # UPDATED: Use environment variable
)
db_cursor = db_connection.cursor()

# Define a dictionary for rephrasing internet abbreviations and slang
REPHRASE_MAPPING = {
    "u": "you",
    "ur": "your",
    "r": "are",
    "lol": "laughing out loud",
    "idk": "I do not know",
    "imho": "in my humble opinion",
    "btw": "by the way",
    "tbh": "to be honest",
    "omg": "oh my god",
    "thx": "thanks",
    "pls": "please",
    "plz": "please",
    "gr8": "great",
    "b4": "before",
    "lmao": "laughing my ass off",
    "rofl": "rolling on the floor laughing",
    "brb": "be right back",
    "afk": "away from keyboard",
    "smh": "shaking my head",
    "nvm": "never mind",
    "ttyl": "talk to you later",
    "fyi": "for your information",
    "jk": "just kidding",
    "wtf": "what the fuck",
    "bff": "best friends forever",
    "ftw": "for the win",
    "tmi": "too much information",
    "sry": "sorry",
    "omw": "on my way",
    "bae": "before anyone else",
    "goat": "greatest of all time",
    "lit": "exciting",
    "salty": "bitter",
    "savage": "fierce",
    "sksksk": "laughter or excitement",
    "stan": "an extremely devoted fan",
    # Add more mappings as needed
}

def clean_text(text):
    """
    Cleans the input text by removing URLs, punctuation, numbers, special characters, and extra whitespace.
    Converts text to lowercase.
    """
    # Remove URLs
    text = re.sub(r'http\S+', '', text)
    # Remove special characters and punctuation
    text = re.sub(r'[^A-Za-z\s]', '', text)
    # Remove numbers
    text = re.sub(r'\d+', '', text)
    # Convert to lowercase
    text = text.lower()
    # Remove extra whitespace
    text = re.sub(r'\s+', ' ', text).strip()
    return text

def paraphrase_text(text, max_length=100):
    """
    Rephrases the input text using the T5 model to ensure grammatical correctness and consistency.
    
    Args:
        text (str): The input text to rephrase.
        max_length (int): The maximum length of the generated paraphrased text.
    
    Returns:
        str: The paraphrased text.
    """
    # Prepare the text for T5
    preprocessed_text = "paraphrase: " + text + " </s>"
    encoding = paraphrase_tokenizer.encode_plus(
        preprocessed_text,
        max_length=512,
        padding='max_length',
        truncation=True,
        return_tensors="pt",
    )
    
    input_ids, attention_masks = encoding["input_ids"], encoding["attention_mask"]
    
    # Generate paraphrased output with deterministic settings
    with torch.no_grad():
        outputs = paraphrase_model.generate(
            input_ids=input_ids,
            attention_mask=attention_masks,
            max_length=max_length,
            num_beams=5,          # Beam search for better quality
            num_return_sequences=1,
            temperature=0.7,      # Lower temperature for less randomness
            early_stopping=True,
            no_repeat_ngram_size=2,
        )
    
    # Decode the generated text
    paraphrased_text = paraphrase_tokenizer.decode(outputs[0], skip_special_tokens=True)
    
    return paraphrased_text

def rephrase_text_mapping(text, mapping=REPHRASE_MAPPING):
    """
    Rephrases the input text by expanding internet abbreviations and slang based on a predefined mapping.
    
    Args:
        text (str): The input text to rephrase.
        mapping (dict): A dictionary mapping abbreviations/slang to their standard forms.
        
    Returns:
        str: The rephrased text.
    """
    # Iterate over the mapping and replace whole words only
    for abbr, full in mapping.items():
        pattern = r'\b' + re.escape(abbr) + r'\b'
        text = re.sub(pattern, full, text)
    
    return text

def filter_spam(text):
    text_lower = text.lower()
    spam_indicators = ["free", "trial", "buy", "offer", "discount",
                       "promo", "sale", "best", "cheap", "guarantee"]
    for kw in spam_indicators:
        if kw in text_lower:
            return True
    return False

def get_or_create_tracked_entity(entity_type, entity_name):
    MAX_NAME_LEN = 50
    if len(entity_name) > MAX_NAME_LEN:
        entity_name = entity_name[:MAX_NAME_LEN]

    sel_q = """
        SELECT setID FROM TrackedEntity
        WHERE entityType = %s AND name = %s
        LIMIT 1
    """
    db_cursor.execute(sel_q, (entity_type, entity_name))
    row = db_cursor.fetchone()
    if row:
        return row[0]

    ins_q = """INSERT INTO TrackedEntity (entityType, name) VALUES (%s, %s)"""
    db_cursor.execute(ins_q, (entity_type, entity_name))
    db_connection.commit()
    return db_cursor.lastrowid

def get_or_create_topic(topic_word, category="Extracted"):
    topic_word = topic_word.strip()
    if not topic_word:
        return None
    if len(topic_word) > 50:
        print(f"Skipping topic '{topic_word}' (exceeds 50 chars).")
        return None

    sel_q = "SELECT topicID FROM Topic WHERE topic = %s LIMIT 1"
    db_cursor.execute(sel_q, (topic_word,))
    row = db_cursor.fetchone()
    if row:
        # Update category if it's different
        topic_id = row[0]
        upd_q = "UPDATE Topic SET category = %s WHERE topicID = %s"
        db_cursor.execute(upd_q, (category, topic_id))
        db_connection.commit()
        return topic_id

    ins_q = "INSERT INTO Topic (topic, category) VALUES (%s, %s)"
    db_cursor.execute(ins_q, (topic_word, category))
    db_connection.commit()
    return db_cursor.lastrowid

def get_or_create_adjective(adjective_word, sentiment_label="emotion"):
    adjective_word = adjective_word.strip()
    if not adjective_word:
        return None
    if len(adjective_word) > 50:
        print(f"Skipping adjective '{adjective_word}' (exceeds 50 characters).")
        return None

    sel_q = "SELECT adjectiveID FROM Adjective WHERE adjective = %s LIMIT 1"
    db_cursor.execute(sel_q, (adjective_word,))
    row = db_cursor.fetchone()
    if row:
        return row[0]

    ins_q = "INSERT INTO Adjective (adjective, sentiment) VALUES (%s, %s)"
    db_cursor.execute(ins_q, (adjective_word, sentiment_label))
    db_connection.commit()
    return db_cursor.lastrowid

def insert_metric_log(setID, topicID, adjectiveID, impressions, date_str, severity, explanation):
    try:
        if topicID is None or adjectiveID is None:
            return
        ins_q = """
            INSERT INTO MetricLog
            (setID, topicID, adjectiveID, impressions, date, severity, explanation)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """
        db_cursor.execute(ins_q, (setID, topicID, adjectiveID, impressions, date_str, severity, explanation))
        db_connection.commit()
    except mysql.connector.Error as err:
        print(f"Error: {err}")
        db_connection.rollback()

def gather_full_thread_text(submission):
    submission.comments.replace_more(limit=None)
    all_comments = submission.comments.list()
    comment_bodies = [c.body for c in all_comments]
    full_text = (submission.title or "") + " " + (submission.selftext or "") + " " + " ".join(comment_bodies)
    return full_text

def get_top_emotion(text):
    text = text.strip()
    if not text:
        return None
    outputs = emotion_pipeline(text[:512])
    all_scores = outputs[0]
    best = max(all_scores, key=lambda x: x["score"])
    return best["label"]

def is_organization_mentioned(text, organization_name):
    """
    Checks if the organization name is mentioned in a meaningful way in the cleaned text.
    
    This function employs:
      1. A regex pattern that requires all tokens in the organization name to appear (in any order).
      2. spaCy NER with fuzzy matching to compare recognized ORG/GPE entities against the organization name.
      3. A fallback sentence-level fuzzy match ensuring context (at least one noun and one verb).
    
    Assumes that the text is already cleaned (lowercase, punctuation removed, etc.).
    """
    org_lower = organization_name.lower()
    text_lower = text.lower()
    
    # 1. Regex-based whole-word matching for all tokens in any order
    org_tokens = org_lower.split()
    pattern = r'\b' + r'\b.*\b'.join(re.escape(token) for token in org_tokens) + r'\b'
    if re.search(pattern, text_lower):
        return True

    # 2. Use spaCy's NER to detect entities and compare them with fuzzy matching.
    doc = nlp(text)  # Assumes nlp (spaCy model) is already loaded.
    for ent in doc.ents:
        if ent.label_ in ["ORG", "GPE"]:
            # Use token_sort_ratio for fuzzy comparison.
            if token_sort_ratio(org_lower, ent.text.lower()) > 80:
                return True

    # 3. Fallback: Sentence-level fuzzy matching with contextual check.
    for sentence in sent_tokenize(text):
        if token_sort_ratio(org_lower, sentence.lower()) > 80:
            doc_sentence = nlp(sentence)
            has_verb = any(token.pos_ == "VERB" for token in doc_sentence)
            has_noun = any(token.pos_ == "NOUN" for token in doc_sentence)
            if has_verb and has_noun:
                return True

    return False

def search_posts(entity_type, entity_name, date_str=None, limit=100):
    """
    If entity_type == 'subreddit', search that sub. For 'Industry', search for "<entity_name> industry".
    Otherwise (e.g., Organisation), search 'all' with the entity_name.
    We do a specified window by date_str. You can remove the date check to get older posts.
    """
    global request_count

    now = datetime.datetime.now(datetime.timezone.utc)
    if date_str:
        try:
            start_dt = datetime.datetime.strptime(date_str, "%Y-%m-%d").replace(tzinfo=datetime.timezone.utc)
            end_dt = start_dt + datetime.timedelta(days=1)
        except ValueError:
            print("Invalid date format. Use YYYY-MM-DD.")
            return []
    else:
        end_dt = now
        start_dt = now - datetime.timedelta(days=1)

    # Build the appropriate search query based on entity_type
    if entity_type.lower() == 'subreddit':
        sub_obj = reddit.subreddit(entity_name.replace("r/", ""))
        posts_source = sub_obj.search("*", sort='new', limit=limit)
    elif entity_type.lower() == 'industry':
        # Append ' industry' to narrow the query
        query = f"{entity_name} industry"
        posts_source = reddit.subreddit('all').search(query, sort='new', limit=limit)
    else:
        posts_source = reddit.subreddit('all').search(entity_name, sort='new', limit=limit)

    results = []
    for submission in posts_source:
        request_count += 1
        rate_limiter()

        post_time = datetime.datetime.fromtimestamp(submission.created_utc, datetime.timezone.utc)
        # If you want older posts, remove or adjust the next check
        if not (start_dt <= post_time < end_dt):
            continue

        # Quick spam check
        text_content = (submission.title or "") + " " + (submission.selftext or "")
        if filter_spam(text_content):
            continue

        # For non-subreddit entity types (excluding organisations) perform the organization check.
        if entity_type.lower() not in ['subreddit', 'organisation']:
            if not is_organization_mentioned(text_content, entity_name):
                print(f"Skipping post: '{submission.title}' (no meaningful mention of {entity_name})")
                continue

        results.append(submission)

    return results


def extract_topics_deepseek(text, existing_topics):
    """
    Extracts topics from text using DeepSeek-R1:8B via Ollama.
    Matches extracted topics with existing topics in the database.

    Args:
        text (str): The input text to extract topics from.
        existing_topics (list): List of existing topics from the database.

    Returns:
        list: A list of extracted topics.
    """
    # Prepare the prompt for DeepSeek
    prompt = f"""
Based on the entire text below, please identify all thematically relevant "abstractive" topics that best capture its core themes. Focus on deeper, context-based aspects rather than trivial or generic words (e.g., "great", "nice"). Avoid speculation beyond what the text provides. Return only the topics, separated by commas.

Example 1:
Text: "I missed the application deadline for University of Edinburgh College of Art, and I am heartbroken. My grades are excellent, but now I must wait until clearing or consider deferring my studies. The course and facilities are perfect for me."
Expected Topics: University Application, Deadline Pressure, Emotional Distress, Deferral Consideration

Example 2:
Text: "The discussion highlights how social media platforms create echo chambers by curating content that only reinforces users' existing views. This phenomenon, known as filter bubbles, intensifies polarization."
Expected Topics: Echo Chambers, Filter Bubbles, Polarization

Example 3:
Text: "The company announced a major restructuring aimed at cutting costs and increasing efficiency. While some see it as a necessary move, many employees are worried about job security and the future corporate culture."
Expected Topics: Corporate Restructuring, Cost-Cutting, Job Security, Corporate Culture

Now, based on the text below, return only the topics as a comma-separated list:

Text: {text}
"""
    # Query DeepSeek-R1:8B
    response = ollama.generate(model=OLLAMA_MODEL, prompt=prompt)
    extracted_topics_raw = response["response"].strip()

    # **Remove <think> tags and their content**
    extracted_topics_cleaned = re.sub(r'<think>.*?</think>', '', extracted_topics_raw, flags=re.DOTALL)

    # Split the extracted topics
    extracted_topics = [t.strip() for t in extracted_topics_cleaned.split(",") if t.strip()]

    # Clean and match topics
    matched_topics = []
    for topic in extracted_topics:
        topic = topic.strip()
        if not topic:
            continue
        # Check if the topic matches any existing topic
        for existing_topic in existing_topics:
            if partial_ratio(topic.lower(), existing_topic.lower()) > 80:  # Fuzzy match
                matched_topics.append(existing_topic)
                break
        else:
            matched_topics.append(topic)

    return matched_topics

def load_existing_topics():
    """
    Loads all existing topics from the database.

    Returns:
        list: A list of existing topics.
    """
    sel_q = "SELECT topic FROM Topic"
    db_cursor.execute(sel_q)
    rows = db_cursor.fetchall()
    return [row[0] for row in rows]

def batch_insert_metric_logs(metric_logs):
    """
    Inserts multiple metric logs into the database in a single batch operation.

    Args:
        metric_logs (list of tuples): Each tuple contains (setID, topicID, adjectiveID, impressions, date_str, severity, explanation)
    """
    if not metric_logs:
        return

    ins_q = """
        INSERT INTO MetricLog
        (setID, topicID, adjectiveID, impressions, date, severity, explanation)
        VALUES (%s, %s, %s, %s, %s, %s, %s)
    """
    try:
        db_cursor.executemany(ins_q, metric_logs)
        db_connection.commit()
    except mysql.connector.Error as err:
        print(f"Error during batch insertion: {err}")
        db_connection.rollback()

def assign_category_zero_shot(topic, classifier, candidate_labels, threshold=0.3):
    """
    Assigns a category to a topic using zero-shot classification.

    Args:
        topic (str): The topic to categorize.
        classifier (pipeline): The zero-shot classification pipeline.
        candidate_labels (list): List of predefined categories.
        threshold (float): Confidence threshold to assign 'Miscellaneous' if no category meets the threshold.

    Returns:
        str: Assigned category.
    """
    if not topic.strip():
        return "Miscellaneous"

    try:
        # Perform zero-shot classification
        classification = classifier(
            sequences=topic, 
            candidate_labels=candidate_labels, 
            multi_class=False
        )
        
        # Extract the top category and its score
        top_category = classification['labels'][0]
        top_score = classification['scores'][0]
        
        # Assign 'Miscellaneous' if confidence is below the threshold
        if top_score < threshold:
            return "Miscellaneous"
        
        return top_category
    except Exception as e:
        print(f"Error during classification of topic '{topic}': {e}")
        return "Miscellaneous"

# ---------------------------------------------------
# MAIN
# ---------------------------------------------------
import argparse
import datetime

def parse_args():
    parser = argparse.ArgumentParser(description="Process submissions and extract topics based on entity type.")
    parser.add_argument(
        '--entity_type',
        type=str,
        required=True,
        choices=['Industry', 'Subreddit', 'Organisation'],
        help="Type of entity (Industry, Subreddit, or Organisation)"
    )
    parser.add_argument(
        '--entity_name',
        type=str,
        required=True,
        help="The name of the entity (e.g., the name of the industry, subreddit, or organisation)"
    )
    parser.add_argument(
        '--date',
        type=str,
        default="",
        help="Date in YYYY-MM-DD format, or leave blank for the last 24 hours"
    )
    parser.add_argument(
        '--limit',
        type=int,
        default=10,
        help="Number of posts to fetch (default: 10)"
    )
    
    return parser.parse_args()

def main():
    # Parse command-line arguments
    args = parse_args()

    # Load the topic name cache (optional if used)
    load_topic_name_cache()
    
    entity_type = args.entity_type
    entity_name = args.entity_name
    date_str = args.date.strip()

    # Validate industries
    if entity_type.lower() == 'industry':
        valid_industries = ["Agriculture", "Food", "Forestry", "Mining", "Oil and Gas", "Metal Production", "Chemical", 
                            "Mechanical and Electrical Engineering", "Transport Equipment Manufacturing", "Clothing", "Commerce", 
                            "Finance", "Tourism", "Media", "Telecommunications", "Postal", "Construction", "Education", "Healthcare", 
                            "Public Service", "Utilities", "Waterway", "Transport", "Care"]
        if entity_name not in valid_industries:
            print(f"ERROR: '{entity_name}' not recognized. Valid: {valid_industries}")
            return

    submissions = search_posts(entity_type, entity_name, date_str=date_str, limit=args.limit)  # Using the passed limit
    if not submissions:
        print("No posts found. Possibly increase limit or remove date filter.")
        return

    set_id = get_or_create_tracked_entity(entity_type, entity_name)

    # Gather all full_texts
    full_texts = []
    submission_details = []  # To keep track of each submission's details
    for idx, submission in enumerate(submissions):
        full_text = gather_full_thread_text(submission)
        # Ensure that the full_text is not empty
        if full_text.strip():
            full_texts.append(full_text)
            submission_details.append({
                'title': submission.title,
                'url': submission.url,
                'full_text': full_text
            })
        else:
            print(f"Skipping post #{idx} due to empty content: '{submission.title}'")

    if not full_texts:
        print("No valid texts to process after filtering.")
        return

    # Load existing topics from the database
    existing_topics = load_existing_topics()

    # Perform topic extraction using DeepSeek
    print("Performing topic extraction on the collected posts...")
    metric_logs = []

    for idx, text in enumerate(full_texts):
        # Extract topics using DeepSeek
        extracted_topics = extract_topics_deepseek(text, existing_topics)
        for topic in extracted_topics:
            # Assign a category to the topic using zero-shot classification
            category = assign_category_zero_shot(
                topic, 
                zero_shot_classifier, 
                TOPIC_CATEGORIES, 
                threshold=0.3  # Adjust threshold as needed
            )
            
            # Insert or get topic
            topic_id = get_or_create_topic(topic, category=category)
            if not topic_id:
                continue

            # Get emotion
            emotion_label = get_top_emotion(text) or "neutral"

            # Insert or get adjective
            adj_id = get_or_create_adjective(emotion_label, "emotion")
            if not adj_id:
                continue

            # Prepare metric log entry
            date_str = args.date.strip() if args.date.strip() else datetime.datetime.now().strftime("%Y-%m-%d")
            explanation = f"Topics & emotion from post+comments about {entity_name}"
            severity = -1

            metric_logs.append((
                set_id,
                topic_id,
                adj_id,
                1,            # impressions
                date_str,
                severity,
                explanation
            ))

            # For debugging:
            print("\n--------------------------------------")
            print(f"POST #{idx} | Title: {submission_details[idx]['title']}")
            print(f"URL: {submission_details[idx]['url']}")
            print(f"Extracted Topic: {topic}")
            print(f"Category: {category}")
            print(f"Overall Emotion: {emotion_label}")

    # Batch insert all metric logs
    batch_insert_metric_logs(metric_logs)

    # Save the updated topic name cache (optional if used)
    save_topic_name_cache()

    print("\nDONE. Check MetricLog for aggregated topic/emotion rows.")


# ---------------------------------------------------
if __name__ == "__main__":
    main()
