# Public Audience API Contracts (v1.2)

## Scope

- No bearer auth.
- Audience identity is cookie-backed (`songtipper_audience_token`).
- Route prefix: `/api/v1/public/projects/{project_slug}`.

---

## Public repertoire

- `GET /repertoire`
- Supports search/sort and metadata filters including `mood`.

---

## Create request

- `POST /requests`
- Supports `Idempotency-Key`.

Request body fields:
- `song_id` (optional from client when `tip_only` / `is_original` flows are used)
- `tip_only` (bool)
- `is_original` (bool)
- `tip_amount_cents` (int)
- `note` (nullable)

Response fields:
- `request_id` (nullable int)
- `client_secret` (nullable string)
- `payment_intent_id` (nullable string)
- `requires_payment` (bool)
- `stripe_account_id` (nullable string, present when `requires_payment=true`)

Paid request intent behavior:
- PaymentIntents are created as Connect direct charges on the performer's
  connected account.
- `stripe_account_id` is returned so web Stripe.js can initialize the
  connected-account payment context correctly.

Canonical persistence rule:
- `requests.song_id` is always populated.
- Tip-only requests map to placeholder song:
  - `title = "Tip Jar Support"`
  - `artist = "Audience"`
- Original requests map to placeholder song:
  - `title = "Original Request"`
  - `artist = "Audience"`

Payout setup gate:
- If owner payout setup is incomplete, request creation returns `422`:

```json
{
  "code": "payout_setup_incomplete",
  "message": "This project is not currently accepting requests."
}
```

- If project requests are disabled independently, API returns `422` with
  message only (no `code` field).

---

## Audience profile and achievements

- `GET /audience/me`
- Returns profile, totals, and achievements list for current cookie identity.

Profile name policy:
- Deterministic pseudonymous `display_name = Adjective + Animal` based on token hash.
- Uses a safe animal allowlist.

---

## Who's here leaderboard

- `GET /audience/leaderboard`
- Uses current active performance session.
- Includes active participants and rank by `SUM(tip_amount_cents)` during session.
- Stable tiebreaker: `joined_at ASC`.
