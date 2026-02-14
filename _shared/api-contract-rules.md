# Song Tipper API Contract

Base URL: `/api/v1`

## Authentication

All authenticated endpoints require a Bearer token in the `Authorization` header:
```
Authorization: Bearer {token}
```

Tokens are obtained via the login endpoint and are Laravel Sanctum tokens.

---

## Enums

### EnergyLevel
```
low | medium | high
```

### RequestStatus
```
active | played
```

### PerformanceSource
```
repertoire | setlist
```

---

## Public Endpoints (No Auth Required)

### POST `/v1/auth/login`

Authenticate and receive access token.

**Request:**
```json
{
  "email": "string (required)",
  "password": "string (required)",
  "device_name": "string (optional, defaults to 'api')"
}
```

**Response (200):**
```json
{
  "token": "1|abc123...",
  "accessBundle": {
    "projects": [
      {
        "id": 1,
        "name": "Friday Jazz Night",
        "slug": "friday-jazz",
        "performer_info_url": "https://example.com/about",
        "performer_profile_image_url": "https://example.com/storage/performers/1/profile.png",
        "min_tip_cents": 500,
        "is_accepting_requests": true,
        "is_accepting_original_requests": true,
        "show_persistent_queue_strip": true,
        "owner_user_id": 1
      }
    ]
  },
  "user": {
    "id": 1,
    "name": "Mike Johnson",
    "email": "mike@example.com"
  }
}
```

**Response (401):**
```json
{
  "message": "Invalid credentials."
}
```

---

### GET `/v1/public/projects/{projectSlug}/repertoire`

Get public repertoire for a project.

**Query Parameters:**
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

**Response (200):**
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

`energy_level` and `genre` in repertoire responses are effective values: project-level
override first, otherwise global song metadata.

---

### POST `/v1/public/projects/{projectSlug}/requests`

Create a song request or a tip-only submission (initiates payment when needed).

**Request:**
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

Tip-only behavior:
- Set `tip_only = true` and omit `song_id` to submit support without a song request.
- Tip-only submissions are stored as already `played` so they do not appear in the active queue.

**Response (200):**
```json
{
  "request_id": 42,
  "client_secret": "pi_xxx_secret_yyy",
  "payment_intent_id": "pi_xxx",
  "requires_payment": true
}
```

Use `client_secret` with Stripe SDK to complete payment.

If tip is `0` and project minimum is `0`, request is created immediately and response is:
```json
{
  "request_id": 42,
  "client_secret": null,
  "payment_intent_id": null,
  "requires_payment": false
}
```

Audience identity for achievements:
- Public request flow uses a long-lived cookie token (`songtipper_audience_token`) to associate audience actions.
- Stripe metadata includes this token so post-payment webhook creation can award achievements consistently.

**Response (422) - Project not accepting:**
```json
{
  "message": "This project is not currently accepting requests."
}
```

**Response (422) - Original requests disabled:**
```json
{
  "message": "This project is not currently accepting original requests."
}
```

**Response (422) - Below minimum tip:**
```json
{
  "message": "The minimum tip for this project is $5.00.",
  "min_tip_cents": 500
}
```

There is no maximum request cap per song.

---

### POST `/v1/webhooks/stripe`

Stripe webhook endpoint. Not for client use.

---

## Authenticated Endpoints

### POST `/v1/auth/logout`

Revoke the current Sanctum access token.

**Response (200):**
```json
{
  "message": "Logged out successfully."
}
```

**Response (401):** Unauthenticated.

---

### GET `/v1/me/projects`

List projects the authenticated user has access to.

**Response (200):**
```json
{
  "data": [
    {
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
      "owner": {
        "id": 1,
        "name": "Mike Johnson"
      }
    }
  ]
}
```

---

### POST `/v1/me/projects`

Create a new project for the authenticated user.

**Request:**
```json
{
  "name": "Friday Jazz Night"
}
```

**Validation Rules:**
- `name`: required, string, max 255 chars, cannot be blank/whitespace-only

**Response (201):**
```json
{
  "message": "Project created successfully.",
  "project": {
    "id": 2,
    "name": "Friday Jazz Night",
    "slug": "friday-jazz-night",
    "owner_user_id": 1,
    "performer_info_url": null,
    "performer_profile_image_url": null,
    "min_tip_cents": 500,
    "is_accepting_requests": true,
    "is_accepting_original_requests": true,
    "show_persistent_queue_strip": true,
    "chart_viewport_prefs": null,
    "owner": {
      "id": 1,
      "name": "Mike Johnson"
    }
  }
}
```

