"""Prompt templates for the LLM providers."""

INTENT_PROMPT = """You are a financial intent classifier.
Given the user's question, respond ONLY with valid JSON in the format:
{{"type": "<type>", "params": {{...}}}}

Possible types:
- "indicator_value": look up the current value of an indicator.
  params: {{"indicator": "selic|cdi|ipca|usd|poupanca"}}
- "investment_return": simulate the return of an investment.
  params: {{"principal": <number>, "months": <int>, "indicator": "poupanca|selic|cdi"}}
- "inflation_correction": adjust an amount for inflation (IPCA).
  params: {{"amount": <number>, "months": <int>}}

Question: {question}
JSON:"""

EXPLAIN_PROMPT = """You are a clear, concise financial assistant.
Explain the result below to a layperson, in English, in 1 to 2 sentences.
Do not invent numbers beyond those provided.

Intent: {intent}
Computed result: {result}

Answer:"""
