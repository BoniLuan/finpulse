# ADR 0006 — Notification channels and the alerts scheduler

**Status:** Accepted

## Context

Alerts were stored and could be evaluated by `bin/console alerts:check`, but
nothing ran it automatically and the only channel (`LogChannel`) just logged.
To make alerts actually fire, we needed automatic evaluation and real delivery —
without forcing paid services.

## Decision

**Delivery channels** (behind the existing `NotificationChannel` port):
- **`email`** — SMTP via `symfony/mailer`. Default dev target is a bundled
  **Mailpit** container (captures mail, UI on `:8025`); point `MAIL_*` at
  Gmail/Brevo/etc. for real delivery. Free and universal.
- **`whatsapp`** — Meta WhatsApp **Cloud API** (Graph API). Free in test mode
  (test sender number + verified recipients, no billing). Recipient comes from
  `WHATSAPP_RECIPIENT`; per-user phone numbers are a future enhancement.
- `log` stays as the safe default.

Recipient is resolved per channel in `CheckAlerts` (email → the user's email,
whatsapp → the configured test recipient, otherwise the user id).

**Scheduler:** a dedicated `scheduler` compose service (reusing the api image)
runs `alerts:check` every `ALERTS_INTERVAL` seconds.

**Anti-spam:** an `AlertThrottle` port (Redis adapter) mutes an alert for
`ALERTS_COOLDOWN` seconds after it fires, so a standing condition doesn't
re-notify every cycle. A failing channel is logged and skipped, never aborting
the batch.

## Consequences

- **Pro:** alerts fire automatically and reach a real inbox for free (Mailpit /
  SMTP); WhatsApp is demonstrable at zero cost in test mode.
- **Pro:** adding channels remains "implement the interface + register it".
- **Con:** WhatsApp free-text needs an open 24h session (recipient messages
  first); production business-initiated templates require billing — out of scope.
- **Con:** cooldown is time-based, not state-change-based (an alert re-notifies
  after the window even if the value never left the threshold). Acceptable for now.
