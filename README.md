# HayFixr CRM (Laravel)

Field-service CRM for home maintenance & waste management: bookings, leads, customers,
WhatsApp inbox + booking bot, technicians, invoices, payments, reviews, support, reports
and automation rules. Built with Laravel 12, Blade, Tailwind (CDN) and Chart.js.

## Run it

```bash
composer install
cp .env.example .env && php artisan key:generate   # first time only
php artisan migrate:fresh --seed
php artisan serve
```

Open http://localhost:8000 and sign in with any seeded account (password `password`):

| Role | Email | Sees |
|---|---|---|
| Super Admin | anjali@hayfixr.in | everything |
| Admin | rahul@hayfixr.in | all but Branches / Users / Settings |
| Branch Manager | fahad@hayfixr.in | own branch's bookings, customers, technicians, invoices |
| Staff | sneha@hayfixr.in | bookings, leads, customers, inbox, support |
| Technician | vinod@hayfixr.in | My Jobs + inbox |

The **Signed in as** switcher in the sidebar jumps between these accounts. Turn it (and
“Reset demo data”) off in production with `CRM_DEMO_SWITCHER=false`.

Under XAMPP you can also browse to `http://localhost/Amrutha/public`.

## Where things live

| | |
|---|---|
| `config/crm.php` | statuses, lead stages, role → section access, sidebar nav, icons, WhatsApp messages per status |
| `app/Services/BookingWorkflow.php` | status advance, technician assignment, invoice generation, payment, automation-gated WhatsApp updates |
| `app/Services/BotService.php` | WhatsApp menu bot; creates a booking + lead when a customer names a service |
| `app/Services/WhatsAppService.php` | records messages; sends through Meta Cloud API when `WHATSAPP_TOKEN` is set |
| `app/Http/Middleware/EnsureCanSee.php` | `crm:<section>` route guard |
| `resources/views/` | one folder per section; `show`/`create` views are drawer/modal partials |
| `public/js/crm.js` | drawers/modals (fetched partials), ⌘K search, toasts |
| `database/seeders/DatabaseSeeder.php` | demo data |

## WhatsApp Cloud API

Set in `.env`: `WHATSAPP_PHONE_ID`, `WHATSAPP_BUSINESS_ID`, `WHATSAPP_TOKEN`,
`WHATSAPP_VERIFY_TOKEN`, `WHATSAPP_APP_SECRET`. Point the Meta webhook at
`https://<your-domain>/webhook/whatsapp`. Inbound text messages create/find the customer,
appear in the inbox, and get a bot reply. Without a token everything stays local.

## Tests

```bash
php artisan test
```
