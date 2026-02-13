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

## 1) What we’re building (high level)

SongTipper is an app for performers to manage:

- Repertoire (songs, metadata, tags)
- Setlists
- Charts (PDFs / images)
- Audience requests & live queue sync (project-scoped)

**Web (Laravel)** provides authenticated APIs, data persistence, uploads, and audience-facing web routes.  
**Mobile App (Flutter)** is the performer app, offline-first where possible.

---

## 2) Shared contract is the source of truth (`_shared/`)

Prefer one of these patterns (use whatever exists; if none exists yet, create it):

- **OpenAPI**: `_shared/openapi.yaml`
- **JSON Schemas**: `_shared/schemas/*.json`
- **Contracts in Markdown**: `_shared/contracts/*.md`

### Required contract rules

- Define request/response bodies, status codes, error format, pagination model, and auth requirements.
- Use consistent naming (prefer `snake_case` in Laravel JSON, and map to `camelCase` in Dart using serializers).
- For nullable fields, explicitly document nullability and default behaviors.
- Never remove or rename fields without a migration plan.

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
   - Identify migrations and rollout steps
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

- Contracts: `_shared/contracts`
- Schemas: `_shared/schemas`
- OpenAPI: `_shared/openapi.yaml` (if present)
- Cross-stack docs: `_shared/README.md`

---

## 9) Conventions

### Naming

- Web JSON: `snake_case` keys
- Mobile App models: `camelCase` fields (map from snake_case)

### Dates

- Use ISO 8601 strings in API
- Document timezone handling in `_shared/`

### Pagination

If pagination is used, prefer a consistent shape, e.g.:

```json
{
  "data": [ ... ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 123
  }
}
```

Or document Laravel paginator output if used.

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
