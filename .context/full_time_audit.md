# Full-Stack Time & Timezone Audit — Expanded Plan

## Context

This is the product-wide expansion of the performance-event timezone plan. The
rule in `.agent-rules/30-conventions-and-delivery.md` is unambiguous — "store
and return UTC on the server; client converts to local for display" — but
enforcement is uneven across the code base:

- one `dateTimeTz` column, many `timestamp` columns, no explicit cast strategy;
- raw SQL that bakes a **fixed UTC offset** computed from `CarbonImmutable::now($tz)` into historical queries that span DST transitions;
- MySQL connection config without an explicit `timezone` key, leaving
  `TIMESTAMP(6)` reads silently dependent on the server's `@@session.time_zone`;
- `now()` used without explicit UTC across ~30 write sites;
- client timestamps (`DateTime.now()`) flowing through outbox, annotations, and
  ETag cache without a UTC boundary;
- `cash_tip.createdAt` typed as `String`, every other mobile `_at` typed as
  `DateTime`;
- `DateTime.toLocal()` scattered over ~20 UI sites, no shared formatter;
- display timezone precedence undefined — device tz, reporting tz, and session
  tz are mixed freely in the same widgets;
- contract files inconsistent on whether `timezone` is required vs optional vs
  "defaults to UTC".

The goal of this change is a single coherent story: **every persisted moment is
UTC, every serialized moment includes `+00:00`, every on-screen moment is
rendered in the zone the performer experienced it in (session tz → reporting tz
→ device tz, in that order), and every date-bucketed aggregate uses per-row
local conversion rather than a cached "now" offset.**

## Decision

- **Display-timezone precedence (all surfaces)**: `event.session.timezone ??
  project.reporting_timezone ?? device_timezone`. Device tz is only a
  last-resort fallback and must never be used when either of the first two is
  known. Server-side, **every "fall back to UTC" is replaced with "fall back
  to project.reporting_timezone"**; UTC is never a meaningful display or
  bucketing default for a performer-facing surface.
- **Storage**: every persisted moment is a MySQL `TIMESTAMP(6)` in UTC. No
  `dateTimeTz`. Connection pinned to `+00:00`. Models use
  `immutable_datetime` casts.
- **Wire format**: every ISO-8601 string the server emits carries an explicit
  `+00:00` offset produced from a `CarbonImmutable` in UTC. Request parsing
  uses an IANA zone name (never an abbreviation, never a numeric offset).
  Microseconds are **dropped** on the way out (`CarbonImmutable::toIso8601String()`
  emits `YYYY-MM-DDTHH:MM:SS+00:00`, not `.uuuuuu`); F8 round-trip tests are
  written against whole-second seeds.
- **Date-bucketed aggregates**: conversion from UTC to local is done **per
  row** via `CONVERT_TZ(column, '+00:00', 'IANA/Name')` (MySQL) or a
  per-row expression (SQLite), never with a single `now()`-derived fixed
  offset. `CONVERT_TZ` with an IANA name returns `NULL` if MySQL's
  `mysql.time_zone_name` table isn't populated — a tzdata load is a
  deployment precondition (see Verification step 3 and Phase H).
- **Clock-skew defense**: any endpoint that accepts a client-supplied
  `occurred_at` clamps it to `min($supplied, CarbonImmutable::now('UTC')->addMinutes(5))`.
  Public iframe surfaces are the main concern; authenticated performer
  writes are trusted.
- **Deployment split**: A2b (MySQL tz pin) ships in its own PR with a data
  probe and all-clear. Other phases are grouped per Phase H split.
- **Scope**: web storage + writers + resources + stats + scheduled commands +
  webhooks; mobile domain + helper + widgets + outbox + local cache; shared
  contracts; CI-level shape and value guards.

---

## Audit — current state (additions to the earlier plan marked [NEW])

### Web (`web/`) — write side

1. `database/migrations/2026_04_17_213437_create_performance_events_table.php:29`
   — `dateTimeTz('occurred_at', 6)`. Single outlier.
2. `app/Services/PerformanceEventLogger.php:33` — `now()` without explicit UTC.
3. ~20 writer sites passing `$model->created_at` / `now()` into
   `occurred_at` (9 in `PerformanceSessionService`, 3 in
   `AudienceRequestPaymentService`, 1 in `RewardThresholdService`, 4 in
   `QueueController`, 3 in `CashTipController`, 1 in `RewardClaimController`,
   1 in `PublicRepertoireController` — already explicit, 1 in
   `AudienceProjectController`, 1 in `PerformanceRepairCommand`).
4. `app/Models/{PerformanceEvent,PerformanceSession,SongPerformance,Request,AudienceRewardClaim,CashTip}.php`
   — plain `datetime` cast, not `immutable_datetime`.
5. `app/Http/Controllers/Api/V1/SongPerformanceHistoryController.php:56-58,
   144, 161, 213-214, 246, 259` — raw string handling; `strtotime()` sorts
   against PHP default tz.
6. `app/Services/SongPerformanceHistoryService.php:63-64, 103-104, 325-326` —
   `' 23:59:59'` concat filters.
7. `performance_sessions.timezone` stored but never exposed on list-side
   responses or used to bucket filters server-side.
8. `database/migrations/2026_04_19_*_backfill_performance_events.php` backfills
   without normalization — depends on producing rows being UTC.
9. **[NEW]** `config/database.php:49-67` — MySQL connection has **no
   `timezone` key**. `TIMESTAMP(6)` columns are read/written through the
   server's `@@session.time_zone`. If that session is ever non-UTC, every
   cast returns a shifted Carbon; `toIso8601String()` still emits `+00:00`
   but the hour is wrong. This is the silent-corruption vector.
10. **[NEW]** `config/database.php:22` — `DB_CONNECTION` defaults to
    `sqlite`. Local dev uses the sqlite branch of `ProjectStatsService`,
    which is structurally different from the MySQL branch — tests written
    against sqlite alone will not exercise the MySQL `CONVERT_TZ` DST bug
    (item 12 below).
11. **[NEW]** `app/Http/Resources/*` — 19 resources use `toIso8601String()`
    (spot-checked: `SetlistResource`, `CashTipResource`,
    `PerformanceSessionResource`, `ChartResource`, `LocationResource`,
    `RequestResource`, `ProjectMemberResource`, `ProjectSongResource`,
    `ChartAnnotationVersionResource`, `ProjectSongAudioFileResource`,
    `PendingRewardClaimResource`, `ChartPageUserPrefResource`). Offset in
    the output depends on the Carbon instance's tz — which depends on the
    DB read path. Item 9 is the upstream fix.
