<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 6.10 - Billing, 6.11 - Payments and 6.12 - Receipts.
 */
return new class extends Migration
{
    public function up(): void
    {
        // FR-090 billing categories
        Schema::create('billing_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->decimal('default_amount', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // FR-091 invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('allocation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);   // FR-093
            $table->string('status')->default('unpaid');     // unpaid|partially_paid|paid|overdue|cancelled
            $table->timestamp('overdue_notified_at')->nullable(); // FR-094 - guards against repeat reminders
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index('due_date');
            // FR-092 - one invoice per allocation per billing period keeps recurring runs idempotent
            $table->unique(['allocation_id', 'billing_period_start', 'billing_period_end'], 'invoices_allocation_period_unique');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_amount', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });

        // FR-100 to FR-103 payments
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_reference')->unique();   // FR-103 prevents duplicate recording
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('method')->default('cash');       // FR-101
            $table->string('payer_name')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('confirmed');  // pending|confirmed|failed|reversed
            $table->string('gateway_reference')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'payment_date']);
        });

        // FR-110 receipts
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('billing_categories');
    }
};
