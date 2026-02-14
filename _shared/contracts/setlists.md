# Setlists API Contracts

## Auth and scope

- All endpoints below require `Authorization: Bearer <token>`.
- All endpoints are performer-scoped and project-scoped via `{project_id}`.
- Route prefix: `/api/v1/me/projects/{project_id}`.

## Add a single song to a set

- **Method**: `POST`
- **Path**: `/setlists/{setlist_id}/sets/{set_id}/songs`

### Request body

```json
{
  "project_song_id": 123,
  "order_index": 0
}
```

- `project_song_id`: required integer (must belong to current project)
- `order_index`: optional integer, defaults to next slot in the set

### Success response (`201`)

```json
{
  "message": "Song added to set",
  "setlist_song": {
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
}
```

## Add multiple songs to a set (bulk)

- **Method**: `POST`
- **Path**: `/setlists/{setlist_id}/sets/{set_id}/songs/bulk`

### Request body

```json
{
  "project_song_ids": [123, 124, 125],
  "start_order_index": 0
}
```

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

Laravel validation format:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "project_song_ids.0": ["The selected project song ids.0 is invalid."]
  }
}
```
