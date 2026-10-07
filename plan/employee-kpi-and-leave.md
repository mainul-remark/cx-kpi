# Employee KPI and Official Leave — Plan

Written 2026-10-07. Status: all four phases built on 2026-10-07, not yet committed. See "As built" at the end for where the build differs from this plan.

## Goal

Give every field user a KPI score for any date range, calculated from the task
numbers they report each day against the targets the admin sets for them.
Official leave must not lower the score.

## What exists today

Reports and targets already mirror each other, one row per user per day.

| Activity | Actual | Target |
|---|---|---|
| Outbound calls | `daily_reports.outbound_calls` | `daily_targets.outbound_calls` |
| Inbound calls | `daily_reports.inbound_calls` | `daily_targets.inbound_calls` |
| Message replies | `daily_reports.message_replies` | `daily_targets.message_replies` |
| Comment replies per platform | `daily_report_platform_replies.total_replies` | `daily_target_platform_replies.total_replies` |
| Calls per project | `daily_report_project_calls.total_calls` | `daily_target_project_calls.total_calls` |

- `holidays` plus the weekly off day (Friday, `Holiday::WEEKLY_OFF_DAY`) give the working-day calendar.
- `ReportDashboardService` already computes per-user achievement (`users()`) and attendance (`attendance()`).
- Nothing in the database records leave. No table of the 32 holds it.
- The KPI itself needs no schema change. Leave needs one new table.

## KPI formula

Match actual against target day by day, then sum over the period and divide once.

**KPI % = total completed on countable days / total target on countable days x 100**

"Total completed" on a day is the sum of the activities that have a target that
day. An activity with no target that day is left out of both sides.

| Day type | Target counted | Actual counted | Effect on KPI |
|---|---|---|---|
| Worked, report submitted | Yes | Yes | Normal |
| Working day, no report, no leave | Yes | 0 | Lowers KPI |
| Official leave, full day | No | No | Neutral |
| Official leave, half day | 50% | Yes | Fair reduction |
| Friday or holiday | No | No | Neutral |
| No target set | No | No | Neutral |
| Day before the user was added | No | No | Neutral |

Rules:

- Sum first, divide once, so busy days weigh more than light days.
- Show the raw percentage (for example 115%), but cap the final score at 100 so one huge day cannot hide absent days.
- A half-day target is rounded up after halving.
- KPI is `null` (shown as "-") when the period has no countable target.

## Official leave

### New table `user_leaves`

One row per user per leave day, the same shape as `holidays`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `user_id` | foreignId | cascade on delete |
| `leave_date` | date, indexed | |
| `portion` | enum `full`, `half` | default `full` |
| `type` | string | casual, sick, annual, other |
| `note` | text, nullable | |
| `approved_by` | foreignId to users, nullable | null on delete |
| timestamps | | |

Unique on (`user_id`, `leave_date`).

### Behaviour

- **KPI:** a full leave day is skipped entirely. A half day halves the target.
- **Attendance:** the day gets a new mark `L`. It counts as neither present nor absent, so attendance % is not hurt.
- **Expected submissions:** full leave days are subtracted from the reports due.
- **Targets are kept:** adding leave does not delete the target row, it is only ignored in the calculation. Cancelling the leave brings the target back by itself. (This differs from `Holiday::createOrUpdateHoliday`, which deletes targets.)
- **Report on a full leave day:** submission is blocked with a validation message.
- **Leave on a Friday or holiday:** rejected, as the day is already off.
- **Who enters leave:** admin only in this phase. Entry covers a date range and one or more users, and stores one row per working day in the range.

## Build steps

### Phase 1 — Leave

1. Migration `create_user_leaves_table`.
2. Model `UserLeave` with `user()`, `approvedByUser()` and a static `setForUsers(array $userIds, array $dates, array $data, ?int $approvedBy)` that upserts, following `DailyTarget::setForUsers`.
3. `UserLeaveRequest` for validation (date range, users, portion, type).
4. `UserLeaveController` with index, store, update, destroy, and routes next to `holidays` in `routes/web.php`.
5. Views under `resources/views/backend/leaves/`, following the holidays pages (list plus modal).
6. Menu entry in `resources/views/backend/includes/menu.blade.php` and resources in `database/seeders/ResourceSeeder.php` for permissions.
7. Block report submission on a full leave day in `DailyReportRequest`.

### Phase 2 — Attendance and submissions

