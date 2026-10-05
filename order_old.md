# Payment System — Complete Concept Design

## Understanding Your Current Data Model

Before designing, here's what I'll leverage from existing tables:

| Existing Column | Where | Role in Payment |
|---|---|---|
| `per_sqr_feet_rent` | `stores` | Rate multiplier for ground & common charges |
| `total_area_sqft` | `stores` | Base for common space calculation |
| `is_ground_type_assets` | `asset_types` | Determines payment type (ground vs static) |
| `width`, `depth`, `dimention_unit_name` | `asset_types` | Floor footprint calculation for ground assets |
| `minimum_fee` | `assets` | Static asset fixed price (already exists — use it) |
| `is_common_asset` | `assets` | If 1: asset space pools into common — no dedicated ground charge generated |
| `is_asset_assigned_currently` | `assign_asset_to_brands` | Which brand assignments are active |

---

## Three Payment Types — Logic Breakdown

### 1. Ground Payment
- **Trigger**: `asset_types.is_ground_type_assets = 1` **AND** `assets.is_common_asset = 0`
- **Formula**: `((width × depth → converted to sqft) × stores.per_sqr_feet_rent) / active_brands_on_asset`
- **Example**: Asset is 5ft × 4ft = 20 sqft. Store rate = 50/sqft. Full cost = 1,000. If 2 brands share this asset → each brand pays 500.
- Assets with `is_common_asset = 1` are **excluded** here — their space pools into common instead.

### 2. Static Payment
- **Trigger**: `asset_types.is_ground_type_assets = 0` **AND** `assets.is_common_asset = 0`
- **Formula**: `assets.minimum_fee / active_brands_on_asset`
- **Example**: minimum_fee = 100 tk. Asset assigned to 2 brands → each brand pays 50 tk.
- Assets with `is_common_asset = 1` are **excluded** here — their fee pools into common instead.
- The count of active brands on the asset is snapshotted at billing time — removing a brand later does NOT change past bills.

### 3. Common/Other Payment
- **Trigger**: Calculated once per store per brand per period — not asset-specific
- Covers two sources of pooled cost:
  - **Ground common assets** (`is_ground_type_assets=1`, `is_common_asset=1`): their floor space stays in remaining pool
  - **Static common assets** (`is_ground_type_assets=0`, `is_common_asset=1`): their `minimum_fee` is added to the common cost pool
- **Formula**:
  ```
  dedicated_ground_sqft    = SUM(sqft of ground assets WHERE is_common_asset = 0)
  remaining_sqft           = store.total_area_sqft - dedicated_ground_sqft
  common_static_fees_total = SUM(minimum_fee of static assets WHERE is_common_asset = 1)

  charge_per_brand = ((remaining_sqft × store.per_sqr_feet_rent) + common_static_fees_total)
                     / count of active brands in store
  ```
- **This is billed as one line item** per brand per store (not per asset)

### Full Decision Matrix

| `is_ground_type_assets` | `is_common_asset` | Payment Type | Who pays |
|---|---|---|---|
| 1 (ground) | 0 | Ground | Assigned brands only, divided by brand count |
| 1 (ground) | 1 | Common | ALL store brands equally |
| 0 (static) | 0 | Static | Assigned brands only, divided by brand count |
| 0 (static) | 1 | Common | ALL store brands equally |

---

## Database Design — 5 New Tables

### Table 1: `bill_periods`
**Purpose**: Defines billing cycles. Admin creates a period (e.g., "May 2026"), then generates all bills for it in one action.

```
bill_periods
├── id                  bigint PK
├── name                varchar(100)       "May 2026", "Q1-2026"
├── period_type         enum('monthly','quarterly','custom')
├── period_start        date               Billing window start
├── period_end          date               Billing window end
├── status              enum('open','generating','generated','finalized')
│                       open = can still be edited
│                       generating = bill generation running
│                       generated = bills exist, not yet sent
│                       finalized = locked, no more changes
├── generated_at        timestamp nullable  When bills were computed
├── finalized_at        timestamp nullable  When period was locked
└── created_by          FK → users
```

---

### Table 2: `store_brand_bills`
**Purpose**: One bill per brand per store per period. This is the invoice header. Admin adjusts here, brands dispute here.

