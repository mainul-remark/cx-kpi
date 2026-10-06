# Plan: CX day-end report entry

Status: implemented 2026-10-05 (not yet clicked through in a browser). Written 2026-10-05 for an implementing agent (Claude Code or Codex).
Source sketch: `call.jpeg` in the repo root.

## Goal

Each CX team user submits one report per day, at the end of the day, containing:

1. Three fixed activity counts, each with an optional note: outbound calls, inbound calls, message replies.
2. One comment-reply count with an optional note **per active social media platform**. Platforms are admin-managed, like projects.
3. One call count with an optional note **per active project**.

The data feeds later KPI reporting, so it must be easy to sum by user, date range, project and platform.

## What exists already

- Laravel app with Jetstream, Blade views under `resources/views/backend`, jQuery + server-side DataTables (Yajra), toastr and SweetAlert2.
- `projects` table: `id`, `name`, `notes`, `slug` (unique), `active` (bool), timestamps. "Active / published project" means `projects.active = true`.
- Project CRUD is the reference pattern to copy:
  - `app/Models/Project.php` (`createOrUpdateProject`, `generateUniqueSlug`)
  - `app/Http/Controllers/ProjectController.php` (JSON responses, try/catch + `report()`)
  - `app/Http/Requests/ProjectRequest.php`
  - `resources/views/backend/projects/index.blade.php` and `partials/modal.blade.php`, `partials/script.blade.php`
  - Route registered in the `Route::resources([...])` block in `routes/web.php`, inside the `auth:sanctum` / `resource.maker` / `auth.acl` group.
  - Menu entry in `resources/views/backend/includes/menu.blade.php`, wrapped in `@allowed('projects.index')`.
- Access control is the `uzzal/acl` package: the `resource.maker` middleware registers route names as resources, `auth.acl` enforces them, and views use `@allowed('route.name')` / `allowed('route.name')`. Confirm how new route names become grantable to roles before relying on it (check the package and `database/seeders/Acl*Seeder.php`).

## Database structure

Four new tables. Create the migrations in this order.

### 1. `social_platforms` (admin-managed lookup, mirrors `projects`)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned, PK | |
| `name` | string(255) | unique |
| `slug` | string(255) | unique |
| `notes` | text, nullable | |
| `active` | boolean, default true | only active platforms appear on the form |
| `created_at`, `updated_at` | timestamps | |

### 2. `daily_reports` (one row per user per day)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned, PK | |
| `user_id` | foreignId -> `users.id` | cascade on delete |
| `report_date` | date | the day being reported |
| `outbound_calls` | unsigned integer, default 0 | |
| `outbound_calls_note` | text, nullable | |
| `inbound_calls` | unsigned integer, default 0 | |
| `inbound_calls_note` | text, nullable | |
| `message_replies` | unsigned integer, default 0 | |
| `message_replies_note` | text, nullable | |
| `created_at`, `updated_at` | timestamps | |

- Unique: (`user_id`, `report_date`).
- Index: `report_date`.

### 3. `daily_report_platform_replies` (one row per platform within a report)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned, PK | |
| `daily_report_id` | foreignId -> `daily_reports.id` | cascade on delete |
| `social_platform_id` | foreignId -> `social_platforms.id` | restrict on delete |
| `total_replies` | unsigned integer, default 0 | comment replies on that platform |
| `note` | text, nullable | |
| `created_at`, `updated_at` | timestamps | |

- Unique: (`daily_report_id`, `social_platform_id`).
- Index: `social_platform_id`.

### 4. `daily_report_project_calls` (one row per project within a report)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned, PK | |
| `daily_report_id` | foreignId -> `daily_reports.id` | cascade on delete |
| `project_id` | foreignId -> `projects.id` | restrict on delete |
| `total_calls` | unsigned integer, default 0 | |
| `note` | text, nullable | |
| `created_at`, `updated_at` | timestamps | |

- Unique: (`daily_report_id`, `project_id`).
- Index: `project_id`.

User and date live only on `daily_reports`; the child tables reach them through `daily_report_id`.

Restrict-on-delete on `project_id` and `social_platform_id` protects history: a project or platform that has report rows cannot be deleted, only deactivated. The existing `ProjectController::destroy` catches the exception and returns a generic 500; change it (and the new platform controller) to return a clear message such as "This project has report data. Deactivate it instead."

## Behaviour rules

Defaults chosen for this plan. The ones marked **confirm** are product decisions the owner has not made yet; implement the default and list them in the final summary.

- One report per user per date. Saving for a date that already has a report updates it (upsert on `user_id` + `report_date`).
- `report_date` defaults to today and cannot be in the future.
- All counts are integers >= 0; notes are optional, max 5000 characters (same limit as project notes).
- The form lists every active project and every active platform. On save, write a child row for each listed item, including zeros, so "reported 0" is distinguishable from "not on the form that day".
- When editing an old report, also show child rows whose project/platform has since been deactivated, so saved data is never hidden or dropped.
- Save the parent and all child rows in one DB transaction.
- **Confirm:** a user can create and edit only their own reports, for any past date. No lock after submission.
- **Confirm:** users who hold a separate "view all" permission see everyone's reports in the list; others see only their own.
- **Confirm:** project call counts are independent of the outbound/inbound totals (no "must add up" validation).
- **Confirm:** message replies stay a single count, not split per platform.

