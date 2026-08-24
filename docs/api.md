# API reference

Base URL (through the gateway): `http://localhost/api/v1`

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

The bearer token is optional. When valid, the completed question and answer are
attached to that user and become available through the history endpoints.
Anonymous questions are not retained by the API.

Supported intents: `indicator_value`, `investment_return` (savings, Tesouro
Selic, or CDB — pass `indicator: cdi` with `percent_of_cdi` for "% of CDI"),
and `inflation_correction`.

Rate limited per client IP (see `RATE_LIMIT_*`). Returns `429` when exceeded.

## Auth

```
POST /api/v1/auth/register   { "email": "...", "password": "..." }  → 201 { "id" }
POST /api/v1/auth/login      { "email": "...", "password": "..." }  → 200 { "token", "expires_in" }
```

`token` is a JWT to be sent as `Authorization: Bearer <token>` on protected
routes.

```
GET /api/v1/auth/me      Authorization: Bearer <token>   → 200 { "id", "email" }
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

### Conversation history

All history routes require `Authorization: Bearer <token>` and are user-scoped.

```text
GET    /api/v1/history       → 200 { "history": [ { id, question, answer, sources, created_at } ] }
DELETE /api/v1/history/{id}  → 204 on success, 404 if not found / not owned
```

Guest history is intentionally a frontend concern and remains in browser
`sessionStorage` only.
