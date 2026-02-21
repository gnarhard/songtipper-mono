# Payouts API Contracts (v1.2)

## Scope and auth

- Protected routes require `Authorization: Bearer <token>`.
- Write endpoints support `Idempotency-Key`.
- Route prefix: `/api/v1/me/payout-account`.

---

## Endpoints

- `GET /api/v1/me/payout-account`
- `POST /api/v1/me/payout-account/onboarding-link`
- `POST /api/v1/me/payout-account/dashboard-link`
- `GET /api/v1/me/projects/{projectId}/wallet`
- `GET /api/v1/me/projects/{projectId}/wallet/sessions`
- `GET /api/v1/me/payouts`

---

## Payout account status model

`payout_account` payload:
- `status` (`not_started|pending|enabled|restricted`)
- `setup_complete` (boolean)
- `status_reason` (nullable string machine code / Stripe reason)
- `stripe_account_id` (nullable string)
- `charges_enabled` (boolean)
- `payouts_enabled` (boolean)
- `requirements_currently_due` (array of strings)
- `requirements_past_due` (array of strings)

Status semantics:
- `setup_complete=true` only when `status=enabled`.
- `return_url` from Stripe onboarding is not a completion signal.
- Completion is derived from Stripe account status
  (`requirements`, `charges_enabled`, `payouts_enabled`), synchronized from
  Stripe API and `account.updated` webhooks.

---

## Onboarding link endpoint

`POST /onboarding-link`:
- Ensures an Express connected account exists for the current user.
- Returns single-use onboarding `url` and latest `payout_account` snapshot.

Example success:

```json
{
  "url": "https://connect.stripe.com/setup/...",
  "payout_account": {
    "status": "pending",
    "setup_complete": false,
    "status_reason": "requirements_due",
    "stripe_account_id": "acct_123",
    "charges_enabled": false,
    "payouts_enabled": false,
    "requirements_currently_due": ["external_account"],
    "requirements_past_due": []
  }
}
```

---

## Dashboard link endpoint

`POST /dashboard-link`:
- Returns one-time Stripe Express dashboard login `url` and current
  `payout_account`.
- If payout setup has not started, returns `422`:

```json
{
  "code": "payout_setup_incomplete",
  "message": "Finish payout setup before opening Stripe Express."
}
```

---

## Wallet summary endpoint

`GET /api/v1/me/projects/{projectId}/wallet`:
- Owner-only endpoint (403 for non-owners).
- Returns:
  - account-level Stripe balance (`available`, `pending`, USD totals),
  - project-level earnings aggregates from SongTipper request/session data,
  - current payout account status snapshot.

Semantics:
- Wallet scope is `account_level` because one performer has one Stripe account.
- Project earnings are reporting views; Stripe balance is source of truth for
  cashout availability.

---

## Session earnings endpoint

`GET /api/v1/me/projects/{projectId}/wallet/sessions`:
- Owner-only endpoint.
- Paginated performance session aggregates:
  - `paid_request_count`
  - `total_tip_amount_cents`
  - session timestamps/status

---

## Payout history endpoint

`GET /api/v1/me/payouts`:
- Returns connected-account payout objects from Stripe.
- Query params:
  - `limit` (1..100, default 20)
  - `status` (`pending|in_transit|paid|failed|canceled`)
- If no payout account exists yet, returns empty `data` with
  `payout_account.status=not_started`.
