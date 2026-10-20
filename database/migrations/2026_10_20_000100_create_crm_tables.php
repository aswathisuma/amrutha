<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city');
            $table->string('district')->nullable();
            $table->string('state')->default('Kerala');
            $table->string('manager_name')->nullable();
            $table->timestamps();
        });

        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('service_categories')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('base_price')->nullable();
            $table->timestamps();
        });

        Schema::create('technicians', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('skill');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('Available'); // Available | On Job | Off Duty
            $table->decimal('rating', 2, 1)->default(5.0);
            $table->unsignedInteger('jobs_done')->default(0);
            $table->unsignedInteger('load')->default(0);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->index();
            $table->string('email')->nullable();
            $table->boolean('whatsapp')->default(true);
            $table->string('area')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('Residential');
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('ref')->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            $table->string('area')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->date('scheduled_on');
            $table->string('time_slot')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('New')->index();
            $table->unsignedInteger('est_amount')->default(0);
            $table->unsignedInteger('final_amount')->nullable();
            $table->string('payment_status')->default('—');
            $table->string('source')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('service_name')->nullable();
            $table->string('source')->nullable();
            $table->string('stage')->default('New Lead');
            $table->unsignedInteger('value')->default(0);
            $table->string('area')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            $table->unsignedInteger('service_charge')->default(0);
            $table->unsignedInteger('material_charge')->default(0);
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('tax')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->string('status')->default('Pending');
            $table->string('method')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('unread')->default(0);
            $table->string('status')->default('open');
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 3); // in | out
            $table->text('body');
            $table->boolean('is_template')->default(false);
            $table->string('wa_message_id')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('issue');
            $table->string('priority')->default('Medium');
            $table->string('status')->default('Open');
            $table->string('assigned_to')->nullable();
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('action');
            $table->timestamps();
        });

        Schema::create('wa_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category')->default('Utility');
            $table->string('language', 8)->default('en');
            $table->text('body');
            $table->string('status')->default('Pending');
            $table->timestamps();
        });

        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('trigger');
            $table->string('action');
            $table->string('template')->nullable();
            $table->string('status_trigger')->nullable(); // booking status that fires this rule
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('runs')->default(0);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'automations', 'wa_templates', 'activities', 'tickets', 'messages', 'conversations',
            'reviews', 'invoices', 'leads', 'bookings', 'customers', 'technicians', 'services', 'service_categories', 'branches'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
