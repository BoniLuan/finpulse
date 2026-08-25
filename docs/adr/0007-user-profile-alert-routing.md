# ADR 0007 — User profile contact routing

## Status

Accepted.

## Context

Email alerts already map naturally to the authenticated user's email, but
WhatsApp alerts previously used one deployment-wide test recipient. That model
cannot safely support multiple users and gives existing accounts no way to
manage their destination.

## Decision

Users have a required display name and an optional normalized international
mobile number. Registration accepts both, and authenticated users can update
them through `PATCH /api/v1/auth/me`. WhatsApp delivery resolves the phone from
the alert owner; when no phone exists, WhatsApp cannot be selected in the web
interface and the scheduler skips that delivery. Email continues to resolve
the alert owner's account email.

## Consequences

- Alert destinations are user-scoped instead of deployment-scoped.
- Existing users remain valid with nullable profile fields and can complete
  their profile later.
- Phone numbers are stored as 10–15 international digits without punctuation.
- A future production release should add phone verification before treating
  WhatsApp as a trusted delivery channel.
