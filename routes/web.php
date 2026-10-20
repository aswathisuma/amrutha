<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Meta WhatsApp Cloud API webhook (GET = verification handshake, POST = events).
Route::match(['get', 'post'], '/webhook/whatsapp', WhatsAppWebhookController::class)->name('webhook.whatsapp');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/switch-user', [AuthController::class, 'switchUser'])->name('switch-user');
    Route::get('/', fn () => redirect()->route(auth()->user()->homeSection()))->name('home');
    Route::get('/search', SearchController::class)->name('search');

    Route::get('/dashboard', DashboardController::class)->middleware('crm:dashboard')->name('dashboard');
    Route::get('/activity', [DashboardController::class, 'activity'])->middleware('crm:dashboard')->name('activity');

    Route::middleware('crm:bookings')->group(function () {
        Route::get('/bookings', [BookingController::class, 'index'])->name('bookings');
        Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
        Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
        Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::get('/bookings/{booking}/assign', [BookingController::class, 'assignForm'])->name('bookings.assign-form');
        Route::post('/bookings/{booking}/assign', [BookingController::class, 'assign'])->name('bookings.assign');
        Route::get('/bookings/{booking}/charges', [BookingController::class, 'chargesForm'])->name('bookings.charges-form');
        Route::post('/bookings/{booking}/charges', [BookingController::class, 'saveCharges'])->name('bookings.charges');
        Route::post('/bookings/{booking}/charges/approve', [BookingController::class, 'approveCharges'])->name('bookings.charges.approve');
    });
    // Shared with technicians (My Jobs); the workflow enforces which statuses they may set.
    Route::middleware('crm:bookings,field')->group(function () {
        Route::post('/bookings/{booking}/advance', [BookingController::class, 'advance'])->name('bookings.advance');
        Route::get('/bookings/{booking}/status', [BookingController::class, 'statusForm'])->name('bookings.status-form');
        Route::post('/bookings/{booking}/status', [BookingController::class, 'changeStatus'])->name('bookings.status');
        Route::get('/bookings/{booking}/work', [BookingController::class, 'workForm'])->name('bookings.work-form');
        Route::post('/bookings/{booking}/work', [BookingController::class, 'saveWork'])->name('bookings.work');
        Route::get('/attachments/{attachment}', AttachmentController::class)->middleware('signed')->name('attachments.show');
    });

    Route::middleware('crm:leads')->group(function () {
        Route::get('/leads', [LeadController::class, 'index'])->name('leads');
        Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
        Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::patch('/leads/{lead}/stage', [LeadController::class, 'stage'])->name('leads.stage');
        Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
    });

    Route::middleware('crm:customers')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers');
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    });

    Route::middleware('crm:inbox')->group(function () {
        Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
        Route::get('/inbox/customer/{customer}', [InboxController::class, 'openForCustomer'])->name('inbox.customer');
        Route::get('/inbox/lead/{lead}', [InboxController::class, 'openForLead'])->name('inbox.lead');
        Route::post('/inbox/{conversation}/send', [InboxController::class, 'send'])->name('inbox.send');
        Route::get('/inbox/{conversation}/templates', [InboxController::class, 'templates'])->name('inbox.templates');
        Route::post('/inbox/{conversation}/template', [InboxController::class, 'sendTemplate'])->name('inbox.template');
        Route::post('/inbox/{conversation}/simulate', [InboxController::class, 'simulate'])->name('inbox.simulate');
        Route::post('/inbox/{conversation}/take-over', [InboxController::class, 'takeOver'])->name('inbox.take-over');
        Route::post('/inbox/{conversation}/release', [InboxController::class, 'release'])->name('inbox.release');
    });

    Route::get('/my-jobs', FieldController::class)->middleware('crm:field')->name('field');

    Route::middleware('crm:technicians')->group(function () {
        Route::get('/technicians', [TechnicianController::class, 'index'])->name('technicians');
        Route::get('/technicians/create', [TechnicianController::class, 'create'])->name('technicians.create');
        Route::post('/technicians', [TechnicianController::class, 'store'])->name('technicians.store');
    });

    Route::middleware('crm:services')->group(function () {
        Route::get('/services', [ServiceController::class, 'index'])->name('services');
        Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    });

    Route::middleware('crm:branches')->group(function () {
        Route::get('/branches', [BranchController::class, 'index'])->name('branches');
        Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
        Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
    });

    Route::middleware('crm:invoices')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    });

    Route::middleware('crm:payments')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments');
        Route::post('/payments/{invoice}/paid', [PaymentController::class, 'markPaid'])->name('payments.paid');
        Route::post('/payments/{invoice}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
    });

    Route::get('/reviews', ReviewController::class)->middleware('crm:reviews')->name('reviews');

    Route::middleware('crm:support')->group(function () {
        Route::get('/support', [TicketController::class, 'index'])->name('support');
        Route::get('/support/create', [TicketController::class, 'create'])->name('support.create');
        Route::post('/support', [TicketController::class, 'store'])->name('support.store');
        Route::get('/support/{ticket}', [TicketController::class, 'show'])->name('support.show');
        Route::patch('/support/{ticket}', [TicketController::class, 'update'])->name('support.update');
        Route::post('/support/{ticket}/comments', [TicketController::class, 'comment'])->name('support.comment');
    });

    Route::get('/reports', ReportController::class)->middleware('crm:reports')->name('reports');

    Route::middleware('crm:automation')->group(function () {
        Route::get('/automation', [AutomationController::class, 'index'])->name('automation');
        Route::post('/automation/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automation.toggle');
        Route::get('/automation/{automation}/runs', [AutomationController::class, 'runs'])->name('automation.runs');
        Route::post('/automation/run', [AutomationController::class, 'runNow'])->name('automation.run');
    });

    Route::get('/templates', TemplateController::class)->middleware('crm:templates')->name('templates');
    Route::get('/users', UserController::class)->middleware('crm:users')->name('users');

    Route::middleware('crm:settings')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings');
        Route::post('/settings/reset', [SettingController::class, 'reset'])->name('settings.reset');
    });
});