12. **[NEW, CRITICAL]** `app/Services/ProjectStatsService.php:820-830,
    1375-1384` — both `moneyHistory()` and `tipTotalsByLocalDate()`
    compute `$offsetSeconds = CarbonImmutable::now($timezone)->getOffset()`
    **once** and bake it into `CONVERT_TZ(..., '+00:00', '%+d seconds')`
    for the entire historical range. A project in `America/Denver`
    querying YTD at the end of March will apply MDT (−06:00) to every
    date, so rows logged in January sit in MST (−07:00) locally and are
    bucketed one hour wrong — crossing a local-midnight boundary for
    roughly 1/24 of events per bucket. This affects `report().records`,
    `report().money`, `moneyHistory()`, `dailyRecordEvent()`,
    `cachedDailyRecordEvent()`, `resolveBestDayRecord()`,
    `grossTipTotalsByLocalDate()`, `netTipTotalsByLocalDate()`, and
    `tipTotalsByLocalDate()`. Fix is to pass the IANA name (not a fixed
    offset) into `CONVERT_TZ`, which performs per-row lookup using
    MySQL's `mysql.time_zone_name` tables, falling back to a per-row
    conversion done in PHP for SQLite. Requires verifying MySQL
    time-zone tables are loaded (`mysql_tzinfo_to_sql`).
13. **[NEW]** `app/Services/ProjectStatsService.php:201` —
    `'generated_at' => now()->toIso8601String()`. Should be
    `CarbonImmutable::now('UTC')->toIso8601String()` for consistency with
    the write-side sweep.
14. **[NEW]** `app/Services/ProjectStatsService.php:735-738` —
    `rewardsGiftedForSession()` uses `session.started_at` / `ended_at`
    as Carbon boundaries against `audience_reward_claims.claimed_at`.
    If `claimed_at` is not written in UTC (see write-site sweep), the
    window misses rows.
15. **[NEW]** `app/Services/ProjectStatsService.php:884` —
    `CarbonImmutable::createFromFormat('Y-m-d', $day)->format('M j')`
    builds the chart label in the server's default Carbon tz (UTC) and
    strips the year. December 31 and January 1 both render as
    `Dec 31 / Jan 1` with no year — acceptable today, but will become
    ambiguous when the YTD feature extends to rolling 12-month windows.
    Flag; no immediate change required.
16. **[NEW]** `app/Services/ProjectStatsService.php:166-198, 1014-1021` —
    `AllTime` preset uses `$projectCreatedAt` as the UTC window start but
    converts it to local with `->setTimezone($timezone)->toDateString()`
    for `local_start_date`. If the project was created late in local
    evening (UTC next day), the displayed "all time" start jumps a day.
    Document the semantic.
17. **[NEW]** `app/Http/Requests/ShowProjectStatsRequest.php:90-99` —
    `CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)` returns
    `null` for invalid dates and the validator doesn't guard the return.
    Pair with `Rule::date()` which already validates format, but add a
    null-guard anyway.
18. **[NEW]** Scheduled commands in `routes/console.php` pin
    `America/Denver` on all business-hour tasks (billing, AI review,
    account usage monitor, R2 cleanup, backup, admin digest). Benign
    — all run server-side and don't compose with user tz — but spell
    out the convention in docs. `performances:end-stale` intentionally
    runs every 5 min in UTC.
19. **[NEW]** Stripe webhook handlers
    (`app/Http/Controllers/Api/V1/StripeWebhookController.php`) and
    `AudienceRequestPaymentService` at lines 154/172/492 write
    `performance_event.occurred_at` from `now()`. Webhook retries
    within Stripe's 24h window must be idempotent; the existing
    `Idempotency-Key` machinery handles this for the customer-facing
    route, but the webhook's internal event writes must also use
    `CarbonImmutable::now('UTC')` to keep the UTC invariant during
    replay (or, better, derive `occurred_at` from the Stripe event's
    own `created` field).
20. **[NEW]** Additional `_at` columns not covered by the original
    plan's model-cast sweep:
    - `account_usage_counters`: `last_activity_at`,
      `inactivity_warning_sent_at`, `archived_render_images_at`,
      `blocked_at` (4 fields)
    - `audience_participations`: `joined_at`, `last_seen_at`
    - `audience_achievements`: `last_seen_at`
    - `ai_batches`: `completed_at`
    - `setlists`: `archived_at`
    - `songs`: `last_integrity_review_at`
    - `users`: `email_verified_at`, `trial_ends_at`
    - `subscriptions` (Cashier): `trial_ends_at`
    - `chart_annotation_versions`: `client_created_at`
      (client-provided; see mobile audit item [NEW-M3])
    - `project_songs`: `last_performed_at`
    - `requests`: `activated_at`, `played_at`
    - `song_performances`: `performed_at`, `skipped_at`
21. **[NEW]** `account_usage_daily_rollups.rollup_date` is `DATE`, not
    `TIMESTAMP`. Any query filtering `rollup_date` against `now()`
    boundaries needs an explicit "in which tz does the day roll?"
    answer — currently the rollup is written in UTC by the daily
    command but filtered against `now()->startOfWeek()` in PHP default
    tz. Document and normalize.

22. **[NEW, from adversarial review]** Eight `Carbon::parse($str)` /
    `CarbonImmutable::parse($str)` callsites without an explicit
    timezone argument are silent-shift vectors — Carbon defaults to
    `config('app.timezone')` which is UTC today, but any unrelated
    future config change silently shifts these reads. Sites:
    - `app/Http/Controllers/Api/V1/ProjectSongController.php:829` —
      `Carbon::parse($validated['performed_at'])`
    - `app/Services/PerformanceSessionService.php:418` —
      `Carbon::parse($lastPerformedAt)`
    - `app/Services/PerformanceSessionService.php:673` —
      `Carbon::parse($lastEvent)`
    - `app/Services/ProjectStatsService.php:1008` —
      `CarbonImmutable::parse($earliestRequestAt)`
    - `app/Console/Commands/PerformanceRepairCommand.php:179, 184, 239`
    - `app/Console/Commands/EvaluateMonthlyBillingCommand.php:25` —
      `Carbon::parse($monthOption.'-01')` (calendar-month, acceptable
      as-is but should be explicit about tz).
    Fix: **prefer `->utc()` on the result** over `Carbon::parse(..., 'UTC')`.
    If the incoming string already carries an offset
    (`"2026-04-09T05:30:00-06:00"`), the second-argument-only form
    ignores it; `->utc()` always ends at UTC. Rule of thumb:
    `CarbonImmutable::parse($str)->utc()` is the one-liner that
    never lies about what's in the variable.

### Web — read side (contracts and responses)

(see Phase B and Phase C below for the corresponding fixes)

### Mobile (`app/`)

1. 18+ `.toLocal()` sites on performance-event timestamps (unchanged).
2. `lib/features/cash_tips/domain/cash_tip.dart:12, 25, 51, 81` —
   `createdAt` is `String`. Every other `*At` is `DateTime`.
3. `lib/features/home/domain/timeline_event.dart:52-54` — null
   `occurred_at` falls back to `DateTime.fromMillisecondsSinceEpoch(0)`.
4. Four inline "now in reporting tz" ternaries.
5. `performance_detail_screen.dart:439, 470` — time picker seeds with
   device local.
6. `pubspec.yaml:61` — `flutter_timezone` only; no IANA-zone conversion
   package.
7. `reporting_timezone_service.dart` exposes a `ValueNotifier<String>`
   rather than a Riverpod source.
