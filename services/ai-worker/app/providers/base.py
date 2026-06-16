"""The LLM provider contract.

New providers (Claude, OpenAI, ...) are added by implementing this Protocol and
registering them in factory.py — no other code changes.
"""

from __future__ import annotations

from typing import Any, Protocol, runtime_checkable


@runtime_checkable
class LLMProvider(Protocol):
    def parse_intent(self, question: str) -> dict[str, Any]:
        """Return a structured intent: {"type": str, "params": dict}."""
        ...

    def explain(self, intent: dict[str, Any], result: dict[str, Any]) -> str:
        """Return a plain-language answer for a computed result."""
        ...