**Response (422):**
```json
{
  "message": "The name field is required.",
  "errors": {
    "name": [
      "The name field is required."
    ]
  }
}
```

**Response (401):** Unauthenticated.

---

### PATCH `/v1/me/projects/{projectId}`

Update project settings. Only the project owner can update a project.

**Request:**
```json
{
  "name": "Friday Jazz Night Updated",
  "performer_info_url": "https://example.com/about",
  "min_tip_cents": 1000,
  "is_accepting_requests": false,
  "is_accepting_original_requests": true,
  "show_persistent_queue_strip": true,
  "chart_viewport_prefs": {
    "10:0": {
      "zoom_scale": 1.6,
      "offset_dx": 42.5,
      "offset_dy": -18.25
    }
  },
  "remove_performer_profile_image": false
}
```

**Validation Rules:**
- `name`: optional, string, max 255 chars
- `performer_info_url`: optional, nullable URL, max 2048 chars
- `min_tip_cents`: optional, integer, min 0
- `is_accepting_requests`: optional, boolean
- `is_accepting_original_requests`: optional, boolean
- `show_persistent_queue_strip`: optional, boolean (defaults to `true` when omitted)
- `chart_viewport_prefs`: optional object keyed by `{chart_id}:{page_number}`, value object with:
  - `zoom_scale`: required numeric (`0.5` to `4`)
  - `offset_dx`: required numeric
  - `offset_dy`: required numeric
- `remove_performer_profile_image`: optional, boolean

All fields are optional - you can update one or more fields in a single request.

**Response (200):**
```json
{
  "message": "Project updated successfully.",
  "project": {
    "id": 1,
    "name": "Friday Jazz Night Updated",
    "slug": "friday-jazz",
    "owner_user_id": 1,
    "performer_info_url": "https://example.com/about",
    "performer_profile_image_url": "https://example.com/storage/performers/1/profile.png",
    "min_tip_cents": 1000,
    "is_accepting_requests": false,
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
  }
}
```

**Response (403):**
```json
{
  "message": "This action is unauthorized."
}
```

Only the project owner can update the project settings. This is enforced in the `UpdateProjectRequest` form request.

---

### POST `/v1/me/projects/{projectId}/performer-image`

Upload or replace the performer profile image for a project. Owner-only.

**Request (multipart/form-data):**
- `image`: required image file (`jpeg`, `png`, `webp`), max 5MB

**Response (201):**
```json
{
  "message": "Performer profile image uploaded successfully.",
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
    "show_persistent_queue_strip": true
  }
}
```

**Response (403):**
```json
{
  "message": "This action is unauthorized."
}
```

---

### GET `/v1/me/projects/{projectId}/queue`

Get active request queue for a project. Supports ETag caching.

**Headers:**
- `If-None-Match`: Previous ETag value (optional)

**Response (200):**
```json
{
  "data": [
    {
      "id": 42,
      "song": {
        "id": 1,
        "title": "Fly Me to the Moon",
        "artist": "Frank Sinatra"
      },
      "tip_amount_cents": 1500,
      "tip_amount_dollars": "15.00",
      "status": "active",
      "requester_name": null,
      "note": "Happy birthday!",
      "activated_at": "2026-02-03T12:00:00+00:00",
      "played_at": null,
      "created_at": "2026-02-03T11:55:00+00:00"
    }
  ]
}
```

Response includes `ETag` header. Queue is ordered by `tip_amount_cents DESC`, then `created_at ASC`.

**Response (304):** No changes since last request (when `If-None-Match` matches).

---

### POST `/v1/me/projects/{projectId}/queue`

Manually add an item to the active queue as an authenticated performer/project member.

**Request (custom):**
```json
{
  "type": "custom",
  "custom_title": "Crowd Favorite Mashup",
  "tip_amount_cents": 750,
  "custom_artist": "Custom Request",
  "note": "Mash two choruses if possible"
}
```

**Request (song from repertoire):**
```json
{
  "type": "repertoire_song",
  "song_id": 123,
  "tip_amount_cents": 500,
  "note": "Acoustic version"
}
```

