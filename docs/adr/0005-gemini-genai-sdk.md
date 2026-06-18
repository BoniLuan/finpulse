# ADR 0005 — Gemini via the `google-genai` SDK with schema JSON

**Status:** Accepted

## Context

ADR 0003 established a pluggable LLM provider with a Gemini adapter. Implementing
it for real, two choices came up: which SDK, and how to get reliable structured
output for intent parsing.

Google now ships the unified **`google-genai`** SDK (`from google import genai`),
which supersedes the legacy `google-generativeai`. It supports native structured
output — a `response_schema` with `response_mime_type="application/json"`.

## Decision

Use **`google-genai`**. For `parse_intent`, request schema-enforced JSON (a flat
Pydantic model mapped to `{type, params}`); for `explain`, request plain text.
Default model: **`gemini-2.5-flash`**. On any SDK/parse error, fall back to the
`fake` provider so the request still succeeds.

## Consequences

- **Pro:** intent parsing is robust (no brittle "strip the code fence"); the
  current SDK is the right thing to show in a portfolio.
- **Pro:** graceful degradation — a Gemini outage downgrades to heuristics, not a
  500.
- **Con:** an extra dependency (`google-genai`) in the `gemini` extra; only
  installed in the worker image, not needed for `fake`.
- **Note:** the SDK import stays lazy so the module loads (and CI tests the
  mapping) without the package or a key.
