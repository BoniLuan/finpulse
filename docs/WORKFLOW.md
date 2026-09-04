# Workflow — how we build FinPulse

> **The docs are the source of truth.** Code implements what the docs describe.
> If code and docs disagree, the docs win and the code is a bug — or the docs
> are stale and must be updated *in the same change*. This file is the map that
> guides both Luan and Claude Code through every change.

## The documentation map

Each `.md` has one job. Keep them in sync; never let code drift ahead of them.

| Document | Owns (source of truth for) | Update when… |
|---|---|---|
| `README.md` | Product pitch, how to run, status/roadmap summary | the product story, setup steps, or high-level status change |
| `CLAUDE.md` | Conventions, layering rules, commands, key paths | a convention, command, tech choice, or directory moves |
| `docs/architecture.md` | Services, responsibilities, data flow | a service is added/removed or a flow changes |
| `docs/api.md` | Every HTTP endpoint and its contract | an endpoint is added, changed, or removed |
| `docs/adr/NNNN-*.md` | One significant decision each (immutable record) | a new significant decision is made (add a file; don't edit old ones) |
| `docs/WORKFLOW.md` | This process + the live roadmap below | the process changes or a roadmap item starts/finishes |

## The development loop

Every change — feature, fix, or refactor — follows the same five steps:

1. **Read the docs first.** Start from this file's roadmap, then the doc that
   owns the area you'll touch (`api.md` for endpoints, `architecture.md` for
   structure). Understand the contract before writing code.
2. **Write/adjust the docs.** Describe the intended behavior *before or
   alongside* the code: update `api.md`, add an ADR for a real decision, note
   the roadmap item as "in progress". Docs lead.
3. **Implement**, respecting the layering in `CLAUDE.md`
   (`Http → Application → Domain ← Infrastructure`; Domain stays pure). Add a
   new provider/channel by implementing its interface — never branch on a type.
4. **Test.** Business rules (Domain) get unit tests; every new/changed endpoint
   gets a test. During development, run `make verify` to test and lint only affected
   services, or `make test` and `make lint` for the complete suite.
5. **Hand off for review after build and tests.** Never commit until the user
   explicitly asks. When requested, use a Conventional Commit and include both
   code and documentation together.

## Definition of done

A change is done only when **all** are true:

- [ ] The owning doc(s) describe the new behavior accurately.
- [ ] `api.md` matches reality (if endpoints changed).
- [ ] An ADR exists for any significant decision.
- [ ] Tests cover the change and `make test` is green.
- [ ] `make lint` is clean.
- [ ] The roadmap item below is checked off.
- [ ] The rebuilt result was tested and handed off for review.
- [ ] If the user explicitly requested a commit, one Conventional Commit
      contains code + docs together.

## Roadmap (the live to-do that conducts the work)

Check items off as they land. Add new items here before starting them.

### ✅ Done
- [x] Repo skeleton, Docker Compose, gateway, CI, ADRs.
- [x] Vertical slice: `POST /ask` (intent → BACEN → calc → AI answer → log).
- [x] Auth (register/login, JWT) + protected `POST /alerts`.
- [x] `alerts:check` console command dispatching via the `log` channel.
- [x] End-to-end auth UI: web login/register + user-scoped alerts (list/create/
      delete) via `GET/POST/DELETE /alerts` and `GET /auth/me`.

### ▶ Next (suggested order)
- [x] Historical data foundation: scheduled, idempotent Selic/IPCA
      collection, normalized PostgreSQL observations, public history API, and
      dashboard comparison chart.
- [x] Deterministic Selic × IPCA analytics: compounded inflation, real-rate
      estimate, monthly correlation, comparison API, and dashboard insights.
- [x] Natural-language Selic × IPCA queries: structured AI intent,
      deterministic backend execution, concise explanation, and source citations.
