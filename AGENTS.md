# AGENTS.md — SongTipper (Flutter + Laravel)

Songtipper is split into multiple repositories:

- `songtipper/web/`: Laravel API + web routes (audience pages, performer signup/login)
- `songtipper/mobile_app/`: Flutter app (performer-side)

Songtipper manages shared information within the root repository (songtipper) here:

- `songtipper/_shared/`: API contracts, schemas, shared docs (source of truth)

## Repository remotes

- Monorepo: `git@github.com:gnarhard/songtipper-mono.git`
- Web: `git@github.com:gnarhard/songtipper_web.git`
- Mobile App: `git@github.com:gnarhard/songtipper_mobile_app.git`

_You MUST work in a git worktree, NEVER in the main repository._

- All code changes happen in `../songtipper-worktrees/<track_id>/`
- After creating a worktree, you MUST `cd` into it before any work
- If worktree creation fails, output ERROR signal and terminate - do NOT fall back to main repo
- The main `songtipper` directory must remain untouched by agents
- Once finished, create a PR for each repo you modified (web and mobile app) to merge into main and share the PR link with the developer

## Ignore (do not read or edit):

- `web/.env`
- `**/*.p8` (App Store private keys)

## Before you start, read the rules for each repo:

```
songtipper/
  web/AGENTS.md
  mobile_app/.agent-rules/*.md
```

In web, there will be AI-specific directories where you can find skills: Claude: `web/.claude` Gemini: `web/.gemini` Codex: `web/.codex` GitHub: `web/.github`

## 0) Prime directive

**Keep web + mobile app in sync via `_shared/`.** Any change to API shape, auth, error format, pagination, sorting/filtering, or field naming MUST update the contract/docs in `_shared/` and corresponding Dart models/services.

If you suspect a change could break existing clients, implement it in a backwards-compatible way (new fields/endpoints, versioning, or feature flags) unless explicitly told otherwise.

---

## 1) What we're building (high level)

SongTipper is an app for performers to manage:

- **Repertoire** - Songs with metadata (keys, capo, tuning, energy, mood, era, genre)
- **To-Learn List** - Songs to practice with YouTube/Ultimate Guitar links
- **Setlists** - Organized song lists with notes, smart generation support
- **Performance Sessions** - Live session tracking with sequential completion and smart reordering
- **Charts** - PDF uploads with rendering, annotations, and per-page viewport preferences
- **Audience Requests** - Live queue with tips, integrated Stripe payments
- **Audience Features** - Profiles, achievements, "Who's Here" leaderboard

**Web (Laravel)** provides authenticated APIs, data persistence, uploads, and audience-facing web routes.
**Mobile App (Flutter)** is the performer app, offline-first where possible.

---

## 2) Shared contract is the source of truth (`_shared/`)

### Canonical API specification (v1.2)

**Primary source of truth:** `_shared/api/openapi.yaml`

This OpenAPI 3.0 document is the machine-readable contract. All endpoint changes MUST be reflected here first.

### Supporting documentation

- **Contracts in Markdown**: `_shared/contracts/*.md` - Domain-oriented explanations organized by resource:
  - `auth.md` - Authentication & password management
  - `projects.md` - Project CRUD and settings
  - `repertoire.md` - Song management, metadata, bulk import, to-learn list
  - `queue.md` - Queue management and request history
  - `charts.md` - Chart upload, rendering, annotations, viewport prefs
  - `setlists.md` - Setlist builder, performance sessions, smart generation
  - `public.md` - Public audience endpoints (repertoire, requests, profile, leaderboard)
- **Overview**: `_shared/api-contract-rules.md` - API design principles and patterns
- **Index**: `_shared/contracts/README.md` - Contract file guide

### Required contract rules

- Define request/response bodies, status codes, error format, pagination model, and auth requirements.
- Use consistent naming (prefer `snake_case` in Laravel JSON, and map to `camelCase` in Dart using serializers).
- For nullable fields, explicitly document nullability and default behaviors.
- Never remove or rename fields without a migration plan.
- All write endpoints MUST support `Idempotency-Key` header for safe retries.

---

## 3) Canonical API response shape

Unless an existing format already exists, use this default:

### Success

```json
{
  "data": ...,
  "meta": { ...optional... }
}
```

### Error

```json
{
  "error": {
    "code": "string_machine_code",
    "message": "human_readable_message",
    "details": { "field": ["msg1", "msg2"] }
  }
}
```

**If the backend currently uses a different structure, follow the existing standard** and document it in `_shared/`.

