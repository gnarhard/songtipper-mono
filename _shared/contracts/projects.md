# Projects API Contracts

## Auth and scope

- All endpoints below require `Authorization: Bearer <token>`.
- Route prefix: `/api/v1/me/projects`

---

## List Projects

- **Method**: `GET`
- **Path**: `/`

List all projects the authenticated user has access to.

### Success response (`200`)

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

## Create Project

- **Method**: `POST`
- **Path**: `/`

Create a new project for the authenticated user.

### Request body

```json
{
  "name": "Friday Jazz Night"
}
```

**Validation Rules:**
- `name`: required, string, max 255 chars, cannot be blank/whitespace-only

### Success response (`201`)

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

### Error response (`422`)

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

---

## Update Project

- **Method**: `PATCH` or `PUT`
- **Path**: `/{projectId}`

Update project settings. **Only the project owner can update a project.**

### Request body

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

### Success response (`200`)

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

### Error response (`403`)

```json
{
  "message": "This action is unauthorized."
}
```

Only the project owner can update the project settings.

---

## Upload Performer Profile Image

- **Method**: `POST`
- **Path**: `/{projectId}/performer-image`

Upload or replace the performer profile image for a project. **Owner-only.**

### Request body (multipart/form-data)

- `image`: required image file (`jpeg`, `png`, `webp`), max 5MB

### Success response (`201`)

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

### Error response (`403`)

```json
{
  "message": "This action is unauthorized."
}
```
