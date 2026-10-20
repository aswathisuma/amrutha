# HayFixr CRM (Laravel)

Field-service CRM for home maintenance & waste management: bookings, leads, customers,
WhatsApp inbox + booking bot, technicians, invoices, payments, reviews, support, reports
and automation rules. Laravel 12, Blade, Tailwind (precompiled) and Chart.js, plus a REST API at `/api/v1`.

How each part of the *Workflow & Backend Developer Documentation* is implemented, and what is
still open, is in **[docs/IMPLEMENTATION.md](docs/IMPLEMENTATION.md)**.

## Run it locally (XAMPP / php artisan serve)

```bash
composer install
cp .env.example .env && php artisan key:generate   # first time only
php artisan migrate:fresh --seed
php artisan serve                                  # http://localhost:8000
```

For local use set `QUEUE_CONNECTION=sync` in `.env` (no worker needed). Sign in with any seeded
account (dummy password `DummyDemoPass#2026`, set by `CRM_DEMO_PASSWORD`):

| Role | Email | Sees |
|---|---|---|
| Super Admin | anjali@hayfixr.in | everything, including the audit log |
| Admin | rahul@hayfixr.in | all but Branches / Users / Settings |
| Branch Manager | fahad@hayfixr.in | own branch only; can approve charges |
| Staff | sneha@hayfixr.in | bookings, leads, customers, inbox, support |
| Technician | vinod@hayfixr.in | own jobs (field statuses, notes, photos) + their customers' chats |

## Production

```bash
APP_ENV=production php artisan migrate --force      # never migrate:fresh; seeding is refused in production
php artisan crm:create-admin you@company.com        # first Super Admin (prints a one-time password)
php artisan queue:work                               # WhatsApp sends + webhook processing
php artisan schedule:work                            # reminders, payment reminders, feedback, follow-ups
```

The demo account switcher, demo logins and "Reset demo data" are always off when `APP_ENV=production`.
Point the Meta webhook at `https://<domain>/webhook/whatsapp` (or `/api/v1/webhooks/whatsapp`) and set
`WHATSAPP_TOKEN`, `WHATSAPP_PHONE_ID`, `WHATSAPP_BUSINESS_ID`, `WHATSAPP_VERIFY_TOKEN` and
`WHATSAPP_APP_SECRET`. Without the app secret, production rejects webhook calls.

**All credentials in `.env` / `.env.example` are dummies** (values start with `dummy`/`Dummy`). The app
treats a `dummy…` WhatsApp token or app secret as "not configured", so nothing is sent to Meta. Replace
them with real values from your own accounts only in the server's `.env`, never in git. Do not reuse
credentials from other projects; generate a fresh `APP_KEY` per environment with `php artisan key:generate`.

## API quick start

```bash
curl -X POST http://localhost:8000/api/v1/auth/login -H "Accept: application/json" \
     -d email=anjali@hayfixr.in -d "password=DummyDemoPass#2026"           # → data.token
curl http://localhost:8000/api/v1/bookings?status=New -H "Authorization: Bearer <token>" -H "Accept: application/json"
```

Writes accept an `Idempotency-Key` header. Every response includes `request_id`. Errors look like
`{"error":{"code":"invalid_transition","message":"…"},"request_id":"…"}`.

## Where things live

| | |
|---|---|
| `config/crm.php` | status transitions, roles → sections, finance policy, WhatsApp settings |
| `app/Services/BookingWorkflow.php` | state machine, assignment, charges approval, invoicing |
| `app/Services/PaymentService.php` | payment ledger, refunds |
| `app/Services/AutomationRunner.php` | status + scheduled automations with dedupe log |
| `app/Services/BotService.php`, `WhatsAppService.php` | bot flow, sending rules (24h window, opt-out, takeover) |
| `app/Jobs/` | queued WhatsApp delivery and webhook processing |
| `app/Http/Controllers/Api/V1/` | REST API |
| `public/css/app.css` | compiled Tailwind — rebuild after adding classes: `npx tailwindcss@3.4.17 -i resources/css/tailwind.css -o public/css/app.css --minify` |

## Tests

```bash
php artisan test
```
