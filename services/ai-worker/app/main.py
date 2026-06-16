"""FinPulse AI worker — FastAPI app.

Pure AI concerns only: parse natural-language questions into intents and write
plain-language answers. No business logic, no database.
"""

from __future__ import annotations

from fastapi import FastAPI

from app.config import Settings
from app.providers.factory import build_provider
from app.schemas import (
    ExplainRequest,
    ExplainResponse,
    IntentRequest,
    IntentResponse,
)

settings = Settings.from_env()
provider = build_provider(settings)

app = FastAPI(title="FinPulse AI worker", version="0.1.0")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "service": "ai-worker", "provider": settings.provider}


@app.post("/infer/intent", response_model=IntentResponse)
def infer_intent(req: IntentRequest) -> IntentResponse:
    intent = provider.parse_intent(req.question)
    return IntentResponse(type=intent["type"], params=intent.get("params", {}))


@app.post("/infer/explain", response_model=ExplainResponse)
def infer_explain(req: ExplainRequest) -> ExplainResponse:
    answer = provider.explain(req.intent.model_dump(), req.result)
    return ExplainResponse(answer=answer)
