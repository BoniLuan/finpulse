"""Deterministic, credential-free provider.

Uses lightweight heuristics so the whole product flow is demoable and testable
without any AI API key. This is the default (LLM_PROVIDER=fake).
"""

from __future__ import annotations

import re
from typing import Any

# Map detection keywords (substring match) to canonical indicator names.
_INDICATOR_KEYWORDS = {
    "selic": "selic",
    "cdi": "cdi",
    "ipca": "ipca",
    "inflation": "ipca",
    "savings": "poupanca",
    "poupanca": "poupanca",
    "usd": "usd",
    "dollar": "usd",
}


def _normalize_indicator(text: str) -> str | None:
    for keyword, canonical in _INDICATOR_KEYWORDS.items():
        if keyword in text:
            return canonical
    return None


def _extract_amount(text: str) -> float:
    # Capture the first number, supporting "10 thousand" / "10k" / "1 million" / "1m".
    match = re.search(r"(\d+(?:[.,]\d+)?)\s*(k|thousand|m|million)?\b", text)
    if not match:
        return 0.0
    value = float(match.group(1).replace(",", ""))
    unit = match.group(2) or ""
    if unit in ("k", "thousand"):
        value *= 1_000
    elif unit in ("m", "million"):
        value *= 1_000_000
    return value


def _extract_months(text: str) -> int:
    if m := re.search(r"(\d+)\s*year", text):
        return int(m.group(1)) * 12
    if m := re.search(r"(\d+)\s*month", text):
        return int(m.group(1))
    return 12


def _investment_params(text: str) -> dict[str, Any]:
    indicator = _normalize_indicator(text) or "poupanca"
    if "treasury" in text or "tesouro" in text:
        indicator = "selic"
    if "cdb" in text:
        indicator = "cdi"

    # A "110%" token would otherwise be mistaken for the principal; pull it out first.
    pct = re.search(r"(\d+(?:\.\d+)?)\s*%", text)
    text_for_amount = re.sub(r"\d+(?:\.\d+)?\s*%", " ", text) if pct else text

    params: dict[str, Any] = {
        "principal": _extract_amount(text_for_amount),
        "months": _extract_months(text),
        "indicator": indicator,
    }
    if indicator == "cdi":
        params["percent_of_cdi"] = float(pct.group(1)) if pct else 100.0
    return params


class FakeProvider:
    def parse_intent(self, question: str) -> dict[str, Any]:
        text = question.lower()

        if any(w in text for w in ("yield", "earn", "invest", "return", "grow", "cdb")):
            return {"type": "investment_return", "params": _investment_params(text)}

        if any(w in text for w in ("worth", "inflation", "adjust", "correct")):
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
                f"Investing ${result['principal']:.2f} for {result['months']} months "
                f"in {result['indicator']} (~{result['monthly_rate_pct']:.4f}% per month) "
                f"would grow to ${result['result']:.2f} "
                f"— a gain of ${result['earnings']:.2f}."
            )
        if kind == "inflation_correction":
            return (
                f"${result['amount']:.2f} adjusted for accumulated inflation "
                f"({result['accumulated_pct']:.2f}%) is worth ${result['corrected']:.2f}."
            )
        return (
            f"The latest value of {result.get('indicator', 'the indicator')} "
            f"is {result.get('value')}."
        )