```
store_brand_bills
├── id                  bigint PK
├── bill_period_id      FK → bill_periods
├── store_id            FK → stores
├── brand_id            FK → brands
│
│   ── Computed Amounts (filled during generation) ──
├── ground_amount       decimal(12,2)      Sum of all ground asset charges for this brand in this store
├── static_amount       decimal(12,2)      Sum of all static asset charges
├── common_amount       decimal(12,2)      Common/other space charge
├── subtotal            decimal(12,2)      ground + static + common
│
│   ── Admin Override ──
├── adjustment_amount   decimal(12,2) default 0   +/- amount admin applies (negative = discount)
├── final_amount        decimal(12,2)      subtotal + adjustment_amount
│
│   ── Workflow ──
├── bill_status         enum('draft','issued','disputed','adjusted','finalized','paid')
│                       draft      = generated, not sent to brand yet
│                       issued     = sent/visible to brand
│                       disputed   = brand raised a dispute
│                       adjusted   = admin made adjustment after dispute
│                       finalized  = locked final amount agreed
│                       paid       = payment received
├── dispute_reason      text nullable      Brand's reason for disputing
├── admin_note          text nullable      Admin's comments when adjusting
│
│   ── Timestamps ──
├── issued_at           timestamp nullable
├── finalized_at        timestamp nullable
├── finalized_by        FK → users nullable
├── timestamps
├── soft deletes
└── UNIQUE KEY          (bill_period_id, store_id, brand_id)   Prevent duplicate bills
```

---

### Table 3: `bill_line_items`
**Purpose**: Asset-wise breakdown inside each bill. This is the invoice detail. Critical for transparency — brands can see exactly what each asset costs and how many brands share it.

```
bill_line_items
├── id                  bigint PK
├── store_brand_bill_id FK → store_brand_bills
│
│   ── What is being billed ──
├── asset_id            FK → assets nullable       NULL for common type line item
├── asset_type_id       FK → asset_types
├── payment_type        enum('ground','static','common')
│
│   ── Calculation Inputs (SNAPSHOT at billing time) ──
│   These are stored as snapshots so future rate changes do not alter old bills
├── asset_sqft          decimal(10,4)      Footprint at billing time (width × depth → sqft)
│                                          For static/common: 0 or allocated sqft
├── rate_per_sqft       decimal(10,4)      Store's per_sqr_feet_rent at billing time
│                                          For static: 0
├── unit_price          decimal(12,2)      For static: asset.minimum_fee at billing time
│                                          For ground/common: rate × sqft
├── quantity            decimal(10,4) default 1   For common: this is the allocated sqft per brand
│
│   ── Multi-Brand Cost Sharing (NEW) ──
├── assigned_brands_count  tinyint unsigned default 1
│                          How many brands are actively assigned to this asset at billing time.
│                          SNAPSHOT — changing brand assignments later will not affect this bill.
│                          Example: asset shared by 2 brands → this = 2
├── full_calculated_amount decimal(12,2)
│                          Total cost of the asset before dividing (ground: sqft × rate,
│                          static: minimum_fee). Stored for audit — shows what the asset
│                          costs in full before brand sharing is applied.
│
│   ── Result ──
├── calculated_amount   decimal(12,2)      full_calculated_amount / assigned_brands_count
│                                          This brand's share. System-computed, never changed after generation.
├── override_amount     decimal(12,2) nullable     Admin can override this brand's share for this line item
├── final_amount        decimal(12,2)      override_amount if set, else calculated_amount
├── note                text nullable      Explanation when overridden
└── timestamps
```

---

### Table 4: `bill_disputes`
**Purpose**: Formal dispute/reduction request workflow. Brand (or store manager) requests a bill reduction. Admin reviews and sets final amount.

```
bill_disputes
├── id                  bigint PK
├── store_brand_bill_id FK → store_brand_bills
├── requested_by        FK → users              Who submitted the dispute
├── original_amount     decimal(12,2)           Bill amount at time of dispute
├── requested_amount    decimal(12,2)           What the brand wants to pay
├── reason              text                    Reason for dispute
│
│   ── Admin Review ──
├── status              enum('pending','approved','partially_approved','rejected')
├── admin_response      text nullable           Admin's decision explanation
├── approved_amount     decimal(12,2) nullable  Admin-approved final amount (may differ from requested)
├── reviewed_by         FK → users nullable
├── reviewed_at         timestamp nullable
├── timestamps
└── soft deletes
```

---

### Table 5: `common_space_logs`
**Purpose**: Audit trail showing exactly how the common space charge was calculated for each store each period. Essential for transparency when brands question their common charge.

