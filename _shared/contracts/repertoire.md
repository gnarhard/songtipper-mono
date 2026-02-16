# Repertoire API Contracts (v1.2)

## Scope and auth

- Protected routes require `Authorization: Bearer <token>`.
- All write endpoints support `Idempotency-Key`.
- Route prefix: `/api/v1/me/projects/{project_id}`.

---

## Mood metadata

- `songs.mood`: nullable global mood.
- `project_songs.mood`: nullable project override.
- Effective mood: `project_songs.mood ?? songs.mood`.
- Validation pattern: `^[a-zA-Z0-9_-]+$` (one word).

---

## Repertoire

### List
- `GET /repertoire`
- Supports `mood` filter.

### Create
- `POST /repertoire`
- Supports mood in request and response payloads.

### Update
- `PUT /repertoire/{projectSongId}`
- Supports mood updates at project override level.

### Delete
- `DELETE /repertoire/{projectSongId}`

### Log performance
- `POST /repertoire/{projectSongId}/performances`
- When a performance session is active, session-aware completion should use `/performances/current/complete`.

---

## Bulk Import

- `POST /repertoire/bulk-import`
- Limits:
  - max files per request: `128`
  - max size per PDF: `2MB`
- Filename metadata supports: `key`, `capo`, `tuning`, `energy`, `era`, `genre`, `mood`.
- Mood filename token example: `-- mood=party`.

---

## Copy Repertoire

- `POST /repertoire/copy-from`
- Body:

```json
{
  "source_project_id": 1,
  "include_charts": true
}
```

Behavior:
- Copies repertoire rows and overrides.
- If `include_charts=true`, copies chart linkage for the destination project.

---

## To-Learn list

- `GET /learning-songs`
- `POST /learning-songs`
- `PUT /learning-songs/{learningSongId}`
- `DELETE /learning-songs/{learningSongId}`

Fields:
- `youtube_video_url` (optional)
  - Backend query: `"<title> by <artist> music video"`
  - If omitted or null on create, backend attempts to resolve the top YouTube
    video result (by view count) and stores a direct watch URL
  - If omitted on update and current value is null, backend backfills using the
    same resolver behavior
  - If resolver is unavailable or no video is found, backend stores fallback
    search URL:
    `https://www.youtube.com/results?search_query=<title+by+artist+music+video>`
- `ultimate_guitar_url` (optional, link/search only; no scraping)
- `notes` (optional)
