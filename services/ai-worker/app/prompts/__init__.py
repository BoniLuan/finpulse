"""Prompt templates for the LLM providers."""

INTENT_PROMPT = """Você é um classificador de intenções financeiras.
Dada a pergunta do usuário, responda APENAS com um JSON válido no formato:
{{"type": "<tipo>", "params": {{...}}}}

Tipos possíveis:
- "indicator_value": consultar o valor atual de um indicador.
  params: {{"indicator": "selic|cdi|ipca|usd|poupanca"}}
- "investment_return": simular rendimento de uma aplicação.
  params: {{"principal": <número>, "months": <int>, "indicator": "poupanca|selic|cdi"}}
- "inflation_correction": corrigir um valor pela inflação (IPCA).
  params: {{"amount": <número>, "months": <int>}}

Pergunta: {question}
JSON:"""

EXPLAIN_PROMPT = """Você é um assistente financeiro claro e objetivo.
Explique o resultado abaixo para um leigo, em português, em 1 a 2 frases.
Não invente números além dos fornecidos.

Intenção: {intent}
Resultado calculado: {result}

Resposta:"""
