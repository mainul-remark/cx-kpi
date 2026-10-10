# Check-in / Check-out

Status: implemented.

## Purpose

Keep a **log** of field users' check-ins and check-outs, and use it for attendance. Nothing more:

- No shifts, no office locations, no late rule. Those are managed in another application, which can work out lateness from this log.
- No forced daily report. A check-in never depends on, or redirects to, the daily report form.
- Only `usages_sector = field` users check in. Corporate users do not; they see everyone's log.

## Presence

A field user is **present** on a day with a daily report **or** a check-in session. Either is enough. The P/A/L/O/H marks in `ReportDashboardService::attendance()` follow this, and additionally carry `checked_in` and `incomplete` day counts.

## Data: `attendance_sessions`

One row per work session (a day can hold several).

| Column | Purpose |
|---|---|
| `user_id` | owner |
| `work_date` | the day the session belongs to, in `attendance.timezone`; stored as a plain `Y-m-d` |
| `checked_in_at`, `checked_out_at` | times, stored as the app stores them. `checked_out_at` stays NULL when the user forgot |
| `check_in_lat/lng/accuracy`, `check_out_lat/lng/accuracy` | optional geo-location; never blocks a check |
| `check_in_ip/device`, `check_out_ip/device` | audit trail |
| `close_reason` | `manual` (user), `auto` (system, forgotten), `admin` (manager correction) |
| `auto_closed_at`, `acknowledged_at` | forgotten-checkout handling and the warning shown once |
| `adjusted_by`, `adjustment_note` | manager correction; also logged with activitylog |
| `open_flag` (generated) | unique `(user_id, open_flag)`: the database allows one open session per user |

## Behaviour

- **Check in** (`POST attendance/check-in`): idempotent. If the user has an open session it is returned unchanged. Stale open sessions of earlier days are closed first (lazy fallback when the scheduler is down).
- **Check out** (`POST attendance/check-out`): closes the open session; with none open it changes nothing.
- **Status** (`GET attendance/status`): drives the header button. It also closes stale sessions, so the button never shows a session from a past day as still open.
- **Auto-close**: `attendance:auto-close` every 15 minutes closes sessions whose `work_date` is before today in `attendance.timezone`. No checkout time is invented: the day shows **Incomplete – no checkout**, hours unknown.
- **Warning**: the user is warned once about a forgotten checkout (header notice, dismiss or next check-in acknowledges it).
- **Reminder**: `attendance:remind` at 19:00 notifies users still checked in (database + mail). Needs the scheduler and mail configured.
- **Manager correction**: `attendance.sessions.adjust` sets the checkout time with a note. Needs the ACL resource granted to the managers' role.

The check-in endpoints sit outside the ACL group, so every field user can use the header button without a granted resource.

## Screens

- **Header button**: Check In (green) / Check Out (red) with a live timer; field users only.
- **Attendance page** (`/attendance`): the P/A/L marks sheet, plus the check-in log: one row per user and day with the first check in, the last check out, the worked time (sum of the day's sessions) and the number of sessions. Corporate users see everyone, who is checked in now, and a Correct action; field users see their own log.
- **KPI**: `checked_in_days` and `incomplete_days` are shown for information. They do not change the score.

## Configuration (`config/attendance.php`)

- `timezone`: `ATTENDANCE_TIMEZONE`, falling back to `APP_TIMEZONE`. Note `config/app.php` hardcodes `'timezone' => 'UTC'`, so this is what defines the work day.
- `max_listed_sessions`: cap on rows listed for a range.

## Tests

`tests/Feature/CheckInTest.php`: check in/out, optional and invalid location, double check-in, one open session at the database, checkout with no session, corporate users refused, midnight auto-close, lazy close on check-in, warning shown once and acknowledged, header visibility, presence from report or check-in, manager vs field user log, manager correction and its validation, reminders, KPI counts.
