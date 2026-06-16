"""Google Gemini provider.

Activated with LLM_PROVIDER=gemini + GEMINI_API_KEY. Falls back to the fake
heuristics if the response cannot be parsed, so a transient AI hiccup never
breaks the flow.
"""

from __future__ import annotations

import json
from typing import Any

from app.prompts import EXPLAIN_PROMPT, INTENT_PROMPT
from app.providers.fake import FakeProvider


class GeminiProvider:
    def __init__(self, api_key: str, model: str) -> None:
        import google.generativeai as genai  # imported lazily; optional dependency

        genai.configure(api_key=api_key)
        self._model = genai.GenerativeModel(model)
        self._fallback = FakeProvider()

    def parse_intent(self, question: str) -> dict[str, Any]:
        prompt = INTENT_PROMPT.format(question=question)
        try:
            raw = self._model.generate_content(prompt).text
            data = json.loads(_strip_code_fence(raw))
            if isinstance(data, dict) and "type" in data:
                data.setdefault("params", {})
                return data
        except Exception:  # noqa: BLE001 — degrade gracefully
            pass
        return self._fallback.parse_intent(question)

    def explain(self, intent: dict[str, Any], result: dict[str, Any]) -> str:
        prompt = EXPLAIN_PROMPT.format(
            intent=json.dumps(intent, ensure_ascii=False),
            result=json.dumps(result, ensure_ascii=False),
        )
        try:
            return self._model.generate_content(prompt).text.strip()
        except Exception:  # noqa: BLE001
            return self._fallback.explain(intent, result)


def _strip_code_fence(text: str) -> str:
    text = text.strip()
    if text.startswith("```"):
        text = text.split("\n", 1)[-1]
        text = text.rsplit("```", 1)[0]
    return text.strip()
