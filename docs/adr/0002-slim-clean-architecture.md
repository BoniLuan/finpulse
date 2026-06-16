# ADR 0002 — Slim + clean architecture (no full framework)

**Status:** Accepted

## Context

The core API could be built on a batteries-included framework (Laravel,
Symfony) or a micro-framework (Slim) with an explicit architecture. The goal is
to *demonstrate* architectural skill, not to hide it behind framework magic.

## Decision

Use **Slim 4** as a thin HTTP layer and implement a **clean architecture** by
hand: `Http → Application → Domain ← Infrastructure`, wired with PHP-DI.

## Consequences

- **Pro:** the architecture is visible and owned — exactly the signal a
  portfolio should send. Domain logic is pure PHP and trivially unit-testable.
- **Pro:** lightweight images and fast boot.
- **Con:** more boilerplate than Laravel (routing, validation, migrations are
  hand-rolled). Accepted as the point of the exercise.
- **Note:** if the project grew into a product, adopting Symfony components
  piecemeal (Console, Validator) would be the natural evolution.
