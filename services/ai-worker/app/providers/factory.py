"""Selects the concrete LLM provider from configuration."""

from __future__ import annotations

from app.config import Settings
from app.providers.base import LLMProvider
from app.providers.fake import FakeProvider


def build_provider(settings: Settings) -> LLMProvider:
    if settings.provider == "gemini" and settings.gemini_api_key:
        from app.providers.gemini import GeminiProvider

        return GeminiProvider(settings.gemini_api_key, settings.model)

    # TODO: add "claude" (anthropic) and "openai" providers here.
    return FakeProvider()