---

## 4) Authentication and authorization assumptions

- Assume performer users authenticate to backend APIs (token-based).
- Authorization is typically scoped to a **Project** (band / solo project).
- Any endpoint that reads/writes performer data must enforce project scoping.

If auth details are unclear, search the code first:

- `web/routes/api.php`
- `web/app/Http/Middleware`
- `web/config/auth.php`
- `mobile_app/lib/**/auth*` and `mobile_app/lib/**/token*`

Then document what you find in `_shared/`.

---

## 5) Offline-first expectations (Flutter)

The Flutter app should behave gracefully offline:

- Cache read models locally (e.g., Hive or other storage if present).
- Queue writes where appropriate (if implemented).
- Never block UI forever on network; show cached data + retry affordances.

Before introducing new caching approaches, inspect existing patterns in:

- `mobile_app/lib/services`
- `mobile_app/lib/repositories`
- `mobile_app/lib/storage`
- any `*cache*`, `*hive*`, `*offline*` files

Match existing architecture rules and conventions.

---

## 6) How to work in this repo (process)

### When given a task:

1. **Locate source of truth**
   - Search in `_shared/` for the contract/spec.
2. **Inspect both stacks**
   - Backend: routes/controllers/requests/resources
   - Frontend: API client/repo/models/UI usage
3. **Plan the change**
   - Keep backwards compatibility
   - Identify migrations and rollout steps. Never exceed table name length to avoid "1059 Identifier name" errors.
4. **Implement**
   - Update backend + frontend + shared contract as needed
5. **Validate**
   - Run relevant tests/linters/builds (see commands below)
6. **Summarize**
   - Provide a short changelog + any migration/rollout notes

### Always:

- Prefer small, reviewable diffs.
- Don’t add new dependencies unless necessary.
- Don’t reformat entire files unless asked or required by formatter.

---

## 7) Commands you can run (common)

Run commands from the repo root unless noted.

### Backend (Laravel) — from `songtipper/web`

```bash
cd web
php -v
composer install
php artisan key:generate
php artisan migrate
php artisan test
```

Formatting/linting (if present):

```bash
./vendor/bin/pint
```

### Mobile App (Flutter) — from `songtipper/mobile_app`

```bash
cd mobile_app
flutter --version
flutter pub get
flutter test
dart format .
dart analyze
```

If build is needed:

```bash
flutter build apk
# or
flutter build ios
```

> If any of these commands fail due to missing env, missing services, or platform constraints, report the failure and pivot to static checks + code reasoning.

---

## 8) File layout hints

### Web

- Routes: `web/routes/api.php`, `web/routes/web.php`
- Controllers: `web/app/Http/Controllers`
- Form requests: `web/app/Http/Requests`
- Resources/Transformers: `web/app/Http/Resources`
- Models: `web/app/Models`
- Migrations: `web/database/migrations`
- Policies: `web/app/Policies`

### Mobile App

- Entry: `mobile_app/lib/main.dart`
- API client: `mobile_app/lib/**/api*` or `mobile_app/lib/**/http*`
- Models: `mobile_app/lib/models` (or similar)
- Repos: `mobile_app/lib/repositories`
- State: `mobile_app/lib/**/notifier*` or `mobile_app/lib/**/provider*`
- Storage/offline: `mobile_app/lib/**/storage*`, `mobile_app/lib/**/cache*`

### Shared

- **OpenAPI Spec**: `_shared/api/openapi.yaml` (v1.2) - Canonical API contract
- **Contracts**: `_shared/contracts/*.md` - Domain-specific API documentation
- **Overview**: `_shared/api-contract-rules.md` - API design principles
- **Architecture**: `ARCHITECTURE.md` - System architecture documentation
- **Cross-stack docs**: `_shared/README.md`

---

## 9) Conventions

### Naming

- Web JSON: `snake_case` keys
- Mobile App models: `camelCase` fields (map from snake_case)

### Dates

- Use ISO 8601 strings in API with timezone: `2026-02-15T18:00:00+00:00`
- Always UTC on server, client converts to local timezone
- Document timezone handling in `_shared/`

### Pagination

Laravel paginator format:

