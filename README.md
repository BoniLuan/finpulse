# FinPulse

> Your AI finance assistant on chat — answers financial questions and fires
> alerts using **real, free Brazilian Central Bank (BACEN) data** + an AI layer.

FinPulse is a small but production-shaped platform: ask things like *"how much
does 10 thousand in savings yield in 1 year?"* or *"what is the current dollar
rate?"* and get a clear, AI-explained answer backed by live SELIC / CDI / IPCA /
USD data. You can also register alerts (e.g. *notify me when the dollar passes
5.00*).

It is a portfolio project built to demonstrate **backend architecture, Docker,
databases, authentication, API integrations, AI, automated tests, CI/CD and
observability**.

---

## Architecture

A monorepo of small services orchestrated by Docker Compose, behind a single
Nginx gateway.

```
   Browser / WhatsApp ──▶ gateway (nginx) ──▶ api (PHP 8.3 + Slim 4)
                                                │     │
                                  HTTP ─────────┘     ├──▶ PostgreSQL 16
                                  ▼                   └──▶ Redis 7 (cache/queue/ratelimit)
                       ai-worker (Python 3.12 + FastAPI)
                       LLM provider selected by env (fake or Gemini)
```

- **`services/api`** — PHP 8.3 + Slim 4, clean architecture. Owns business logic
  and orchestration: auth (JWT), validation, rate limiting, BACEN ingestion +
  caching, the calculation engine, alerts, and outbound channels.
- **`services/ai-worker`** — Python 3.12 + FastAPI. AI only: parses natural
  language into intents and writes plain-language answers, behind a pluggable
  `LLMProvider` (`fake` by default; Gemini available via env).
- **`services/web`** — static HTML/CSS/JS (ES modules, no build step), served by
  Nginx. Intentionally thin UI: landing page, live indicators widget, and a chat
  box. The backend is the star.
- **`infra/gateway`** — Nginx reverse proxy, the single public entry point.
- **PostgreSQL** for data, **Redis** for cache + a simple queue + rate limiting.

See [`docs/architecture.md`](docs/architecture.md) and the
[ADRs](docs/adr/) for the reasoning behind these choices.

> **Building on this?** Start at [`docs/WORKFLOW.md`](docs/WORKFLOW.md) — the
> documentation is the source of truth and that file is the map (dev loop,
> doc ownership, roadmap). Docs and code change together, in one commit.

---

## Quick start

Requirements: Docker + Docker Compose.

```bash
cp .env.example .env      # defaults work out of the box (AI runs in "fake" mode)
make dev-up               # start the development stack
make migrate              # create the database schema
```

Then open <http://localhost:8080>, create an account, sign in, and try the
chat box.

To use real AI, set `LLM_PROVIDER=gemini` and `GEMINI_API_KEY=...` in `.env`
(Gemini has a free tier). With `LLM_PROVIDER=fake` (the default) the worker
returns deterministic canned answers, so the whole flow is demoable with **zero
credentials**.

| Command | Description |
|---|---|
| `make up` / `make down` | start / stop production |
| `make dev-up` / `make dev-down` | start / stop development |
| `make migrate` / `make seed` | create schema / load sample data |
| `make test` | run PHPUnit + pytest |
| `make lint` | run all linters |
| `make verify` | test services affected by current changes |
| `make rebuild-affected` | verify and rebuild only affected images |
| `make deploy-fast` | test and deploy affected production services |
| `make ship` | deploy the current commit and then push it |
| `make rollback` | restore images from the previous deployment |
| `make hooks-install` | auto-deploy after each local commit |
| `make logs` | tail all service logs |

The live server currently operates as a public staging environment for rapid
iteration. See [`docs/WORKFLOW.md`](docs/WORKFLOW.md#public-staging-deployment)
for automatic post-commit deployment, health checks, and rollback behavior.

Production uses only `docker-compose.yml`, publishes no FinPulse host ports, and
expects the external `web-proxy` network used by the public reverse proxy. Create
it once with `docker network create web-proxy`. Development explicitly merges
`compose.dev.yml`; all of its published ports bind to `127.0.0.1`.

---

## Status & roadmap

This repository contains a working vertical slice for questions, auth and
user-scoped alerts. Alerts are evaluated automatically and can be delivered to
the application log, SMTP email (Mailpit by default), or the Meta WhatsApp Cloud
API. The dashboard also includes cached BTC/BRL and ETH/BRL spot prices from
Coinbase's public Data API. Planned next:

- Prometheus + Grafana observability (`--profile observability`)
- OpenAPI spec + Swagger UI
- End-to-end stack test
- Additional LLM provider adapters (Claude, OpenAI)

---

## License

[MIT](LICENSE)
