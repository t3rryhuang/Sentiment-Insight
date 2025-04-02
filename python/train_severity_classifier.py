#!/usr/bin/env python3
"""
Enhanced Severity Classifier Training Script
- Handles class imbalance (upsampling)
- Uses smaller model (`distilroberta-base`) for better convergence
- Implements improved evaluation metrics
"""

import argparse
import os
import logging
import numpy as np
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.utils.class_weight import compute_class_weight
from sklearn.metrics import balanced_accuracy_score, f1_score

import torch
from transformers import (
    AutoTokenizer,
    AutoModelForSequenceClassification,
    Trainer,
    TrainingArguments,
    EarlyStoppingCallback
)
from datasets import Dataset, DatasetDict

def parse_args():
    parser = argparse.ArgumentParser(description="Train an improved severity classifier")
    parser.add_argument("--data_path", type=str, required=True, help="Path to training CSV")
    parser.add_argument("--model_name", type=str, default="distilroberta-base", help="Base model")
    parser.add_argument("--output_dir", type=str, default="./severity_model_v3", help="Output directory")
    parser.add_argument("--epochs", type=int, default=10, help="Number of training epochs")
    parser.add_argument("--batch_size", type=int, default=16, help="Batch size")
    parser.add_argument("--lr", type=float, default=3e-5, help="Learning rate")
    parser.add_argument("--max_len", type=int, default=256, help="Max sequence length")
    return parser.parse_args()

def load_and_balance_data(data_path):
    """Load data and upsample rare classes"""
    df = pd.read_csv(data_path)

    # Ensure 'severity' is integer and 1-10
    df['severity'] = df['severity'].astype(int)
    df['severity'] -= 1  # Convert to 0-9

    # Compute class distribution
    class_counts = df['severity'].value_counts()
    min_samples = class_counts.min()
    
    # Upsample rare classes dynamically
    balanced_df = df.groupby('severity', group_keys=False).apply(lambda x: x.sample(min_samples, replace=True))

    return balanced_df.reset_index(drop=True)

def compute_class_weights(labels):
    """Compute balanced class weights"""
    classes = np.unique(labels)
    weights = compute_class_weight('balanced', classes=classes, y=labels)
    return torch.tensor(weights, dtype=torch.float)

def main():
    args = parse_args()
    logging.basicConfig(level=logging.INFO)
    logger = logging.getLogger(__name__)

    # Load and balance dataset
    logger.info("Loading and balancing dataset...")
    df = load_and_balance_data(args.data_path)

    # Split data
    train_df, test_df = train_test_split(df, test_size=0.2, stratify=df['severity'], random_state=42)

    # Create datasets
    dataset = DatasetDict({
        'train': Dataset.from_pandas(train_df),
        'test': Dataset.from_pandas(test_df)
    })

    # Compute class weights
    class_weights = compute_class_weights(df['severity'])

    # Load tokeniser
    logger.info(f"Loading tokeniser: {args.model_name}")
    tokenizer = AutoTokenizer.from_pretrained(args.model_name)

    def tokenize(batch):
        return tokenizer(batch['text'], padding="max_length", truncation=True, max_length=args.max_len)

    dataset = dataset.map(tokenize, batched=True)
    dataset.set_format('torch', columns=['input_ids', 'attention_mask', 'severity'])

    # Load model
    model = AutoModelForSequenceClassification.from_pretrained(args.model_name, num_labels=10)

    # Loss function with class weighting
    def compute_loss(model, inputs):
        labels = inputs.pop("labels")
        outputs = model(**inputs)
        logits = outputs.logits
        loss_fn = torch.nn.CrossEntropyLoss(weight=class_weights.to(logits.device))
        loss = loss_fn(logits, labels)
        return loss, outputs

    # Training arguments
    training_args = TrainingArguments(
        output_dir=args.output_dir,
        num_train_epochs=args.epochs,
        per_device_train_batch_size=args.batch_size,
        per_device_eval_batch_size=args.batch_size,
        learning_rate=args.lr,
        evaluation_strategy="epoch",
        save_strategy="epoch",
        metric_for_best_model="balanced_acc",
        load_best_model_at_end=True,
        logging_steps=50,
        fp16=torch.cuda.is_available(),  # Only use FP16 if GPU is available
        gradient_accumulation_steps=2,
        warmup_ratio=0.1,
        report_to="none"
    )

    # Compute evaluation metrics
    def compute_metrics(pred):
        labels = pred.label_ids
        preds = pred.predictions.argmax(-1)
        return {
            "balanced_acc": balanced_accuracy_score(labels, preds),
            "f1_macro": f1_score(labels, preds, average='macro')
        }

    trainer = Trainer(
        model=model,
        args=training_args,
        train_dataset=dataset['train'],
        eval_dataset=dataset['test'],
        compute_metrics=compute_metrics,
        callbacks=[EarlyStoppingCallback(early_stopping_patience=2)]
    )

    logger.info("Starting training...")
    trainer.train()

    logger.info("Final evaluation:")
    metrics = trainer.evaluate()
    logger.info(f"Balanced Accuracy: {metrics['eval_balanced_acc']:.4f}")
    logger.info(f"Macro F1: {metrics['eval_f1_macro']:.4f}")

    logger.info("Saving model...")
    trainer.save_model(args.output_dir)
    tokenizer.save_pretrained(args.output_dir)

if __name__ == "__main__":
    main()
