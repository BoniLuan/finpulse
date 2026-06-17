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
observability**

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
                       LLM behind a pluggable provider (Gemini default)
```

- **`services/api`** — PHP 8.3 + Slim 4, clean architecture. Owns business logic
  and orchestration: auth (JWT), validation, rate limiting, BACEN ingestion +
  caching, the calculation engine, alerts, and outbound channels.
- **`services/ai-worker`** — Python 3.12 + FastAPI. AI only: parses natural
  language into intents and writes plain-language answers, behind a pluggable
  `LLMProvider` (default **Gemini**, swappable to Claude/OpenAI via env).
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
make up                   # build & start the whole stack
make migrate              # create the database schema
```

Then open <http://localhost> and try the chat box.

To use real AI, set `LLM_PROVIDER=gemini` and `GEMINI_API_KEY=...` in `.env`
(Gemini has a free tier). With `LLM_PROVIDER=fake` (the default) the worker
returns deterministic canned answers, so the whole flow is demoable with **zero
credentials**.

| Command | Description |
|---|---|
| `make up` / `make down` | start / stop the stack |
| `make migrate` / `make seed` | create schema / load sample data |
| `make test` | run PHPUnit + pytest |
| `make lint` | run all linters |
| `make logs` | tail all service logs |

---

## Status & roadmap

This repository currently contains the **full structure plus one working
vertical slice** (ask a question end-to-end + auth + one alert). It is designed
to grow. Planned next:

- Real WhatsApp Cloud API channel (interface already in place)
- Prometheus + Grafana observability (`--profile observability`)
- More indicators and calculators
- OpenAPI spec + Swagger UI
- Additional LLM provider adapters (Claude, OpenAI)

---

## License

[MIT](LICENSE)
