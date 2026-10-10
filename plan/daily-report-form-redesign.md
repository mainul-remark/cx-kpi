# Daily report form redesign (calls tab + comments/message tab)

Page: `/daily-reports/create` (also `edit`). Status: **implemented** (migration `2026_10_10_080000_restructure_daily_report_activities`, not yet run on the dev database). Revision 2.

Implementation notes: per-entity note is kept on the Comments + Message tab only; rows not submitted are no longer
deleted on save; a project/platform activity key in the KPI service is `project:<id>:<count>` /
`platform:<id>:<count>`; weights of `0` in `config/kpi.php` mark the unscored approximate activities.

## 1. Goal

Replace the stacked cards on the daily report form with two Bootstrap nav-pill tabs, driven by the
`has_outbound_calls`, `has_comments`, `has_message_replies` flags on `projects` and `social_platforms`.

| Tab | Inputs |
|-----|--------|
| **Calls** | Outbound Calls, Order Processing Calls, Inbound Calls (each with a note); per-project and per-social-platform inbound call inputs, shown only where the entity has `has_outbound_calls = 1` |
| **Comments + Message** | per project **and** per social platform, two **separate** inputs: Comments (if `has_comments`) and Message Replies (if `has_message_replies`) |

- The top-level `message_replies` / `message_replies_note` inputs are removed: the message total is the sum of the
  per-entity rows.
- **No report date input.** The system chooses the date (section 2).

## 2. Report date is chosen by the system

The form no longer shows or posts `report_date`. The server decides:

- **Create / store:** the oldest date the user still owes a report for (`AttendanceService::owedReportDates()`,
  the same list `RequireOwedDailyReport` uses); when nothing is owed, **today**.
