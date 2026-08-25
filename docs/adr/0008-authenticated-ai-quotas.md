# ADR 0008 — Authenticated AI access and per-user quotas

**Status:** Accepted

## Context

Each question can consume up to two model calls: one to parse the intent and one
to explain the calculated result. A public, unlimited `/ask` endpoint would let
anonymous traffic consume the Gemini quota and make usage difficult to attribute.
The existing global IP limit protects the API generally, but it is too broad to
control paid AI capacity by account.

## Decision

`POST /api/v1/ask` requires a valid JWT. Redis-backed fixed-window counters apply
both a short per-user limit (`AI_RATE_LIMIT_MAX` in `AI_RATE_LIMIT_WINDOW`) and a
daily per-user limit (`AI_DAILY_LIMIT`). The defaults are 5 questions per minute
and 50 per 24-hour window. The global `RATE_LIMIT_*` IP limit remains in place.

Questions are limited to 1,000 bytes at the API boundary and 1,000 characters at
the worker schema boundary. The gateway rejects request bodies larger than 16 KiB.
The Gemini client uses a 15-second SDK timeout and caps each model response at 512
tokens.

## Consequences

- AI usage is attributable to an authenticated user and bounded independently of
  ordinary API traffic.
- One accepted question may still make two Gemini calls; quotas count questions,
  not low-level provider requests.
- Guest chat and guest conversation history are no longer supported.
- Redis availability is required for API rate limiting, as it already was for the
  global limiter.
