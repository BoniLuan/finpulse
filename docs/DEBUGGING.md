# Step debugging the api (Xdebug)

The api runs as **php-fpm inside Docker**. Xdebug 3 is built into the image and
turned on in dev via `XDEBUG_MODE=debug` (see `docker-compose.override.yml`).
It connects back to your editor on **port 9003**.

## One-time setup

1. Install the **PHP Debug** extension (`xdebug.php-debug`) in VS Code.
2. The launch config is already provided: `.vscode/launch.json`
   ("Listen for Xdebug — FinPulse api"), mapping the container path
   `/var/www/html` → `services/api`.
3. Rebuild the api image so Xdebug is present:
   ```
   docker compose up -d --build api
   ```

## Debugging a request

1. In VS Code, open the Run panel → start **"Listen for Xdebug — FinPulse api"**
   (it listens on 9003).
2. Set breakpoints, e.g. in `src/Infrastructure/Bacen/BacenClient.php`
   (`fetch()` = the actual BACEN HTTP call) and in
   `src/Application/Ask/AskQuestion.php` (`handle()`).
3. Trigger a request: open <http://localhost> and ask, or
   ```
   curl -X POST http://localhost/api/v1/ask -H "Content-Type: application/json" \
        -d "{\"question\":\"what is the current selic?\"}"
   ```

## Request flow (where breakpoints land)

```
public/index.php
  → RateLimitMiddleware → JsonErrorMiddleware
  → AskAction::__invoke
    → AskQuestion::handle
      → AiWorkerClient::parse        (HTTP → ai-worker: intent)
      → BacenClient::latest → series → fetch   ← BACEN HTTP call
      → InvestmentCalculator / InflationCorrector
      → AiWorkerClient::write        (HTTP → ai-worker: answer)
      → QueryLogRepository::log
```

## Note: the BACEN call is cached

`BacenClient` caches each series in Redis (`BACEN_CACHE_TTL`, default 1h), so
`fetch()` only runs on a **cache miss**. To force the breakpoint to hit, flush
the cache first:

```
docker compose exec redis redis-cli FLUSHALL
```

`xdebug.start_with_request=yes` means every request starts a debug session, so
the page's `/indicators` call will also pause on a `BacenClient` breakpoint.
```