8. **[NEW-M1]** `lib/core/outbox/outbox_item.dart:16, 33, 42, 60` —
   captures `DateTime.now()` for `createdAt` / `lastAttemptAt` in
   device local time, then Hive-serializes via native `DateTime` (not
   an ISO string). On replay after a timezone change (user travels;
   laptop clock rolled back across DST) these values may non-monotonic.
   Server trusts its own `now()` on sync, so data is correct — but the
   retry backoff logic reads its own `lastAttemptAt` and can misbehave
   at DST-fall when the wall clock repeats an hour. Normalize to
   `DateTime.now().toUtc()` everywhere in the outbox.
9. **[NEW-M2]** `lib/features/annotations/domain/annotation_version.dart:61`
   — `syncedAt = DateTime.now()` (device local). Used only for local
   "is this already synced" checks, but the field is also persisted to
   Hive and can be serialized back to JSON. Normalize to `.toUtc()` at
   assignment; parse `.toUtc()` if it comes back from Hive.
10. **[NEW-M3]** `lib/features/annotations/` — `client_created_at` is
    sent from mobile as a Hive-serialized DateTime converted to JSON
    via `.toIso8601String()`. If the underlying Hive value is device
    local, the ISO string carries the local offset (e.g.
    `2026-04-18T22:15:00.000-06:00`) — which the server's
    `'datetime'` cast parses correctly (Carbon handles offset) but
    which then gets re-serialized by the server resource as
    `...+00:00`. Result is correct but brittle. Add an invariant at the
    mobile outbox boundary: every `_at` field in a JSON payload leaving
    the device is `.toUtc().toIso8601String()`.
11. **[NEW-M4]** `lib/core/http/etag_cache.dart:64` — `cachedAt =
    DateTime.now()`; used only for local TTL math; normalize to UTC
    for DST safety.
12. **[NEW-M5]** `lib/core/session/setlist_performance_tracker.dart`
    `lastActivityAt` — device-local `DateTime.now()`; used only
    locally; normalize.
13. **[NEW-M6]** `lib/features/queue/data/queue_repository_impl.dart` —
    `createdAt: DateTime.now()` set on optimistic pending rows before
    the server returns. Must be `.toUtc()` to match the confirmed
    row's UTC `createdAt` (otherwise reconciliation by timestamp
    drifts at DST).
14. **[NEW-M7]** `DateTime.now().timeZoneName` sites:
    `perform_screen.dart`, `performance_detail_screen.dart`,
    `setlist_detail_screen.dart`, `setlists_list_screen.dart`. This
    yields a device-tz abbreviation (e.g. `MDT`). Replace with the
    effective-tz IANA name via `AppTime.zoneLabel(sessionTimezone,
    reportingTimezone)` — not device.
15. **[NEW-M8]** Domain models with `DateTime` fields outside the
    original plan: `chart.dart` (`createdAt`, `updatedAt`),
    `annotation_version.dart` (`createdAt`, `syncedAt`),
    `location.dart` (`createdAt`, `updatedAt`),
    `audio_file.dart` (`createdAt`, `updatedAt`),
    `setlist.dart` (`createdAt`, `archivedAt`),
    `past_session.dart` (`startedAt`, `endedAt`),
    `location_session.dart` (`startedAt`, `endedAt`),
    `performed_song.dart` (`performedAt`),
    `song_request.dart` (`createdAt`, `activatedAt`, `playedAt`).
    All parse via `DateTime.parse(json[...])` which requires the
    server to always include a `Z` or `+00:00` suffix. No current
    fallback; the server does emit the offset, so this works, but
    document the invariant and add a guard test (see Phase F).
16. **[NEW-M9]** Hive stores `DateTime` via its native binary format,
    which is an int64 `microsecondsSinceEpoch`. No timezone is
    persisted — but `DateTime.toLocal()` and `DateTime.toUtc()`
    preserve the underlying instant. Deserialization always returns a
    UTC-flagged `DateTime`. Safe; document.
17. **[NEW-M10]** `lib/features/home/controller/stats_controller.dart`
    sends stats requests with a user-timezone query parameter read from
    `reporting_timezone_service`. No session tz involvement (stats are
    project-scoped). Also calls `_nextMidnightDelay()` — which uses
    `DateTime.now()` and `DateTime.now().timeZoneOffset` — to schedule
    the next refresh. Switch to `AppTime.nowInReporting()` so midnight
    refresh fires at the user's reporting midnight, not the device
    midnight.
18. **[NEW-M11]** Date formatters — no `intl`/`DateFormat` uses were
    found outside of custom `_formatArrivalDate()`/`_formatDate()`
    helpers. The app has no `intl` dependency and produces date
    labels through string concatenation. Phase D adds a single
    `AppTime.formatDate()` / `formatTime()` that all callers route
    through; these can be backed by the existing helper shapes.
19. **[NEW-M12]** `lib/features/locations/presentation/location_picker_flow.dart`
    imports `flutter_timezone` and reads the device IANA name to stamp
    `location.timezone` on creation. Good — first-class IANA name is
    the only correct input. But if the device tz is a weird value
    (`Factory`, `GMT+07:00`), the server must refuse. Add
    `timezone:all` validation on the `LocationController` store/update.

### Shared contracts (`_shared/contracts/`)

1. No session-level timezone convention across contracts.
2. `cash_tips.local_date` + `timezone` well defined; `occurred_at`
   semantic documented.
3. `session.timezone` not uniformly required on responses.
4. No rule stating which local timezone the client should use for
   display.
5. **[NEW-C1]** `queue.md:28-29` says `timezone` is optional and
   defaults to UTC when omitted; `projects.md` (stats) requires
   `timezone`; `payouts.md` requires `timezone`;
   `song-performances.md` history endpoints currently accept neither
   (see original plan Phase B3). Reconcile: **make `timezone`
   required on every endpoint that returns or filters by local
   calendar dates**; UTC is never a meaningful fallback for a
   performer-facing aggregate.
6. **[NEW-C2]** `locations.md:473` (DOW analytics) says "timezone
   uses the timezone stored on the performance session; falls back
   to UTC". That contradicts the display-tz precedence (session →
   reporting → device). Change to "falls back to project reporting
   timezone".
7. **[NEW-C3]** `setlists.md:59` (`archived_at`), `charts.md:191,
   204` (`updated_at`), `locations.md` (session `started_at`,
   `ended_at`), `cash_tips.md` (`created_at`), `projects.md` (stats
   `generated_at`) — all ISO-8601 but not explicitly documented as
   UTC. Add a one-line clarification.
8. **[NEW-C4]** `projects.md:241-280` (stats) needs a note that
   local calendar bucketing is **per-row DST-aware**, not
   per-request-fixed-offset, once Phase B fix lands. This
   documents the invariant so it doesn't regress.

---

## Implementation plan

### Phase A — Web storage and write-side UTC