## Implementation steps

### Step 1: Social platforms CRUD

Copy the Projects feature one-to-one, renaming project -> social platform.

- Migration `create_social_platforms_table`.
- `app/Models/SocialPlatform.php` with fillable, `active` cast, `createOrUpdateSocialPlatform`, slug generation. The slug logic duplicates `Project::generateUniqueSlug`; extract a small shared trait only if it stays simple, otherwise duplicate.
- `app/Http/Requests/SocialPlatformRequest.php` (same rules as `ProjectRequest`, unique on `social_platforms.name`).
- `app/Http/Controllers/SocialPlatformController.php`.
- Views `resources/views/backend/social-platforms/index.blade.php`, `partials/modal.blade.php`, `partials/script.blade.php`.
- Route `'social-platforms' => SocialPlatformController::class` in the `Route::resources` block.
- Menu entry under `@allowed('social-platforms.index')`.
- Seeder with starter platforms (Facebook, Instagram, WhatsApp, TikTok, YouTube, LinkedIn), idempotent via `firstOrCreate` on slug; register it in `DatabaseSeeder`.

### Step 2: Report schema and models

- Migrations for `daily_reports`, `daily_report_platform_replies`, `daily_report_project_calls`.
- Models:
  - `DailyReport`: `belongsTo(User)`, `hasMany(DailyReportPlatformReply, 'daily_report_id')` as `platformReplies`, `hasMany(DailyReportProjectCall, 'daily_report_id')` as `projectCalls`; cast `report_date` to `date`.
  - `DailyReportPlatformReply`: `belongsTo(DailyReport)`, `belongsTo(SocialPlatform)`.
  - `DailyReportProjectCall`: `belongsTo(DailyReport)`, `belongsTo(Project)`.
  - Add `hasMany` inverses on `User`, `Project`, `SocialPlatform`.
- A static `DailyReport::saveForUser(User $user, array $data): self` that does the transactional upsert of parent and children, following the `createOrUpdateProject` style.

### Step 3: Validation

`app/Http/Requests/DailyReportRequest.php`. Expected payload:

```
report_date                     required, date, before_or_equal:today
outbound_calls                  required, integer, min:0
outbound_calls_note             nullable, string, max:5000
inbound_calls                   required, integer, min:0
inbound_calls_note              nullable, string, max:5000
message_replies                 required, integer, min:0
message_replies_note            nullable, string, max:5000
platforms                       array
platforms.*.social_platform_id  required, distinct, exists:social_platforms,id
platforms.*.total_replies       required, integer, min:0
platforms.*.note                nullable, string, max:5000
projects                        array
projects.*.project_id           required, distinct, exists:projects,id
projects.*.total_calls          required, integer, min:0
projects.*.note                 nullable, string, max:5000
```

Reject a project or platform that is inactive unless the report being edited already has a row for it.

### Step 4: Controller and routes

`app/Http/Controllers/DailyReportController.php`, resource route `daily-reports`.

- `index`: Blade page, plus DataTables JSON on ajax (date, user, the three totals, summed platform replies, summed project calls). Scope to the current user unless they have the view-all permission. Add date-range and user filters.
- `create`: the entry form for a date (default today). If a report already exists for that user and date, load it so the form doubles as edit.
- `store`: validate, call `DailyReport::saveForUser`, return JSON in the same shape as `ProjectController::store`.
- `show` / `edit`: return the report with `platformReplies.socialPlatform` and `projectCalls.project`; 403 if it is not the user's own and they lack view-all.
- `update` / `destroy`: same ownership check.

### Step 5: Entry form UI

`resources/views/backend/daily-reports/`. A full page rather than a modal, since the form grows with the number of projects and platforms. Layout follows the sketch: label, number input, note input per row.

- Date picker at the top; changing the date reloads the saved report for that date, if any.
- Section "Daily totals": outbound calls, inbound calls, message replies.
- Section "Comment replies by platform": one row per active platform, with a running total.
- Section "Calls by project": one row per active project, with a running total.
- Submit via ajax, field errors rendered with the same `data-error-for` approach as `projects/partials/script.blade.php`. Nested error keys arrive as `projects.0.total_calls`, so the error-display helper needs to map dotted keys to input names.
- Menu entries: "Daily Report" (entry form) and "Report History" (list), each under its `@allowed(...)` check.

### Step 6: Tests

Check `phpunit.xml` for the test database setup first. Feature tests:

- Social platform CRUD: create, duplicate name rejected, update, delete blocked when referenced.
- Report save creates parent plus one child row per active project and platform.
- Saving twice for the same user and date updates instead of duplicating.
- Future date, negative count and inactive project/platform are rejected.
- A user cannot view or edit another user's report without the view-all permission.

## Verification

1. `php artisan migrate` runs clean; `php artisan migrate:rollback` drops the four tables in reverse order.
2. `php artisan test` passes.
3. In the browser: create two platforms and two projects, submit today's report, reopen it and confirm values persist, deactivate one project and confirm it disappears from a new date's form but still shows on the saved report.
4. Sanity query for KPI use: total calls per project for a date range returns the expected sums via `daily_report_project_calls` joined to `daily_reports`.

## Out of scope

- KPI dashboards, charts and exports.
- Targets or scoring per user.
- Reminders for users who have not submitted.
- Manager approval or locking of reports.