- [x] `GET /indicators` endpoint so the web widget stops abusing `/ask`.
- [x] More calculators (Tesouro Selic, CDB %CDI) + their unit tests.
- [x] Notification channels: `email` (SMTP/Mailpit) + `whatsapp` (Meta Cloud API) (ADR 0006).
- [x] Alerts scheduler (`scheduler` service) + Redis cooldown so alerts fire automatically.
- [x] Gemini provider (`google-genai`, schema JSON) — implemented (ADR 0005).
- [ ] `claude` / `openai` provider adapters.
- [ ] OpenAPI spec + Swagger UI; generate `api.md` from it.
- [ ] Observability profile: Prometheus + Grafana (`docker compose --profile observability`).
- [ ] End-to-end test (spin the stack, hit `/ask`, assert).
- [x] Authenticated conversation history with row deletion and a
      session-persisted show/hide preference.
- [x] Progressive account panels: session-persisted show/hide controls for history
      and authenticated alerts, without fetching hidden records.
- [x] Flexible alert builder: readable conditions, delivery selection, and
      scheduler-backed BACEN plus BTC/ETH market metrics.
- [x] User profiles with display name and optional mobile number; email and
      WhatsApp alerts resolve the owning user's contact destination.
- [x] Persistent light/dark theme switch.
- [x] More free live indicators: cached BTC/BRL and ETH/BRL spot prices.
- [x] Responsive dashboard refresh: wider desktop layout, contextual side rail,
      mobile indicator carousel, theme-specific continuous generated market
      artwork, and a rotating question-suggestion carousel.
- [x] Browser-native voice dictation for the question field, with graceful
      fallback when speech recognition is unavailable.

## Public-staging deployment

Until FinPulse has external users, the server intentionally operates as a
public staging environment: commits can be tested and deployed rapidly, and no
uptime commitment is implied. A commit remains the deployment boundary; saving
a source file never triggers deployment.

Run `make hooks-install` once to enable the tracked `post-commit` hook and mark
the current commit as the initial deployed state. Each subsequent local commit
runs `make deploy-fast` synchronously. The deployment:

1. refuses a dirty working tree and locks against overlapping deployments;
2. detects runtime services changed since the last successful deployment;
3. runs affected tests, lint, and static analysis in isolated test images;
4. snapshots running image IDs, builds production images, applies idempotent
   migrations when needed, and recreates only affected services;
5. checks the public site and `/api/v1/health`, automatically restoring the
   previous service images if either check fails.

Useful commands:

```sh
make verify         # affected checks for uncommitted work
make deploy-fast    # deploy the current committed version
make ship           # deploy successfully, then git push
make rollback       # restore images from the previous deployment
make hooks-disable  # stop automatic post-commit deployment
```

Set `FINPULSE_AUTO_DEPLOY=0` for a single commit that must not auto-deploy.
Deployment state, locks, and rollback image IDs live under `.git/`. Rollback
does not undo database migrations, so migrations must remain backward compatible.

GitHub Actions remains an independent CI check, but public-staging deployment
does not wait for its queue. Once real users arrive, deployment should move
behind CI and use published, commit-tagged images.

## Conventions quick-reference

See `CLAUDE.md` for the full list. The essentials:
- Conventional Commits (`feat:`, `fix:`, `docs:`, `test:`, `chore:`).
- Config via env only; never commit secrets.
- One class per file (PHP), type-hinted Python, plain ES-module JS (no build).

## Development containers

The base `docker compose` command loads only `docker-compose.yml` and is safe for
production. Development explicitly adds `compose.dev.yml`. Application source is
bind-mounted for fast development feedback: web changes need only a browser
refresh, while the AI worker reloads automatically. PHP source is immediately
available in both the API and scheduler containers. Unversioned CSS and JavaScript revalidate on refresh,
while large static assets retain a browser cache.

Start development with `make dev-up`, then use `make rebuild-affected` after
changes. It runs relevant tests and rebuilds only when a Dockerfile, dependency
manifest, Nginx configuration, Compose file, or other image input changed. Web
changes receive JavaScript syntax and HTTP smoke checks without an image rebuild.
Use `make dev-build` for an intentional full development rebuild.

Production runs use only immutable images. Copy `.env.example` to `.env`, set
strong production credentials, and create the shared network once with
`docker network create web-proxy` before the first start:

```sh
docker compose -f docker-compose.yml up -d --build
```