```
common_space_logs
├── id                          bigint PK
├── bill_period_id              FK → bill_periods
├── store_id                    FK → stores
│
│   ── Calculation Snapshot ──
├── total_store_sqft            decimal(12,2)   stores.total_area_sqft at billing time
│
│   ── Ground space breakdown ──
├── dedicated_ground_sqft       decimal(12,2)   SUM sqft of ground assets WHERE is_common_asset = 0
│                                               Subtracted from store total (dedicated to specific brands)
├── common_ground_asset_sqft    decimal(12,2)   SUM sqft of ground assets WHERE is_common_asset = 1
│                                               NOT subtracted — stays in remaining pool; stored for audit
├── remaining_sqft              decimal(12,2)   total_store_sqft - dedicated_ground_sqft
│                                               (common_ground_asset_sqft is already inside this value)
│
│   ── Static common asset fees ──
├── common_static_fees_total    decimal(12,2)   SUM of minimum_fee of static assets WHERE is_common_asset = 1
│                                               These are not space-based but still shared by all brands
│
│   ── Final calculation ──
├── brand_count                 smallint unsigned  Active brands in this store at billing time
├── rate_per_sqft               decimal(10,4)   stores.per_sqr_feet_rent at billing time
├── common_charge_per_brand     decimal(12,2)   ((remaining_sqft × rate_per_sqft) + common_static_fees_total)
│                                               / brand_count
├── calculated_at               timestamp
└── timestamps
```

---

## Application Architecture

### Services (Core Business Logic)

**`BillGenerationService`** — The engine of the whole system

```
generateForPeriod(BillPeriod $period)
    └── For each store:
        ├── calculateCommonSpaceLog(store, period)  → saves to common_space_logs
        ├── For each active brand in this store:
        │   ├── createStoreBrandBill(store, brand, period)
        │   ├── For each ground asset (is_common_asset = 0) assigned to this brand in this store:
        │   │   └── createGroundLineItem(bill, asset)
        │   │       ├── brand_count = count of active assign_asset_to_brands for this asset
        │   │       ├── full_amount = getAssetFootprintSqft(asset.type) × store.per_sqr_feet_rent
        │   │       └── calculated_amount = full_amount / brand_count
        │   │   NOTE: ground assets WHERE is_common_asset = 1 → SKIPPED, no line item
        │   ├── For each static asset (is_common_asset = 0) assigned to this brand in this store:
        │   │   └── createStaticLineItem(bill, asset)
        │   │       ├── brand_count = count of active assign_asset_to_brands for this asset
        │   │       ├── full_amount = asset.minimum_fee
        │   │       └── calculated_amount = full_amount / brand_count
        │   │   NOTE: static assets WHERE is_common_asset = 1 → SKIPPED, fee goes to common pool
        │   ├── createCommonLineItem(bill, store, commonSpaceLog)
        │   │   └── common_charge_per_brand from commonSpaceLog — already includes both
        │   │       ground common space cost AND static common fees, divided by brand_count
        │   └── updateBillTotals(bill)  → sets ground_amount, static_amount, common_amount, subtotal, final_amount

getAssetFootprintSqft(AssetType $assetType)
    └── Returns width × depth converted to sqft based on dimention_unit_name

convertToSqft(float $value, string $unit): float
    └── Handles: ft (×1), in (÷144), cm (÷929), m (×10.764), mm (÷92900), yd (×9)
```

### Models (5 new)

```
BillPeriod          → hasMany(StoreBrandBill), hasMany(CommonSpaceLog)
StoreBrandBill      → belongsTo(BillPeriod, Store, Brand)
                       hasMany(BillLineItem), hasMany(BillDispute)
BillLineItem        → belongsTo(StoreBrandBill, Asset, AssetType)
BillDispute         → belongsTo(StoreBrandBill)
                       belongsTo(User, 'requested_by'), belongsTo(User, 'reviewed_by')
CommonSpaceLog      → belongsTo(BillPeriod, Store)
```

### Controllers (2 new)

**`BillingController`**
```
index()                → List bill periods (paginated, with status)
createPeriod()         → Form to create new period
storePeriod()          → Save new period
generateBills(period)  → Trigger BillGenerationService (async job for large datasets)
showStoreBills(store)  → All brand bills for a specific store in a period
showBill(bill)         → Bill detail: header + line items + dispute history
adjustBill(bill)       → Admin sets adjustment_amount, updates final_amount
finalizeBill(bill)     → Lock the bill, change status to 'finalized'
issueBill(bill)        → Change status to 'issued' (visible to brand)
downloadInvoice(bill)  → Generate PDF invoice (asset-wise breakdown)
```

