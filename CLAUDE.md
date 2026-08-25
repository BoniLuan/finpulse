# FinPulse

Personal finance assistant: answers financial questions and fires alerts over
log, email, or WhatsApp using **real free BACEN public data** + an AI
layer. Portfolio project demonstrating backend architecture, Docker, DBs, auth,
integrations, AI, tests, CI/CD and observability.

## Documentation is the source of truth (read this first)
The `.md` files lead; code implements them. **`docs/WORKFLOW.md` is the map** —
it holds the development loop, the documentation map (which doc owns what), the
"definition of done", and the live roadmap. Start there for any task.

Rule for every change (feature, fix, refactor): update the owning doc(s) with
the code. Build first, test the rebuilt result, and leave changes uncommitted
until the user explicitly asks for a commit. When requested, code and docs ship
together in the same commit.
- Endpoint added/changed → update `docs/api.md`.
- Service or data flow changed → update `docs/architecture.md`.
- Significant decision → add an ADR in `docs/adr/` (don't edit old ones).
- Process/roadmap moved → update `docs/WORKFLOW.md` (check the item off).
If code and docs disagree, that is a bug to fix, not a state to leave.

## Architecture (monorepo, Docker Compose)
- `services/api` — PHP 8.3 + Slim 4, clean architecture. Business + orchestration.
- `services/ai-worker` — Python 3.12 + FastAPI. AI only, behind a pluggable LLM provider (Gemini default).
- `services/web` — static HTML/CSS/JS (ES modules, no build step), served by Nginx. Thin UI: landing, live indicators, chat.
- `infra/gateway` — Nginx reverse proxy (single entry point).
- PostgreSQL 16 (data) · Redis 7 (cache + queue + rate limit).

PHP owns business logic and orchestration; Python owns AI. They talk over HTTP.

## Layering (services/api/src)
Http → Application (use cases) → Domain (entities, calculators, repo interfaces)
← Infrastructure (Postgres repos, BacenClient, Redis, AiWorkerClient, channels).
Dependencies point inward; Domain has no framework/IO imports.

## Common commands
- `make up` / `make down` — start / stop production
- `make dev-up` / `make dev-down` — start / stop development
- `make migrate` / `make seed` — DB schema / sample data
- `make test` — PHPUnit + pytest + web tests
- `make lint` — php-cs-fixer + phpstan + ruff + mypy + eslint
- `make verify` — test services affected by uncommitted changes
- `make rebuild-affected` — verify and rebuild only affected images
- Production: `docker compose -f docker-compose.yml up -d --build`
- API: `services/api` · `composer test`, `php bin/console <cmd>`
- AI:  `services/ai-worker` · `pytest`, `uvicorn app.main:app --reload`

## Conventions
- **Commits:** Never commit without an explicit user request. When requested,
  use Conventional Commits (`feat:`, `fix:`, `chore:`, `docs:`, `test:`).
- **PHP:** PSR-12, strict_types, constructor injection via PHP-DI, no logic in controllers.
- **Python:** type-hinted, ruff + mypy clean; AI provider chosen via `LLM_PROVIDER` env.
- **Config:** all via env (`.env`, never commit secrets); see `.env.example`.
- **Tests:** business rules (calculators) and each new endpoint must have tests.
- **Add a provider/channel** by implementing the interface — never branch on type.
- **Docs:** update the owning `.md` in the same commit as the code (see top of file).

## Key paths
- Calculations: `services/api/src/Domain/Finance/`
- BACEN integration: `services/api/src/Infrastructure/Bacen/BacenClient.php`
- Use cases: `services/api/src/Application/`
- LLM providers: `services/ai-worker/app/providers/`
- Notification channels: `services/api/src/Infrastructure/Channel/`
