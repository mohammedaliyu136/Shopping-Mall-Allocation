<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 6.3 - Mall Management (FR-020, FR-021) and 6.4 - Shop Management (FR-030, FR-031).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('malls', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mall_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['mall_id', 'code']);
        });

        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->integer('level')->default(0);
            $table->timestamps();

            $table->unique(['block_id', 'code']);
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mall_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shop_code')->unique();       // FR-030 unique Shop ID
            $table->string('shop_number');
            $table->string('shop_type')->default('retail');
            $table->decimal('size_sqm', 10, 2)->nullable();
            $table->string('status')->default('available'); // FR-031
            $table->decimal('annual_rent', 15, 2)->default(0);
            $table->decimal('service_charge', 15, 2)->default(0);
            $table->decimal('other_fees', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['mall_id', 'shop_number']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
        Schema::dropIfExists('floors');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('malls');
    }
};