`song_id` must belong to the selected `{projectId}` repertoire.

**Request (original):**
```json
{
  "type": "original",
  "tip_amount_cents": 0
}
```

Original requests are only allowed when project setting `is_accepting_original_requests` is `true`.
`tip_amount_cents` is optional for all types and defaults to `0` when omitted.

**Response (201):**
```json
{
  "message": "Queue item added.",
  "request": {
    "id": 42,
    "song": {
      "id": 1,
      "title": "Crowd Favorite Mashup",
      "artist": "Custom Request"
    },
    "tip_amount_cents": 0,
    "tip_amount_dollars": "0.00",
    "status": "active",
    "note": "Mash two choruses if possible",
    "played_at": null,
    "created_at": "2026-02-14T17:10:00+00:00"
  }
}
```

**Response (404):** User does not have access to the project.

**Response (422):**
- Validation failure (invalid type, missing `custom_title`, invalid `song_id`, etc.)
- `{"message":"This project is not currently accepting original requests."}` when `type = "original"` and originals are disabled.

---

### GET `/v1/me/projects/{projectId}/requests/history`

Get played requests history (paginated).

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `per_page` | int | Items per page (default: 50) |
| `page` | int | Page number |

**Response (200):** Same structure as queue, but with `status: "played"` and `played_at` populated.

---

### POST `/v1/me/requests/{requestId}/played`

Mark a request as played.

**Response (200):**
```json
{
  "message": "Request marked as played.",
  "request": {
    "id": 42,
    "song": {
      "id": 1,
      "title": "Fly Me to the Moon",
      "artist": "Frank Sinatra"
    },
    "tip_amount_cents": 1500,
    "tip_amount_dollars": "15.00",
    "status": "played",
    "requester_name": null,
    "note": "Happy birthday!",
    "activated_at": "2026-02-03T12:00:00+00:00",
    "played_at": "2026-02-03T12:30:00+00:00",
    "created_at": "2026-02-03T11:55:00+00:00"
  }
}
```

**Response (403):** Not authorized to mark this request.

---

## Repertoire Management (Authenticated)

### GET `/v1/me/projects/{projectId}/repertoire`

Get repertoire for a project you have access to.

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `per_page` | int | Items per page (default: 50) |
| `page` | int | Page number |

