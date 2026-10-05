# Billing System — Complete Concept & Implementation Plan

> **Context**: Redesigned billing system for the retail asset management project.
> Previous concept is in `order_old.md`. This document supersedes it entirely.
> Written: 2026-06-09

---

## Table of Contents

1. [Project Context](#1-project-context)
2. [Two-Panel Architecture Overview](#2-two-panel-architecture-overview)
3. [Terminology](#3-terminology)
4. [Existing Key Columns Used in Billing](#4-existing-key-columns-used-in-billing)
5. [What Exists vs What Changes](#5-what-exists-vs-what-changes)
6. [Asset Billing Paths — Decision Matrix](#6-asset-billing-paths--decision-matrix)
7. [Space Allocation Formula — Core New Logic](#7-space-allocation-formula--core-new-logic)
8. [Complete Table Designs](#8-complete-table-designs)
9. [Lock / Immutability Rules](#9-lock--immutability-rules)
10. [Admin Panel — All Pages](#10-admin-panel--all-pages)
11. [Brand Portal — All Pages](#11-brand-portal--all-pages)
12. [Application Architecture](#12-application-architecture)
13. [Bill Generation Flow](#13-bill-generation-flow)
14. [Key Design Decisions](#14-key-design-decisions)
15. [Implementation Order](#15-implementation-order)
16. [Unit Conversion Reference](#16-unit-conversion-reference)

---

## 1. Project Context

This is a retail asset management system where:
- **Stores** have floor space tracked in sqft (`stores.total_area_sqft`, `stores.per_sqr_feet_rent`)
- **Assets** are physical fixtures (gondolas, wall panels, shelves, etc.) assigned to one store and one or more brands
- **Brands** pay rent for the floor/wall space their fixtures occupy in each store
- **Admin** generates bills per billing period, issues them to brands, and manages disputes
- **Brand representatives** are users with `users.represented_brand_id` set — they log in and see only their brand's data

---

## 2. Two-Panel Architecture Overview

The system has two completely separate user-facing panels sharing the same Laravel auth (Jetstream / Sanctum).

```
Same login page (/login)
        │
        ├─── Admin users (no represented_brand_id)
        │    Middleware: auth:sanctum + auth.acl
        │    Landing: /dashboard
        │    Full access: all stores, all brands, all billing operations
        │
        └─── Brand representative users (represented_brand_id is set)
             Middleware: auth:sanctum + brand.access (new)
             Landing: /brand-portal/dashboard
             Restricted: only their own brand's bills and disputes
```

### Auth Key — `users.represented_brand_id`

The `User` model already has `represented_brand_id` (FK → brands) and a `representedBrand()` relationship. This is the single flag that determines which panel a user belongs to.

- `represented_brand_id = NULL` → admin/staff user → admin panel
- `represented_brand_id = {brand_id}` → brand rep user → brand portal only

### New Middleware: `EnsureUserRepresentsBrand`

```php
// app/Http/Middleware/EnsureUserRepresentsBrand.php
public function handle(Request $request, Closure $next): Response
{
    if (!auth()->check() || !auth()->user()->represented_brand_id) {
        abort(403, 'Access restricted to brand representatives.');
    }
    return $next($request);
}
```

Register as `brand.access` in `bootstrap/app.php`.

### Route Group Structure

```php
// Admin panel — existing group, unchanged
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'resource.maker', 'auth.acl'])
    ->group(function () {
        // all existing routes
        // all new billing admin routes
    });

// Brand portal — new group
Route::prefix('brand-portal')
    ->name('brand.')
    ->middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'brand.access'])
    ->group(function () {
        // all brand portal routes
    });
```

---

## 3. Terminology

| Project Term | Industry Term Used Here | DB / Enum Value |
|---|---|---|
| Ground asset (`is_ground_type_assets=1`) | **Floor Fixture** | `payment_type = 'floor'` |
| Non-ground asset (`is_ground_type_assets=0`) | **Overhead Fixture** (wall/ceiling) | `payment_type = 'overhead'` |
| BillingRate model | **Asset Rate Card** | model `AssetRateCard`, table `asset_rate_cards` |
| Per sq ft mode | **Space-Based Billing** | `billing_mode = 'space_based'` |
| Asset type price mode | **Rate Card Billing** | `billing_mode = 'rate_card'` |
| Remaining space share charge | **Space Allocation Charge** | `payment_type = 'space_alloc'` |
| Ground fixed-type billed | **Fixed-Rate Floor Fixture** | `payment_type = 'floor_fixed'` |
| Rent gap from fixed assets | **Fixed-Rate Offset** | folded into `space_alloc` line item |
| Brand rep user | **Brand Portal User** | `users.represented_brand_id IS NOT NULL` |

---

## 4. Existing Key Columns Used in Billing

| Column | Table | Role in Billing |
|---|---|---|
| `per_sqr_feet_rent` | `stores` | Rate multiplier in space-based billing |
| `total_area_sqft` | `stores` | Store total floor space |
| `is_ground_type_assets` | `asset_types` | 1 = floor fixture, 0 = overhead fixture |
| `fixed_type_billing` | `asset_types` | 1 = flat fee for all stores/brands (not sqft-based) |
| `width`, `depth`, `dimention_unit_name` | `asset_types` | Floor footprint calculation |
| `minimum_fee` | `assets` | Per-asset fixed price for overhead billing |
| `is_common_asset` | `assets` | 1 = shared facility, space/fee pools into redistribution |
| `is_asset_assigned_currently` | `assign_asset_to_brands` | Active brand assignments only |
| `represented_brand_id` | `users` | Links a user to a brand for brand portal access |

---

## 5. What Exists vs What Changes

| Component | Status | Action Required |
|---|---|---|
| `bill_periods` | ✅ Exists | Modify: add `billing_mode`, expand `period_type` enum |
| `store_brand_bills` | ✅ Exists | Keep — no structural change |
| `bill_line_items` | ✅ Exists | Modify: new payment types, 4 new snapshot columns |
| `bill_disputes` | ✅ Exists | Keep — no change |
| `brand_bill_disputes` | ✅ Exists | Keep — no change |
| `common_space_logs` | ✅ Exists | Major rework: drop old column, add 7 new columns |
| `asset_types.fixed_type_billing` | ✅ Exists in migration | No change needed |
| `store_asset_type_prices` | ❌ Missing | **Create new table + model** (standalone admin CRUD) |
| `asset_rate_cards` | ❌ Missing | **Create new table + model** (auto-snapshot at generation) |
| `brand_space_allocations` | ❌ Missing | **Create new table + model** |
| `BillGenerationService` | ⚠️ Exists | **Full rewrite** with new calculation logic |
| Admin billing controllers | ⚠️ Exist | Extend with rate card methods |
| Brand portal | ❌ Missing | **Create entirely** — middleware, controllers, views |

---

## 6. Asset Billing Paths — Decision Matrix

Every asset falls into exactly one path during bill generation:

| `is_ground_type_assets` | `fixed_type_billing` | `is_common_asset` | `billing_mode` | Line Item Type | Formula |
|---|---|---|---|---|---|
| 1 (floor) | 0 | 0 | space_based | `floor` | `sqft × rate ÷ brands_sharing` |
| 1 (floor) | 1 | 0 | space_based | `floor_fixed` | `flat_fee ÷ brands_sharing` |
| 1 (floor) | any | 0 | rate_card | `floor` | `rate_card_price ÷ brands_sharing` |
| 1 (floor) | any | 1 | any | ❌ No line item | Space goes into redistribution pool |
| 0 (overhead) | 0 | 0 | space_based | `overhead` | `minimum_fee ÷ brands_sharing` |
| 0 (overhead) | 1 | 0 | space_based | `overhead_fixed` | `asset_type.default_price ÷ brands_sharing` |
| 0 (overhead) | any | 0 | rate_card | `overhead` | `rate_card_price ÷ brands_sharing` |
| 0 (overhead) | any | 1 | any | ❌ No line item | Fee goes into redistribution pool |
| N/A | N/A | N/A | space_based only | `space_alloc` | One per brand per store — see §7 |

---

## 7. Space Allocation Formula — Core New Logic

> **Key change from old system**: Old system split common charge **equally** among all brands.
> New system splits by **ratio** based on each brand's occupied non-fixed floor sqft.

```
── STEP 1: Store-level snapshot ───────────────────────────────────────────────

dedicated_floor_sqft = SUM(sqft of all non-common floor assets in store)
                     = non_fixed_floor_sqft + fixed_floor_sqft

remaining_sqft = store.total_area_sqft − dedicated_floor_sqft

NOTE: Common floor assets (is_common_asset=1) are NOT deducted here.
      Their space stays inside remaining_sqft naturally.

── STEP 2: Rent gap from fixed-type floor assets ──────────────────────────────

fixed_billing_total      = SUM(flat fees charged for fixed-type floor assets)
fixed_expected_rent      = fixed_floor_sqft × store.per_sqr_feet_rent
rent_gap                 = fixed_expected_rent − fixed_billing_total
                           (positive when fixed prices < what sqft rate would earn)

overhead_common_fees     = SUM(minimum_fee of overhead assets WHERE is_common_asset=1)

── STEP 3: Redistribution pool ────────────────────────────────────────────────

redistribution_pool = (remaining_sqft × rate_per_sqft)
                    + rent_gap
                    + overhead_common_fees

── STEP 4: Per-brand ratio ────────────────────────────────────────────────────

total_brand_floor_sqft = SUM(non-fixed floor sqft of ALL brands in this store)
                         — denominator for ratios

For each brand B in this store:
  brand_floor_sqft    = SUM(non-fixed floor sqft of assets assigned to brand B here)
  ratio               = brand_floor_sqft ÷ total_brand_floor_sqft
  brand_space_charge  = redistribution_pool × ratio

Edge case — if total_brand_floor_sqft = 0:
  Fall back: brand_space_charge = redistribution_pool ÷ brand_count
```

### Worked Example

```
Store: 1000 sqft, rate = 100 tk/sqft, total expected rent = 100,000 tk

Assets:
  Asset A (floor, non-fixed, 140 sqft) → Brand A only
  Asset B (floor, non-fixed, 180 sqft) → Brand B only
  Asset C (floor, non-fixed, 280 sqft) → Brand C only
  Asset D (floor, FIXED, 20 sqft)      → Brand A, flat fee = 500 tk
  Asset E (floor, FIXED, 20 sqft)      → Brand B, flat fee = 500 tk
  Asset F (overhead, COMMON)           → minimum_fee = 2000 tk

Step 1: dedicated = 140+180+280+20+20 = 640 | remaining = 1000−640 = 360
Step 2: fixed_billing = 1000 | fixed_expected = 40×100 = 4000 | gap = 3000
        overhead_common = 2000
Step 3: pool = (360×100) + 3000 + 2000 = 41,000 tk
Step 4: total non-fixed = 140+180+280 = 600
        Brand A: 140/600 = 23.33% → 41000×0.2333 = 9,567 tk
        Brand B: 180/600 = 30.00% → 41000×0.3000 = 12,300 tk
        Brand C: 280/600 = 46.67% → 41000×0.4667 = 19,133 tk

Final bills:
  Brand A: floor(14000) + floor_fixed(500) + space_alloc(9567)  = 24,067 tk
  Brand B: floor(18000) + floor_fixed(500) + space_alloc(12300) = 30,800 tk
  Brand C: floor(28000) + space_alloc(19133)                    = 47,133 tk
  Total collected: 102,000 tk (rounding; last brand absorbs remainder in implementation)
```

---

## 8. Complete Table Designs

---

### TABLE 1: Modify `bill_periods`

**New column:**
```sql
billing_mode ENUM('space_based', 'rate_card') DEFAULT 'space_based'
-- space_based: per sqft × store rate + fixed-type asset and remaining space logic
-- rate_card:   all assets billed from asset_rate_cards prices only
```

**Expand existing enum:**
```sql
period_type ENUM('monthly', 'weekly', 'biweekly', 'quarterly', 'annual', 'custom')
-- Added: weekly, biweekly (15-day cycle), annual
```

**Lock rule**: Once `status >= 'generated'`, `billing_mode` cannot be changed.

---

### TABLE 2a: New — `store_asset_type_prices` (admin-managed, standalone)

```
store_asset_type_prices
├── id                bigint PK
├── store_id          FK → stores          Store this price applies to
├── asset_type_id     FK → asset_types     Asset type this price covers
├── rate_amount       decimal(12,2)        Admin-set price. Applies to ALL billing
│                                          periods until the admin changes it.
├── note              text nullable        Admin explanation
├── created_by        FK → users           Audit trail
├── timestamps
└── UNIQUE KEY (store_id, asset_type_id)
```

**No period dependency.** Admin sets prices once and updates them whenever rates change.
Past periods are unaffected because prices are snapshotted at generation time (see TABLE 2b).

---

### TABLE 2b: `asset_rate_cards` (auto-created snapshot, read-only)

```
asset_rate_cards
├── id                bigint PK
├── bill_period_id    FK → bill_periods    Period this snapshot belongs to
├── store_id          FK → stores          Store
├── asset_type_id     FK → asset_types     Asset type
├── source_price_id   FK → store_asset_type_prices (nullable, nullOnDelete)
│                                          Traces back to the source price row.
│                                          Null if the source price was later deleted.
├── rate_amount       decimal(12,2)        Price AT THE MOMENT of bill generation.
│                     In rate_card mode  → this IS the bill amount per asset
│                     In space_based mode→ used for fixed_type_billing=1 flat-fee assets
├── note              text nullable        Copied from source price at snapshot time
├── created_by        FK → users           The user who triggered bill generation
├── timestamps
└── UNIQUE KEY (bill_period_id, store_id, asset_type_id)
```

**Auto-created**: `BillGenerationService::generateForPeriod()` calls
`AssetRateCardService::snapshotPricesForPeriod()` at the START of every generation run.
This copies all current `store_asset_type_prices` into `asset_rate_cards` for that period.
**Never admin-editable.** No admin upsert/destroy routes exist for this table.

---

### TABLE 3: Modify `common_space_logs`

```
common_space_logs
│
│  ── Existing columns (keep) ─────────────────────────────────────────────
├── bill_period_id           FK → bill_periods
├── store_id                 FK → stores
├── total_store_sqft         decimal(12,2)   stores.total_area_sqft snapshot
├── dedicated_ground_sqft    decimal(12,2)   = non_fixed_floor_sqft + fixed_floor_sqft
├── common_ground_asset_sqft decimal(12,2)   sqft of is_common_asset=1 floor assets
│                                            (inside remaining_sqft, NOT deducted)
├── remaining_sqft           decimal(12,2)   total_store_sqft − dedicated_ground_sqft
├── rate_per_sqft            decimal(10,4)   stores.per_sqr_feet_rent snapshot
├── brand_count              smallint        Active brands in store at billing time
├── calculated_at            timestamp
├── timestamps
│
│  ── DROP this column (replaced by brand_space_allocations) ───────────────
│  common_charge_per_brand    → DROP
│  common_static_fees_total   → RENAME to overhead_common_fees
│
│  ── New columns ──────────────────────────────────────────────────────────
├── billing_mode             varchar(20)     Snapshot: 'space_based' or 'rate_card'
├── non_fixed_floor_sqft     decimal(12,2)   SUM sqft of fixed_type_billing=0,
│                                            non-common floor assets.
│                                            Billed directly at sqft×rate to brands.
├── fixed_floor_sqft         decimal(12,2)   SUM sqft of fixed_type_billing=1,
│                                            non-common floor assets.
│                                            Space deducted but billed at flat fee.
├── fixed_billing_total      decimal(12,2)   Actual flat fees charged for fixed floor assets.
├── fixed_expected_rent      decimal(12,2)   fixed_floor_sqft × rate_per_sqft
│                                            (what they would have earned at sqft rate)
├── rent_gap                 decimal(12,2)   fixed_expected_rent − fixed_billing_total
│                                            Redistributed among brands.
├── overhead_common_fees     decimal(12,2)   SUM of minimum_fee of overhead assets
│                                            WHERE is_common_asset=1
│                                            Goes into redistribution pool.
├── total_brand_floor_sqft   decimal(12,2)   SUM of non-fixed floor sqft across ALL brands.
│                                            Denominator for ratio calculations.
│                                            If = 0, fall back to equal distribution.
└── redistribution_pool      decimal(12,2)   (remaining_sqft × rate_per_sqft)
                                             + rent_gap + overhead_common_fees
                                             Split among brands by ratio.
```

---

### TABLE 4: New — `brand_space_allocations`

Per-brand audit trail for the redistribution pool split.

```
brand_space_allocations
├── id                   bigint PK
├── common_space_log_id  FK → common_space_logs    Parent store-level log
├── bill_period_id       FK → bill_periods          Denormalized for queries
├── store_id             FK → stores                Denormalized for queries
├── brand_id             FK → brands                Which brand
├── brand_floor_sqft     decimal(12,2)   SUM of non-fixed floor sqft assigned to this
│                                        brand in this store. Ratio numerator.
│                                        Fixed-type floor assets are excluded.
├── ratio_numerator      decimal(10,4)   brand_floor_sqft ÷ total_brand_floor_sqft
│                                        (e.g., 0.2333 = 23.33%)
├── remaining_sqft_share decimal(12,2)   remaining_sqft × ratio_numerator
├── rent_gap_share       decimal(12,2)   rent_gap × ratio_numerator
├── overhead_fee_share   decimal(12,2)   overhead_common_fees × ratio_numerator
├── total_space_charge   decimal(12,2)   Amount placed into the space_alloc line item.
│                                        = (remaining_sqft_share × rate_per_sqft)
│                                          + rent_gap_share + overhead_fee_share
├── timestamps
└── UNIQUE KEY (common_space_log_id, brand_id)
```

**Immutability**: Never edited after creation. If period regenerated, old records deleted and recreated.

---

### TABLE 5: Modify `bill_line_items`

**Expand `payment_type` enum:**
```
OLD: ENUM('ground', 'static', 'common')
NEW: ENUM('floor', 'floor_fixed', 'overhead', 'overhead_fixed', 'space_alloc')
```

| Type | Trigger | Formula | `asset_id` |
|---|---|---|---|
| `floor` | Floor, not fixed, not common | `sqft × rate ÷ brands_count` | set |
| `floor_fixed` | Floor, fixed_type_billing=1, not common | `flat_fee ÷ brands_count` | set |
| `overhead` | Overhead, not fixed, not common | `min_fee ÷ brands_count` or `rate_card ÷ brands_count` | set |
| `overhead_fixed` | Overhead, fixed_type_billing=1 | `default_price ÷ brands_count` | set |
| `space_alloc` | One per brand per store per period | `remaining_share×rate + gap_share + overhead_share` | NULL |

**Add 4 new columns:**
```
├── billing_mode_snapshot  VARCHAR(20)    'space_based' or 'rate_card' at generation time
├── is_fixed_type_snapshot TINYINT(1)     asset_type.fixed_type_billing snapshot
├── space_alloc_sqft       DECIMAL(10,4)  For space_alloc: brand's remaining_sqft_share
│                                         Displayed on invoice: "Your space: X sqft at Y rate"
└── rent_gap_share         DECIMAL(12,2)  For space_alloc: brand's rent gap portion
                                          Displayed on invoice: "Fixed-asset adjustment: Z tk"
```

**Column mapping by type:**

| Column | `floor` | `floor_fixed` | `overhead` | `space_alloc` |
|---|---|---|---|---|
| `asset_sqft` | footprint sqft | footprint sqft | 0 | 0 |
| `rate_per_sqft` | store rate | 0 | 0 | store rate |
| `unit_price` | 0 | flat fee | min_fee / rate_card | overhead_fee_share |
| `assigned_brands_count` | snapshot | snapshot | snapshot | 1 |
| `full_calculated_amount` | sqft×rate | flat fee total | fee total | total_space_charge |
| `calculated_amount` | full ÷ brands | full ÷ brands | full ÷ brands | = full |
| `space_alloc_sqft` | 0 | 0 | 0 | remaining_sqft_share |
| `rent_gap_share` | 0 | 0 | 0 | brand's gap portion |

---

### Tables 6, 7, 8 — Unchanged

```
store_brand_bills      — no structural change
bill_disputes          — no change
brand_bill_disputes    — no change
```

`store_brand_bills` column remapping (no schema change, logic change only):
- `ground_amount` → sum of `floor` + `floor_fixed` line items
- `static_amount` → sum of `overhead` + `overhead_fixed` line items
- `common_amount` → sum of `space_alloc` line items

---

## 9. Lock / Immutability Rules

| Trigger | What Gets Locked | Enforcement |
|---|---|---|
| Bill generation starts | `asset_rate_cards` auto-created from `store_asset_type_prices` | `AssetRateCardService::snapshotPricesForPeriod()` — never admin-editable |
| `bill_periods.status >= 'generated'` | `billing_mode` on the period itself | Service guard before generation |
| `store_brand_bills.bill_status >= 'issued'` | Bill + all its line items | Controller guard + model observer |
| `bill_periods.status = 'finalized'` | Entire period — no regeneration | Service guard |
| `brand_space_allocations` | Never editable after creation | No update/delete routes exist |
| `common_space_logs` | Never editable after creation | No update/delete routes exist |

---

## 10. Admin Panel — All Pages

All admin pages live under the existing auth middleware group. These are the billing-specific pages.

---

### Admin Page 1: Billing Periods Index
**Route**: `GET /billing/periods` → `billing.periods.index`

Displays:
- Table of all billing periods: Name, Period Type, Date Range, Billing Mode, Status, Brands Billed, Total Amount
- Status badges: open / generating / generated / finalized
- Actions per row: View, Generate Bills (if open), Finalize (if generated)
- "Create New Period" button

---

### Admin Page 2: Create Billing Period
**Route**: `GET /billing/periods/create` → `billing.periods.create`
**POST**: `POST /billing/periods` → `billing.periods.store`

Form fields:
- **Name** (text) — e.g., "June 2026"
- **Period Type** (select) — Monthly / Weekly / Biweekly (15 days) / Quarterly / Annual / Custom
- **Period Start** (date picker)
- **Period End** (date picker) — auto-filled based on type, editable for custom
- **Billing Mode** (radio) — Space-Based Billing (default) / Rate Card Billing
- Helper text: "Space-Based uses sqft × store rate. Rate Card uses prices you set per store."

---

### Admin Page 3: Period Detail
**Route**: `GET /billing/periods/{period}` → `billing.periods.show`

Displays:
- Period info header (name, dates, mode, status)
- **If billing_mode = rate_card AND status = open**: Rate Card Setup section
  - List of stores with a "Set Rates" button each → opens Rate Card page
  - Stores with rates set show a green tick; stores without show warning
- Table of all stores with their billing summary:
  - Store name, brands billed, total amount, status
  - "View Store Bills" link per row
- Action buttons:
  - "Generate Bills" (only if status = open) — triggers generation
  - "Issue All Bills" (only if status = generated) — issues all draft bills
  - "Finalize Period" (only if all bills finalized) — locks the period

---

### Admin Page 4: Rate Card Management (per store)
**Route**: `GET /billing/periods/{period}/stores/{store}/rate-cards` → `billing.rate-cards.index`
**POST**: `POST /billing/periods/{period}/stores/{store}/rate-cards` → `billing.rate-cards.upsert`
**DELETE**: `DELETE /billing/rate-cards/{rateCard}` → `billing.rate-cards.destroy`

Displays:
- Store name + period name in header
- Table with one row per active asset type:
  - Asset Type Name | Current Rate (editable input) | Note (editable) | Saved indicator
- "Save All Rates" button — bulk upserts all asset type rates for this store + period
- Lock indicator shown if period is already generated (inputs become read-only)

---

### Admin Page 5: Store Bills List (for a store within a period)
**Route**: `GET /billing/periods/{period}/store/{store}/bills` (new, add to existing)

Displays:
- Store info + period info
- Table: Brand Name, Floor Amount, Overhead Amount, Space Alloc Amount, Subtotal, Adjustment, Final Amount, Status
- "Issue All" and "Finalize All" bulk action buttons
- Each row links to individual bill detail

---

### Admin Page 6: Bill Detail
**Route**: `GET /billing/bills/{bill}` → `billing.bills.show`

Displays:
- Bill header: Store, Brand, Period, Status, Amounts
- **Line Items table**:
  - Type badge (Floor / Fixed Floor / Overhead / Space Alloc)
  - Asset name (or "Space Allocation" for space_alloc)
  - Full cost | Brands sharing | Brand's share | Override | Final
  - Override button per line item → inline form for `override_amount` + note
- **Space Allocation breakdown** (expandable):
  - Shows the `brand_space_allocations` record: floor sqft, ratio%, pool amount, share
- **Bill-level adjustment**: input for `adjustment_amount` + admin note
- **Dispute history** — shows any disputes for this bill with resolution
- Action buttons: Issue / Finalize / Mark Paid (context-sensitive)

---

### Admin Page 7: Disputes Index
**Route**: `GET /billing/disputes` → `billing.disputes.index`

Displays:
- All open disputes (default filter: pending)
- Filter by: status, period, brand, store
- Columns: Period, Store, Brand, Original Amount, Requested Amount, Status, Submitted Date
- "Review" button per row

---

### Admin Page 8: Dispute Review
**Route**: `GET /billing/disputes/{dispute}` → `billing.disputes.show`

Displays:
- Which bill the dispute is about (store, brand, period, original amount)
- Brand's requested amount and reason
- Admin response textarea
- Approved amount input
- Three action buttons: Approve (full requested amount) / Partially Approve (custom amount) / Reject

---

### Admin Page 9: Brand-Level Disputes
**Route**: `GET /billing/brand-disputes` (new admin index for `brand_bill_disputes`)

Same structure as Page 7 but for brand-level period-wide disputes.

---

### Admin Page 10: Invoice / PDF View
**Route**: `GET /billing/bills/{bill}/invoice` → `billing.bills.invoice`

Printable/downloadable invoice showing:
- Store details, Brand details, Period details
- Asset-by-asset line items with full breakdown
- Space allocation explanation box
- Totals: subtotal, adjustment, final amount
- Status and payment date if paid

---

## 11. Brand Portal — All Pages

All brand portal pages live under the new `brand.access` middleware group.
Brand users see ONLY their own brand's data. `represented_brand_id` is the scope filter on every query.

---

### Brand Page 1: Dashboard
**Route**: `GET /brand-portal/dashboard` → `brand.dashboard`

**Controller**: `BrandPortal\DashboardController@index`

Displays:
- Brand logo, name, and representative name
- Summary cards:
  - Total Issued Bills (count)
  - Total Amount Due (sum of final_amount where status = issued/disputed/adjusted)
  - Total Disputed (count where status = disputed)
  - Total Paid (sum where status = paid)
- Recent billing periods table: Period Name | Date Range | Stores | Total Amount | Status | Link
- Pending disputes list (quick view, last 5)

---

### Brand Page 2: Bills List (by Period)
**Route**: `GET /brand-portal/bills` → `brand.bills.index`

**Controller**: `BrandPortal\BrandBillController@index`

Displays:
- All billing periods where this brand has at least one issued (or beyond) bill
- Filter by: period type, status, date range
- Columns: Period Name | Dates | Stores Count | Total Amount | Status | Action
- "View" button per period → goes to Period Bill Overview
- Drafts are **never shown** to brand users

---

### Brand Page 3: Period Bill Overview
**Route**: `GET /brand-portal/bills/period/{period}` → `brand.bills.period`

**Controller**: `BrandPortal\BrandBillController@periodOverview`

Displays:
- Period name, dates, billing mode (displayed as "Space-Based" or "Fixed Rate")
- One card per store where this brand has a bill:
  - Store name | Floor Charges | Overhead Charges | Space Allocation | Grand Total | Status
  - "View Details" button → store bill detail page
- Period-wide total at the bottom
- "Raise Period-Wide Dispute" button (if at least one bill is issued and no pending period dispute)

---

### Brand Page 4: Store Bill Detail
**Route**: `GET /brand-portal/bills/{storeBrandBill}` → `brand.bills.show`

**Controller**: `BrandPortal\BrandBillController@show`

Displays:
- Store name, period, billing mode
- **Line Items table**:
  - Asset Name | Asset Type | Charge Type | Full Asset Cost | Brands Sharing | Your Share | Final
  - Space Alloc row shown last with an info icon that expands the breakdown
- **Space Allocation Detail** (expandable info box):
  - "Your non-fixed floor space: X sqft"
  - "Total brand floor space in this store: Y sqft"
  - "Your ratio: Z%"
  - "Redistribution pool: P tk → Your share: Q tk"
  - (Sourced from `brand_space_allocations`)
- **Bill Summary**: Floor Total | Overhead Total | Space Alloc Total | Subtotal | Adjustment | **Final Amount**
- **Status timeline**: Issued on → Disputed on (if applicable) → Finalized on
- **"Raise Dispute" button** — shown only when:
  - `bill_status = 'issued'` AND no pending dispute on this bill
- **"Download Invoice" button** — PDF download

---

### Brand Page 5: Raise Dispute (store bill)
**Route**: `GET /brand-portal/bills/{storeBrandBill}/dispute/create` → `brand.disputes.create`
**POST**: `POST /brand-portal/bills/{storeBrandBill}/dispute` → `brand.disputes.store`

**Controller**: `BrandPortal\BrandDisputeController@create` / `@store`

Form fields:
- Original Amount (read-only, pre-filled from bill.final_amount)
- Requested Amount (required, must be less than original, must be > 0)
- Reason (required textarea — must explain the dispute)
- Submit button

Guards:
- `bill_status` must be `issued` — otherwise 403
- No pending dispute may already exist for this bill — otherwise redirect with message
- Creates `bill_disputes` record
- Changes `bill_status` to `disputed`

---

### Brand Page 6: Disputes List
**Route**: `GET /brand-portal/disputes` → `brand.disputes.index`

**Controller**: `BrandPortal\BrandDisputeController@index`

Displays:
- All disputes submitted by this brand across all stores and all periods
- Filter by status: pending / approved / partially approved / rejected / all
- Columns: Period | Store | Original Amount | Requested Amount | Status | Submitted Date | Action
- "View" button per row

---

### Brand Page 7: Dispute Detail
**Route**: `GET /brand-portal/disputes/{dispute}` → `brand.disputes.show`

**Controller**: `BrandPortal\BrandDisputeController@show`

Displays:
- Which bill this dispute is for (store, period, original amount)
- Requested amount and reason submitted by brand
- **Admin response section** (visible after review):
  - Admin response text
  - Approved amount
  - Decision status badge
- Timeline: Submitted → Reviewed → Outcome
- Link to the parent bill

---

### Brand Page 8: Raise Period-Wide Dispute
**Route**: `GET /brand-portal/bills/period/{period}/dispute/create` → `brand.period-disputes.create`
**POST**: `POST /brand-portal/bills/period/{period}/dispute` → `brand.period-disputes.store`

**Controller**: `BrandPortal\BrandDisputeController@createPeriodDispute` / `@storePeriodDispute`

Used when a brand wants to dispute their total bill across ALL stores in a period (uses `brand_bill_disputes` table).

Form fields:
- Total amount across all stores (read-only)
- Requested total amount
- Reason

---

## 12. Application Architecture

### New Models

```
AssetRateCard
├── belongsTo(BillPeriod)
├── belongsTo(Store)
├── belongsTo(AssetType)
├── belongsTo(User, 'created_by')
└── (guard: prevent save when period is locked)

BrandSpaceAllocation
├── belongsTo(CommonSpaceLog)
├── belongsTo(BillPeriod)
├── belongsTo(Store)
└── belongsTo(Brand)
```

### Updated Existing Models

```
BillPeriod
├── ADD: hasMany(AssetRateCard)
├── ADD: isSpaceBased(): bool  → billing_mode === 'space_based'
├── ADD: isRateCard(): bool    → billing_mode === 'rate_card'
└── ADD: isLocked(): bool      → status in ['generated', 'finalized']

CommonSpaceLog
├── ADD: hasMany(BrandSpaceAllocation)
└── ADD: new fillable/cast columns

BillLineItem
├── ADD: new fillable/cast columns
├── ADD: isSpaceAlloc(): bool
└── ADD: isFloor(): bool

StoreBrandBill, BillDispute, BrandBillDispute — keep existing
```

### New Controllers

```
Admin panel (inside existing Backend namespace):
  BillingController            — extend with rateCardIndex, rateCardUpsert, rateCardDestroy

Brand portal (new namespace App\Http\Controllers\BrandPortal):
  DashboardController          — dashboard
  BrandBillController          — index, periodOverview, show
  BrandDisputeController       — index, show, create, store, createPeriodDispute, storePeriodDispute
```

### New Services

```
BillGenerationService          — full rewrite (see §13 for logic)
AssetRateCardService           — canModify(), upsertRateCard(), bulkUpsert(), destroyRateCard()
```

### New Middleware

```
EnsureUserRepresentsBrand      — registered as 'brand.access'
                                 Checks: auth()->user()->represented_brand_id !== null
```

### Complete Routes

**Admin billing routes** (inside billing prefix group):
```php
// Rate Card Snapshot — read-only, auto-created at generation time
Route::get('/periods/{period}/stores/{store}/rate-cards', [BillingController::class, 'rateCardIndex'])->name('rate-cards.index');

// Store Asset Type Prices — standalone admin CRUD, no period dependency
Route::get('/store-prices',                    [StoreAssetTypePriceController::class, 'index'])->name('store-prices.index');
Route::get('/store-prices/{store}',            [StoreAssetTypePriceController::class, 'storeIndex'])->name('store-prices.store');
Route::post('/store-prices/{store}',           [StoreAssetTypePriceController::class, 'upsert'])->name('store-prices.upsert');
Route::delete('/store-prices/{store}/{price}', [StoreAssetTypePriceController::class, 'destroy'])->name('store-prices.destroy');
```

**Brand portal routes** (new group):
```php
Route::prefix('brand-portal')->name('brand.')->middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'brand.access'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Bills
    Route::get('/bills',                                         [BrandBillController::class, 'index'])->name('bills.index');
    Route::get('/bills/period/{period}',                         [BrandBillController::class, 'periodOverview'])->name('bills.period');
    Route::get('/bills/{storeBrandBill}',                        [BrandBillController::class, 'show'])->name('bills.show');
    Route::get('/bills/{storeBrandBill}/invoice',                [BrandBillController::class, 'invoice'])->name('bills.invoice');

    // Store-level disputes
    Route::get('/bills/{storeBrandBill}/dispute/create',         [BrandDisputeController::class, 'create'])->name('disputes.create');
    Route::post('/bills/{storeBrandBill}/dispute',               [BrandDisputeController::class, 'store'])->name('disputes.store');
    Route::get('/disputes',                                      [BrandDisputeController::class, 'index'])->name('disputes.index');
    Route::get('/disputes/{dispute}',                            [BrandDisputeController::class, 'show'])->name('disputes.show');

    // Period-wide disputes
    Route::get('/bills/period/{period}/dispute/create',          [BrandDisputeController::class, 'createPeriodDispute'])->name('period-disputes.create');
    Route::post('/bills/period/{period}/dispute',                [BrandDisputeController::class, 'storePeriodDispute'])->name('period-disputes.store');
});
```

### View Structure

```
resources/views/
│
├── backend/billing/                     (existing — extend)
│   ├── periods/
│   │   ├── index.blade.php              update: add billing_mode column
│   │   ├── create.blade.php             update: add billing_mode radio + new period types
│   │   └── show.blade.php               update: add rate card section for rate_card mode
│   ├── rate-cards/
│   │   └── index.blade.php              NEW — asset type rate input table per store
│   ├── bills/
│   │   ├── show.blade.php               update: new payment type labels + space alloc detail
│   │   └── invoice.blade.php            update: new labels
│   └── disputes/                        (existing — no change)
│
└── brand-portal/                        NEW — entire folder
    ├── layouts/
    │   └── app.blade.php                NEW — brand portal layout (simpler sidebar)
    ├── dashboard.blade.php              NEW
    ├── bills/
    │   ├── index.blade.php              NEW — bills by period list
    │   ├── period.blade.php             NEW — per-period store cards
    │   ├── show.blade.php               NEW — store bill detail + line items
    │   └── invoice.blade.php            NEW — printable invoice
    └── disputes/
        ├── index.blade.php              NEW — disputes list
        ├── show.blade.php               NEW — dispute detail
        ├── create.blade.php             NEW — raise store dispute form
        └── create-period.blade.php      NEW — raise period-wide dispute form
```

---

## 13. Bill Generation Flow

```
Admin: Create BillPeriod
  ├── Choose period_type (monthly/weekly/biweekly/quarterly/annual/custom)
  ├── Choose billing_mode (space_based ← default | rate_card)
  └── IF rate_card:
        Admin sets asset_rate_cards for each store before generating
        /billing/periods/{period}/stores/{store}/rate-cards
        (Locked once generation starts)

Admin: Trigger "Generate Bills"
  → period.status = 'generating'
        ↓
BillGenerationService::generateForPeriod(BillPeriod $period)
        ↓
  For each active store:

    1. computeSpaceDistribution(store, period)
       → Upserts CommonSpaceLog (all new snapshot columns)
       → For each active brand in store:
           Upserts BrandSpaceAllocation (ratio, share amounts)

    2. For each active brand in this store:

       a. Get or create StoreBrandBill (upsert on unique key period+store+brand)
       b. Delete existing line items (safe to regenerate)

       c. IF space_based mode:
            For each non-common FLOOR asset assigned to this brand in this store:
              IF fixed_type_billing = 1 → createFloorFixedLineItem()
              ELSE                      → createFloorLineItem()
            For each non-common OVERHEAD asset assigned to this brand in this store:
              IF fixed_type_billing = 1 → createOverheadFixedLineItem()
              ELSE                      → createOverheadLineItem()
            createSpaceAllocLineItem()  ← from BrandSpaceAllocation record

          IF rate_card mode:
            For each non-common asset (floor + overhead) assigned to brand in store:
              createRateCardLineItem()  ← uses asset_rate_cards.rate_amount
            (NO space_alloc line item in rate_card mode)

       d. bill.recalculateTotals()
          → ground_amount  = sum of floor + floor_fixed line items
          → static_amount  = sum of overhead + overhead_fixed line items
          → common_amount  = sum of space_alloc line items
          → subtotal       = ground + static + common
          → final_amount   = subtotal + adjustment_amount

  → period.status = 'generated'
  → period.generated_at = now()

        ↓
Admin reviews → Issues bills → bill_status: 'issued' [LOCKED to brand users]
        ↓
Brand sees bill in brand portal → optionally raises dispute
        ↓
Admin reviews dispute → Approve / Partially Approve / Reject
        ↓
bill_status: 'finalized' → 'paid'
```

### BillGenerationService Key Methods

```
generateForPeriod(BillPeriod $period): void
computeSpaceDistribution(Store $store, BillPeriod $period): CommonSpaceLog
getOrCreateBill(Store $store, Brand $brand, BillPeriod $period): StoreBrandBill
createFloorLineItem(StoreBrandBill $bill, Asset $asset, Store $store): BillLineItem
createFloorFixedLineItem(StoreBrandBill $bill, Asset $asset, Store $store, BillPeriod $period): BillLineItem
createOverheadLineItem(StoreBrandBill $bill, Asset $asset, BillPeriod $period, Store $store): BillLineItem
createOverheadFixedLineItem(StoreBrandBill $bill, Asset $asset): BillLineItem
createSpaceAllocLineItem(StoreBrandBill $bill, Brand $brand, Store $store, CommonSpaceLog $log): BillLineItem
createRateCardLineItem(StoreBrandBill $bill, Asset $asset, BillPeriod $period, Store $store): BillLineItem
getRateForAssetType(BillPeriod $period, Store $store, AssetType $type): float
getAssetFootprintSqft(Asset $asset): float
convertToSqft(float $value, string $unit): float
```

### AssetRateCardService Key Methods

```
canModify(BillPeriod $period): bool
    → false if period.status in ['generating', 'generated', 'finalized']

upsertRateCard(BillPeriod, Store, AssetType, float $amount, ?string $note): AssetRateCard
bulkUpsert(BillPeriod, Store, array $rateData): Collection
destroyRateCard(AssetRateCard): void
```

---

## 14. Key Design Decisions

| Decision | Why |
|---|---|
| Ratio-based space allocation (not equal split) | Brands with more floor assets occupy proportionally more store space. Equal split unfairly charges small-footprint brands the same as large ones. |
| `billing_mode` per period, not per store | Mixing modes within one period creates reporting inconsistency. One period = one mode keeps reconciliation clean. |
| `rent_gap` redistributed among brands | When fixed-price assets charge less than sqft rate, the gap doesn't disappear — it's redistributed. Store always collects expected total rent. |
| `brand_space_allocations` as separate table | When brands dispute space charges, an immutable audit record is required showing exact ratio and calculation inputs. |
| `asset_rate_cards` locked after generation | Standard financial control. Rate changes after bill generation would create unexplained historical variances. |
| `is_fixed_type_snapshot` in line items | If `fixed_type_billing` flag changes later on an asset type, old bills must still reflect what was true at billing time. |
| Overhead common assets → redistribution pool | Shared overhead assets benefit all brands equally, so their fees are distributed by floor ratio alongside remaining space. |
| `space_alloc` line item only in `space_based` mode | In `rate_card` mode, admin pre-priced everything explicitly. No floor space gap concept applies. |
| Common floor assets NOT deducted from store total | `is_common_asset=1` floor assets count as remaining space by design — they serve all brands so their footprint stays in the redistribution pool. |
| Snapshots in line items (sqft, rate, brands_count) | All inputs frozen at billing time. Rate changes, dimension changes, and brand assignment changes must not retroactively alter past bills. |
| `full_calculated_amount` + `calculated_amount` stored | Brands see: "This asset costs 1,000 tk total, shared by 2 brands → your share: 500 tk." Full transparency for disputes. |
| Brand portal uses same auth, separate middleware | No need for a second login system. `represented_brand_id` on User already distinguishes brand users from admin users. |
| Brand portal never shows draft bills | Brands only see bills that have been issued. Draft state is internal to admin workflow. |
| Period-wide dispute (`brand_bill_disputes`) separate from per-bill dispute (`bill_disputes`) | Brands may want to negotiate their total multi-store exposure in one conversation rather than store-by-store. |

---

## 15. Implementation Order

Build in this exact sequence to respect FK dependencies and avoid rework.

### Phase 1 — Database

1. Alter `bill_periods` — add `billing_mode` column, expand `period_type` enum
2. Alter `common_space_logs` — drop `common_charge_per_brand`, rename `common_static_fees_total`, add 7 new columns
3. Alter `bill_line_items` — expand `payment_type` enum, add 4 new columns
4. Create `store_asset_type_prices` table
5. Create `asset_rate_cards` table (add `source_price_id` FK)
6. Create `brand_space_allocations` table

### Phase 2 — Models

7. New model `StoreAssetTypePrice`
8. New model `AssetRateCard` (read-only snapshot, `source_price_id` relationship)
9. New model `BrandSpaceAllocation` (immutable)
10. Update `BillPeriod` — new fillable, new helper methods
11. Update `CommonSpaceLog` — new fillable/casts, add `hasMany(BrandSpaceAllocation)`
12. Update `BillLineItem` — new fillable/casts, new helper methods
13. Update `Store` — add `hasMany(StoreAssetTypePrice)`

### Phase 3 — Core Services

14. Rewrite `BillGenerationService` — full calculation engine + snapshot call at generation start
15. New `AssetRateCardService` — `snapshotPricesForPeriod()` + `getSnapshotForStore()`

### Phase 4 — Admin Panel

16. New `StoreAssetTypePriceController` — standalone CRUD (index, storeIndex, upsert, destroy)
17. Update `BillingController` — `rateCardIndex` reads snapshot only (no upsert/destroy)
18. Update routes — add store-prices routes; rate-card route is GET-only snapshot
19. New views: `backend/billing/store-prices/index.blade.php` + `store.blade.php`
20. Update view: `backend/billing/rate-cards/index.blade.php` — read-only snapshot
21. Update view: `backend/billing/periods/create.blade.php`
22. Update view: `backend/billing/periods/show.blade.php`
23. Update view: `backend/billing/bills/show.blade.php`
24. Update view: `backend/billing/bills/invoice.blade.php`

### Phase 5 — Brand Portal

20. New middleware `EnsureUserRepresentsBrand` — register as `brand.access`
21. New controller `BrandPortal\DashboardController`
22. New controller `BrandPortal\BrandBillController`
23. New controller `BrandPortal\BrandDisputeController`
24. Add brand portal route group to `routes/web.php`
25. New layout: `brand-portal/layouts/app.blade.php`
26. New views (8 total): dashboard, bills/index, bills/period, bills/show, bills/invoice, disputes/index, disputes/show, disputes/create, disputes/create-period

### Phase 6 — Testing & Hardening

27. Test bill generation for space_based mode with all asset type combinations
28. Test bill generation for rate_card mode
29. Test space allocation ratio calculation with edge case (total_brand_floor_sqft = 0)
30. Test rounding — last brand absorbs remainder
31. Test lock rules — verify rate cards lock after generation
32. Test brand portal — verify brand only sees their own data
33. Test dispute flow end-to-end (store-level + period-level)

---

## 16. Unit Conversion Reference

Used in `convertToSqft(float $value, string $unit): float`

| Unit | Conversion to sqft |
|---|---|
| `ft` | × 1 |
| `in` | ÷ 144 |
| `cm` | ÷ 929.0304 |
| `m` | × 10.7639 |
| `mm` | ÷ 92903.04 |
| `yd` | × 9 |
| `px` | → 0 (no real-world sqft equivalent) |

Note: Floor footprint uses `width × depth` (not height). `asset_types.dimention_unit_name` holds the unit.
Both width and depth use the same unit on a given asset type.