```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 50,
    "total": 123
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

### Idempotency

All write operations support `Idempotency-Key` header:
- Mobile app outbox MUST send stable keys per logical operation
- Key format: UUID v4
- Server deduplicates based on key within 24-hour window
- Retries with same key return original response (200/201)

### Metadata Fields

**Mood** (v1.2):
- Global: `songs.mood` (nullable)
- Project override: `project_songs.mood` (nullable)
- Effective value: `project_songs.mood ?? songs.mood`
- Validation: `^[a-zA-Z0-9_-]+$` (one word, e.g., "party", "chill", "romantic")
- Supported in repertoire list, create, update, and bulk import

**Energy Level**:
- Values: `low`, `medium`, `high`
- Global + project override pattern (same as mood)

**Genre**:
- Free text, max 50 chars
- Global + project override pattern

---

## 10) Safety checks for cross-stack changes

Before you change any API:

- Search frontend usage for the endpoint/model.
- Ensure response shape matches what Dart expects.
- If you change validation rules, ensure frontend form handling matches.

Before you change any Flutter model:

- Confirm backend sends those fields.
- If you add fields, ensure backend includes them and document them in `_shared/`.

---

## 11) PR-style summary format (use in your final response)

When you finish a task, summarize with:

- **What changed (web)**
- **What changed (mobile app)**
- **Contract updates (shared)**
- **How to test**
- **Rollout / migration notes** (if any)

---

## 12) If you are unsure

Do not guess silently. Inspect the codebase first.  
If something is still ambiguous:

- Make the smallest safe change
- Add a note in `_shared/` describing the open question
- Suggest the next best verification step (where in code/config to confirm)

## 13) Finish the job completely

If you can help it, don't ask the user to do anything in addition to your changes. Do everything. If a database needs migrating or anything of the sort, migrate it/do the task.

---

## 14) Key features and capabilities (v1.2)

### Performance Sessions

- Routes: `POST /performances/start`, `POST /performances/stop`, `GET /performances/current`
- Session modes: `manual` or `smart`
- Track completion: `POST /performances/current/complete` (sequential with `performed_order_index`)
- Skip/Random: `POST /performances/current/skip`, `POST /performances/current/random`
- Smart mode can reorder pending items after skip/complete
- Exactly one active session per project (409 Conflict if starting while active)

### Smart Setlist Generation

- Route: `POST /setlists/generate-smart`
- Stores generation metadata (`seed`, version, constraints) on created setlist
- Can regenerate with different parameters

### To-Learn List

- Routes: `GET|POST|PUT|DELETE /learning-songs`
- Fields: `youtube_video_url`, `ultimate_guitar_url` (link/search only, no scraping), `notes`
- Separate from repertoire (songs you're actively practicing)

### Copy Repertoire

- Route: `POST /repertoire/copy-from`
- Body: `{ "source_project_id": 1, "include_charts": true }`
- Copies repertoire rows, overrides, and optionally chart linkage

### Chart Viewport Preferences

- **NEW (v1.2)**: Per `(user, chart, page)` in `chart_page_user_prefs`
- Routes: `GET|PUT /charts/{chartId}/pages/{page}/viewport`
- **DEPRECATED**: `projects.chart_viewport_prefs` (project-level blob)
- Clients should migrate to per-page endpoint

### Setlist Notes

- Supported at three levels: `setlists.notes`, `setlist_sets.notes`, `setlist_songs.notes`
- All create/update payloads accept `notes` as nullable text

### Text Import for Setlists

- Route: `POST /setlists/{setlistId}/sets/{setId}/songs/import-text`
- Accepts newline-separated lines: `Title` or `Title - Artist`
- Auto-matches to repertoire

### Audience Features

**Profile & Achievements**:
- Route: `GET /public/projects/{slug}/audience/me`
- Cookie-backed identity (`songtipper_audience_token`)
- Deterministic pseudonymous display name: `Adjective + Animal` (hash-based)
- Returns profile, totals, achievements list

**Who's Here Leaderboard**:
- Route: `GET /public/projects/{slug}/audience/leaderboard`
- Uses current active performance session
- Ranks by `SUM(tip_amount_cents)` during session
- Stable tiebreaker: `joined_at ASC`

### Request Creation

**Canonical persistence**:
- `requests.song_id` is always populated (never null)
- Tip-only requests → placeholder song: `"Tip Jar Support" / "Audience"`
- Original requests → placeholder song: `"Original Request" / "Audience"`
- Client can send `tip_only` or `is_original` flags, backend maps to placeholders

### Bulk Import

- Limits (v1.2): max `128` files per request, max `2MB` per PDF
- Filename metadata supports: `key`, `capo`, `tuning`, `energy`, `era`, `genre`, `mood`
- Mood token example: `Songname - Artist -- mood=party.pdf`
