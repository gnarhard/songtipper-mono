# Public API Contracts

## Auth and scope

Public endpoints **do not require authentication**. They are accessible to audience members.

Route prefix: `/api/v1/public/projects/{projectSlug}`

---

## Get Public Repertoire

- **Method**: `GET`
- **Path**: `/repertoire`

Get public repertoire for a project. Allows audience to browse available songs.

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `search` | string | Search by song title or artist |
| `energy_level` | string | Filter by effective energy (`project_songs.energy_level` override, fallback `songs.energy_level`) |
| `era` | string | Filter by era (e.g., "80s", "90s") |
| `genre` | string | Filter by effective genre (`project_songs.genre` override, fallback `songs.genre`) |
| `sort` | string | Sort by: `title`, `artist`, `era`, `genre`, `highest_active_tip_cents` (default: `title`) |
| `direction` | string | Sort direction: `asc`, `desc` (default: `asc`) |
| `per_page` | int | Items per page (default: 50, max: 100) |
| `page` | int | Page number |

### Success response (`200`)

```json
{
  "data": [
    {
      "id": 1,
      "song": {
        "id": 1,
        "title": "Fly Me to the Moon",
        "artist": "Frank Sinatra"
      },
      "energy_level": "medium",
      "era": "60s",
      "genre": "Jazz",
      "performed_musical_key": "C",
      "original_musical_key": "D",
      "tuning": "Standard",
      "capo": 2,
      "duration_in_seconds": 210,
      "needs_improvement": false,
      "performance_count": 7,
      "last_performed_at": "2026-02-11T22:20:10+00:00",
      "highest_active_tip_cents": 1500
    }
  ],
  "meta": {
    "project": {
      "id": 1,
      "name": "Friday Jazz Night",
      "slug": "friday-jazz",
      "owner_user_id": 1,
      "performer_info_url": "https://example.com/about",
      "performer_profile_image_url": "https://example.com/storage/performers/1/profile.png",
      "min_tip_cents": 500,
      "is_accepting_requests": true,
      "is_accepting_original_requests": true,
      "show_persistent_queue_strip": true,
      "chart_viewport_prefs": {
        "10:0": {
          "zoom_scale": 1.6,
          "offset_dx": 42.5,
          "offset_dy": -18.25
        }
      },
      "owner": {
        "id": 1,
        "name": "Mike Johnson"
      }
    },
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 10
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  }
}
```

**Notes:**
- `energy_level` and `genre` in repertoire responses are effective values: project-level override first, otherwise global song metadata
- `highest_active_tip_cents` shows the current highest tip for this song in the active queue

---

## Create Public Request

- **Method**: `POST`
- **Path**: `/requests`

Create a song request or a tip-only submission (initiates payment when needed).

### Request body

```json
{
  "song_id": 1,
  "tip_only": false,
  "tip_amount_cents": 1000,
  "note": "Happy birthday to Sarah! (optional, max 500 chars)"
}
```

**Validation Rules:**
- `song_id`: required unless `tip_only = true`; must exist in songs table when provided
- `tip_only`: optional boolean, defaults to `false`
- `tip_amount_cents`: required, integer, min 0 (but project min_tip_cents may be higher)
- `note`: optional, max 500 characters

**Tip-only behavior:**
- Set `tip_only = true` and omit `song_id` to submit support without a song request
- Tip-only submissions are stored as already `played` so they do not appear in the active queue

### Success response (`200`)

```json
{
  "request_id": 42,
  "client_secret": "pi_xxx_secret_yyy",
  "payment_intent_id": "pi_xxx",
  "requires_payment": true
}
```

Use `client_secret` with Stripe SDK to complete payment.

**If tip is 0 and project minimum is 0:**

```json
{
  "request_id": 42,
  "client_secret": null,
  "payment_intent_id": null,
  "requires_payment": false
}
```

**Audience identity for achievements:**
- Public request flow uses a long-lived cookie token (`songtipper_audience_token`) to associate audience actions
- Stripe metadata includes this token so post-payment webhook creation can award achievements consistently

### Error responses

**Project not accepting requests (`422`):**

```json
{
  "message": "This project is not currently accepting requests."
}
```

**Original requests disabled (`422`):**

```json
{
  "message": "This project is not currently accepting original requests."
}
```

**Below minimum tip (`422`):**

```json
{
  "message": "The minimum tip for this project is $5.00.",
  "min_tip_cents": 500
}
```

**Notes:**
- There is no maximum request cap per song
