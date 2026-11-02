<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: data model. A ward has exactly one WardConfig; the config holds the
 * ward type and everything the master template lets a ward switch on or off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('specialty')->nullable();
            $table->timestamps();
        });

        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('specialty')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ward_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('ward_type', 20);
            $table->string('bed_layout', 20);
            $table->unsignedSmallInteger('bed_count');
            $table->json('enabled_tabs');
            $table->json('doctor_actions');
            $table->json('nurse_actions');
            $table->string('obs_chart_type', 20);
            $table->unsignedSmallInteger('obs_frequency_hours');
            // Phase 6: wards move to the new view one at a time.
            $table->string('rollout_stage', 20)->default('live');
            // Bumped on every save; the client cache is keyed on it.
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('ward_config_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->string('event', 20);
            $table->json('before')->nullable();
            $table->json('after');
            $table->unsignedInteger('version');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->string('label', 20);
            $table->string('group_label', 30)->nullable();
            $table->unsignedSmallInteger('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['ward_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beds');
        Schema::dropIfExists('ward_config_changes');
        Schema::dropIfExists('ward_configs');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('teams');
    }
};
