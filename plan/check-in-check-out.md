# Check-in / Check-out plan

Status: all three phases implemented.

Phase 3 (implemented):
- **Shifts and late rule.** `work_shifts` (name, start time, grace) and `users.work_shift_id`. A user without a shift follows `attendance.default_shift` (10:00, 15 min grace by default). The first check in of a working day gets `late_minutes` (minutes after the shift start, 0 when on time). It is stored at check-in, so changing a shift later does not rewrite history. Later sessions of the day, off days, holidays and days with approved leave (full or half) are not judged (NULL).
- **Office rules.** `office_locations` (point plus radius, and/or IP addresses or CIDR ranges). Each check in records `check_in_place` (`office`, `field`, `unknown`) and the matched office. `ATTENDANCE_REQUIRE_OFFICE=true` makes check ins from outside every active office fail with a validation error; by default it only records the place.
- **Admin page.** `Shifts & Offices` (`attendance-settings.*`, corporate users, needs the ACL resources granted) manages shifts, who is on each shift, and office locations.
- **KPI.** The KPI rows and summary now carry `checked_in_days`, `late_days` and `incomplete_days`, shown on the KPI page. They inform only: the score is unchanged and the frozen monthly snapshots do not hold them. Only days with a check in are judged.

Implementation notes:
- Check-in endpoints (`attendance/status|check-in|check-out|acknowledge`) sit outside the ACL group, so every field user can use the header button without a granted resource. The manager correction route (`attendance.sessions.adjust`) is inside the ACL group and needs that resource granted to the managers' role.
- Timezone for the work day comes from `config/attendance.php` (`ATTENDANCE_TIMEZONE`, falling back to `APP_TIMEZONE`).
- Scheduler: `attendance:auto-close` every 15 minutes, `attendance:remind` daily at 19:00. Both need the scheduler (`schedule:run` every minute) to be running.
- Reminders use the `notifications` table (new migration) and the `mail` channel.
- Presence: a user is present on a day with a `daily_reports` row OR a check-in session, so a user can be present without ever clicking Check In. A check-in without a report is made up for by the forced report below.
- Forced report: the `report.owed` middleware (`RequireOwedDailyReport`) sends a field user, on any page load before today, to `daily-reports/create?date=<oldest day>` while they have a check-in day with no daily report. The report form itself, ajax calls and non-GET requests are never blocked. Today is excluded, since the report is written at day end./

## Findings from the codebase

- The column is `usages_sector` (values `field` / `corporate`), not `usage_type`.
- `/attendance` already exists. It derives marks from daily reports through `ReportDashboardService::attendance()`. Real check-in data should feed into it later and not replace it.
- `APP_TIMEZONE` is `Asia/Dhaka` in `.env`, but `config/app.php` hardcodes `'timezone' => 'UTC'`. Verify before relying on "12am". Store timestamps in UTC and compute the work date per user timezone.
- The scheduler lives in `routes/console.php`, next to `kv:expire-assignments` and `kpi:snapshot`. The auto-close job goes there.
- The header button goes in `resources/views/backend/includes/header.blade.php`, in a new `header-element` before the `header-theme-mode` block (line ~118).

## 1. Data model

One row per work session, not one row per day. This allows multiple visits or a lunch break later without a migration.

**`attendance_sessions`**

| Column | Purpose |
|---|---|
| `id`, `user_id` (FK) | owner |
| `work_date` (date) | the day the session belongs to, in the user's timezone. Stays correct if a session crosses midnight. |
| `checked_in_at`, `checked_out_at` (UTC, nullable) | actual times |
| `check_in_lat/lng/accuracy`, `check_out_lat/lng/accuracy` | optional geo-location |
| `check_in_ip`, `check_out_ip`, `check_in_device`, `check_out_device` | audit trail |
| `close_reason` | `manual`, `auto`, `admin` |
| `auto_closed_at` | when the system closed it |
| `acknowledged_at` | when the user saw the "forgot checkout" warning |
| `adjusted_by`, `adjustment_note` | when a manager corrects it |
| `timestamps` | |

Indexes and constraints:
- Index `(user_id, work_date)`.
- Index `(checked_out_at)` for the auto-close query.
- **One open session per user.** MySQL has no partial unique index. Add a generated column `open_flag = IF(checked_out_at IS NULL, 1, NULL)` with a unique index on `(user_id, open_flag)`. A double-click or two tabs cannot create two open sessions, because the database rejects the second.

**Config.** In `site_settings` or `config/attendance.php`:
- auto-close time
- maximum session length
- whether geo-location is required
- grace period

Log manual adjustments with spatie/activitylog (already installed).

## 2. Auto-checkout design

Do not write a fake checkout time into `checked_out_at` as if the user left then. Separate "the system closed it" from "the user left":

