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
- `GET /api/v1/me/charts/{chartId}/render-status`
- `GET /api/v1/me/charts/{chartId}/signed-url`
- `GET /api/v1/me/charts/{chartId}/page?page={page}&theme={light|dark}`
- `POST /api/v1/me/charts/{chartId}/render` (`Idempotency-Key` supported)
- `DELETE /api/v1/me/charts/{chartId}` (`Idempotency-Key` supported)

### Render Status Endpoint

- **Method**: `GET`
- **Path**: `/api/v1/me/charts/{chartId}/render-status`
- **Purpose**: Single-call render verification for mobile bulk import and cache preflight.
- **Response**:
  - `status`: `ready | pending | failed`
  - `ready`: boolean
  - `pending`: boolean
  - `failure_reason`: nullable string machine code
    - `source_pdf_missing`
    - `render_file_missing`
    - `render_metadata_inconsistent`
  - `page_count`: integer
  - `render_count`: integer
  - `expected_render_count`: nullable integer
  - `has_renders`: boolean
  - `missing_render_file_count`: integer

Notes:
- `pending` means the chart exists in DB but image rendering is not complete yet.
- `failed` means artifacts are inconsistent/missing and upload verification should fail.
- `failed` with `render_metadata_inconsistent` means render rows and chart metadata (`page_count`, `has_renders`) disagree.
- Clients should prefer this endpoint over per-page render URL checks to reduce request fan-out.
