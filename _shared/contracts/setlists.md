# Setlists + Performance API Contracts (v1.2)

## Scope and auth

- Protected routes require `Authorization: Bearer <token>`.
- Write endpoints support `Idempotency-Key`.
- Route prefix: `/api/v1/me/projects/{project_id}`.

---

## Setlist notes

Notes are supported at three levels:
- `setlists.notes`
- `setlist_sets.notes`
- `setlist_songs.notes`

Create/update payloads accept `notes` as nullable text.

---

## Setlist management

- `GET /setlists`
- `POST /setlists`
- `GET /setlists/{setlistId}`
- `PUT /setlists/{setlistId}`
- `DELETE /setlists/{setlistId}`

Sets:
- `POST /setlists/{setlistId}/sets`
- `PUT /setlists/{setlistId}/sets/{setId}`
- `DELETE /setlists/{setlistId}/sets/{setId}`

Set songs:
- `POST /setlists/{setlistId}/sets/{setId}/songs`
- `POST /setlists/{setlistId}/sets/{setId}/songs/bulk`
- `PUT /setlists/{setlistId}/sets/{setId}/songs/{songId}`
- `PUT /setlists/{setlistId}/sets/{setId}/songs/reorder`
- `DELETE /setlists/{setlistId}/sets/{setId}/songs/{songId}`

Paste import:
- `POST /setlists/{setlistId}/sets/{setId}/songs/import-text`
- Accepts newline-separated lines (`Title` or `Title - Artist`).

---

## Smart generation

- `POST /setlists/generate-smart`
- Stores generation metadata (`seed`, version, constraints) on the created setlist.

---

## Performance sessions

Routes:
- `POST /performances/start`
- `POST /performances/stop`
- `GET /performances/current`
- `POST /performances/current/complete`
- `POST /performances/current/skip`
- `POST /performances/current/random`

Rules:
- Exactly one active session per project.
- Starting while active returns `409 Conflict`.
- Session mode: `manual` or `smart`.
- `complete` records sequential `performed_order_index`.
- Smart mode can reorder pending items after skip/complete.