**Response (200):**
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
      "last_performed_at": "2026-02-11T22:20:10+00:00"
    }
  ]
}
```

`energy_level` and `genre` in authenticated repertoire responses are effective values:
project-level override first, otherwise global song metadata.

---

### POST `/v1/me/projects/{projectId}/repertoire`

Add a song to repertoire.

**Request (existing song):**
```json
{
  "song_id": 1,
  "energy_level": "medium",
  "era": "60s",
  "genre": "Jazz"
}
```

**Request (new song):**
```json
{
  "title": "New Song",
  "artist": "Some Artist",
  "energy_level": "high",
  "era": "2020s",
  "genre": "Pop"
}
```

**Validation Rules:**
- `song_id`: required without title, must exist
- `title`: required without song_id, max 255 chars
- `artist`: required with title, max 255 chars
- `energy_level`: optional project-level override, enum: `low`, `medium`, `high` (inherits song value when omitted/null)
- `era`: optional, max 50 chars
- `genre`: optional project-level override, max 50 chars (inherits song value when omitted/null)
- `performed_musical_key`: optional
- `tuning`: optional, max 50 chars
- `capo`: optional, integer, min 0, max 12
- `duration_in_seconds`: optional, integer, min 0, max 86400

When a brand-new song is created via `title` + `artist`, provided `energy_level` and
`genre` seed the global song metadata and the project inherits them by default.

**Response (201):**
```json
{
  "message": "Song added to repertoire.",
  "project_song": { ... }
}
```

**Response (409) - Duplicate:**
```json
{
  "message": "This song is already in the repertoire.",
  "project_song": { ... }
}
```

---

### GET `/v1/me/projects/{projectId}/repertoire/metadata`

Fetch metadata suggestion for a song title + artist.

Lookup order:
1. Check `songs` table by normalized title/artist key.
2. If no metadata exists in `songs`, query Gemini using title + artist prompt.

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `title` | string | Required song title |
| `artist` | string | Required artist name |

**Response (200):**
```json
{
  "data": {
    "source": "songs_table",
    "metadata": {
      "energy_level": "high",
      "era": "90s",
      "genre": "Rock",
      "original_musical_key": "F#",
      "duration_in_seconds": 259
    }
  }
}
```

`source` values:
- `songs_table`: metadata came from existing DB song record.
- `gemini`: metadata came from Gemini fallback.
- `none`: no metadata found.

When `source = none`, all `metadata` fields are returned as `null`.

---

### PUT `/v1/me/projects/{projectId}/repertoire/{projectSongId}`

Update a repertoire song's metadata.

**Request:**
```json
{
  "energy_level": "high",
  "era": "70s",
  "genre": "Rock",
  "needs_improvement": true
}
```

**Validation Rules:**
- `energy_level`: optional project-level override, enum: `low`, `medium`, `high` (`null` clears override and falls back to song metadata)
- `era`: optional, max 50 chars
- `genre`: optional project-level override, max 50 chars (`null` clears override and falls back to song metadata)
- `performed_musical_key`: optional
- `tuning`: optional, max 50 chars
- `capo`: optional, integer, min 0, max 12
- `duration_in_seconds`: optional, integer, min 0, max 86400
- `needs_improvement`: optional, boolean

**Response (200):**
```json
{
  "message": "Repertoire song updated.",
  "project_song": { ... }
}
```

---

### POST `/v1/me/projects/{projectId}/repertoire/{projectSongId}/performances`

Log a song performance and update performance counters.

**Request (repertoire source):**
```json
{
  "source": "repertoire"
}
```

**Request (setlist source):**
```json
{
  "source": "setlist",
  "performed_at": "2026-02-11T21:00:00Z",
  "setlist_id": 10,
  "set_id": 22,
  "setlist_song_id": 37
}
```

**Validation Rules:**
- `source`: required, enum: `repertoire`, `setlist`
- `performed_at`: optional, date/ISO8601 (defaults to server `now()`)
- `setlist_id`: optional, integer, must exist; prohibited when `source = repertoire`
- `set_id`: optional, integer, must exist; prohibited when `source = repertoire`
- `setlist_song_id`: optional, integer, must exist; prohibited when `source = repertoire`
- When setlist IDs are supplied, they must belong to the same project and be internally consistent.
- If `setlist_song_id` is supplied, it must reference the same `projectSongId` from the URL.

**Response (201):**
```json
{
  "message": "Performance logged successfully.",
  "project_song": {
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
    "performance_count": 8,
    "last_performed_at": "2026-02-11T22:20:10+00:00"
  },
  "performance": {
    "id": 88,
    "performed_at": "2026-02-11T22:20:10+00:00",
    "source": "setlist",
    "setlist_id": 10,
    "set_id": 22,
    "setlist_song_id": 37
  }
}
```

**Response (401):** Unauthenticated.

**Response (404):** Project not found or no access to project.

**Response (403):** `projectSongId` does not belong to `projectId`.

**Response (422):** Validation error (invalid `source`, invalid/mismatched setlist references, prohibited fields for repertoire source).

Writes are executed transactionally: performance insert + `performance_count` increment + `last_performed_at` max update.

---

### DELETE `/v1/me/projects/{projectId}/repertoire/{projectSongId}`

Remove a song from repertoire.

**Response (200):**
```json
{
  "message": "Song removed from repertoire."
}
```

---

### POST `/v1/me/projects/{projectId}/repertoire/bulk-import`

Bulk import songs from PDF filenames.

**Request:** `multipart/form-data`
- `files[]`: PDF files (`1..20`, max 10MB each)
- `items[index][title]`: optional song title hint for file at `index`
- `items[index][artist]`: optional artist hint for file at `index`
- `title`: optional string (deprecated, ignored)
- `artist`: optional string (deprecated, ignored)

Chunking requirement:
- Clients must split selections larger than 20 files into multiple requests.
- The same request/response contract applies to each chunk.

Filename parsing:
- Default filename base format: `Song Title - Artist.pdf`
- Optional metadata tokens:
  - Append tokens with ` -- ` delimiters
  - Token syntax: `key=value` (or `key:value`)
  - Supported keys:
    - `key`, `performed_key`, `performed_musical_key`
    - `original_key`, `original_musical_key`
    - `tuning`
    - `capo` (0-12)
    - `energy`, `energy_level` (`low`, `medium`, `high`)
    - `era`
    - `genre`
    - `duration`, `duration_seconds`, `duration_in_seconds`
- Example:
  - `Wonderwall - Oasis -- key=F#m -- original_key=Gm -- tuning=Drop D -- capo=2 -- energy=high -- era=90s -- genre=Rock -- duration=240.pdf`

