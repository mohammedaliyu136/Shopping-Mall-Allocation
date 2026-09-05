<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 6.6 - Allocation Management, 6.7 - Allocation Letters,
 * 6.8 - Allocation Cards and 6.9 - QR Verification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();     // FR-051
            $table->foreignId('mall_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->date('allocation_date');
            $table->date('expiry_date')->nullable();
            $table->string('allocation_type')->default('lease');
            $table->text('terms')->nullable();
            $table->string('status')->default('draft');       // FR-052

            // FR-080 / 7.2 - secure, non-guessable verification identifier
            $table->string('verification_token', 64)->unique();

            $table->decimal('annual_rent', 15, 2)->default(0);
            $table->decimal('service_charge', 15, 2)->default(0);
            $table->decimal('other_fees', 15, 2)->default(0);

            // FR-123 - ownership history chain, never deleted
            $table->foreignId('previous_allocation_id')->nullable()->constrained('allocations')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['owner_id', 'status']);
        });

        // FR-053 - optional multi-level approval workflow
        Schema::create('allocation_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('level');
            $table->string('stage');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(['allocation_id', 'level']);
        });

        // FR-060 / FR-061 - generated allocation letters and other documents
        Schema::create('allocation_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_id')->constrained()->cascadeOnDelete();
            $table->string('type');                     // letter | card | other
            $table->string('document_number')->unique(); // FR-062 unique verification reference
            $table->string('file_path')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // FR-070 to FR-072 - allocation cards, including reissue history
        Schema::create('allocation_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_id')->constrained()->cascadeOnDelete();
            $table->string('card_number')->unique();
            $table->date('issue_date');
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('active'); // FR-071
            $table->foreignId('reissue_of_id')->nullable()->constrained('allocation_cards')->nullOnDelete();
            $table->string('reissue_reason')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_cards');
        Schema::dropIfExists('allocation_documents');
        Schema::dropIfExists('allocation_approvals');
        Schema::dropIfExists('allocations');
    }
};
