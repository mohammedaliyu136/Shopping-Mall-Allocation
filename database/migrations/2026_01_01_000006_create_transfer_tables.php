<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 6.13 - Ownership Transfer Management (FR-120 to FR-124).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ownership_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allocation_id')->constrained()->cascadeOnDelete();   // allocation being transferred
            $table->foreignId('from_owner_id')->constrained('owners')->cascadeOnDelete();
            $table->foreignId('to_owner_id')->constrained('owners')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');   // pending|approved|rejected|cancelled

            // FR-121 outstanding obligations check
            $table->decimal('outstanding_at_request', 15, 2)->default(0);
            $table->boolean('obligations_cleared')->default(false);

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();

            // FR-124 the allocation created for the new owner
            $table->foreignId('new_allocation_id')->nullable()->constrained('allocations')->nullOnDelete();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
        });

        Schema::create('transfer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ownership_transfer_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_documents');
        Schema::dropIfExists('ownership_transfers');
    }
};
