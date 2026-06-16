"""Runtime configuration from environment variables."""

from __future__ import annotations

import os
from dataclasses import dataclass


@dataclass(frozen=True)
class Settings:
    provider: str
    model: str
    gemini_api_key: str

    @staticmethod
    def from_env() -> "Settings":
        return Settings(
            provider=os.getenv("LLM_PROVIDER", "fake").lower(),
            model=os.getenv("LLM_MODEL", "gemini-1.5-flash"),
            gemini_api_key=os.getenv("GEMINI_API_KEY", ""),
        )
