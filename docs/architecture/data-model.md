# Data model (v1)

Postgres 17+ with PostGIS. This is the target shape; migrations are the source of truth once written, and this file must be updated in the same change as any migration that alters it.

## Conventions

- PostGIS is enabled by migration `2026_10_03_000000_enable_postgis_extension`. Framework tables in place: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.

- Primary keys: `bigint` identity (`id`) for joins. Every table exposed in a URL also has a `public_id` **ULID** (unique). **Never put `id` in a URL or API response.**
- Money: `*_cents bigint not null`, currency fixed to ZAR (column `currency char(3) default 'ZAR'` only where amounts can be shown to users).
- Time: `timestamptz`, stored UTC, shown in `Africa/Johannesburg`.
- Flexible answers: `jsonb` (scoping answers, flags). Validate shape in code before writing.
- Soft deletes only where noted. Money, events and audit rows are **never** deleted or updated after the fact (append-only).
- Encrypted columns (Laravel `encrypted` cast): ID numbers, bank account numbers, registration numbers. Store a separate keyed hash if lookup is needed.
- Foreign keys always declared with explicit `on delete` behaviour (`restrict` by default).

```mermaid
erDiagram
    users ||--o{ properties : owns
    users ||--o| pros : "may be"
    users ||--o{ service_jobs : posts
    properties ||--o{ service_jobs : "located at"
    suburbs ||--o{ properties : contains
    trades ||--o{ services : has
    services ||--o{ scoping_questions : asks
    services ||--o{ service_jobs : "is for"
    pros }o--o{ services : offers
    pros }o--o{ suburbs : covers
    service_jobs ||--o{ service_job_events : logs
    service_jobs ||--o{ service_job_invites : invites
    service_jobs ||--o{ quotes : receives
    quotes ||--o{ quote_lines : itemises
    service_jobs ||--o{ invoices : bills
    invoices ||--o{ payments : "paid by"
    payments ||--o{ refunds : "refunded by"
    pros ||--o{ payouts : receives
    service_jobs ||--o| reviews : "reviewed in"
    service_jobs ||--o{ disputes : "disputed in"
```

## Tables

### Identity and consent
| Table | Key columns |
|---|---|
| `users` | public_id, first_name, last_name, phone_e164 (unique), phone_verified_at, email (nullable, unique), password (nullable; admins only), app_authentication_secret + app_authentication_recovery_codes (encrypted, hidden; admin MFA), locale, deleted_at — **in place** (migration `2026_10_03_000007`) |
| `phone_otps` | phone_e164, code_hash (HMAC-SHA256 with app key), channel (whatsapp/sms), purpose, expires_at, attempts, consumed_at, ip — **in place**; pruned daily after 90 days |
| `consents` | user_id (restrict on delete), type (terms/privacy/marketing/pro_agreement), version, granted_at, withdrawn_at, ip, user_agent — **in place** |
| Roles & permissions | `spatie/laravel-permission` tables. Roles: `customer`, `pro`, `admin_super`, `admin_support`, `admin_vetting`, `admin_finance` — **in place**; created by migration `2026_10_03_000003_create_default_roles`, mirrored by `App\Domain\Accounts\Enums\Role` |

### Places
| Table | Key columns |
|---|---|
| `suburbs` | slug (unique; URL key), name (unique per municipality), region (berea_central/north/west/south), municipality, centroid (geography Point 4326), boundary (geography MultiPolygon, nullable), is_active — **in place** (`2026_10_04_000003`) |
| `properties` | public_id, user_id, label, street_address (encrypted, hidden), suburb_id, location (geography Point; suburb centre until a geocoder exists), postal_code, property_type (house/flat/townhouse/business/other), deleted_at — **in place** |
| `waitlist_entries` | first_name, phone_e164, suburb_text, suburb_key, suburb_id (nullable), service_id, privacy_version, consented_at, timestamps; unique phone + suburb key + service; pruned after 12 months — **in place** (spec 006) |

