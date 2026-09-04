# API reference

Base URL (development gateway): `http://localhost:8080/api/v1`

All responses are JSON. Errors use the shape:

```json
{ "error": { "code": "string", "message": "human readable" } }
```

> A formal OpenAPI spec + Swagger UI is on the roadmap. This file is the
> hand-maintained reference until then.

## Health

```
GET /api/v1/health        → 200 { "status": "ok", "service": "api" }
```

## Indicators

```
GET /api/v1/indicators
```

Returns the latest value of every supported indicator (a failing source yields
`value: null` rather than an error). BACEN supplies economic series; Coinbase's
public spot-price API supplies cached BTC/BRL and ETH/BRL values. Used by the web
indicators widget.

```json
{
  "indicators": [
    { "key": "selic", "label": "Selic target rate (annual)", "value": 14.5, "series": 432 },
    { "key": "ipca",  "label": "IPCA inflation (monthly)",    "value": 0.46, "series": 433 }
  ]
}
```

## Ask (core flow)

```
POST /api/v1/ask
Authorization: Bearer <token>
Content-Type: application/json

{ "question": "how much does 10 thousand in savings yield in 1 year?" }
```

Response:

```json
{
  "answer": "Investing $10,000 in savings for 12 months ...",
  "data": { "type": "investment_return", "result": 10612.5, "inputs": { "...": "..." } },
  "sources": [
    { "name": "BACEN SGS", "series": 196, "label": "Savings yield (monthly)" }
  ]
}
```

A valid bearer token is required. The completed question and answer are attached
to the authenticated user and become available through the history endpoints.
The `question` field must contain between 1 and 1,000 bytes.

Supported intents: `indicator_value`, `investment_return` (savings, Tesouro
Selic, or CDB — pass `indicator: cdi` with `percent_of_cdi` for "% of CDI"),
and `inflation_correction`. If no supported tool or indicator can be extracted,
the request still completes through the default indicator response instead of
exposing an internal AI-worker validation error.

All API traffic is limited per client IP by `RATE_LIMIT_*` (60 requests per 60
seconds by default). In addition, this endpoint is limited per authenticated user
by `AI_RATE_LIMIT_*` (5 questions per 60 seconds) and `AI_DAILY_LIMIT` (50
questions per 24-hour fixed window). A rejected request returns `429` with a
`Retry-After` header. One accepted question can generate up to two provider calls.

## Auth

```
POST /api/v1/auth/register   { "email", "password", "display_name", "phone"? }  → 201 { "id" }
POST /api/v1/auth/login      { "email": "...", "password": "..." }  → 200 { "token", "expires_in" }
```

`token` is a JWT to be sent as `Authorization: Bearer <token>` on protected
routes.

```
GET   /api/v1/auth/me    Authorization: Bearer <token>   → 200 { "id", "email", "display_name", "phone" }
PATCH /api/v1/auth/me    { "display_name", "phone"? }  → 200 { "id", "email", "display_name", "phone" }
```

## Alerts (protected, user-scoped)

All alert routes require `Authorization: Bearer <token>` and operate only on the
authenticated user's alerts.

```
GET    /api/v1/alerts            → 200 { "alerts": [ { id, indicator, operator, threshold, channel } ] }
POST   /api/v1/alerts            { "indicator": "usd", "operator": ">", "threshold": 5.00, "channel": "log" }  → 201 { "id" }
DELETE /api/v1/alerts/{id}       → 204 on success, 404 if not found / not owned
```

`channel` is one of `log`, `email`, or `whatsapp`. Stored alerts are evaluated
automatically by the `scheduler` service (which runs `php bin/console
alerts:check` on an interval) and dispatched through that channel, with a
per-alert cooldown to avoid repeat notifications. An unsupported channel is
rejected with `400`.

`indicator` accepts `selic`, `cdi`, `ipca`, `usd`, `poupanca`, `btc`, or
`eth`. The first five use cached BACEN data; BTC and ETH use cached BRL spot
prices from Coinbase. Operators remain `>` and `<` in the HTTP contract, but
  the web interface presents them as “rises above” and “falls below.”

### Conversation history

All history routes require `Authorization: Bearer <token>` and are user-scoped.

```text
GET    /api/v1/history       → 200 { "history": [ { id, question, answer, sources, created_at } ] }
DELETE /api/v1/history/{id}  → 204 on success, 404 if not found / not owned
```

The web client exposes question history only to authenticated users.

## Historical indicator observations

```text
GET /api/v1/indicators/{key}/observations?months=24
```

Returns normalized observations persisted by the scheduled collector rather than
performing an upstream request in the HTTP path. `key` currently accepts `selic`
or `ipca`; `months` defaults to `24` and must be between `1` and `120`.

```json
{
  "indicator": { "key": "selic", "label": "Selic target rate (annual)", "series": 432 },
  "period": { "from": "2024-09-04", "to": "2026-09-04" },
  "observations": [
    { "date": "2026-08-01", "value": 15.0 }
  ]
}
```

An empty `observations` list is valid while the first background collection is
pending. Collection uses an overlapping incremental window and is idempotent by indicator and observation
date.

## Selic × IPCA comparison

```text
GET /api/v1/comparisons/selic-ipca?months=24
```

Reads the normalized PostgreSQL history and returns both series plus deterministic
analytics: Selic start/latest/change in percentage points, compounded IPCA for
the selected period and trailing 12 months, estimated latest real annual rate,
and Pearson correlation between monthly Selic averages and monthly IPCA. Metrics
are `null` when the stored data is insufficient. `months` accepts `1` through
`120`.
