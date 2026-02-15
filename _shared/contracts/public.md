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

Canonical persistence rule:
- `requests.song_id` is always populated.
- Tip-only requests map to placeholder song:
  - `title = "Tip Jar Support"`
  - `artist = "Audience"`
- Original requests map to placeholder song:
  - `title = "Original Request"`
  - `artist = "Audience"`

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
