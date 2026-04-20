# Timezone & Time Contract

This is the single source of truth for how Song Tipper stores, transmits,
and displays timestamps and dates across all layers.

---

## Wire Format

- All timestamps on the API wire are **ISO 8601 with explicit UTC offset**.
- Example: `2026-04-19T12:00:00+00:00`
- The `+00:00` suffix is required — bare `Z` is accepted on inbound requests
  but `+00:00` is preferred for outbound server responses.
- Local-date fields (calendar day without a time component) use `YYYY-MM-DD`.

---

## Storage

- Every `TIMESTAMP` or `DATETIME` column is stored in **UTC**.
- MySQL session is locked to `'+00:00'`: `SET @@session.time_zone = '+00:00'`
  (enforced in `config/database.php`).
- Column type: `TIMESTAMP(6)` for microsecond precision; `TIMESTAMP` otherwise.
- Never store a local time without an accompanying IANA timezone column.

---

## Display Precedence

When deciding which timezone to use when rendering a timestamp for a user:

1. **Session timezone** — the IANA zone recorded on the performance session
   (e.g. `America/Denver`). Highest priority; reflects where the gig occurred.
2. **Reporting timezone** — the user's chosen "home" timezone stored in their
   project preferences. Used when no session timezone is available.
3. **Device timezone** — fallback for situations where neither of the above is
   known (e.g. the feature is pre-login or the field is absent).

---

## Date Bucketing

- "Which calendar day did event X happen?" is always answered by converting
  the stored UTC timestamp to the **effective timezone** using `CONVERT_TZ`
  (MySQL) or `TZDateTime.from` (Dart) — never by applying a per-request
  offset or forcing UTC midnight.
- `local_date` columns are stored pre-bucketed in the IANA zone specified by
  the accompanying `timezone` column.

---

## Timezone-Name Validity

- All timezone identifiers in the API and database must be **IANA zone names**
  (e.g. `America/New_York`, `Europe/London`, `UTC`).
- Client code must validate inbound IANA names with `tz.getLocation()` before
  persisting.
- The `timezone:all` rule applies to every API endpoint that returns or
  accepts a timezone field — include it in request validation.

---

## Key Implementation Points

| Layer | Class / Config | Responsibility |
|---|---|---|
| PHP models | `immutable_datetime` cast | Parse DB timestamps as `CarbonImmutable` (UTC) |
| PHP helpers | `Time::clampFutureDrift()` | Guard against clock-skew on inbound timestamps |
| PHP resources | `PerformanceEventResource` | Serialize `occurred_at` as ISO-8601 `+00:00` |
| Dart bootstrap | `tz_data.initializeTimeZones()` | Load IANA zone database at app start |
| Dart helper | `AppTime` (`core/time/app_time.dart`) | Convert / format using effective timezone |
| Dart outbox | `JsonTimeSerializer` (`core/time/json_time_serializer.dart`) | Normalize `DateTime` → UTC ISO-8601 before HTTP POST/PUT/PATCH |

---

## Related Documents

- [`_shared/api-contract-rules.md`](../api-contract-rules.md) — general API conventions (§4 Dates and Times)
- [`_shared/ARCHITECTURE.md`](../ARCHITECTURE.md) — system overview (§API Design Principles)