- The auto-close job sets `close_reason = 'auto'` and `auto_closed_at = now`.
- `checked_out_at` stays NULL for an auto-closed session, or is set to a computed value such as check-in plus the standard shift length (see decision 1).
- Reports and the KPI score show these sessions as **"Incomplete – no checkout"**. They do not count as a normal full day, and hours worked are unknown. The checkout time stays hidden from field users.
- A manager can correct the session later through an adjust action. That sets `adjusted_by` and `adjustment_note` and is logged.

**Mechanism: scheduled command plus lazy fallback.**
1. `attendance:auto-close` runs every 15 minutes (`Schedule::command(...)->everyFifteenMinutes()->withoutOverlapping()`). It finds open sessions whose `work_date` is earlier than today in the user's timezone, or that exceed the maximum session length, and closes them in chunks inside a transaction.
2. Running every 15 minutes (not once at midnight) handles users in different timezones, a missed run, and server downtime.
3. **Lazy fallback.** On check-in, if the user still has an open session from an earlier day, close it as `auto` first, then open the new one. If the scheduler is down, the data stays consistent.

## 3. Forgot-to-checkout warning

- Auto-close sets `acknowledged_at = NULL`.
- On the user's next page load (a shared Blade view composer or a small header component), if an unacknowledged auto-closed session exists, show a dismissible banner: "You forgot to check out on 7 Oct. The session was closed automatically and marked incomplete."
- The banner also shows at the next check-in attempt. Dismissing it, or checking in, sets `acknowledged_at`.
- Optionally send a Laravel `database` notification so the warning appears in a notification list and the manager can be told too.
- **Same-day reminder (recommended).** Around the end of the work day (for example 7 pm), remind anyone still checked in. This prevents most forgotten checkouts.

## 4. Header UI

- A single toggle button. **Check In** (green) when there is no open session, **Check Out** (red) when there is one.
- While checked in, show a live timer ("Checked in 03:42h").
- Render only if `auth()->user()->usages_sector === 'field'`. Enforce the same rule server-side in a policy or the controller.
- Post through fetch/AJAX. Disable the button while the request runs. Confirm before check-out.
- On mobile, show only the icon.

## 5. Backend structure

- `App\Services\Attendance\AttendanceService`: `checkIn()`, `checkOut()`, `autoClose()`, `currentSession()`. Controllers stay thin.
- `AttendanceCheckController`:
  - `POST /attendance/check-in` and `POST /attendance/check-out`, throttled (`throttle:6,1`).
  - `GET /attendance/status` for the header state.
- Both actions are idempotent: repeated requests return the current state and never create duplicates. Use `lockForUpdate()` on the user's open session inside a transaction.
- `AutoCloseAttendanceCommand`: the scheduled job above. It can dispatch to a queue for large user counts.
- `AttendanceSession` model with `scopeOpen()` and `scopeForDate()`, plus a `User::attendanceSessions()` relation.
- Permissions through the existing `uzzal/acl` resources, for example `attendance.adjust` for managers.

## 6. Integration with existing features

- Presence is report OR check-in. `ReportDashboardService::attendance()` keeps the P/A/L/O/H marks and adds `checked_in` and `incomplete` counts. The Check-in Log card shows check-in and check-out time, hours and a status for the days a user did check in.
- KPI (Phase 3): rules built on check-in data (late arrival, missed checkout, hours) apply only to days that have a session, so a user who never checks in is not penalised for it while their reports still count as present.
- Corporate users get a manager view: who is currently checked in, plus correction actions.

## 7. Scalability and hardening

- Write volume is small (two row touches per user per day). The real concern is read queries, which the indexes above cover. Archive or partition after a few years if needed.
- All timestamps are UTC. `work_date` is computed once at check-in and stored, so reports do not depend on timezone arithmetic.
- Geo-location is optional (confirmed). Denial or no browser support never blocks check-in or check-out; the row simply keeps NULL coordinates, which reports show as "location unavailable".
- Device-clock tampering is not a risk, because all times come from the server.
- Tests: feature tests for double check-in, checkout with no open session, midnight rollover, the auto-close command, and the banner.

## 8. Phased delivery

1. **Phase 1 (core).** Migration, model, service, endpoints, header button, auto-close command and schedule, forgot-checkout banner.
2. **Phase 2.** Manager view, correction flow, merge into `/attendance`, same-day reminder notification.
3. **Phase 3.** Geo-fence or office IP rules, late rule and grace period, KPI integration, per-user shift schedules.

## Decisions (confirmed)

1. **Auto-closed hours.** Leave `checked_out_at` empty and mark the day "Incomplete – no checkout". No fake checkout time is stored.
2. **Auto-close trigger.** Midnight in the user's timezone, run by the 15-minute scheduled job, with the lazy fallback on check-in.
3. **Geo-location.** Capture it at check-in and check-out, but it is optional. If the user denies permission or the browser has no support, the check-in still succeeds and the row records "location unavailable" (lat/lng/accuracy stay NULL).
4. **Scope of the first build.** Phases 1 and 2.