**`BillDisputeController`**
```
store(bill)            → Brand submits a dispute (creates bill_disputes record)
index()                → Admin views all pending disputes
show(dispute)          → View dispute detail
approve(dispute)       → Admin approves full requested_amount
partialApprove(dispute)→ Admin approves custom amount
reject(dispute)        → Admin rejects, provides reason
```

### Routes (prefix: `billing`)

```
GET    /billing/periods                    → periods index
POST   /billing/periods                    → create period
GET    /billing/periods/{id}               → period detail (all store bills)
POST   /billing/periods/{id}/generate      → trigger bill generation
GET    /billing/store/{store}/bills        → store's brand bills list
GET    /billing/bills/{bill}               → bill detail + line items
POST   /billing/bills/{bill}/issue         → issue bill to brand
POST   /billing/bills/{bill}/adjust        → admin adjusts bill
POST   /billing/bills/{bill}/finalize      → admin finalizes
GET    /billing/bills/{bill}/invoice       → download PDF
POST   /billing/disputes                   → submit dispute
GET    /billing/disputes                   → admin dispute list
GET    /billing/disputes/{id}              → dispute detail
POST   /billing/disputes/{id}/approve      → approve
POST   /billing/disputes/{id}/partial      → partial approve
POST   /billing/disputes/{id}/reject       → reject
```

---

## Bill Generation Flow (Visual)

```
Admin creates BillPeriod (May 2026, 2026-05-01 to 2026-05-31)
        ↓
Admin triggers "Generate Bills"
        ↓
BillGenerationService loops each Store
        ↓
   ┌── For Store A (900 sqft, 50/sqft rent):
   │   ├── Ground assets: asset1 (5×4 ft = 20 sqft), asset2 (10×3 ft = 30 sqft), ..total = 600 sqft
   │   ├── Remaining sqft = 900 - 600 = 300 sqft
   │   ├── Active brands = 3 → sqft per brand = 100 sqft
   │   ├── Common charge per brand = 100 × 50 = 5,000
   │   │
   │   ├── Brand X's bill in Store A (asset1 shared with Brand Y, asset3 shared with Brand Y & Z):
   │   │   ├── Line: asset1 (ground) → full=20×50=1,000 / 2 brands = 500  [assigned_brands_count=2]
   │   │   ├── Line: asset3 (static) → full=minimum_fee=900 / 3 brands = 300  [assigned_brands_count=3]
   │   │   ├── Line: Common space → 100 sqft × 50 = 5,000  (no sharing — already per-brand)
   │   │   └── StoreBrandBill: ground=500, static=300, common=5000, subtotal=5,800
   │   │
   │   ├── Brand Y's bill → similar...
   │   └── Brand Z's bill → similar...
   │   dedicated_ground_sqft = asset1(20) + asset2(30) = 50 sqft  [is_common_asset=0]
   │   common_asset_sqft     = asset4(50 sqft, is_common_asset=1) → stays in pool, no ground charge
   │   remaining_sqft        = 900 - 50 = 850 sqft  (includes common asset space)
   └── Logs common_space_logs for audit trail
        ↓
Bills created as 'draft' → Admin reviews → Issues to brands
        ↓
Brand disputes if needed → Admin reviews dispute → Adjusts or rejects
        ↓
Bill finalized → PDF invoice generated store-wise per brand
```

---

## Key Design Decisions & Why

| Decision | Reason |
|---|---|
| Snapshots in `bill_line_items` (sqft, rate, assigned_brands_count stored at billing time) | Rate, dimension, and brand assignment changes should NOT retroactively alter past bills |
| `assigned_brands_count` + `full_calculated_amount` stored separately | Brands can see "this asset costs 1,000 total, shared by 2 brands, so your share is 500" — full transparency |
| Separate `common_space_logs` table | Brands will ask "why am I paying 5,000 for common space?" — this gives exact audit trail |
| `adjustment_amount` at bill level, not line item | Cleaner for admin — one adjustment field. Line-item overrides are for granular control |
| `minimum_fee` in `assets` used for static price | Already per-asset (and each asset has a `store_id`), so effectively already store-specific |
| `UNIQUE KEY (bill_period_id, store_id, brand_id)` | Prevents double billing; safe to re-run generation (upsert) |
| Soft deletes on `store_brand_bills` and `bill_disputes` | Financial records should never be hard-deleted; audit requirement |
| `bill_status` workflow with 6 states | Mirrors real-world invoice lifecycle; each state gate-keeps allowed actions |

