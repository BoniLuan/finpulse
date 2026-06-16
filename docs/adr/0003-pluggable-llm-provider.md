# ADR 0003 — Pluggable LLM provider

**Status:** Accepted

## Context

The AI worker must call an LLM, but the project should not be locked to one
vendor. We also want the full flow to be demoable with **zero credentials**.

## Decision

Define an `LLMProvider` interface (Python `Protocol`) and select the concrete
implementation at runtime via the `LLM_PROVIDER` env var. Ship:
- `fake` — deterministic canned answers, **default**, no credentials needed.
- `gemini` — Google Gemini (generous free tier).
- `claude` / `openai` — documented stubs to add next.

## Consequences

- **Pro:** vendor-agnostic; switching providers is a one-line env change.
- **Pro:** CI and local demos run with `fake` — no secrets, deterministic tests.
- **Pro:** the *interface* is the deliverable, insulating business code from any
  single AI SDK.
- **Con:** a lowest-common-denominator interface (`parse_intent`, `explain`); 
  provider-specific features (tools, streaming) would need interface extensions.
