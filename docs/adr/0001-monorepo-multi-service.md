# ADR 0001 — Monorepo, multi-service

**Status:** Accepted

## Context

FinPulse needs to demonstrate backend architecture, Docker orchestration,
integrations and AI in a single coherent portfolio piece. The alternatives were:
(a) one monolith, (b) several separate repositories, or (c) one monorepo with
multiple small services.

## Decision

Use **one monorepo with multiple services** (api, ai-worker, web) orchestrated
by Docker Compose behind an Nginx gateway.

## Consequences

- **Pro:** one coherent narrative and clone-and-run experience; shows
  service orchestration, networking and a gateway — "AWS-like" locally.
- **Pro:** clear separation of concerns (PHP = business, Python = AI) without
  the overhead of cross-repo coordination.
- **Pro:** grows by adding services/profiles, not new repos.
- **Con:** a single `docker compose up` is heavier than a monolith; mitigated by
  small images and a `fake` AI mode that needs no credentials.
