<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production workflow schema (spec §4–§7): status history, charge approval,
 * invoice items + payment ledger, WhatsApp delivery/webhook tracking,
 * automation run log, ticket events, attachments, audit log, idempotency keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- bookings: lifecycle metadata ---------------------------------------
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status_reason')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('material_charge')->default(0);
            $table->unsignedInteger('discount')->default(0);
            $table->timestamp('charges_approved_at')->nullable();
            $table->unsignedBigInteger('charges_approved_by')->nullable();
            $table->text('work_notes')->nullable();
            $table->index('branch_id');
            $table->index('technician_id');
            $table->index('scheduled_on');
        });

        Schema::create('booking_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label')->nullable(); // "WhatsApp bot", "system" …
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ---- customers / leads -------------------------------------------------
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('whatsapp_opt_out')->default(false);
            $table->timestamp('opted_out_at')->nullable();
            $table->unique('phone');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lost_reason')->nullable();
            $table->index('stage');
            $table->index('phone');
        });

        // ---- finance -----------------------------------------------------------
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('currency', 3)->default('INR');
            $table->decimal('tax_rate', 5, 4)->default(0.18);
            $table->unsignedInteger('amount_paid')->default(0);
            $table->date('due_date')->nullable();
            $table->json('customer_snapshot')->nullable();
            $table->unsignedInteger('reminders_sent')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->unique('booking_id'); // one invoice per booking → idempotent generation
            $table->index('status');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->integer('unit_price'); // negative for discounts
            $table->integer('amount');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('payment'); // payment | refund
            $table->integer('amount'); // refunds are negative; rows are never edited
            $table->string('method');
            $table->string('reference')->nullable();
            $table->string('status')->default('succeeded');
            $table->string('idempotency_key')->nullable()->unique();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
        });

        // ---- WhatsApp ----------------------------------------------------------
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('mode')->default('bot'); // bot | human
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('bot_state')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->boolean('needs_attention')->default(false);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('status')->default('received'); // received | queued | sent | delivered | read | failed | local
            $table->string('template_name')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('status_at')->nullable();
            $table->index('wa_message_id');
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('whatsapp');
            $table->string('event_id');
            $table->string('type');
            $table->string('payload_hash', 64);
            $table->json('payload');
            $table->string('status')->default('pending'); // pending | processed | failed | ignored
            $table->string('error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->unique(['provider', 'event_id']);
        });

        // ---- automation --------------------------------------------------------
        Schema::table('automations', function (Blueprint $table) {
            $table->string('key')->nullable()->unique();
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status')->nullable();
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->string('dedupe_key')->unique();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('status'); // sent | skipped | failed
            $table->string('detail')->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        // ---- support -----------------------------------------------------------
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('source')->default('Phone');
            $table->timestamp('resolved_at')->nullable();
        });

        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // comment | status | assignment | created
            $table->text('body')->nullable();
            $table->string('from_value')->nullable();
            $table->string('to_value')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ---- attachments / audit / idempotency ---------------------------------
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedInteger('size');
            $table->string('label')->nullable(); // before | after | receipt …
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('route');
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamps();
            $table->unique(['key', 'user_id', 'route']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['idempotency_keys', 'audit_logs', 'attachments', 'ticket_events', 'automation_runs',
            'webhook_events', 'payments', 'invoice_items', 'booking_status_history'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['phone', 'last_login_at']));
        Schema::table('tickets', function (Blueprint $t) {
            $t->dropConstrainedForeignId('booking_id');
            $t->dropColumn(['description', 'source', 'resolved_at']);
        });
        Schema::table('automations', function (Blueprint $t) {
            $t->dropUnique(['key']);
            $t->dropColumn(['key', 'max_attempts', 'last_run_at', 'last_run_status']);
        });
        Schema::table('messages', function (Blueprint $t) {
            $t->dropIndex(['wa_message_id']);
            $t->dropConstrainedForeignId('sent_by');
            $t->dropColumn(['status', 'template_name', 'error_code', 'error_message', 'attempts', 'status_at']);
        });
        Schema::table('conversations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('assigned_user_id');
            $t->dropColumn(['mode', 'bot_state', 'last_inbound_at', 'needs_attention']);
        });
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropUnique(['booking_id']);
            $t->dropIndex(['status']);
            $t->dropColumn(['currency', 'tax_rate', 'amount_paid', 'due_date', 'customer_snapshot', 'reminders_sent', 'last_reminder_at']);
        });
        Schema::table('leads', function (Blueprint $t) {
            $t->dropIndex(['stage']);
            $t->dropIndex(['phone']);
            $t->dropConstrainedForeignId('customer_id');
            $t->dropConstrainedForeignId('branch_id');
            $t->dropConstrainedForeignId('owner_user_id');
            $t->dropColumn('lost_reason');
        });
        Schema::table('customers', function (Blueprint $t) {
            $t->dropUnique(['phone']);
            $t->dropColumn(['whatsapp_opt_out', 'opted_out_at']);
        });
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropIndex(['branch_id']);
            $t->dropIndex(['technician_id']);
            $t->dropIndex(['scheduled_on']);
            $t->dropColumn(['status_reason', 'assigned_by', 'assigned_at', 'completed_at', 'material_charge', 'discount',
                'charges_approved_at', 'charges_approved_by', 'work_notes']);
        });
    }
};