Queue-only flow:
- If `items[index][title|artist]` matches an existing `songs` row, backend
  imports chart immediately and links `project_songs` without Gemini
  enrichment.
- For immediate imports, chart storage is idempotent per
  `(owner_user_id, project_id, song_id)`:
  - If source PDF bytes match the existing chart source, backend reuses the
    existing chart row.
  - If source PDF bytes differ, backend replaces the existing chart source PDF
    in place and clears stale render records.
- Otherwise backend stores chart and queues asynchronous song identification
  from chart image content.

Duplicate detection (server-side):
- The client should still upload charts even when the song already exists in project repertoire.
- Backend returns `action = "duplicate"` only when all of the following are true:
  - `project_songs` already contains `(project_id, song_id)`.
  - At least one existing chart already exists for `(owner_user_id, project_id, song_id)`.
  - The uploaded chart PDF is byte-identical to an existing chart PDF.
- If any of these are false, backend treats the upload as non-duplicate.

**Response (200):**
```json
{
  "message": "Imported 1 song(s), Queued 1 chart(s) for identification.",
  "imported": 1,
  "queued": 1,
  "duplicates": 0,
  "songs": [
    {
      "filename": "Wonderwall - Oasis.pdf",
      "action": "imported",
      "chart_id": 55,
      "song_id": 12
    },
    {
      "filename": "Unknown Chart.pdf",
      "action": "queued",
      "chart_id": 56
    }
  ]
}
```

`songs[*].action` is one of:
- `imported`: existing song match, chart stored immediately
- `queued`: chart queued for Gemini identification
- `duplicate`: matching chart already exists (byte-identical), upload skipped

For duplicate results, backend may include `duplicate_of` for display.

---

## Charts (Authenticated)

### GET `/v1/me/charts`

List your charts.

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `project_id` | int | Filter by project |
| `song_id` | int | Filter by song |
| `per_page` | int | Items per page (default: 50) |

---

### POST `/v1/me/charts`

Upload a new chart PDF.

**Request:** `multipart/form-data`
- `file`: PDF file (required, max 10MB)
- `song_id`: int (required)
- `project_id`: int (required)

**Response (201):**
```json
{
  "message": "Chart uploaded successfully.",
  "chart": { ... }
}
```

---

### GET `/v1/me/charts/{chartId}`

Get a single chart record owned by the authenticated performer.

**Response (200):**
```json
{
  "chart": {
    "id": 10,
    "song": {
      "id": 42,
      "title": "Fly Me to the Moon",
      "artist": "Frank Sinatra"
    },
    "project_id": 3,
    "has_renders": true,
    "page_count": 4,
    "created_at": "2026-02-12T10:15:00+00:00"
  }
}
```

**Response (403):** Not authorized to access this chart.

---

### GET `/v1/me/charts/{chartId}/signed-url`

Get a temporary signed URL to download the chart PDF.

**Response (200):**
```json
{
  "url": "https://r2.example.com/charts/...?signature=..."
}
```

URL expires after a configured TTL (typically 15 minutes).

---

### GET `/v1/me/charts/{chartId}/page`

Get a temporary signed URL for a rendered chart page image.

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `page` | int | Page number (required, starts at 1) |
| `theme` | string | `light` or `dark` (required) |

**Response (200):**
```json
{
  "url": "https://r2.example.com/charts/.../renders/light/page-1.png?signature=..."
}
```

URL expires after 15 minutes.

**Response (404):**
```json
{
  "message": "Chart render is not available yet. Please try again shortly."
}
```

Returned when the chart has not been rendered yet or the page/theme combination doesn't exist.

**Response (403):** Not authorized to access this chart.

---

### POST `/v1/me/charts/{chartId}/pages/{page}/annotations`

Store an annotation version for a rendered chart page. This endpoint is
idempotent by `local_version_id` per `(chartId, page)`.

