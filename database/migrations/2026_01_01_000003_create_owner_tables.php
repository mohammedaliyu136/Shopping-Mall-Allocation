<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 6.5 - Owner Management (FR-040 to FR-043).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('owner_code')->unique();

            // FR-040 personal information
            $table->string('full_name');
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('nationality')->nullable();

            // FR-040 contact information
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();

            // FR-040 identification
            $table->string('id_type')->nullable();
            $table->string('id_number')->nullable();

            // FR-040 next of kin
            $table->string('nok_name')->nullable();
            $table->string('nok_relationship')->nullable();
            $table->string('nok_phone')->nullable();
            $table->string('nok_address')->nullable();

            // FR-041 business information
            $table->string('business_name')->nullable();
            $table->string('business_reg_number')->nullable();
            $table->string('business_address')->nullable();
            $table->string('business_nature')->nullable();

            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('full_name');
            $table->index('phone');
            $table->index('email');
        });

        // FR-042 owner documents
        Schema::create('owner_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('other');
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_documents');
        Schema::dropIfExists('owners');
    }
};