### Catalogue (seeded from `docs/product/scoping/*.yaml`)
| Table | Key columns |
|---|---|
| `trades` | key (unique; URL key), name, status (demo/live), is_active, sort — **in place** |
| `services` | trade_id (restrict delete), key (unique within trade; URL key), name, description, requires_registration (pirb / electrical_registered_person), emergency_capable, safety_advice jsonb, is_active, sort — **in place** |
| `scoping_questions` | service_id, key (unique within service), prompt, type (single_choice/multi_choice/yes_no/number/text), options jsonb, required, flags jsonb (`urgent_if`), sort — **in place** |

Enums: `TradeStatus`, `QuestionType`, `RegistrationType` (`App\Domain\Catalogue\Enums`). Keys never change. Permissions `catalogue.view` (all admin roles) and `catalogue.edit` (super, support).

### Pros
| Table | Key columns |
|---|---|
| `pros` | public_id, user_id (unique), status (draft/submitted/changes_requested/approved/rejected/suspended — only `ProStatusMachine` changes it), business_name (nullable until the business step), business_type (sole_trader/company), vat_number, bio, weekly_job_cap, vetting_consent_at, submitted_at, decided_by, decided_at, decision_reason (shown to the pro), approved_at, suspended_at, reapply_after, last_activity_at — **in place** (spec 006 + `2026_10_04_130000`, spec 008); base_location, ratings and response metrics arrive with spec 009 |
| `pro_services` | pro_id, service_id (unique pair) — **in place** |
| `pro_service_areas` | pro_id, suburb_id (unique pair) — **in place** |
| `pro_documents` | public_id (ULID, used in signed URLs), pro_id, type (id_document/proof_of_address/profile_photo/pirb/electrical_registered_person; unique per pro), status (pending/verified/flagged), number (encrypted; registrations), one private media file, verified_at, verified_by, expires_at, flag_message (shown to the pro until resubmission), notes (private to vetting) — **in place** (spec 008) |
| `pro_job_allocations` | pro_id, service_job_id (unique pair), allocated_at — rolling weekly cap input, in place (spec 006); invite workflow will write records in spec 009 |
| `pro_customer_exclusions` | pro_id + customer_id (unique pair), service_job_id, upheld_at — upheld dispute exclusion, in place (spec 006); dispute workflow will write records later |
| `pro_references` | pro_id, name, phone_e164 (encrypted), relationship, outcome (pending/positive/negative/no_answer), note (private), checked_by, checked_at — **in place** (spec 008) |
| `pro_events` | pro_id, from_status, to_status, actor_id, reason, created_at — append-only status history; the retention prune blanks `reason` (spec 008, decision 034) — **in place** |
| `pro_bank_accounts` | pro_id, bank_name, account_holder, account_number (encrypted), branch_code, provider_recipient_ref, verified_at |
| `pro_strikes` | pro_id, type, service_job_id, notes, created_by |

### Jobs
| Table | Key columns |
|---|---|
| `service_jobs` | public_id, customer_id, property_id, service_id, status, urgency (normal/urgent), preferred_date, time_window (morning/afternoon/flexible/today), scoping_answers jsonb (`{key: {prompt, type, answer}}` as asked), customer_notes, ai_summary (≤ 600 chars), ai_summary_source (ai/customer_edited/none), ai_summary_generated_at, ai_summary_input_hash (sha256 of service + answers + notes the description was written for), quotes_count, accepted_quote_id, posted_at, quote_window_ends_at, scheduled_for, started_at, completed_at, cancelled_at, cancelled_by_type/id, cancel_reason — **in place** (`2026_10_04_000004`; summary provenance columns `2026_10_04_120000`, spec 007) except quotes_count, accepted_quote_id, scheduled_for, started_at, completed_at, cancelled_by_type/id (added with their specs) |
| `service_job_events` | service_job_id, from_status, to_status, event_type, actor_type, actor_id, payload jsonb, created_at (append-only; model refuses updates/deletes) — **in place** |