**Request:**
```json
{
  "chart_id": 10,
  "page_number": 1,
  "local_version_id": "a1874b61-3c1a-4515-9f3a-dfdc16f5b0d8",
  "base_version_id": "previous-version-id-optional",
  "created_at": "2026-02-11T20:15:00Z",
  "strokes": [
    {
      "points": [
        { "x": 0.1, "y": 0.2 },
        { "x": 0.3, "y": 0.4 }
      ],
      "color_value": 4281545523,
      "thickness": 2.5,
      "is_eraser": false
    }
  ]
}
```

**Validation Rules:**
- `local_version_id`: required, string, max 255 chars
- `base_version_id`: optional, string, max 255 chars
- `created_at`: required, ISO8601 datetime
- `strokes`: required key, array (can be empty)
- `chart_id` and `page_number` are optional in payload, but when provided must
  match route values

**Response (201):**
```json
{
  "message": "Annotation version stored.",
  "annotation_version": {
    "id": 77,
    "chart_id": 10,
    "page_number": 1,
    "local_version_id": "a1874b61-3c1a-4515-9f3a-dfdc16f5b0d8",
    "base_version_id": null,
    "strokes": [],
    "client_created_at": "2026-02-11T20:15:00+00:00",
    "created_at": "2026-02-11T20:15:01+00:00"
  }
}
```

**Response (200):** Same body as above when a duplicate `local_version_id` is
already stored (idempotent retry).

**Response (403):** Not authorized to annotate this chart.

---

### POST `/v1/me/charts/{chartId}/render`

Trigger background job to render chart pages as images.

**Response (200):**
```json
{
  "message": "Chart render job dispatched.",
  "chart": { ... }
}
```

---

### DELETE `/v1/me/charts/{chartId}`

Delete a chart and its files from storage.

**Response (200):**
```json
{
  "message": "Chart deleted successfully."
}
```

---

## Setlists (Authenticated)

Setlists allow performers to organize songs into sets for performances.

### GET `/v1/me/projects/{projectId}/setlists`

