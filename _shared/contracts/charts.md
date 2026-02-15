# Charts API Contracts

## Auth and scope

- All endpoints below require `Authorization: Bearer <token>`.
- Route prefix: `/api/v1/me/charts`

---

## List Charts

- **Method**: `GET`
- **Path**: `/`

List your charts.

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `project_id` | int | Filter by project |
| `song_id` | int | Filter by song |
| `per_page` | int | Items per page (default: 50) |

---

## Upload Chart

- **Method**: `POST`
- **Path**: `/`

Upload a new chart PDF.

### Request body (multipart/form-data)

- `file`: PDF file (required, max 10MB)
- `song_id`: int (required)
- `project_id`: int (required)

### Success response (`201`)

```json
{
  "message": "Chart uploaded successfully.",
  "chart": { ... }
}
```

---

## Get Chart

- **Method**: `GET`
- **Path**: `/{chartId}`

Get a single chart record owned by the authenticated performer.

### Success response (`200`)

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

### Error response (`403`)

Not authorized to access this chart.

---

## Get Chart Signed URL

- **Method**: `GET`
- **Path**: `/{chartId}/signed-url`

Get a temporary signed URL to download the chart PDF.

### Success response (`200`)

```json
{
  "url": "https://r2.example.com/charts/...?signature=..."
}
```

**Notes:**
- URL expires after a configured TTL (typically 15 minutes)

---

## Get Chart Page Image

- **Method**: `GET`
- **Path**: `/{chartId}/page`

Get a temporary signed URL for a rendered chart page image.

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `page` | int | Page number (required, starts at 1) |
| `theme` | string | `light` or `dark` (required) |

### Success response (`200`)

```json
{
  "url": "https://r2.example.com/charts/.../renders/light/page-1.png?signature=..."
}
```

**Notes:**
- URL expires after 15 minutes

### Error response (`404`)

```json
{
  "message": "Chart render is not available yet. Please try again shortly."
}
```

Returned when the chart has not been rendered yet or the page/theme combination doesn't exist.

### Error response (`403`)

Not authorized to access this chart.

---

## Save Annotations

- **Method**: `POST`
- **Path**: `/{chartId}/pages/{page}/annotations`

Store an annotation version for a rendered chart page. **This endpoint is idempotent by `local_version_id` per `(chartId, page)`.**

### Request body

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
- `chart_id` and `page_number` are optional in payload, but when provided must match route values

### Success response (`201`)

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

### Idempotent retry response (`200`)

Same body as above when a duplicate `local_version_id` is already stored.

### Error response (`403`)

Not authorized to annotate this chart.

---

## Trigger Chart Render

- **Method**: `POST`
- **Path**: `/{chartId}/render`

Trigger background job to render chart pages as images.

### Success response (`200`)

```json
{
  "message": "Chart render job dispatched.",
  "chart": { ... }
}
```

---

## Delete Chart

- **Method**: `DELETE`
- **Path**: `/{chartId}`

Delete a chart and its files from storage.

### Success response (`200`)

```json
{
  "message": "Chart deleted successfully."
}
```
