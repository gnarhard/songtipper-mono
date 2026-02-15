# Charts API Contracts (v1.2)

## Scope and auth

- Protected routes require `Authorization: Bearer <token>`.
- Write endpoints support `Idempotency-Key`.
- Canonical viewport storage is per `(user_id, chart_id, page)` in `chart_page_user_prefs`.
- `projects.chart_viewport_prefs` is deprecated for new clients.

---

## Upload Chart

- **Method**: `POST`
- **Path**: `/api/v1/me/charts`
- **Body**: multipart
  - `file`: PDF (required, max 2MB)
  - `song_id`: int (required)
  - `project_id`: int (required)

---

## Viewport Preferences

### Get viewport
- **Method**: `GET`
- **Path**: `/api/v1/me/charts/{chartId}/pages/{page}/viewport`

### Save viewport
- **Method**: `PUT`
- **Path**: `/api/v1/me/charts/{chartId}/pages/{page}/viewport`
- **Headers**: `Idempotency-Key` (recommended)
- **Body**:

```json
{
  "zoom_scale": 1.2,
  "offset_dx": 0,
  "offset_dy": 0
}
```

---

## Annotation Versions

### Get latest annotation
- **Method**: `GET`
- **Path**: `/api/v1/me/charts/{chartId}/pages/{page}/annotations/latest`
- Semantics: latest-selection (LWW) per `(user, chart, page)`.

### Store annotation version
- **Method**: `POST`
- **Path**: `/api/v1/me/charts/{chartId}/pages/{page}/annotations`
- **Headers**: `Idempotency-Key`
- **Body**:

```json
{
  "local_version_id": "uuid",
  "base_version_id": "optional-parent-version",
  "created_at": "2026-02-15T18:00:00Z",
  "strokes": []
}
```

Notes:
- `base_version_id` is retained for future merge strategies.
- MVP conflict policy is versioned + idempotent with latest-selection semantics.

---

## Chart Render and Download

- `GET /api/v1/me/charts/{chartId}`
- `GET /api/v1/me/charts/{chartId}/signed-url`
- `GET /api/v1/me/charts/{chartId}/page?page={page}&theme={light|dark}`
- `POST /api/v1/me/charts/{chartId}/render` (`Idempotency-Key` supported)
- `DELETE /api/v1/me/charts/{chartId}` (`Idempotency-Key` supported)