List all setlists for a project.

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "project_id": 1,
      "name": "Friday Night Set",
      "created_at": "2026-02-04T12:00:00+00:00",
      "sets": [
        {
          "id": 1,
          "setlist_id": 1,
          "name": "Set 1",
          "order_index": 0,
          "songs": [
            {
              "id": 1,
              "set_id": 1,
              "project_song_id": 5,
              "order_index": 0,
              "song": {
                "id": 10,
                "title": "Fly Me to the Moon",
                "artist": "Frank Sinatra"
              }
            }
          ]
        }
      ]
    }
  ]
}
```

---

### POST `/v1/me/projects/{projectId}/setlists`

Create a new setlist.

**Request:**
```json
{
  "name": "Saturday Gig"
}
```

**Validation Rules:**
- `name`: required, string, max 255 chars

**Response (201):**
```json
{
  "message": "Setlist created",
  "setlist": {
    "id": 2,
    "project_id": 1,
    "name": "Saturday Gig",
    "created_at": "2026-02-04T12:00:00+00:00",
    "sets": []
  }
}
```

---

### GET `/v1/me/projects/{projectId}/setlists/{setlistId}`

Get a single setlist with its sets and songs.

**Response (200):**
```json
{
  "setlist": {
    "id": 1,
    "project_id": 1,
    "name": "Friday Night Set",
    "created_at": "2026-02-04T12:00:00+00:00",
    "sets": [ ... ]
  }
}
```

---

### PUT `/v1/me/projects/{projectId}/setlists/{setlistId}`

Update a setlist.

**Request:**
```json
{
  "name": "Updated Name"
}
```

**Response (200):**
```json
{
  "message": "Setlist updated",
  "setlist": { ... }
}
```

---

### DELETE `/v1/me/projects/{projectId}/setlists/{setlistId}`

Delete a setlist and all its sets/songs.

**Response (200):**
```json
{
  "message": "Setlist deleted"
}
```

---

## Setlist Sets (Authenticated)

### POST `/v1/me/projects/{projectId}/setlists/{setlistId}/sets`

Create a new set within a setlist.

**Request:**
```json
{
  "name": "Set 1",
  "order_index": 0
}
```

**Validation Rules:**
- `name`: required, string, max 255 chars
- `order_index`: optional, integer, min 0 (auto-increments if not provided)

**Response (201):**
```json
{
  "message": "Set created",
  "set": {
    "id": 1,
    "setlist_id": 1,
    "name": "Set 1",
    "order_index": 0,
    "songs": []
  }
}
```

---

### PUT `/v1/me/projects/{projectId}/setlists/{setlistId}/sets/{setId}`

Update a set.

**Request:**
```json
{
  "name": "First Set",
  "order_index": 1
}
```

**Validation Rules:**
- `name`: optional, string, max 255 chars
- `order_index`: optional, integer, min 0

**Response (200):**
```json
{
  "message": "Set updated",
  "set": { ... }
}
```

---

### DELETE `/v1/me/projects/{projectId}/setlists/{setlistId}/sets/{setId}`

Delete a set and all its songs.

**Response (200):**
```json
{
  "message": "Set deleted"
}
```

---

## Setlist Songs (Authenticated)

### POST `/v1/me/projects/{projectId}/setlists/{setlistId}/sets/{setId}/songs`

Add a song to a set.

**Request:**
```json
{
  "project_song_id": 5,
  "order_index": 0
}
```

**Validation Rules:**
- `project_song_id`: required, must exist in project_songs table and belong to this project
- `order_index`: optional, integer, min 0 (auto-increments if not provided)

**Response (201):**
```json
{
  "message": "Song added to set",
  "setlist_song": {
    "id": 1,
    "set_id": 1,
    "project_song_id": 5,
    "order_index": 0,
    "song": {
      "id": 10,
      "title": "Fly Me to the Moon",
      "artist": "Frank Sinatra"
    }
  }
}
```

**Response (500) - Duplicate:** A song cannot appear twice in the same set (database constraint).

---

### DELETE `/v1/me/projects/{projectId}/setlists/{setlistId}/sets/{setId}/songs/{songId}`

Remove a song from a set.

**Response (200):**
```json
{
  "message": "Song removed from set"
}
```

---

### PUT `/v1/me/projects/{projectId}/setlists/{setlistId}/sets/{setId}/songs/reorder`

Reorder songs within a set.

**Request:**
```json
{
  "song_ids": [3, 1, 2]
}
```

The order of IDs in the array determines the new `order_index` values (0, 1, 2, ...).

**Validation Rules:**
- `song_ids`: required, array of setlist_song IDs
- `song_ids.*`: must exist in setlist_songs table

**Response (200):**
```json
{
  "message": "Songs reordered",
  "songs": [
    {
      "id": 3,
      "set_id": 1,
      "project_song_id": 7,
      "order_index": 0,
      "song": { ... }
    },
    {
      "id": 1,
      "set_id": 1,
      "project_song_id": 5,
      "order_index": 1,
      "song": { ... }
    },
    {
      "id": 2,
      "set_id": 1,
      "project_song_id": 6,
      "order_index": 2,
      "song": { ... }
    }
  ]
}
```

---

## Error Responses

All errors follow this format:

```json
{
  "message": "Human-readable error message.",
  "errors": {
    "field_name": ["Validation error message."]
  }
}
```

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 304 | Not Modified (ETag match) |
| 401 | Unauthorized (invalid/missing token) |
| 403 | Forbidden (no permission) |
| 404 | Not Found |
| 409 | Conflict (duplicate) |
| 422 | Validation Error |
| 429 | Rate Limited |
| 500 | Server Error |

---

## Polling Best Practices

### Queue Polling

Use ETag for efficient polling:

```dart
final response = await http.get(
  Uri.parse('$baseUrl/v1/me/projects/$projectId/queue'),
  headers: {
    'Authorization': 'Bearer $token',
    'If-None-Match': lastEtag ?? '',
  },
);

if (response.statusCode == 304) {
  // No changes, skip processing
  return;
}

lastEtag = response.headers['etag'];
// Process new data...
```

Recommended poll interval: 5-10 seconds.

---

## Stripe Integration

After creating a request, use the `client_secret` with Stripe SDK:

```dart
await Stripe.instance.confirmPayment(
  paymentIntentClientSecret: clientSecret,
  data: PaymentMethodParams.card(
    paymentMethodData: PaymentMethodData(),
  ),
);
```

Payment methods supported: Card, Apple Pay, Google Pay.

After successful payment, the request status changes to `active` and appears in the queue.

### Implementation note (server-side)

Internally, requests may use a `pending` status between initial creation and confirmed payment activation. The public API continues to expose only `active` and `played` states.