---

## Summary: What You Need to Build

| Component | Count | What |
|---|---|---|
| New migrations | 5 | bill_periods, store_brand_bills, bill_line_items, bill_disputes, common_space_logs |
| New models | 5 | BillPeriod, StoreBrandBill, BillLineItem, BillDispute, CommonSpaceLog |
| New service | 1 | BillGenerationService (core calculation engine) |
| New controllers | 2 | BillingController, BillDisputeController |
| New views | ~8 | Period list, period detail, bill detail, dispute views, PDF invoice |
| New routes | ~14 | All under `/billing` prefix |
| Existing tables changed | 0 | Nothing needs to change — all needed columns already exist |

---

## Implementation Checklist (ordered)

### Step 1 — Migrations
- [ ] `create_bill_periods_table`
- [ ] `create_store_brand_bills_table`
- [ ] `create_bill_line_items_table`
- [ ] `create_bill_disputes_table`
- [ ] `create_common_space_logs_table`

### Step 2 — Models
- [ ] `app/Models/BillPeriod.php`
- [ ] `app/Models/StoreBrandBill.php`
- [ ] `app/Models/BillLineItem.php`
- [ ] `app/Models/BillDispute.php`
- [ ] `app/Models/CommonSpaceLog.php`

### Step 3 — Service (core logic)
- [ ] `app/Services/BillGenerationService.php`
  - [ ] `generateForPeriod(BillPeriod $period)`
  - [ ] `createGroundLineItem($bill, $asset, $store)` — includes brand count snapshot + division
  - [ ] `createStaticLineItem($bill, $asset)` — includes brand count snapshot + division
  - [ ] `createCommonLineItem($bill, $store, $commonSpaceLog)`
  - [ ] `getAssetFootprintSqft(AssetType $assetType)`
  - [ ] `convertToSqft(float $value, string $unit)`

### Step 4 — Controllers
- [ ] `app/Http/Controllers/Backend/Billing/BillingController.php`
- [ ] `app/Http/Controllers/Backend/Billing/BillDisputeController.php`

### Step 5 — Routes
- [ ] Add billing route group to `routes/web.php`

### Step 6 — Views
- [ ] `resources/views/backend/billing/periods/index.blade.php`
- [ ] `resources/views/backend/billing/periods/create.blade.php`
- [ ] `resources/views/backend/billing/periods/show.blade.php`
- [ ] `resources/views/backend/billing/bills/show.blade.php` (line items + dispute history)
- [ ] `resources/views/backend/billing/bills/invoice.blade.php` (PDF layout)
- [ ] `resources/views/backend/billing/disputes/index.blade.php`
- [ ] `resources/views/backend/billing/disputes/show.blade.php`

---

## Critical Implementation Notes

1. **Always count `assigned_brands_count` from live `assign_asset_to_brands`** where `is_asset_assigned_currently = 1` AND `status = 1` at the moment `generateForPeriod()` runs — then store it as a snapshot in `bill_line_items.assigned_brands_count`. Never recalculate from live data for an already-generated bill.

2. **Common space is already per-brand** — the `common_space_logs` divides by brand count once. Do NOT divide again in line items.

3. **`full_calculated_amount` must be stored** alongside `calculated_amount` in line items so invoices can show: *"Asset total cost: 1,000 tk — shared by 2 brands — your share: 500 tk"*

4. **Bill regeneration** — if admin regenerates a period that already has bills, use upsert on the UNIQUE KEY `(bill_period_id, store_id, brand_id)` and delete+recreate line items for that bill. Never duplicate.

5. **sqft unit conversion** — `asset_types.dimention_unit_name` can be ft, in, cm, mm, m, yd. Always convert to sqft before multiplying by `per_sqr_feet_rent`.
   - ft → ×1, in → ÷144, cm → ÷929.03, m → ×10.7639, mm → ÷92903, yd → ×9

6. **`is_common_asset = 1` applies to BOTH ground and static assets — always skip them in dedicated line items.**
   - Ground + `is_common_asset=1` → skip `createGroundLineItem`; their sqft stays in `remaining_sqft`
   - Static + `is_common_asset=1` → skip `createStaticLineItem`; their `minimum_fee` goes into `common_static_fees_total`
   - Both contribute to the single `common_charge_per_brand` line item via the formula:
     `((remaining_sqft × rate) + common_static_fees_total) / brand_count`
   - `common_space_logs` stores `common_ground_asset_sqft` and `common_static_fees_total` separately for full audit transparency.
