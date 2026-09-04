# ADR 0010 — Persist normalized economic history

## Status

Accepted.

## Context

Live BACEN requests supported current values and calculations but made historical
analysis dependent on upstream latency and left FinPulse without its own data
asset. Historical collection must tolerate retries and source corrections.

## Decision

Store a generic indicator catalog and observations in PostgreSQL, uniquely keyed
by indicator and observation date. A dedicated scheduled `collector` performs an
initial 25-month backfill, then requests a small overlapping window after the
latest stored observation and upserts normalized values. Public history requests
read PostgreSQL only. Redis caches upstream BACEN range requests.

Start with Selic and IPCA. Keep collection behind application ports so new
sources and a Redis-backed job queue can be introduced without changing the
HTTP or domain contracts.

## Consequences

Retries, restarts, and corrected source values are safe. Historical reads remain
available during BACEN outages after initial collection. The collector currently
runs as one scheduled process; queue retry policies and run-level observability
remain a later platform milestone.