- **Edit / update:** the report's own date; it cannot be changed.
- `DailyReportController::create()` stops reading `?date=` (the owed-report redirect keeps working, it just
  doesn't need the param). `resolveDate()` is replaced by a `reportDateFor($user)` helper.
- `DailyReportRequest`: remove the `report_date` rules; the checks that mattered stay but run against the
  derived date (full-day approved leave → refuse; one report per user per day is already enforced by
  `saveForUser`). A request carrying `report_date` is ignored.
- `DailyReport::saveForUser` receives the date from the controller instead of `$data['report_date']`.
- Form shows the chosen date as plain read-only text in the card header (e.g. "Report for 10 Oct 2026"), not as an
  input, and the existing "you owe these dates" alert stays. Drop the label too if you want nothing shown.
- Filing one owed day at a time: after saving, the next owed date (if any) is picked automatically on reload.

## 3. KPI rule

**Scored (mandatory target):**
- **Outbound calls.** A target is always set. For the KPI the actual is `outbound_calls + order_processing`,
  and that sum is compared with the outbound call target.
- Outbound Calls and Order Processing are separate inputs on the report. Order processing has no target and no
  weight of its own; it only feeds the outbound activity.

**Not scored (optional, approximate targets):**
- Inbound calls (top-level and per project / per platform), comments, and message replies (per project and per
  platform). We can't know how many customers will call, comment or message, so these take an *approximate*
  target that is shown for reference (actual vs approximate target) but does **not** change the KPI percentage,
  and leaving the target empty is fine.

Consequences:
- `DailyTargetRequest` today requires "at least one activity" target. New rule: **`outbound_calls` target is
  required** (`required|integer|min:1`); every other target is nullable.
- `config/kpi.php`: `outbound_calls` keeps its weight; `inbound_calls`, `message_replies`, `comment_replies`,
  `project_calls` stop counting towards the score (weight `0`, which the file already defines as "left out").
  Implementation check: confirm weight 0 still lists the activity in the breakdown.

## 4. Database

New migration(s), after `2026_10_10_061550_add_columns_to_social_platforms_table.php`.

**`daily_reports`**
- add `order_processing` `unsignedInteger default 0`, `order_processing_note` `text nullable`
- drop `message_replies`, `message_replies_note`
- keep `outbound_calls(+note)`, `inbound_calls(+note)`

**`daily_report_project_calls`** (report + project) and **`daily_report_platform_replies`** (report + platform)
- add `inbound_calls`, `comments`, `message_replies` (`unsignedInteger default 0`); keep `note`
- data: project `total_calls` → `inbound_calls`; platform `total_replies` → `comments`; then drop the old column
- the two inputs `comments` and `message_replies` are stored separately for both projects and platforms

**Targets** (needed so the approximate targets exist per entity)
- `daily_targets`: drop `message_replies`; keep `outbound_calls` (now required), `inbound_calls` (optional)
- `daily_target_project_calls` and `daily_target_platform_replies`: add nullable `inbound_calls`, `comments`,
  `message_replies`; old `total_calls` → `inbound_calls`, `total_replies` → `comments`; drop the old columns

Down migrations restore the dropped columns (values for dropped top-level `message_replies` are lost).

## 5. Backend changes

| File | Change |
|------|--------|
| `app/Models/DailyReport.php` | fillable/casts: add `order_processing(_note)`, remove `message_replies(_note)`; `saveForUser(user, data, report, date)` writes the three per-entity counts |
| `DailyReportProjectCall.php`, `DailyReportPlatformReply.php` | fillable/casts for `inbound_calls`, `comments`, `message_replies` |
| `DailyTargetProjectCall.php`, `DailyTargetPlatformReply.php`, `DailyTarget.php` | same for the target side, nullable |
| `DailyReportRequest.php` | add `order_processing(_note)`; drop `message_replies*` and `report_date`; `projects.*` / `platforms.*` validate `inbound_calls`, `comments`, `message_replies` |
| `DailyTargetRequest.php` | `outbound_calls` required, rest nullable; drop the "at least one activity" check and top-level `message_replies`; per-entity rows validate the three optional counts |
| `DailyReportController.php` | derive date server-side; `form()` passes flags and the three values per row; index `withSum` on the new columns |
| `DailyTargetController.php` + target form | per-entity rows with the three optional inputs, outbound required |

Server-side guard: a value for an entity whose flag is off is ignored (not stored).

## 6. KPI / dashboard changes

`EmployeeKpiService::figures()` and `ReportDashboardService` currently read top-level `outbound_calls`,
`inbound_calls`, `message_replies` and the child `total_calls` / `total_replies`.

- **Outbound (reports source):** actual = `outbound_calls + order_processing`; target side stays `outbound_calls`.
  This is the only activity feeding `weighted_actual` / `weighted_target` and the KPI %.
- **Optional activities:** each entity gets keys `project:<id>:inbound_calls|comments|message_replies` and
  `platform:<id>:inbound_calls|comments|message_replies` (plus top-level `inbound_calls`). They are returned in
  `breakdown`/`activities` with their approximate `target` and `actual`, and a `scored => false` marker so views
  show them as informational and the KPI % ignores them. `metricOf()` maps these to dashboard metrics
  (`inbound_calls`, `comments`, `message_replies`).
- Dashboard SQL sums move to the child tables for comments/message replies; outbound total includes
  `order_processing`.
- `EmployeeKpiExport` follows the service output; verify columns still line up.

## 7. Views

**`daily-reports/form.blade.php`**
- No date input. Tabs: `ul.nav.nav-pills` (`Calls`, `Comments + Message`) and `tab-content`.
- Calls pane: top-level table (Outbound, Order Processing, Inbound: total + note), then "Inbound calls by
  project" and "Inbound calls by social platform" limited to flagged entities.
- Comments + Message pane: a table for projects and a table for social platforms, each with a Comments column
  (only `has_comments`) and a separate Message Replies column (only `has_message_replies`); entities with
  neither flag are left out.
- Running totals as badges (existing `data-sum-of` pattern); an error badge on a tab with invalid fields, and
  switch to the first tab with an error.
- `partials/rows.blade.php` is generalised to take a list of count columns instead of a single `countField`.
- `partials/form-script.blade.php`: nested payload for projects/platforms, no `report_date`, map validation
  errors to the right tab.

**`daily-reports/show.blade.php`** and index script: add Order Processing, drop top-level Message Replies, show
per-entity inbound / comments / message replies.

**`daily-targets/form.blade.php`** and its scripts: outbound required marker, other fields labelled
"Approximate (optional)".

## 8. Tests

Update `DailyReportTest`, `DailyTargetTest`, `EmployeeKpiTest`, `DashboardTest`:
- create/update with new fields; flag-off entity input ignored
- date: posted `report_date` ignored; oldest owed date used, else today; edit keeps its own date;
  full-day leave on the derived date is refused
- outbound KPI: target 100, outbound 60 + order processing 40 → 100 %
- optional activities (inbound, comments, messages) with or without a target do not change the KPI %
- target save fails without an outbound target; succeeds with only that
- migrations copy `total_calls`/`total_replies` into `inbound_calls`/`comments` on both report and target tables

## 9. Implementation order

1. Migrations + models (reports and targets)
2. Requests + `saveForUser` + server-side date
3. Controller `form()`, rows partial
4. Report form (tabs) + script
5. Target form/request changes
6. KPI service, config, dashboard service
7. `show` / index views, export
8. Tests, then manual check: create, edit, owed-report redirect, KPI page

## 10. Remaining questions (defaults used if not answered)

1. **Top-level Inbound Calls:** manual field next to the per-entity inputs (default), or the read-only sum of them?
2. **Optional targets not scored:** read "approximate ... target which will not" as *will not count in the KPI*.
   Correct?
3. **Date label:** show the chosen date as read-only text (default), or nothing at all?
4. **Several owed days:** user files the oldest first and the next is picked after saving (default).
5. **"platform"** in the earlier message is read as "project"; old top-level message replies are dropped.