A1. **Pre-migration verification** (manual gate before A2):
   - **[EXPANDED]** The config probe must confirm both Laravel app tz
     and the MySQL connection resolve to UTC. Add a **connection-level
     probe** too — not just `@@session.time_zone`, but specifically:
     ```
     php artisan tinker --execute '
       echo "app.timezone=".config("app.timezone").PHP_EOL;
       echo "db.mysql.timezone=".(config("database.connections.mysql.timezone") ?? "null").PHP_EOL;
       echo "session.time_zone=".DB::selectOne("SELECT @@session.time_zone AS tz")->tz.PHP_EOL;
       echo "global.time_zone=".DB::selectOne("SELECT @@global.time_zone AS tz")->tz.PHP_EOL;
     '
     ```
     Expected: `app.timezone=UTC`, `db.mysql.timezone=+00:00` (after
     A2b), `session.time_zone` reads `+00:00` or `UTC`,
     `global.time_zone` is whatever — we set session explicitly via
     connection config.
   - Data sanity probe (unchanged — confirms existing rows are UTC).
   - Throw from the migration's `up()` if the config probe fails.

A2. `database/migrations/2026_04_19_*_convert_performance_events_occurred_at_to_timestamp.php`
    — raw `ALTER TABLE performance_events MODIFY occurred_at
    TIMESTAMP(6) NOT NULL`. Drop + recreate `pe_*_occurred_idx`
    indexes inside the migration.

A2b. **[NEW]** Pin the MySQL connection timezone. Add
    `'timezone' => '+00:00'` to `config/database.php` under the
    `mysql` and `mariadb` blocks. This runs `SET time_zone = '+00:00'`
    on every connection — the only reliable way to prevent a
    non-UTC server session from silently shifting `TIMESTAMP` reads.
    Verify by re-running the probe in A1.

A3. Model casts (`casts()` method per Laravel 12) →
    `'immutable_datetime'` on `PerformanceEvent`, `PerformanceSession`,
    `SongPerformance`, `Request`, `AudienceRewardClaim`, `CashTip`.
    **[EXPANDED]** Also cast every `_at` field listed in audit item 20:
    `User.email_verified_at`, `User.trial_ends_at` (Cashier already
    casts; verify), `Setlist.archived_at`, `Song.last_integrity_review_at`,
    `AiBatch.completed_at`, `AccountUsageCounter.{last_activity_at,
    inactivity_warning_sent_at, archived_render_images_at, blocked_at}`,
    `AudienceParticipation.{joined_at, last_seen_at}`,
    `AudienceAchievement.last_seen_at`, `ProjectSong.last_performed_at`,
    `ChartAnnotationVersion.client_created_at`. Use `immutable_datetime`
    uniformly. Cashier's `trial_ends_at` is managed by the package;
    don't override — verify the downstream behavior is unchanged.

A4. `PerformanceEventLogger::record()` —
    - `CarbonImmutable::now('UTC')` for ambient writes.
    - Context-supplied timestamps: `CarbonImmutable::parse($occurredAt)->utc()`
      (the `->utc()` variant — if the incoming string already has an
      offset, the second-arg form would silently ignore it).
    - **[NEW, clock-skew clamp]** cap context-supplied `occurred_at`
      at `min($supplied, CarbonImmutable::now('UTC')->addMinutes(5))`.
      Extract a `\App\Support\Time::clampFutureDrift(Carbon|string $v): CarbonImmutable`
      helper so the clamp lives in one place. Emit a `report()`
      breadcrumb when clamping fires so we can detect misbehaving
      clients.
    - **[NEW — real client-supplied-time surfaces]** The clamp must
      fire on every endpoint that writes a client-provided datetime,
      not just `PerformanceEventLogger::record()`. Confirmed sites:
      - `ProjectSongController::store`/history-create lines 828-830
        — `performed_at` from mobile. Apply `Time::clampFutureDrift()`
        before persisting.
      - `ChartAnnotationVersion.client_created_at` —
        `ChartAnnotationVersionController::store` accepts this from
        the mobile outbox. Apply the clamp before assigning to the
        model. This is the most drift-prone surface because mobile
        clients can queue writes for hours/days on a skewed clock.
      - Audience iframe surfaces (`AudienceProjectController`,
        `PublicRepertoireController`) currently all use
        server-side `CarbonImmutable::now('UTC')`; the clamp is
        *defensive* here — don't skip it, a future change could
        forward a client timestamp.
      Grep `'performed_at'\|'occurred_at'\|'client_created_at'` in
      every `app/Http/Controllers` directory once more during the
      PR and add the clamp wherever a client value flows to
      storage.

A5. Sweep the ~20 writer sites. Replace `now()` →
    `CarbonImmutable::now('UTC')`; `$model->created_at` →
    `$model->created_at->utc()`.
    **[EXPANDED]** Additional write sites from audit item 19 and 20:
    - `AudienceRequestPaymentService.php:154,172,492` — already listed;
      double-check that the Stripe event's own `created` timestamp is
      preferred when present (`CarbonImmutable::createFromTimestampUTC($stripeEvent->created)`)
      to make replays idempotent by value.
    - `AccountUsageService::touchUserActivity()` and every caller that
      writes to `account_usage_counters.*_at`.
    - `AiBatchPoller` / `PollBatchResults` — `completed_at` assignment.
    - `AudienceProjectController.php:65` (already listed).
    - Any artisan command that writes an `_at` field (e.g. the
      repair command at `PerformanceRepairCommand.php:357`,
      account-usage monitor).

A5a. **[NEW, from audit item 22]** Sweep all 8 `Carbon::parse($str)`
     callsites that omit the timezone argument. Pass `'UTC'` as the
     second argument, or chain `->utc()`. Silent-shift prevention.

A6. Run `vendor/bin/pint --dirty --format agent` after each PHP batch.

A7. **[NEW]** Add a connection-initialization assertion for
    non-production environments that logs a warning (or hard-fails in
    CI) if `DB::selectOne('SELECT @@session.time_zone AS tz')->tz` is
    not `+00:00` or `UTC`. Wire into
    `app/Providers/AppServiceProvider::boot()` inside
    `if ($this->app->environment('testing', 'local')) { ... }`.

### Phase B — Web responses and filter semantics

B1. Create `app/Http/Resources/PerformanceEventResource.php` (unchanged
    from original plan).

B2. `SongPerformanceHistoryService.php` — accept/require
    `string $timezone`; replace `' 23:59:59'` concat; sort events
    via Carbon; include `'timezone' => $session->timezone` on
    session objects.

B3. `SongPerformanceHistoryController.php` — add `timezone` validation;
    pass through; rebuild events through
    `PerformanceEventResource::collection`.
    **[REVISED, from adversarial review]** Make `timezone` **optional
    with a default of `$project->reporting_timezone`**, not required.
    This matches how `queue.md` already behaves, removes the
    mobile-ships-first deployment constraint, and still gives the
    client the control it needs. Validation rule:
    `['nullable', 'timezone:all']`. Tighten to `required` in a
    follow-up release after the mobile clients always send the
    param.

B4. Verify `PerformanceSessionResource`, `CashTipResource`,
    `RequestResource`, `PerformedSongResource` emit every `_at`
    via `toIso8601String()` on a UTC Carbon. (Inventory in audit
    item 11 confirms all use `toIso8601String()`; depends on A2b
    + A3 to guarantee UTC input.)

