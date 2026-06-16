"""Deterministic, credential-free provider.

Uses lightweight heuristics so the whole product flow is demoable and testable
without any AI API key. This is the default (LLM_PROVIDER=fake).
"""

from __future__ import annotations

import re
from typing import Any

_INDICATORS = ("selic", "cdi", "ipca", "poupanca", "poupança", "usd", "dolar", "dólar")


def _normalize_indicator(text: str) -> str | None:
    for name in _INDICATORS:
        if name in text:
            if name in ("poupança",):
                return "poupanca"
            if name in ("dolar", "dólar"):
                return "usd"
            return name
    return None


def _extract_amount(text: str) -> float:
    # Capture the first number, supporting "10 mil" / "1 milhão".
    match = re.search(r"(\d+(?:[.,]\d+)?)\s*(mil|milh[aã]o|milh[oõ]es)?", text)
    if not match:
        return 0.0
    value = float(match.group(1).replace(".", "").replace(",", "."))
    unit = match.group(2) or ""
    if unit.startswith("mil"):
        value *= 1_000
    elif unit.startswith("milh"):
        value *= 1_000_000
    return value


def _extract_months(text: str) -> int:
    if m := re.search(r"(\d+)\s*ano", text):
        return int(m.group(1)) * 12
    if m := re.search(r"(\d+)\s*m[eê]s", text):
        return int(m.group(1))
    return 12


class FakeProvider:
    def parse_intent(self, question: str) -> dict[str, Any]:
        text = question.lower()

        if any(w in text for w in ("rende", "render", "investir", "aplicar", "rendimento")):
            return {
                "type": "investment_return",
                "params": {
                    "principal": _extract_amount(text),
                    "months": _extract_months(text),
                    "indicator": _normalize_indicator(text) or "poupanca",
                },
            }

        if any(w in text for w in ("valia", "valem", "corrig", "inflaç", "vale hoje")):
            return {
                "type": "inflation_correction",
                "params": {"amount": _extract_amount(text), "months": _extract_months(text)},
            }

        return {
            "type": "indicator_value",
            "params": {"indicator": _normalize_indicator(text) or "selic"},
        }

    def explain(self, intent: dict[str, Any], result: dict[str, Any]) -> str:
        kind = result.get("type", intent.get("type"))

        if kind == "investment_return":
            return (
                f"Aplicando R$ {result['principal']:.2f} por {result['months']} meses "
                f"em {result['indicator']} (≈ {result['monthly_rate_pct']:.4f}% ao mês), "
                f"o montante final seria R$ {result['result']:.2f} "
                f"— um rendimento de R$ {result['earnings']:.2f}."
            )
        if kind == "inflation_correction":
            return (
                f"R$ {result['amount']:.2f} corrigidos pela inflação acumulada "
                f"({result['accumulated_pct']:.2f}%) equivalem a R$ {result['corrected']:.2f}."
            )
        return (
            f"O valor mais recente de {result.get('indicator', 'indicador')} "
            f"é {result.get('value')}."
        )
