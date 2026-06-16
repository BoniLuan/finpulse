"""Request/response models for the AI worker HTTP API."""

from __future__ import annotations

from typing import Any

from pydantic import BaseModel, Field


class IntentRequest(BaseModel):
    question: str = Field(min_length=1)


class IntentResponse(BaseModel):
    type: str
    params: dict[str, Any] = Field(default_factory=dict)


class IntentPayload(BaseModel):
    type: str
    params: dict[str, Any] = Field(default_factory=dict)


class ExplainRequest(BaseModel):
    intent: IntentPayload
    result: dict[str, Any] = Field(default_factory=dict)


class ExplainResponse(BaseModel):
    answer: str
