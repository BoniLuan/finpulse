# Architecture

FinPulse is a monorepo of small services orchestrated by Docker Compose behind a
single Nginx gateway. The guiding principle: **PHP owns business logic and
orchestration; Python owns AI; the frontend is thin.**

```
                       ┌────────────────────────┐
   Browser / WhatsApp  │      gateway (nginx)    │  single entry, routing
        │              └───────────┬────────────┘
        ▼                          │
  ┌───────────┐         ┌──────────▼───────────┐      ┌──────────────────┐
  │   web     │  REST   │     api (PHP/Slim)    │ HTTP │ ai-worker (Python │
  │ static JS │────────▶│  Http/App/Domain/Infra│─────▶│  FastAPI + LLM    │
  └───────────┘         │  auth · alerts · calc │      │  provider iface   │
                        │  BACEN client · queue │      └────────┬─────────┘
                        └───┬───────────┬───────┘               │
                            │           │                  Gemini│/Claude/OpenAI
                     ┌──────▼─────┐ ┌───▼────┐              (env-selected)
                     │ PostgreSQL │ │ Redis  │  cache · queue · ratelimit
                     └────────────┘ └────────┘
                            ▲
                            │ scheduled command (cron-style worker)
                   bin/console alerts:check  → Channel (log/email/WhatsApp)
```

## Services

| Service | Tech | Responsibility |
|---|---|---|
| `gateway` | Nginx | Single public entry; routes `/api`, `/ai` (internal), `/` to web. |
| `api` | PHP 8.3 + Slim 4 | HTTP ingress, auth (JWT), validation, rate limiting, BACEN ingestion + caching, calculation engine, alerts, outbound channels, orchestration. |
| `ai-worker` | Python 3.12 + FastAPI | NL → intent parsing and NL answer generation, behind a pluggable `LLMProvider`. No business logic, no DB. |
| `web` | static HTML/CSS/JS (ES modules, no build) | Landing page, live indicators widget, chat box. |
| `db` | PostgreSQL 16 | Users, alerts, and authenticated conversation history/query logs. |
| `redis` | Redis 7 | BACEN series cache, simple job queue, rate-limit counters. |

## Clean architecture in `api`

```
Http  →  Application (use cases)  →  Domain (entities, calculators, repo IFaces)
                                          ↑
                              Infrastructure (Postgres repos, BacenClient,
                              RedisCache, AiWorkerClient, JWT, channels)
```

Dependencies point **inward**. The `Domain` layer has no framework or IO imports
— it is pure PHP and fully unit-testable. `Infrastructure` implements the
interfaces declared by `Domain`/`Application`; the DI container wires them in
`config/`.

## Data flow — "ask a question" (the implemented vertical slice)

1. `web` chat box → `POST /api/v1/ask { question }` via the gateway.
2. `api` `RateLimit` middleware checks Redis → `AskQuestion` use case runs.
3. Use case calls `ai-worker` `POST /infer/intent` → `{ type, params }`
   (`indicator_value` | `investment_return` | `inflation_correction`).
4. `BacenClient` fetches the needed SGS series (Redis-cached; live HTTP on miss).
5. A `Domain` service computes the result
   (`InvestmentCalculator` / `InflationCorrector`).
6. Use case calls `ai-worker` `POST /infer/explain` → plain-language answer.
7. With an optional valid JWT, a `query_logs` row is persisted and attached to
   that user as conversation history. Anonymous questions are not persisted by
   the API. The API returns `{ id, answer, data, sources }`.

The static web client stores anonymous conversation history in
`sessionStorage`. Authenticated history is read and deleted through user-scoped
API routes. Previous history and alerts are collapsed at the start of each UI
session and loaded only after an explicit user action.

## Alerts

`POST /api/v1/alerts` (JWT) persists a user-scoped alert. The **`scheduler`**
service runs `php bin/console alerts:check` every `ALERTS_INTERVAL` seconds; it
evaluates each alert against live data and, when triggered, dispatches through a
`NotificationChannel` — `log`, **`email`** (SMTP / Mailpit), or **`whatsapp`**
(Meta Cloud API). A Redis-backed `AlertThrottle` mutes a fired alert for
`ALERTS_COOLDOWN` seconds to prevent re-notifying every cycle. See
[ADR 0006](adr/0006-notification-channels-and-scheduler.md).

## Why these choices

See the ADRs:
- [0001 — Monorepo, multi-service](adr/0001-monorepo-multi-service.md)
- [0002 — Slim + clean architecture (no full framework)](adr/0002-slim-clean-architecture.md)
- [0003 — Pluggable LLM provider](adr/0003-pluggable-llm-provider.md)
- [0004 — Static frontend, no build tooling](adr/0004-static-frontend-no-build.md)
- [0005 — Gemini via the google-genai SDK with schema JSON](adr/0005-gemini-genai-sdk.md)
- [0006 — Notification channels and the alerts scheduler](adr/0006-notification-channels-and-scheduler.md)
