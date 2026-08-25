# ADR 0009 — Explicit development and production Compose environments

**Status:** Accepted

## Context

Docker Compose automatically loads a conventionally named override file. The old
`docker-compose.override.yml` published database, Redis, worker and frontend ports
and enabled bind mounts/Xdebug. That behavior is convenient locally but unsafe on
a public VPS because a plain `docker compose up` could apply development settings.

## Decision

`docker-compose.yml` is the production-safe base: immutable images, `APP_ENV=prod`,
debug disabled and no host-published FinPulse ports. It joins the external
`web-proxy` network used by the public Boniluan reverse proxy.

Development settings live in `compose.dev.yml` and must be selected explicitly
with `-f docker-compose.yml -f compose.dev.yml` or `make dev-up`. Development
ports bind only to `127.0.0.1`. API development images include Xdebug and test
dependencies, while production images omit them. The development override creates its own
non-external proxy network.

## Consequences

- `docker compose up -d` and `make up` are safe production operations.
- Local development is explicit and uses `http://localhost:8080`.
- Production requires the external `web-proxy` network to exist before first
  startup.
- CI builds only the production base; developer verification explicitly merges
  both files.