Settings: `job_timers.quote_window_hours` (72), `job_timers.draft_expiry_days` (7) via `App\Settings\JobTimers`.
| `service_job_invites` | service_job_id, pro_id, wave, status, invited_at, viewed_at, responded_at, expires_at, decline_reason (unique job+pro) |
| `quotes` | public_id, service_job_id, pro_id, version, status, labour_cents, materials_cents, callout_cents, vat_cents, total_cents, deposit_cents, earliest_start_date, valid_until, notes, submitted_at, accepted_at, supersedes_quote_id |
| `quote_lines` | quote_id, kind (labour/materials/callout), description, quantity (decimal 10,2), unit_price_cents, line_total_cents, sort |

### Money
| Table | Key columns |
|---|---|
| `invoices` | public_id, service_job_id, quote_id, pro_id, type (deposit/final), number (unique per pro), status, labour_cents, materials_cents, vat_cents, total_cents, issued_at, due_at, paid_at |
| `payments` | public_id, invoice_id, provider, provider_reference (unique), method, status, amount_cents, provider_fee_cents, idempotency_key (unique), succeeded_at, failed_reason |
| `refunds` | payment_id, amount_cents, reason, status, requested_by, approved_by, provider_reference |
| `payouts` | public_id, pro_id, service_job_id, gross_cents, commission_cents, net_cents, status, scheduled_for, paid_at, hold_reason, provider_reference, idempotency_key (unique) |
| `ledger_entries` | service_job_id, account, direction, amount_cents, source_type, source_id, idempotency_key, occurred_at (append-only) |
| `webhook_events` | provider, provider_event_id (unique per provider), type, signature_valid, payload jsonb, received_at, processed_at, error |

### Trust
| Table | Key columns |
|---|---|
| `reviews` | service_job_id (unique), customer_id, pro_id, rating (1–5), body, status (published/hidden), pro_reply, pro_replied_at |
| `disputes` | public_id, service_job_id, raised_by_type/id, reason, description, status, resolution (refund_full/refund_partial/rework/release), refund_cents, resolution_notes, resolved_by, resolved_at |

### Platform
| Table | Key columns |
|---|---|
| `media` | `spatie/laravel-medialibrary` on private `media` disk. `job_photos` collection belongs to `ServiceJob`; up to 5 processed WebP images per job, source metadata stripped, SHA-256 custom property for repeat upload detection. Cancelled drafts delete their photos. Pro documents and invoice PDFs use later collections. |
| `activity_log` | `spatie/laravel-activitylog` — admin and sensitive actions; append-only, never cleaned — **in place** |
| `settings` | `spatie/laravel-settings` — timers, commission, deposit cap — **table in place**; settings classes arrive with their features (`job_timers`; `ai`: enabled, suggestion_min_confidence, daily_call_budget, usage_retention_days — spec 007; `vetting`: reapply_after_days, retention_months — spec 008) |
| `ai_usage` | purpose (suggest_service/summarise), provider, model, input_tokens, output_tokens, latency_ms, outcome (ok/invalid/timeout/error/throttled), service_job_id (nullable, null on delete), created_at; index (created_at, purpose). **No customer text and no user ID.** Pruned after `ai.usage_retention_days` (default 90) — **in place** (spec 007) |
| `notifications` | Laravel database notifications |
| Queue/cache/session | Laravel defaults on Postgres (`jobs`, `failed_jobs`, `cache`, `sessions`) |

## Key indexes and constraints

- `service_jobs (status, quote_window_ends_at)` for the scheduler.
- `service_job_invites (pro_id, status)` for the pro inbox.
- GiST indexes on all geography columns.
- Check constraints: `rating between 1 and 5`; all `*_cents >= 0`; `quotes.total_cents = labour + materials + callout + vat`.
- Partial unique index: one `accepted` quote per job.