1. `ReportDashboardService::attendance()`: load leave rows for the range, mark `L` for full leave, keep `P` when a report exists on a half day.
2. `ReportDashboardService::submissions()`: subtract full leave days from `expected`.
3. Attendance view and script: add the `L` mark to the legend and the cell colours.

### Phase 3 — KPI

1. New `App\Services\Kpi\EmployeeKpiService`:
   - loads targets, reports and leave per user per day for the range in grouped queries,
   - applies the day rules in the table above,
   - returns per user: target total, actual total, raw %, capped score, days worked, days absent, days on leave, and a per-activity breakdown.
2. KPI page: one row per field user with date range presets (reuse `ReportDashboardService::range()`), sorted by score, with a per-user detail view showing the day-by-day figures.
3. A user sees only their own KPI; admins see everyone. Follow the `withTargets` / `withUsers` visibility rules the dashboard uses.
4. Replace the achievement figure in `ReportDashboardService::users()` with the KPI service, so the dashboard and the KPI page agree. Today that method sums targets and actuals over the range separately, which counts work done on days without a target and inflates achievement.

### Phase 4 — Optional

- `kpi_snapshots` table (user, month, target total, actual total, score, generated at) written by a monthly command, so past scores do not change when an old report or target is edited. Worth doing if KPI feeds appraisal or salary.
- Excel export of the KPI sheet (the project already has `export_requests` and Excel exports).
- Per-activity weights, if a call should count for more than a reply.
- Employee apply and manager approve flow for leave, using `users.reporting_user_id`.

## Tests

Feature tests in `tests/Feature/`, following `DailyTargetTest` and `AttendanceTest`:

- `UserLeaveTest`: create over a range, skips Fridays and holidays, unique per user per day, permission checks, delete.
- `AttendanceTest`: `L` mark, attendance % unchanged by leave, expected submissions reduced.
- `DailyReportTest`: submission blocked on a full leave day, allowed on a half day.
- `EmployeeKpiTest`: one case per row of the day-rules table, plus the 100 cap, the no-target `null` case, and a user who joined mid-range.

## Open decisions

| Decision | Proposed default |
|---|---|
| Cap on over-achievement | Raw % shown, score capped at 100 |
| Half-day leave | Supported, target halved |
| Leave entry | Admin only for now |
| Report on a full leave day | Blocked |
| Monthly snapshots | Deferred to phase 4 |
| Activity weights | None, all activities count equally |

## Known limits

- The KPI measures volume only. Nothing in the data covers call outcome, response time or customer satisfaction.
- Figures are self-reported with no verification step.

## As built

Where the build differs from, or adds to, the plan above:

- **Leave and reports never overlap:** a full day of leave is rejected on a day the user already reported on, both when an admin sets it and when it is approved. This mirrors the report block.
- **Activities are matched one by one:** each platform and each project is its own activity, so replies on a platform without a target do not count towards a target set for another platform.
- **Days still to come are left out:** a target set ahead is not held against anyone until its day arrives. Today counts as absent until the report is in, the same as on the attendance sheet.
- **Half-day leave without a report** is an absence on the attendance sheet and counts as zero against the halved target.
- **Field users and targets:** a field user sees their own KPI %, score and completed counts, but no target figures, in line with the dashboard.
- **Dashboard:** only the per-user achievement uses the KPI service. The team cards per activity still sum the whole range.
- **Weights:** set in `config/kpi.php`, all 1 by default. They change the KPI %, the target and completed totals shown stay plain counts.
- **Snapshots:** `php artisan kpi:snapshot [YYYY-MM] [--force]`, scheduled for the 1st of each month at 00:30. A month still running cannot be frozen, and a frozen month is only worked out again with `--force`. The KPI page lists them under "Frozen Monthly Scores".
- **Excel export:** `kpi/export`, with the same filters as the sheet.
- **Leave requests:** a field user asks for leave on "My Leaves" (`my-leaves`), up to 31 days at a time. It waits as pending and counts only once approved. Whoever holds the `leaves.approve` and `leaves.reject` permissions decides, `users.reporting_user_id` is not used. A leave set by an admin is approved at once and takes over a pending request for the same day.

Open items:

- Run `php artisan db:seed --class=ResourceSeeder` to label the new permissions, then grant them to the roles that need them.
- The server needs `php artisan schedule:run` on cron for the monthly snapshot to run by itself.
- The pages were covered by feature tests only, not clicked through in a browser.