B5. **[NEW, CRITICAL]** `ProjectStatsService.php` — eliminate the
    fixed-offset DST bug (audit item 12). New approach:
    - Change the `$localDateExpr` builder to take an IANA name,
      not a numeric offset:
      ```
      'mysql', 'mariadb' => sprintf(
          "DATE(CONVERT_TZ(performance_events.occurred_at, '+00:00', %s))",
          DB::getPdo()->quote($timezone),
      ),
      ```
      `CONVERT_TZ` accepts an IANA name and consults
      `mysql.time_zone_name` per row — DST-correct by
      construction.
    - For SQLite, fall back to fetching `occurred_at` + `id` and
      converting in PHP via `CarbonImmutable::parse($v, 'UTC')->setTimezone($timezone)->toDateString()`
      grouped in a subquery, or (simpler) maintain a server-side
      helper that does the conversion in two passes. Since SQLite
      is only used in test/local, the perf hit is acceptable.
    - Verify MySQL time-zone tables are loaded:
      ```
      SELECT COUNT(*) FROM mysql.time_zone_name WHERE Name = 'America/Denver';
      ```
      Must return `1`. If `0`, production ops must run
      `mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql`.
      Document in the deployment note. CI is handled separately
      in H4.
    - Touch every callsite: `moneyHistory()`,
      `tipTotalsByLocalDate()`, `grossTipTotalsByLocalDate()`,
      `netTipTotalsByLocalDate()`, `dailyRecordEvent()`,
      `cachedDailyRecordEvent()`, `resolveBestDayRecord()`.
    - Remove `formatMysqlOffset()` helper once unused.
    - **[NEW — performance impact decision]** `CONVERT_TZ(col, '+00:00',
      'IANA/Name')` as a `GROUP BY` key is non-sargable — MySQL cannot
      use the `(project_id, occurred_at)` B-tree for grouping, so for
      `AllTime` on a large `performance_events` table it materializes an
      intermediate set. Decision: **accept for v1 and monitor**. Two
      mitigations are designed-but-deferred until a slow-query alert
      fires:
      1. Post-fetch rollup cache — `moneyHistory` already fetches row
         groups; push the convert-and-bucket work into PHP
         (`CarbonImmutable::parse($row->occurred_at, 'UTC')->setTimezone($tz)
         ->toDateString()`) after the DB returns the raw range. Trades
         index-on-bucket for a single O(n) pass; faster for
         windows ≤ 200k rows and DST-correct.
      2. Generated column — add `occurred_local_date_cache` as a
         `VARCHAR(10) GENERATED ALWAYS AS (DATE_FORMAT(CONVERT_TZ(
         occurred_at, '+00:00', 'America/Denver'), '%Y-%m-%d')) STORED`
         indexed by `(project_id, occurred_local_date_cache)`. Locks the
         project into a single tz and mixes migration + deploy
         coordination; avoid unless query latency proves it necessary.
      Pick option (1) as the default fallback; flag option (2) as
      "only if p95 of AllTime stats exceeds 2s under production load."

B6. **[NEW]** `ProjectStatsService.php:201` — change
    `'generated_at' => now()->toIso8601String()` to
    `CarbonImmutable::now('UTC')->toIso8601String()`.

B7. **[NEW]** `ShowProjectStatsRequest.php:90-99` — null-guard the
    `createFromFormat` return and convert error to a
    validator-level rejection instead of a 500.

B8. **[NEW]** Stats endpoints already accept `timezone` and use it
    correctly for window computation. Verify the response shape for
    `PerformanceSessionStatsController` also includes
    `session.timezone` as a first-class field, so the mobile stats
    view can render times in the correct zone.

