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

Delete behavior:
- Remaining sets are always reindexed so `order_index` stays contiguous (`0..N-1`).
- For normal setlists (`generation_meta` is null/empty), remaining default-numbered titles (`Set <number>`) are renumbered sequentially.

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
- `constraints.themes_include[]` accepts only canonical theme enum values:
  `love`, `party`, `worship`, `story`, `st_patricks`, `christmas`, `halloween`, `patriotic`.
- Invalid theme values return `422`.

---

## Strategic generation

- `POST /setlists/generate-strategic`
- Separate from smart generation; existing smart flow remains unchanged.
- Strategic setlists are generated on the backend (online-only client flow).

Request shape:

```json
{
  "name": "Strategic Set 2026-02-23",
  "seed": 12345,
  "sets": [
    {
      "name": "Set 1",
      "song_count": 8,
      "criteria": {
        "energy_levels": ["low", "medium"],
        "eras": [],
        "genres": [],
        "themes": []
      }
    }
  ]
}
```

Rules:
- `sets` is required (`1..8`).
- `song_count` is required (`1..50`) per set.
- Criteria matching is:
  - AND across fields (`energy_levels`, `eras`, `genres`, `themes`)
  - OR within each field's values
- Field matching for genre/theme is case-insensitive.
- Selection excludes songs already chosen in earlier sets (no duplicates across generated sets).
- If a set has fewer matches than `song_count`, backend returns a partial set.
- If any set resolves to zero matches, backend returns `422` and no setlist is created.
- Omitted/blank set names default to `Set 1`, `Set 2`, etc.

Enums:
- `energy_levels`: `low | medium | high`
- `eras`: `50s | 60s | 70s | 80s | 90s | 2000s | 2010s | 2020s`
- `genres`: `Jazz | Rock | Pop | Blues | Country | Classical | R&B | Hip Hop | Folk | Electronic | Soul | Reggae | Latin`
- `themes`: `love | party | worship | story | st_patricks | christmas | halloween | patriotic`

Response:
- `201` with `{ data, meta }`
- `meta.generation.generation_version = strategic-v1`
- Per-set metadata includes requested and selected song counts.

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
