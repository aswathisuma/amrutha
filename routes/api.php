<?php

/*
| REST API — mounted at /api/v1 (see bootstrap/app.php). Spec §8.
| Auth: Bearer token from POST /auth/login (Laravel Sanctum, expires per SANCTUM_TOKEN_EXPIRATION).
| Every response carries request_id; errors use { error: { code, message, details } }.
*/

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [V1\AuthController::class, 'login'])->middleware('throttle:login');

// Meta webhook (also reachable at /webhook/whatsapp for existing Meta configuration).
Route::match(['get', 'post'], '/webhooks/whatsapp', WhatsAppWebhookController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [V1\AuthController::class, 'logout']);
    Route::get('/me', [V1\AuthController::class, 'me']);

    Route::get('/dashboard', V1\DashboardController::class);

    Route::get('/leads', [V1\LeadController::class, 'index']);
    Route::post('/leads', [V1\LeadController::class, 'store']);
    Route::patch('/leads/{lead}', [V1\LeadController::class, 'update']);
    Route::post('/leads/{lead}/convert', [V1\LeadController::class, 'convert'])->middleware('idempotent');

    Route::get('/customers', [V1\CustomerController::class, 'index']);
    Route::post('/customers', [V1\CustomerController::class, 'store'])->middleware('idempotent');

    Route::get('/bookings', [V1\BookingController::class, 'index']);
    Route::post('/bookings', [V1\BookingController::class, 'store'])->middleware('idempotent');
    Route::get('/bookings/{booking}', [V1\BookingController::class, 'show']);
    Route::patch('/bookings/{booking}', [V1\BookingController::class, 'update']);
    Route::post('/bookings/{booking}/assign-technician', [V1\BookingController::class, 'assignTechnician']);
    Route::post('/bookings/{booking}/status', [V1\BookingController::class, 'status']);
    Route::post('/bookings/{booking}/invoice', [V1\BookingController::class, 'invoice'])->middleware('idempotent');

    Route::get('/technicians', [V1\TechnicianController::class, 'index']);

    Route::get('/invoices', [V1\InvoiceController::class, 'index']);
    Route::get('/invoices/{invoice}', [V1\InvoiceController::class, 'show']);
    Route::post('/invoices/{invoice}/payments', [V1\InvoiceController::class, 'pay'])->middleware('idempotent');

    Route::get('/conversations', [V1\ConversationController::class, 'index']);
    Route::get('/conversations/{conversation}/messages', [V1\ConversationController::class, 'messages']);
    Route::post('/conversations/{conversation}/messages', [V1\ConversationController::class, 'send'])->middleware('idempotent');

    Route::get('/automation-rules', [V1\AutomationController::class, 'index']);
    Route::post('/automation-rules', [V1\AutomationController::class, 'store']);
    Route::patch('/automation-rules/{automation}', [V1\AutomationController::class, 'update']);

    Route::get('/reports/operations', [V1\ReportController::class, 'operations']);

    Route::get('/support/tickets', [V1\TicketController::class, 'index']);
    Route::post('/support/tickets', [V1\TicketController::class, 'store'])->middleware('idempotent');
});
