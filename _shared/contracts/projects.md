# Projects API Contracts (v1.2)

## Scope and auth

- Protected routes require `Authorization: Bearer <token>`.
- Write endpoints support `Idempotency-Key`.
- Route prefix: `/api/v1/me/projects`.

---

## Project CRUD

- `GET /`
- `POST /`
- `PUT /{projectId}`
- `PATCH /{projectId}`
- `POST /{projectId}/performer-image`

---

## Canonical settings fields

- `name`
- `performer_info_url`
- `min_tip_cents`
- `is_accepting_requests`
- `is_accepting_original_requests`
- `show_persistent_queue_strip`
- `remove_performer_profile_image`

---

## Deprecated field

`chart_viewport_prefs` at project scope is deprecated.

Canonical viewport persistence is now per `(user, chart, page)` via:
- `GET /api/v1/me/charts/{chartId}/pages/{page}/viewport`
- `PUT /api/v1/me/charts/{chartId}/pages/{page}/viewport`

Clients should migrate to these endpoints and stop writing project-level viewport blobs.
