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
                            │           │                  Gemini│ or fake
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
| `gateway` | Nginx | Single public entry; routes `/api/*` to PHP-FPM and `/*` to the web SPA. |
| `api` | PHP 8.3 + Slim 4 | HTTP ingress, auth (JWT), validation, rate limiting, BACEN ingestion + caching, calculation engine, alerts, outbound channels, orchestration. |
| `ai-worker` | Python 3.12 + FastAPI | NL → intent parsing and NL answer generation, behind a pluggable `LLMProvider`. No business logic, no DB. |
| `web` | static HTML/CSS/JS (ES modules, no build) | Responsive dashboard, live indicator carousel, chat, contextual history/alerts, and a theme-aware market background. |
| `db` | PostgreSQL 16 | User profiles/contact destinations, alerts, and authenticated conversation history/query logs. |
| `redis` | Redis 7 | BACEN/market cache, collector fetch cache, alert cooldowns, and rate-limit counters. |
| `scheduler` | PHP 8.3 CLI | Evaluates stored alerts on a configured interval. |
| `collector` | PHP 8.3 CLI | Periodically fetches, normalizes, and upserts BACEN historical observations. |
| `mailpit` | Mailpit | Internal SMTP sink used until a real SMTP service is configured; UI is exposed only in development. |

The gateway resolves `api` and `web` through Docker's embedded DNS with a short
TTL. Rebuilding either upstream can change its container IP without requiring a
gateway restart or causing stale-upstream `502` responses.

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

1. An authenticated `web` chat box sends `POST /api/v1/ask { question }`
   through the gateway.
2. The API validates the JWT, applies global per-IP and AI per-user Redis quotas,
   then runs the `AskQuestion` use case.
3. Use case calls `ai-worker` `POST /infer/intent` → `{ type, params }`
   (`indicator_value` | `investment_return` | `inflation_correction` | `macro_comparison`).
4. `BacenClient` fetches the needed SGS series (Redis-cached; live HTTP on miss).
5. A `Domain` service computes the result
   (`InvestmentCalculator` / `InflationCorrector`).
6. Use case calls `ai-worker` `POST /infer/explain` → plain-language answer.
   The API always serializes intent parameters as a JSON object, including when
   no supported tool or indicator was extracted and the parameter map is empty.
7. A `query_logs` row is persisted for the authenticated user. The API returns `{ id, answer, data, sources }`.

Conversation history is read and deleted through authenticated, user-scoped API
routes. History and alerts are loaded only after an explicit user action;
their expanded preference is then retained for the current browser-tab session.

The live-indicators use case combines BACEN SGS economic series with BTC/BRL
and ETH/BRL spot prices from Coinbase's unauthenticated public Data API. Crypto
responses are cached in Redis for `CRYPTO_CACHE_TTL` seconds.

## Alerts

`POST /api/v1/alerts` (JWT) persists a user-scoped alert. The **`scheduler`**
service runs `php bin/console alerts:check` every `ALERTS_INTERVAL` seconds. It
evaluates BACEN metrics through `IndicatorDataProvider` and BTC/ETH metrics
through `CryptoPriceProvider`; when triggered, it dispatches through a
`NotificationChannel` — `log`, **`email`** (the owner's account email via SMTP
/ Mailpit), or **`whatsapp`** (the owner's profile phone via Meta Cloud API). A
Redis-backed `AlertThrottle` mutes a fired alert for
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
- [0007 — User profile contact routing](adr/0007-user-profile-alert-routing.md)
- [0008 — Authenticated AI access and quotas](adr/0008-authenticated-ai-quotas.md)
- [0009 — Explicit Compose environments](adr/0009-explicit-compose-environments.md)

## Historical data flow

The `collector` service periodically runs `indicators:collect`. It fetches cached
BACEN SGS series through the existing provider, normalizes source dates and
numeric values, and upserts them into `indicator_observations`. The composite
primary key `(indicator_key, observed_on)` makes retries and restarts idempotent.
HTTP history requests read PostgreSQL only, keeping upstream latency and failures
out of the user request path. Redis continues to protect BACEN from redundant
fetches. The first persisted catalog covers daily Selic and monthly IPCA data;
the generic schema can accept additional economic series without a new table.

## Deterministic macro analytics

Natural-language questions classified as `macro_comparison` call the same
application service. The AI worker receives only the computed period, summary,
and observation counts; it never calculates the financial metrics or receives
the full historical payload.


`CompareSelicIpca` reads both persisted series for one period and delegates all
math to the pure `MacroComparisonCalculator` domain service. Daily Selic points
are averaged by calendar month before correlation with monthly IPCA. Inflation
is compounded rather than summed, and the estimated real rate uses the Fisher
relationship between latest annual Selic and trailing 12-month IPCA. The same
structured result feeds the REST endpoint and dashboard; a later AI intent will
explain these computed values instead of asking an LLM to perform arithmetic.
