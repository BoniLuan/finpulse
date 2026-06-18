"""Google Gemini provider (modern `google-genai` SDK).

Activated with LLM_PROVIDER=gemini + GEMINI_API_KEY. Intent parsing uses
schema-enforced JSON output; on any error it falls back to the fake heuristics
so a transient AI hiccup never breaks the flow.
"""

from __future__ import annotations

import json
from typing import Any

from pydantic import BaseModel

from app.prompts import EXPLAIN_PROMPT, INTENT_INSTRUCTION
from app.providers.fake import FakeProvider


class _IntentOut(BaseModel):
    """Flat schema Gemini fills; mapped to {type, params} by `_to_intent`."""

    type: str
    indicator: str | None = None
    principal: float | None = None
    months: int | None = None
    amount: float | None = None
    percent_of_cdi: float | None = None


_PARAM_FIELDS = ("indicator", "principal", "months", "amount", "percent_of_cdi")


def _to_intent(data: dict[str, Any]) -> dict[str, Any]:
    """Convert the flat schema dict into the {type, params} shape the API expects."""
    params = {field: data[field] for field in _PARAM_FIELDS if data.get(field) is not None}
    return {"type": data.get("type") or "indicator_value", "params": params}


class GeminiProvider:
    def __init__(self, api_key: str, model: str) -> None:
        from google import genai  # lazy: optional dependency

        self._client = genai.Client(api_key=api_key)
        self._model = model
        self._fallback = FakeProvider()

    def parse_intent(self, question: str) -> dict[str, Any]:
        from google.genai import types

        try:
            response = self._client.models.generate_content(
                model=self._model,
                contents=f"{INTENT_INSTRUCTION}\n\nQuestion: {question}",
                config=types.GenerateContentConfig(
                    response_mime_type="application/json",
                    response_schema=_IntentOut,
                    temperature=0,
                ),
            )
            data = json.loads(response.text or "{}")
            if isinstance(data, dict) and data.get("type"):
                return _to_intent(data)
        except Exception:  # noqa: BLE001 — degrade gracefully
            pass
        return self._fallback.parse_intent(question)

    def explain(self, intent: dict[str, Any], result: dict[str, Any]) -> str:
        from google.genai import types

        prompt = EXPLAIN_PROMPT.format(
            intent=json.dumps(intent, ensure_ascii=False),
            result=json.dumps(result, ensure_ascii=False),
        )
        try:
            response = self._client.models.generate_content(
                model=self._model,
                contents=prompt,
                config=types.GenerateContentConfig(temperature=0.3),
            )
            return (response.text or "").strip() or self._fallback.explain(intent, result)
        except Exception:  # noqa: BLE001
            return self._fallback.explain(intent, result)
