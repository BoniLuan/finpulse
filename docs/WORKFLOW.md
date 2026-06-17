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
   gets a test. Run `make test` and `make lint` (or the per-service commands).
5. **Commit** with a Conventional Commit. The change must include both the code
   *and* the doc updates from step 2 — they ship together, never separately.

## Definition of done

A change is done only when **all** are true:

- [ ] The owning doc(s) describe the new behavior accurately.
- [ ] `api.md` matches reality (if endpoints changed).
- [ ] An ADR exists for any significant decision.
- [ ] Tests cover the change and `make test` is green.
- [ ] `make lint` is clean.
- [ ] The roadmap item below is checked off.
- [ ] One Conventional Commit contains code + docs together.

## Roadmap (the live to-do that conducts the work)

Check items off as they land. Add new items here before starting them.

### ✅ Done
- [x] Repo skeleton, Docker Compose, gateway, CI, ADRs.
- [x] Vertical slice: `POST /ask` (intent → BACEN → calc → AI answer → log).
- [x] Auth (register/login, JWT) + protected `POST /alerts`.
- [x] `alerts:check` console command dispatching via the `log` channel.

### ▶ Next (suggested order)
- [ ] `GET /indicators` endpoint so the web widget stops abusing `/ask`.
- [ ] More calculators (Tesouro Selic, CDB %CDI) + their unit tests.
- [ ] Real `WhatsAppChannel` (Meta Cloud API) — implement the stub, add ADR 0004.
- [ ] Gemini provider live test + a `claude` provider adapter.
- [ ] Alerts scheduler (cron container or queue worker) instead of manual command.
- [ ] OpenAPI spec + Swagger UI; generate `api.md` from it.
- [ ] Observability profile: Prometheus + Grafana (`docker compose --profile observability`).
- [ ] End-to-end test (spin the stack, hit `/ask`, assert).

## Conventions quick-reference

See `CLAUDE.md` for the full list. The essentials:
- Conventional Commits (`feat:`, `fix:`, `docs:`, `test:`, `chore:`).
- Config via env only; never commit secrets.
- One class per file (PHP), type-hinted Python, plain ES-module JS (no build).
