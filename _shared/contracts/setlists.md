# Setlists API Contracts

## Auth and scope

- All endpoints below require `Authorization: Bearer <token>`.
- All endpoints are performer-scoped and project-scoped via `{project_id}`.
- Route prefix: `/api/v1/me/projects/{project_id}/setlists`

---

## List Setlists

- **Method**: `GET`
- **Path**: `/`

List all setlists for a project.

### Success response (`200`)

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

## Create Setlist

- **Method**: `POST`
- **Path**: `/`

Create a new setlist.

### Request body

```json
{
  "name": "Saturday Gig"
}
```

**Validation Rules:**
- `name`: required, string, max 255 chars

### Success response (`201`)

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

## Get Setlist

- **Method**: `GET`
- **Path**: `/{setlistId}`

Get a single setlist with its sets and songs.

### Success response (`200`)

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

## Update Setlist

- **Method**: `PUT`
- **Path**: `/{setlistId}`

Update a setlist.

### Request body

```json
{
  "name": "Updated Name"
}
```

### Success response (`200`)

```json
{
  "message": "Setlist updated",
  "setlist": { ... }
}
```

---

## Delete Setlist

- **Method**: `DELETE`
- **Path**: `/{setlistId}`

Delete a setlist and all its sets/songs.

### Success response (`200`)

```json
{
  "message": "Setlist deleted"
}
```

---

## Create Set

- **Method**: `POST`
- **Path**: `/{setlistId}/sets`

Create a new set within a setlist.

### Request body

```json
{
  "name": "Set 1",
  "order_index": 0
}
```

**Validation Rules:**
- `name`: required, string, max 255 chars
- `order_index`: optional, integer, min 0 (auto-increments if not provided)

### Success response (`201`)

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

## Update Set

- **Method**: `PUT`
- **Path**: `/{setlistId}/sets/{setId}`

Update a set.

### Request body

```json
{
  "name": "First Set",
  "order_index": 1
}
```

**Validation Rules:**
- `name`: optional, string, max 255 chars
- `order_index`: optional, integer, min 0

### Success response (`200`)

```json
{
  "message": "Set updated",
  "set": { ... }
}
```

---

## Delete Set

- **Method**: `DELETE`
- **Path**: `/{setlistId}/sets/{setId}`

Delete a set and all its songs.

### Success response (`200`)

```json
{
  "message": "Set deleted"
}
```

---

## Add Song to Set

- **Method**: `POST`
- **Path**: `/{setlistId}/sets/{setId}/songs`

Add a single song to a set.

### Request body

```json
{
  "project_song_id": 5,
  "order_index": 0
}
```

**Validation Rules:**
- `project_song_id`: required, must exist in project_songs table and belong to this project
- `order_index`: optional, integer, min 0 (auto-increments if not provided)

### Success response (`201`)

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

### Error response (`500`)

A song cannot appear twice in the same set (database constraint).

---

## Bulk Add Songs to Set

- **Method**: `POST`
- **Path**: `/{setlistId}/sets/{setId}/songs/bulk`

Add multiple songs to a set.

### Request body

```json
{
  "project_song_ids": [123, 124, 125],
  "start_order_index": 0
}
```

**Validation Rules:**
- `project_song_ids`: required array of integer IDs, at least one item
- `project_song_ids` must be distinct
- each `project_song_id` must belong to current project
- each `project_song_id` must not already exist in the target set
- `start_order_index`: optional integer, defaults to current song count in set

### Success response (`201`)

```json
{
  "message": "Songs added to set",
  "setlist_songs": [
    {
      "id": 1001,
      "set_id": 55,
      "project_song_id": 123,
      "order_index": 0,
      "song": {
        "id": 9,
        "title": "Song Title",
        "artist": "Artist"
      }
    }
  ]
}
```

### Error response (`422`)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "project_song_ids.0": ["The selected project song ids.0 is invalid."]
  }
}
```

---

## Remove Song from Set

- **Method**: `DELETE`
- **Path**: `/{setlistId}/sets/{setId}/songs/{songId}`

Remove a song from a set.

### Success response (`200`)

```json
{
  "message": "Song removed from set"
}
```

---

## Reorder Songs in Set

- **Method**: `PUT`
- **Path**: `/{setlistId}/sets/{setId}/songs/reorder`

Reorder songs within a set.

### Request body

```json
{
  "song_ids": [3, 1, 2]
}
```

The order of IDs in the array determines the new `order_index` values (0, 1, 2, ...).

**Validation Rules:**
- `song_ids`: required, array of setlist_song IDs
- `song_ids.*`: must exist in setlist_songs table

### Success response (`200`)

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
