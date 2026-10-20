# HayFixr — implementation status against the Workflow & Backend spec (v1.0)

This maps each section of *HayFixr_Workflow_Backend_Developer_Documentation.pdf* to the code.
The spec recommends Node/NestJS; §3 allows equivalent technology, so this is **Laravel 12 (PHP 8.2)**.

| Spec | Status | Where |
|---|---|---|
| §3 Architecture | Laravel monolith: Blade UI + `/api/v1` REST. Database queue (Redis-compatible via `QUEUE_CONNECTION=redis`). Private file storage with signed URLs. | `routes/web.php`, `routes/api.php`, `app/Jobs` |
| §4.1 Lead → booking | E.164 phone normalisation, auto-link to existing customer, owner, branch, **Lost requires reason**, conversion creates/links customer + booking. | `app/Services/CrmService.php` |
| §4.2 Booking → completion | Create validates date/slot/branch scope. Assignment checks off-duty + **same date/slot conflicts** under row locks, records `assigned_by/at`, moves technician load. Exception statuses need reason. Work notes + photos. **Final charges review with approval threshold** before invoicing. | `app/Services/BookingWorkflow.php` |
| §4.3 Invoice → payment | One invoice per booking (unique key + `insertOrIgnore`), line items, customer snapshot, tax rate, due date. **Append-only ledger** (immutable rows), idempotency keys, partial payments (configurable), overpayment rejected, refunds as negative rows (admins only), booking set Paid when balance hits zero. | `app/Services/PaymentService.php`, `app/Models/Payment.php` |
| §4.4 WhatsApp | Signature check (`X-Hub-Signature-256`, **required in production**), events stored once in `webhook_events` (dedupe), processed on the queue. Delivery statuses tracked, out-of-order updates ignored. STOP/START opt-out. Step-by-step bot that **confirms instead of guessing**, hands off to staff after repeated misses or on request. Staff takeover pauses the bot. 24h window enforced for free-form replies. | `WhatsAppWebhookController`, `Jobs/ProcessWhatsAppEvent`, `Jobs/SendWhatsAppMessage`, `Services/BotService` |
| §4.5 Support | Booking link, description, source, assignee, status transitions + comments in `ticket_events`; closing an unresolved ticket needs a note. | `CrmService`, `support/show.blade.php` |
| §4.6 Automation | Status rules fire after commit; scheduled rules (tomorrow reminder, payment reminder ×3 at 24h spacing, feedback +24h, follow-up +90d) run every 5 min. Every run logged in `automation_runs` under a unique dedupe key. Guards: rule enabled, approved template, parameters present, opt-out, booking/invoice state. | `app/Services/AutomationRunner.php`, `routes/console.php` |
| §5 Status model | Transition table in `config/crm.php` (`transitions`), enforced server-side; `booking_status_history` with actor + reason; technicians limited to field statuses; Invoice Generated / Paid only via their actions. | `BookingWorkflow::transition()` |
| §6 Roles | Server-side section guard (`crm:` middleware) + record scoping (branch managers → own branch, technicians → own jobs and their customers' chats) on web and API. | `EnsureCanSee`, `User::scopeBookingQuery()` |
| §7 Database | All listed tables except `organizations`, `roles/user_roles`, `technician_skills` (see decisions). Money is stored as **integer rupees** (exact, never floating point). | `database/migrations/*` |
| §8 REST API | All 30 endpoints under `/api/v1`; Sanctum bearer tokens (expiring); `{data, meta, request_id}` / `{error:{code,message,details}, request_id}`; allow-listed sort; `limit ≤ 100`; status codes per §8.1; `Idempotency-Key` replay on writes. | `app/Http/Controllers/Api/V1` |
| §9 WhatsApp integration | Secrets only in `.env`, masked in UI. Retries with exponential backoff + jitter; permanent 4xx not retried; provider error codes kept. | `SendWhatsAppMessage` |
| §10 Security | Login throttling (web + API), security headers, audit log (status, assignment, charges, invoices, payments, refunds, rules, logins), request IDs in logs, `/up` health check, demo features hard-disabled in production. | `AuditLog`, `Middleware/*` |
| §11 NFR | Timestamps stored in UTC, shown in `CRM_DISPLAY_TIMEZONE`; paginated lists; indexes on common filters. | `config/crm.php`, `local_time()` |
| §13 Testing | 58 feature tests: transitions, permissions per role, cross-branch/unassigned access, assignment conflicts, tax, invoice idempotency, ledger, webhook signature/duplicates/out-of-order, opt-out, 24h window, takeover, automation duplicates/cancellations/paid/opt-out, API envelopes, rate limiting. | `tests/Feature` |
| §14 Deployment | Production refuses demo seeding; `php artisan crm:create-admin`, `crm:remove-demo-users`; env checklist below. | `routes/console.php` |

## Environment mapping (spec §14 → Laravel)

| Spec | Here |
|---|---|
| `DATABASE_URL` | `DB_URL` (or `DB_CONNECTION=pgsql` + `DB_HOST`…). Needs the `pdo_pgsql` PHP extension. |
| `AUTH_SECRET / SESSION_SECRET` | `APP_KEY` (`php artisan key:generate`) |
| `WHATSAPP_ACCESS_TOKEN` | `WHATSAPP_TOKEN` |
| `WHATSAPP_PHONE_NUMBER_ID` / `…BUSINESS_ACCOUNT_ID` | `WHATSAPP_PHONE_ID` / `WHATSAPP_BUSINESS_ID` |
| `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN` | same names |
| `PUBLIC_API_BASE_URL` | `APP_URL` (API at `/api/v1`) |
| `QUEUE_REDIS_URL` | `QUEUE_CONNECTION=redis` + `REDIS_URL` (or keep `database`) |
| `OBJECT_STORAGE_*` | `FILESYSTEM_DISK` / `AWS_*` (attachments use the private `local` disk today) |
| `LOG_LEVEL / ERROR_TRACKING_DSN` | `LOG_LEVEL`, `LOG_STACK` |

**Production processes:** web server, `php artisan queue:work`, `php artisan schedule:work` (or cron `schedule:run` every minute).
**Local XAMPP:** `.env` uses `QUEUE_CONNECTION=sync`, so no worker is needed.

## Not done yet / decisions for the product owner (spec §15)

- **Multi-tenancy:** single organisation assumed (no `organizations` table). Customers are shared across branches.
- **IDs:** sequential integer keys with server-side authorisation on every record. Switch to UUID/ULID route keys if non-guessable URLs are required.
- **Roles:** one role per user, configured in `config/crm.php`. No `roles/user_roles` tables or multi-branch managers yet.
- **Technician availability:** status + same-slot conflict check only. No working hours, durations, travel buffers or skill matrix (`technician_skills`).
- **Payments:** manual recording (cash/UPI/bank). No payment gateway or webhooks.
- **Tax:** single GST rate (`CRM_GST_RATE`). No CGST/SGST split or HSN/SAC codes on invoices.
- **Invoice numbering:** `INV-24000+id`, unique and server-generated. Confirm the legal format (e.g. per financial year).
- **Bot:** English only, text messages only. Media and unknown types are flagged for staff.
- **Not built:** retention/deletion jobs, MFA, CORS allow-list for a separate front end, malware scanning of uploads, user invite flow, rule-builder UI (rules can be created via `POST /api/v1/automation-rules`).
