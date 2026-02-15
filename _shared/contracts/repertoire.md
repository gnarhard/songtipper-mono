# Repertoire API Contracts

## Auth and scope

- All endpoints below require `Authorization: Bearer <token>`.
- All endpoints are performer-scoped and project-scoped via `{project_id}`.
- Route prefix: `/api/v1/me/projects/{project_id}/repertoire`

---

## List Repertoire

- **Method**: `GET`
- **Path**: `/`

Get repertoire for a project you have access to.

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `per_page` | int | Items per page (default: 50) |
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
      "last_performed_at": "2026-02-11T22:20:10+00:00"
    }
  ]
}
```

**Notes:**
- `energy_level` and `genre` are effective values: project-level override first, otherwise global song metadata

---

## Add Song to Repertoire

- **Method**: `POST`
- **Path**: `/`

Add a song to repertoire.

### Request body (existing song)

```json
{
  "song_id": 1,
  "energy_level": "medium",
  "era": "60s",
  "genre": "Jazz"
}
```

### Request body (new song)

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

**Notes:**
- When a brand-new song is created via `title` + `artist`, provided `energy_level` and `genre` seed the global song metadata and the project inherits them by default

### Success response (`201`)

```json
{
  "message": "Song added to repertoire.",
  "project_song": { ... }
}
```

### Duplicate response (`409`)

```json
{
  "message": "This song is already in the repertoire.",
  "project_song": { ... }
}
```

---

## Get Song Metadata

- **Method**: `GET`
- **Path**: `/metadata`

Fetch metadata suggestion for a song title + artist.

**Lookup order:**
1. Check `songs` table by normalized title/artist key
2. If no metadata exists in `songs`, query Gemini using title + artist prompt

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `title` | string | Required song title |
| `artist` | string | Required artist name |

### Success response (`200`)

```json
{
  "data": {
    "source": "songs_table",
    "metadata": {
      "energy_level": "high",
      "era": "90s",
      "genre": "Rock",
      "performed_musical_key": "F#",
      "original_musical_key": "F#",
      "duration_in_seconds": 259
    }
  }
}
```

**Source values:**
- `songs_table`: metadata came from existing DB song record
- `gemini`: metadata came from Gemini fallback
- `none`: no metadata found

**Notes:**
- `performed_musical_key` resolution: Use project-level `project_songs.performed_musical_key` when a matching project song exists, otherwise mirror `original_musical_key`
- When `source = none`, all `metadata` fields are returned as `null`

---

## Update Repertoire Song

- **Method**: `PUT`
- **Path**: `/{projectSongId}`

Update a repertoire song's metadata.

### Request body

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

### Success response (`200`)

```json
{
  "message": "Repertoire song updated.",
  "project_song": { ... }
}
```

---

## Delete Repertoire Song

- **Method**: `DELETE`
- **Path**: `/{projectSongId}`

Remove a song from repertoire.

### Success response (`200`)

```json
{
  "message": "Song removed from repertoire."
}
```

---

## Log Performance

- **Method**: `POST`
- **Path**: `/{projectSongId}/performances`

Log a song performance and update performance counters.

### Request body (repertoire source)

```json
{
  "source": "repertoire"
}
```

### Request body (setlist source)

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
- When setlist IDs are supplied, they must belong to the same project and be internally consistent
- If `setlist_song_id` is supplied, it must reference the same `projectSongId` from the URL

### Success response (`201`)

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

### Error responses

**Unauthenticated (`401`)**

**Project not found or no access (`404`)**

**ProjectSongId does not belong to projectId (`403`)**

**Validation error (`422`):** Invalid `source`, invalid/mismatched setlist references, prohibited fields for repertoire source

**Notes:**
- Writes are executed transactionally: performance insert + `performance_count` increment + `last_performed_at` max update

---

## Bulk Import

- **Method**: `POST`
- **Path**: `/bulk-import`

Bulk import songs from PDF filenames.

### Request body (multipart/form-data)

- `files[]`: PDF files (`1..20`, max 10MB each)
- `items[index][title]`: optional song title hint for file at `index`
- `items[index][artist]`: optional artist hint for file at `index`
- `title`: optional string (deprecated, ignored)
- `artist`: optional string (deprecated, ignored)

**Chunking requirement:**
- Clients must split selections larger than 20 files into multiple requests
- The same request/response contract applies to each chunk
- Performer mobile app default behavior: send `1` file per request to reduce 413 risk on default nginx setups

**Infrastructure requirement (to avoid 413):**
- For default nginx (`client_max_body_size 1m`), only smaller PDFs will pass
- Larger PDFs still require higher proxy/PHP body limits
- If supporting full-size chunk requests (`20 x 10MB`), set a minimum request budget of `~220MB` (recommended `256MB`)
- Nginx target: `client_max_body_size 256m;`
- PHP target: `upload_max_filesize=10M`, `post_max_size=256M`

**Filename parsing:**
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
  - Example: `Wonderwall - Oasis -- key=F#m -- original_key=Gm -- tuning=Drop D -- capo=2 -- energy=high -- era=90s -- genre=Rock -- duration=240.pdf`

**Queue-only flow:**
- If `items[index][title|artist]` matches an existing `songs` row, backend imports chart immediately and links `project_songs` without Gemini enrichment
- For immediate imports, chart storage is idempotent per `(owner_user_id, project_id, song_id)`:
  - If source PDF bytes match the existing chart source, backend reuses the existing chart row
  - If source PDF bytes differ, backend replaces the existing chart source PDF in place and clears stale render records
- Otherwise backend stores chart and queues asynchronous song identification from chart image content

**Duplicate detection (server-side):**
- The client should still upload charts even when the song already exists in project repertoire
- Backend returns `action = "duplicate"` only when all of the following are true:
  - `project_songs` already contains `(project_id, song_id)`
  - At least one existing chart already exists for `(owner_user_id, project_id, song_id)`
  - The uploaded chart PDF is byte-identical to an existing chart PDF
- If any of these are false, backend treats the upload as non-duplicate

### Success response (`200`)

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

**Action values:**
- `imported`: existing song match, chart stored immediately
- `queued`: chart queued for Gemini identification
- `duplicate`: matching chart already exists (byte-identical), upload skipped

For duplicate results, backend may include `duplicate_of` for display.

### Error response (`413`)

Request payload exceeded proxy/PHP body size limits.

**Client action:**
- Show a per-chart failed message in the Failed tab

**User action:**
- Reduce oversized PDF files

**Server action:**
- Increase `client_max_body_size` (proxy) and PHP `post_max_size` to handle a full chunk
