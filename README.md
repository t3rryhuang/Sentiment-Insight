# Sentiment Insight - README


---

## Overview
In this project, we analyse Reddit data and derive sentiment insights on a 1–10 severity scale. It consists of:
- Python scripts for collecting, processing, and condensing data.
- A PHP-based web application (with MySQL as the database) for searching, viewing, and interacting with these insights.
- Environment variable configuration for production or local setups.

### Key Points
1. **Reddit data collection**: Uses `collect-reddit-data.py`, which queries the Reddit API.
2. **Severity updates**: Uses `update_severity.py` to apply a transformer-based classifier for severity scoring.
3. **Data condensation**: Uses `condense_metric_log.py` to produce aggregated results suitable for quick querying in the web interface.

The web component is built with PHP, MySQL, JavaScript, CSS, and runs under a local server environment such as XAMPP. When searching or saving data on the website, a **TrackedEntity** record is created if it doesn’t already exist.

---


Key folders and files:
- **python**: Contains Python scripts and model artifacts for data processing.
- **chart-functions**: PHP scripts that return data for specific chart types (force field diagrams, Sankey, time-series line charts, etc.).
- **js**: JavaScript code to render interactive charts and handle AJAX calls.
- **css**: Front-end styles.
- **images**: Logos, icons, and other assets.

---

## Prerequisites
1. **XAMPP or similar web server stack**:
   - For local development, you’ll need PHP, MySQL, and Apache (all included in XAMPP).
2. **Python 3.8+**:
   - Required to run the Python scripts.
3. **Pip / Virtual Environment**:
   - Recommended to manage dependencies. 
4. **Reddit API Credentials**:
   - A Reddit account and an application/client set up on [https://www.reddit.com/prefs/apps](https://www.reddit.com/prefs/apps) to get `REDDIT_CLIENT_ID` and `REDDIT_CLIENT_SECRET`.
  
## Database Setup
1. **Create Database**:
   - Open **phpMyAdmin** (bundled with XAMPP) or use the MySQL CLI.
2. **Import `sentiment_insight.sql`**:
   - In phpMyAdmin, select your newly created database and import the `sentiment_insight.sql` file. This will set up your database schema with the necessary tables.
   - Alternatively, from the command line:
     ```bash
     mysql -u username -p database_name < path/to/sentiment_insight.sql
     ```
   - Replace `username`, `database_name`, and `path/to/sentiment_insight.sql` with your MySQL username, the name of your database, and the path to your SQL file, respectively.
   - You will be prompted to enter your MySQL password after executing the command.


## Environment Variables
Ensure the following environment variables are set for Python scripts:
- `REDDIT_CLIENT_ID`, `REDDIT_CLIENT_SECRET`, `REDDIT_USER_AGENT` for Reddit API access.
- `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` for database access.

### Setting Environment Variables
You can set these variables in a `.env` file placed in your project directory. Here is an example of what the `.env` file should contain:

```plaintext
REDDIT_CLIENT_ID=your_reddit_client_id
REDDIT_CLIENT_SECRET=your_reddit_client_secret
REDDIT_USER_AGENT='your_reddit_user_agent'

DB_HOST=localhost
DB_USER=your_database_username
DB_PASSWORD=your_database_password
DB_NAME=your_database_name
```

### Python Scripts
Here’s a single command that installs the necessary Python libraries used at the top of the Python scripts:

```bash
pip install torch numpy transformers fastapi uvicorn pydantic mysql-connector-python sentence-transformers scikit-learn python-dotenv pandas datasets argparse logging ollama
```
## Running the Website Locally
1. **Install XAMPP** (or similar) and start Apache and MySQL.
2. **Place Files**:
   - Copy the entire project folder into the `htdocs` directory (or subfolder) of your XAMPP installation.
   - Example: `C:\xampp\htdocs\sentiment_insight\`
3. **Update `connectDB()`**:
   - If your MySQL credentials differ, change them in `functions.php`.
4. **Visit the Site**:
   - Open a browser and go to [http://localhost/sentiment_insight/index.php](http://localhost/sentiment_insight/index.php) (adjust path if needed).
   - Register a new user account or log in (registration-page.php, sign-in-page.php).
   - You can now search and view aggregated sentiment data.

## Notes on TrackedEntity Records
When you type in a search term (like a subreddit name, an organisation, or an industry) that doesn’t exist in the database, a **TrackedEntity** record will be automatically created by the pipeline or by the website’s scripts. This ensures that any new search is logged and can then be updated with fresh data from subsequent pipeline runs.

## Installing and Using DeepSeek-R1:8B Locally

DeepSeek-R1:8B is used to extract topics from Reddit posts. This section provides instructions on setting up and using DeepSeek-R1:8B locally through the Ollama library. A smaller model may be used in substitution of this.

### Setting Up DeepSeek-R1:8B
Before running the Python scripts to fetch sentiment data, you must integrate DeepSeek-R1:8B into your local environment as follows:

**Initialise the Model**:
   - Import Ollama in your Python script and initialise DeepSeek-R1:8B with the following commands:
     ```python
     from ollama import Ollama
     model = Ollama('deepseek-r1:8b')
      ```

## Training the Severity Classifier

This section details the process of training the severity classifier used in the Sentiment Insight project. The classifier enhances performance by handling class imbalance and leveraging a compact model architecture for better convergence. It utilises evaluation metrics such as balanced accuracy and macro F1 score to gauge the effectiveness of the model.

The training script `train_severity_classifier.py` is designed to:
- Handle class imbalance through dynamic upsampling.
- Use the `distilroberta-base` model for efficient training and convergence.
- Implement enhanced evaluation metrics such as balanced accuracy and F1 score.

```bash
python train_severity_classifier.py \
  --data_path "./data/severity_training_data.csv" \
  --output_dir "./trained_models/severity_classifier" \
  --epochs 5 \
  --batch_size 8 \
  --lr 2e-5 \
  --max_len 128
```

Training data has been provided for you. The classifier must be trained and created prior to running the Python scripts to fetch and extract topics.

### Python Script Usage Example
You can call `collect-reddit-data.py` for each date of interest, specifying the entity type and name:
```bash
python collect-reddit-data.py --entity_type {Subreddit | Organisation | Industry} --entity_name "ENTER ENTITY NAME" --date YYYY-MM-DD --limit 500
```
After you have collected all the relevant data for a given TrackedEntity, run the severity update script once:
```bash
python update_severity.py
```
Then, for each date you collected, run:
```bash
python condense_metric_log.py --setID {SET ID INTEGER OF TRACKED ENTITY. Check the TrackedEntity table for it.} --date YYYY-MM-DD
```

