# ADR 0004 — Static frontend, no build tooling

**Status:** Accepted (supersedes the Vite + TypeScript choice)

## Context

The web UI is intentionally thin — a landing page, a live-indicators widget,
and a chat box. The initial scaffold used Vite + TypeScript, which adds a Node
toolchain (npm install, bundler, tsc) for very little UI. The project is
backend-focused and the maintainer does not use Vite.

## Decision

Serve the frontend as **plain static HTML/CSS/JS using native ES modules**,
delivered as-is by Nginx. No bundler, no Node dependencies, no build step.

## Consequences

- **Pro:** zero frontend tooling; the web image is just Nginx + static files.
  Faster builds, nothing to learn or maintain.
- **Pro:** matches the "backend is the star" intent.
- **Con:** no TypeScript types or bundling. Acceptable at this UI size; CI keeps
  a `node --check` syntax pass on the JS files.
- **Note:** if the UI ever grows beyond a few files, revisit with a light
  bundler — but only then.