B9. **[NEW, from adversarial review]** Fallback-to-UTC sweep.
    Every server-side "fall back to UTC when X tz is null" must
    become "fall back to `project.reporting_timezone`". Known sites:
    - `locations.md:473` (DOW analytics) — documented; the service
      that computes DOW must resolve
      `$session->timezone ?? $project->reporting_timezone ?? 'UTC'`
      and only use UTC as a last resort when the project itself has
      no reporting tz (which shouldn't happen post-signup).
    - Any stats code path that currently would treat null tz as
      UTC. `ProjectStatsService::report()` requires a tz from the
      request; confirmed not affected. But audit every caller of
      `LocationService` / DOW aggregator / per-session rollups for
      the same anti-pattern before closing this phase.

### Phase C — Shared contracts

C1. `_shared/contracts/song-performances.md` — add a "Timezone
    handling" section (unchanged). The working tree is clean; no
    in-flight edit to merge with.

C2. Add `timezone` to history-endpoint query-parameter tables.
    Required.

C3. Add `timezone` to session fields tables.

C4. Touch `cash-tips.md`, `setlists.md`, `queue.md`, `payouts.md`,
    `charts.md`, `locations.md`, `projects.md` so every `_at` /
    date field is explicitly ISO 8601 UTC.

C5. **[NEW]** Update `queue.md:28-29` to remove the "defaults to
    UTC" fallback — make `timezone` required to return the daily
    record event (without it, omit the whole field rather than
    compute it in UTC, which is never meaningful for a performer).
    Alternative: keep the fallback but mark it deprecated and
    require `timezone` for v2.

C6. **[NEW]** Update `locations.md:473` to document the session →
    reporting → device precedence for DOW analytics and similar
    reports; remove the "falls back to UTC" language.

C7. **[NEW]** Add a top-level document
    `_shared/contracts/timezone-and-time.md` that is the single
    source of truth:
    - Wire format: ISO-8601 with `+00:00`.
    - Storage: UTC, `TIMESTAMP(6)`, MySQL `@@session.time_zone = '+00:00'`.
    - Display precedence: session.timezone → project.reporting_timezone → device.
    - Date bucketing: per-row `CONVERT_TZ`, never per-request offset.
    - Zone-name validity: IANA only; `timezone:all` on every endpoint.
    Cross-link from every affected contract. Update
    `api-contract-rules.md:87-90` and `ARCHITECTURE.md:4,606` to
    link here instead of repeating.

### Phase D — Mobile helper + domain parsing

D1. `pubspec.yaml` — add `timezone: ^0.10.0`. Initialize
    `tz_data.initializeTimeZones()` at app bootstrap.

D2. Create `app/lib/core/time/app_time.dart`:
    ```
    class AppTime {
      const AppTime(this.reportingTimezone);
      final String reportingTimezone;
      tz.TZDateTime inZone(DateTime utc, {String? sessionTimezone});
      DateTime dayBucket(DateTime utc, {String? sessionTimezone});
      String formatTime(DateTime utc, {String? sessionTimezone});
      String formatDate(DateTime utc, {String? sessionTimezone});
      String zoneLabel({String? sessionTimezone});  // [NEW] — short zone abbrev for the effective zone
      DateTime nowInReporting();
      DateTime nowUtc();  // [NEW] — one place for outbox/annotation writers
    }
    ```

D3. `app/lib/core/time/app_time_provider.dart` — Riverpod provider
    watching `reporting_timezone_service` through the existing
    wrapper pattern in `app/lib/core/providers/`.

D4. `cash_tip.dart` — `createdAt` becomes `DateTime`; update
    `fromJson`/`toJson`/cache reader. **Also sweep every caller
    of `cashTip.createdAt`**: any string-concatenation, template
    interpolation, or sort-key use that treats it as a `String`
    will silently produce garbage (`"Logged at Instance of
    'DateTime'"`). `grep -rn 'cashTip.createdAt\|\.createdAt' lib/features/cash_tips lib/features/home lib/features/perform`
    before committing. **[CLARIFIED, from adversarial
    review]** Hive storage is safe: `cash_tip_repository_impl.dart:76-79,
    168` stores JSON-encoded strings in a string-valued box (not a
    typed Hive adapter). The cache entry is opaque JSON; `fromJson`
    always sees `created_at` as a string and can call
    `DateTime.parse(...)`. **However**, before touching any other
    mobile domain model with a `DateTime` field, grep
    `lib/core/storage/hive_adapters.g.dart` for a typed adapter. If
    one exists, a schema change requires a typeId bump (which wipes
    the box on next launch) or a one-shot migration in app bootstrap.
    Known typed adapters today: OutboxItem, Chart, ChartImageCacheEntry,
    AnnotationVersion, SongRequest, Setlist, and the EtagCache entry
    (see `typeId = 0..20+` in `hive_adapters.g.dart`). Any DateTime
    field on those models is already in a Hive int64 slot —
    unchanged; safe.

D5. `timeline_event.dart:52-54` — remove epoch-0 fallback; make
    `occurredAt` non-nullable and throw on missing JSON.

D6. `performance_history_session.dart` — add `final String? timezone`;
    wire from JSON.

D7–D11. **[REVISED, from adversarial review — DEBUGGABILITY, not
    bug-fix]** Normalize client-local `DateTime.now()` to
    `.toUtc()` at the sites below. Dart `.difference()` and
    `.compareTo()` already operate on `microsecondsSinceEpoch`,
    so DST fall-back doesn't cause negative durations — this is
    not a correctness fix. The motivation is **debuggability**:
    every persisted DateTime reads the same in every log viewer,
    every Hive inspection, and every device tz. Reclassifying
    these from "bug fix" to "invariant hygiene" prevents
    overclaiming in the PR description and discourages writing
    DST-testing that will pass regardless.
    - D7: `outbox_item.dart` — `createdAt`, `lastAttemptAt`
    - D8: `annotation_version.dart:61` — `syncedAt`
    - D9: `etag_cache.dart:64` — `cachedAt`
    - D10: `setlist_performance_tracker.dart` — `lastActivityAt`
    - D11: `queue_repository_impl.dart` — optimistic `createdAt`

D12. **[NEW — the one that matters]** `JsonTimeSerializer` at the
    outbox wire boundary enforces `.toUtc().toIso8601String()` on
    every `_at` field leaving the device. Centralizes the
    invariant so no `toJson` has to remember. **This covers the
    client-clock-offset case that D7-D11 individually do not.**
    M3 (`client_created_at`) is fixed by this step — no separate
    work item needed.
    **Acceptance criterion**: every non-test JSON payload leaving
    the device routes through a single function
    (`encodeOutboundJson(Map<String, dynamic>)`) whose type
    signature forces UTC via an `AppTime` dependency. Measured
    by: grep `http_client.dart|outbox_processor.dart|api_client`
    for bare `jsonEncode` calls — must be zero hits after D12.
    Injection point: wrap
    `lib/core/http/http_client.dart`'s outgoing body encode with
    the helper; every repository API call already routes there.

### Phase E — Mobile display + filter send

E1. Replace every `.toLocal()` on a performance-event timestamp
    (18 sites) with `appTime.formatTime/formatDate` using the
    effective zone (session > reporting > device).
    **[EXPANDED]** Also touch the domain-object `.toLocal()` sites
    in `settings_screen_account_tab.dart`
    (`_formatArrivalDate`), anywhere `history_session.startedAt
    .toLocal()` or similar appears, and the additional
    `stats_controller.dart` sites.

E2. Replace the four "now in reporting tz" ternaries with
    `appTime.nowInReporting()`.

E3. `performance_detail_screen.dart:439, 470` — time picker
    seeded with `appTime.inZone(session.startedAt,
    sessionTimezone: session.timezone)`; convert to UTC on save.

E4. History-endpoint callers — add `timezone` query param from
    `reportingTimezoneServiceProvider`.

E5. **[NEW]** `DateTime.now().timeZoneName` (4 sites) →
    `appTime.zoneLabel(sessionTimezone: ...)`.

E6. **[NEW]** `stats_controller.dart._nextMidnightDelay()` —
    compute against `appTime.nowInReporting()` instead of
    `DateTime.now()`.

E7. **[NEW]** Location create/update — `LocationController`
    continues to read device tz via `flutter_timezone`, but the
    Laravel-side validator must reject non-IANA names
    (Phase A/B follow-up for the locations endpoint).

E8. **[NEW, from adversarial review]** Cash-tip event tiles render
    `local_date` (the authoritative bucket key), not `createdAt`.
    The server stores cash tips as "performance-session cash total
    logged at end of set"; `createdAt` is the server-side write
    moment, which can be minutes-to-hours after the wall-clock
    moment the audience actually handed over cash. The tile
    already shows `local_date` for grouping; keep it; do not
    replace with `createdAt` during the Phase E `.toLocal()`
    sweep. Add a comment above the cash-tip tile rendering line
    explaining the divergence so a future refactor doesn't
    "fix" it.

### Phase F — Tests

F1. `SongPerformanceHistoryFilterTimezoneTest.php` — cross-midnight
    filter test (unchanged).

F2. `HistoryResponseUtcShapeTest.php` — regex every ISO-8601 string
    for `+00:00`. **[EXPANDED]** Shape test alone is insufficient
    — also add a **value-preservation** test: seed a known UTC
    instant, retrieve it through the whole stack
    (write → cast → resource serialize), and assert the ISO
    string equals the seed exactly. Shape plus value catches the
    MySQL-session-tz silent-shift bug that a pure regex passes.

F3. `app_time_test.dart` — session-tz precedence, reporting-tz
    fallback, invalid tz fallback, cross-midnight day bucket.

F4. `performance_detail_cross_midnight_test.dart` — widget test
    with session tz Denver + UTC event at 05:30Z → asserts
    `Apr 8` label, not `Apr 9`.

F5. Update `CashTipTest.php` and related model tests for
    `createdAt: DateTime`.

F6. **[NEW, CRITICAL]** `ProjectStatsDstBoundaryTest.php` (Pest) —
    seed a project in `America/Denver` with tips on
    `2026-03-07 23:30 local` (MST, UTC `2026-03-08 06:30`) and
    `2026-03-08 23:30 local` (MDT, UTC `2026-03-09 05:30`). Call
    `moneyHistory()` at a test-frozen `now = 2026-03-15 UTC`.
    Assert buckets are `2026-03-07` and `2026-03-08`, not one
    mis-bucketed into the other. This test fails against the
    current fixed-offset implementation; it must pass after B5.
    **[EXPANDED, from adversarial review]** Run twice, once per
    DB driver:
    - `F6a` (MySQL fixture) — via CI's MySQL container. Asserts
      `count($buckets) >= 2` before asserting content, so a
      missing `mysql.time_zone_name` (Phase H4) fails loudly
      rather than passing with empty buckets.
    - `F6b` (SQLite fixture) — exercises the PHP fall-through
      path from B5. Most local/unit tests hit SQLite; without
      this, developers could ship code that works on one driver
      and corrupts on the other. Use Pest's `->group()` to run
      both by default; use `--filter=DstBoundary` to run in
      isolation.

F7. **[NEW]** `MySqlSessionTimezoneGuardTest.php` — opens a fresh
    connection and asserts `SELECT @@session.time_zone` returns
    `+00:00` or `UTC`. Catches a broken `config/database.php`
    before it corrupts data.

F8. **[NEW]** `HistoryResponseValueRoundTripTest.php` (pairs with
    F2) — feature test that creates a performance event with
    `CarbonImmutable::create(2026, 4, 9, 5, 30, 15, 'UTC')` (no
    microseconds — see next bullet), hits the history endpoint,
    and asserts the returned ISO string is exactly
    `2026-04-09T05:30:15+00:00` — character for character.
    Exposes any cast-layer shift even when shape is correct.
    **Verified empirically**: `CarbonImmutable::toIso8601String()`
    truncates microseconds, so the seed must be whole-second for
    the exact-equals assertion to work. F2's regex
    `(?:\.\d+)?\+00:00` remains load-bearing for any future path
    that uses `format('Y-m-d\TH:i:s.uP')` instead.

F9. **[RESHAPED, from adversarial review]** Mobile
    `outbox_wire_serializer_test.dart` — test the D12
    `JsonTimeSerializer` boundary: seed payloads with a local-tz
    `DateTime`, pass through the serializer, and assert every
    `_at` field emits an ISO-8601 string ending in `+00:00` or
    `Z`, with no local offset leaking through. The original F9
    (DST fall-back retryDelay) passes against the current code
    because `.difference()` uses microsecondsSinceEpoch — it
    wouldn't have caught a real bug. The new F9 actually guards
    the invariant that matters.

F10. **[NEW]** Contract snapshot test — in CI, a script runs over
     `_shared/contracts/*.md`, extracts every code block, and
     asserts every ISO-8601 example string includes `+00:00` (or
     is explicitly tagged as "local — not UTC"). Prevents
     contract drift.

### Phase G — Scheduled jobs

G1. **[NEW]** `routes/console.php` — document in a leading
    comment that Denver tz is the performer-business tz; every
    `timezone('America/Denver')` is deliberate; UTC-schedule tasks
    call out the UTC nature explicitly (`->timezone('UTC')` even
    though it's the default).

G2. **[NEW]** `performances:end-stale` currently runs every 5
    minutes with no timezone. Add `->timezone('UTC')` to make the
    convention explicit; no behavioral change.

G3. **[NEW]** Backfill / repair commands that touch historical
    rows (`PerformanceRepairCommand`) — add a `--dry-run` flag
    that prints before/after UTC instants, so operations can
    sanity-check before running destructively.

### Phase H — CI & guards

H1. **[NEW]** Add a `composer test:tz` alias that runs F6, F7,
    F8 and the shape tests. Run on every PR that touches
    anything under `app/Services/ProjectStatsService.php`,
    `app/Http/Resources/`, `app/Models/`, or `config/database.php`.

H2. **[NEW]** Add a Flutter test tag `tz` for F3, F4, F9 and
    run via `flutter test --tags tz` on every PR that touches
    `lib/core/time/`, `lib/core/outbox/`, or
    `lib/features/home/domain/`.

H3. **[NEW]** CI grep for regressions: any new `dateTimeTz`, any
    new `now()` not inside `CarbonImmutable::now('UTC')` in
    writer code, any new `DateTime.now()` in Dart without a
    trailing `.toUtc()` outside of three allowlisted UI-only
    sites, any new `Carbon::parse($str)` without a second tz
    argument. Simple pattern-grep; fail the build if hits exceed
    the allowlist.

H4. **[NEW, from adversarial review]** Ensure the CI MySQL fixture
    runs `mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root
    mysql` during image bootstrap. Without it, `CONVERT_TZ(t,
    '+00:00', 'America/Denver')` returns `NULL` for every row and
    F6 passes *with empty buckets* — false green. F6 must also
    assert `count($buckets) > 0` before asserting their content,
    so a missing tzdata fails the test loudly.

H5. **[NEW, from adversarial review — PR split]** Ship as five
    PRs, not one monolithic change:
    1. **PR1**: A1 probe + A7 local/testing guard only, no
       behavior change. Validates data health across staging
       and production before we touch anything else. If the
       probe shows historical non-UTC writes, **halt**.
       Remediation if halted: enumerate the offending rows
       (`SELECT id, occurred_at, created_at FROM performance_events
       WHERE TIMESTAMPDIFF(SECOND, occurred_at, created_at) NOT
       BETWEEN -3600 AND 3600` — rows where the two timestamps
       diverge by more than an hour likely witnessed a tz shift),
       back them up, and write a one-shot correction migration
       before resuming. Do not merge subsequent PRs until
       historical data is repaired or quarantined.
       **Tests in PR1**: F7 (MySQL session-tz guard).
    2. **PR2**: A2b (MySQL tz pin `'+00:00'`). Ships alone so
       the connection config change and its data probe can be
       re-run side-by-side. If a non-UTC session was ever in
       play, this PR reveals it; no data is modified but reads
       might shift.
       **Tests in PR2**: F7 re-run on the pinned connection.
    3. **PR3**: A2 + A3 + A4 + A5 + A5a + A6 (storage migration +
       model casts + writer sweep + clock-skew clamp).
       **Tests in PR3**: F2 (shape) + F8 (value round-trip) +
       new clock-skew-clamp tests for `ProjectSongController` and
       `ChartAnnotationVersionController`.
    4. **PR4**: B + C (response shape + contracts + stats
       DST fix).
       **Tests in PR4**: F1 (cross-midnight filter) + F6a +
       F6b (DST boundary on both drivers) + F10 (contract
       snapshot).
    5. **PR5**: D + E + G (mobile + scheduled).
       **Tests in PR5**: F3 (AppTime) + F4 (tile widget) +
       F5 (CashTip model) + F9 (outbox wire serializer).
    Each PR runs its own test subset (tagged `tz`). PRs 3+
    may parallel once PR2 lands.

---

## Critical files (union of original + new)

**Web**
- `web/database/migrations/2026_04_17_213437_create_performance_events_table.php` (audit only)
- `web/database/migrations/2026_04_19_*_convert_performance_events_occurred_at_to_timestamp.php` (new)
- `web/config/database.php` **[NEW]**
- `web/app/Providers/AppServiceProvider.php` **[NEW]** (for A7 local/testing guard)
- `web/app/Services/PerformanceEventLogger.php`
- `web/app/Services/PerformanceSessionService.php`
- `web/app/Services/AudienceRequestPaymentService.php`
- `web/app/Services/RewardThresholdService.php`
- `web/app/Services/SongPerformanceHistoryService.php`
- `web/app/Services/ProjectStatsService.php` **[NEW — critical]**
- `web/app/Services/AccountUsageService.php` **[NEW]**
- `web/app/Http/Controllers/Api/V1/SongPerformanceHistoryController.php`
- `web/app/Http/Controllers/Api/V1/QueueController.php`
- `web/app/Http/Controllers/Api/V1/CashTipController.php`
- `web/app/Http/Controllers/Api/V1/RewardClaimController.php`
- `web/app/Http/Controllers/Api/V1/PerformanceSessionStatsController.php` **[NEW]**
- `web/app/Http/Controllers/Api/V1/StripeWebhookController.php` **[NEW]**
- `web/app/Http/Controllers/Public/AudienceProjectController.php`
- `web/app/Http/Requests/ShowProjectStatsRequest.php` **[NEW]**
- `web/app/Http/Resources/PerformanceEventResource.php` (new)
- `web/app/Http/Resources/PerformanceSessionResource.php`
- `web/app/Http/Resources/CashTipResource.php`
- `web/app/Http/Resources/RequestResource.php`
- `web/app/Http/Resources/LocationResource.php` **[NEW]**
- `web/app/Http/Resources/ChartResource.php` **[NEW]**
- `web/app/Http/Resources/ChartAnnotationVersionResource.php` **[NEW]**
- `web/app/Models/PerformanceEvent.php`
- `web/app/Models/PerformanceSession.php`
- `web/app/Models/SongPerformance.php`
- `web/app/Models/Request.php`
- `web/app/Models/AudienceRewardClaim.php`
- `web/app/Models/CashTip.php`
- `web/app/Models/User.php` **[NEW]**
- `web/app/Models/Setlist.php` **[NEW]**
- `web/app/Models/Song.php` **[NEW]**
- `web/app/Models/AccountUsageCounter.php` **[NEW]**
- `web/app/Models/AudienceParticipation.php` **[NEW]**
- `web/app/Models/AudienceAchievement.php` **[NEW]**
- `web/app/Models/AiBatch.php` **[NEW]**
- `web/app/Models/ProjectSong.php` **[NEW]**
- `web/app/Models/ChartAnnotationVersion.php` **[NEW]**
- `web/routes/console.php` **[NEW]** (G1/G2)

**Mobile**
- `app/lib/core/time/app_time.dart` (new)
- `app/lib/core/time/app_time_provider.dart` (new)
- `app/lib/core/time/json_time_serializer.dart` **[NEW]** (D12)
- `app/lib/core/outbox/outbox_item.dart` **[NEW]**
- `app/lib/core/outbox/outbox_repository.dart` **[NEW]**
- `app/lib/core/http/etag_cache.dart` **[NEW]**
- `app/lib/core/session/setlist_performance_tracker.dart` **[NEW]**
- `app/lib/features/cash_tips/domain/cash_tip.dart`
- `app/lib/features/home/domain/timeline_event.dart`
- `app/lib/features/home/domain/performance_history_session.dart`
- `app/lib/features/home/controller/stats_controller.dart` **[NEW]**
- `app/lib/features/queue/data/queue_repository_impl.dart` **[NEW]**
- `app/lib/features/annotations/domain/annotation_version.dart` **[NEW]**
- All six event tile files
- `app/lib/features/perform/presentation/performance_detail_screen.dart`
- `app/lib/features/perform/presentation/perform_screen.dart` **[NEW]**
- `app/lib/features/setlists/presentation/setlist_detail_screen.dart` **[NEW]**
- `app/lib/features/setlists/presentation/setlists_list_screen.dart` **[NEW]**
- `app/lib/features/home/presentation/{home_screen,recent_performances_section,performance_history_screen}.dart`
- `app/lib/features/settings/presentation/settings_screen_account_tab.dart` **[NEW]**
- `app/pubspec.yaml`

**Shared**
- `_shared/contracts/song-performances.md`
- `_shared/contracts/cash-tips.md`
- `_shared/contracts/queue.md` **[NEW]**
- `_shared/contracts/projects.md` **[NEW]**
- `_shared/contracts/payouts.md` **[NEW]**
- `_shared/contracts/locations.md` **[NEW]**
- `_shared/contracts/setlists.md` **[NEW]**
- `_shared/contracts/charts.md` **[NEW]**
- `_shared/contracts/timezone-and-time.md` (new, C7)
- `_shared/api-contract-rules.md` (C7 cross-link)
- `_shared/ARCHITECTURE.md` (C7 cross-link)

---

## Verification

1. **Web**: `php artisan test --compact`; all Pest suites green
   including F1, F2, F6, F7, F8. `vendor/bin/pint --dirty --format
   agent` clean.
2. **Mobile**: `flutter test` green; `flutter analyze` clean.
3. **MySQL time-zone tables**: production ops must confirm
   `SELECT COUNT(*) FROM mysql.time_zone_name WHERE Name =
   'America/Denver'` returns `1`. If it's `0`, `CONVERT_TZ(t,
   '+00:00', 'America/Denver')` returns `NULL` and every bucket
   for Denver projects becomes empty. **This is a blocker for
   Phase B5.** CI covered by H4.
4. **End-to-end smoke**: performer in `America/Denver`, tips at
   9pm local on a DST-transition weekend. Confirm session tile,
   event tile, history filter, stats money card, and best-day
   record all agree on the local calendar day.
5. **Contract lint**: CI step from H1/H2.
6. **Deployment ordering** (five-PR split, see H5):
   PR1 (probe) → PR2 (A2b tz pin + data sanity) → PR3 (storage +
   casts + writers) → PR4 (responses + contracts) → PR5 (mobile +
   tests + scheduled). PR4's history endpoints ship `timezone`
   as **optional with `project.reporting_timezone` default**
   (see B3 revision) — no mobile-first release-ordering
   constraint.
7. **Mobile smoke**: turn the device clock to Sunday 01:59 MDT
   → roll forward to Sunday 03:00 MDT (DST spring-forward) in
   the middle of a performance. Log a song. Confirm the tile
   reads the correct wall-clock time and the event is not
   duplicated/lost. Repeat for fall-back.
8. **Pre-implementation probes** (must run before PR1 merges):
   - `SELECT @@session.time_zone, @@global.time_zone` on
     production and staging. If either is non-UTC, halt and
     inspect `performance_events` / `cash_tips` / `requests`
     for historical writes that may already be shifted.
   - `SELECT COUNT(*) FROM mysql.time_zone_name` on production.
     Must be > 0 before PR4 merges.
   - `grep -rn 'Carbon.*parse(' app/` — confirm all 8 sites
     from audit item 22 are covered; no new ones introduced.

---

## Out of scope (follow-up PRs)

- Converging `SongPerformanceHistoryController::index()` onto
  `performance_events` (unchanged).
- Stats response caching keyed by tz+local_date (safe once B5
  lands; current `cachedDailyRecordEvent` already keys on
  `now($tz)->toDateString()`).
- Session-tz capture on free-play sessions that currently don't
  set `performance_sessions.timezone`.
- `chart_annotation_versions.client_created_at` clock-skew
  handling (server currently trusts client time).
- Converting any remaining `DATE` columns
  (`cash_tips.local_date`, `account_usage_daily_rollups.rollup_date`,
  `requests.played_at` is a TIMESTAMP — unchanged) to a documented
  "local-date-in-{timezone}" pattern.
