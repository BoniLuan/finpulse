"""Prompt templates for the LLM providers."""

# Used with schema-enforced JSON output (see GeminiProvider). The response shape
# is guaranteed by the schema; this only conveys the semantics of each field.
INTENT_INSTRUCTION = """Classify the user's finance question and extract its parameters.

Set "type" to one of:
- "indicator_value": the user asks for the current value of an indicator.
- "investment_return": the user asks how much an amount would yield.
- "inflation_correction": the user asks what an amount is worth after inflation.

Fill only the relevant fields:
- indicator: one of selic, cdi, ipca, usd, poupanca
- principal, months: for investment_return
- percent_of_cdi: when a CDB is quoted as a percentage of CDI (set indicator=cdi)
- amount, months: for inflation_correction
"""

EXPLAIN_PROMPT = """You are a clear, concise financial assistant.
Explain the result below to a layperson, in English, in 1 to 2 sentences.
Do not invent numbers beyond those provided.
All monetary amounts are in Brazilian reais — show them with the "R$" symbol.

Intent: {intent}
Computed result: {result}

Answer:"""
