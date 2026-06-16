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

## Ask (core flow)

```
POST /api/v1/ask
Content-Type: application/json

{ "question": "quanto rende 10 mil na poupança em 1 ano?" }
```

Response:

```json
{
  "answer": "Aplicando R$10.000 na poupança por 12 meses ...",
  "data": { "type": "investment_return", "result": 10612.5, "inputs": { "...": "..." } },
  "sources": [
    { "name": "BACEN SGS", "series": 196, "label": "Poupança" }
  ]
}
```

Rate limited per client IP (see `RATE_LIMIT_*`). Returns `429` when exceeded.

## Auth

```
POST /api/v1/auth/register   { "email": "...", "password": "..." }  → 201 { "id" }
POST /api/v1/auth/login      { "email": "...", "password": "..." }  → 200 { "token", "expires_in" }
```

`token` is a JWT to be sent as `Authorization: Bearer <token>` on protected
routes.

## Alerts (protected)

```
POST /api/v1/alerts
Authorization: Bearer <token>

{ "indicator": "usd", "operator": ">", "threshold": 5.00, "channel": "log" }
```

Returns `201 { "id" }`. Evaluated by `php bin/console alerts:check`.
