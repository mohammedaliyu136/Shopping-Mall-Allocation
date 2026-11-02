<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('mrn', 20)->unique();
            $table->string('name');
            $table->date('date_of_birth');
            $table->string('sex', 10);
            // Header badges, not actions.
            $table->json('allergies')->nullable();
            $table->string('dnr_status', 20)->default('not_recorded');
            $table->boolean('critical_flag')->default(false);
            $table->string('critical_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ward_id')->constrained();
            $table->foreignId('bed_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->index();
            $table->string('presenting_complaint')->nullable();
            $table->json('diagnoses')->nullable();
            $table->timestamp('arrived_at');
            $table->timestamp('admitted_at')->nullable();
            // Emergency
            $table->unsignedTinyInteger('triage_category')->nullable();
            $table->timestamp('triaged_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->string('disposition', 20)->nullable();
            // Set while the patient is away for a scan or theatre.
            $table->string('off_ward', 60)->nullable();
            // Critical care 1:1 nursing
            $table->foreignId('assigned_nurse_id')->nullable()->constrained('users')->nullOnDelete();
            // Discharge
            $table->string('discharge_type', 30)->nullable();
            $table->json('discharge_checklist')->nullable();
            $table->timestamp('discharge_started_at')->nullable();
            $table->timestamp('discharged_at')->nullable();
            // Observation frequency override set from the ward round note.
            $table->unsignedSmallInteger('obs_frequency_hours')->nullable();
            $table->timestamps();
            $table->index(['ward_id', 'status']);
        });

        Schema::create('clinical_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('parent_id')->nullable()->constrained('clinical_entries')->nullOnDelete();
            $table->string('category', 20);
            $table->string('action_key', 40);
            $table->string('type', 40)->nullable();
            $table->string('summary');
            $table->json('payload')->nullable();
            $table->string('status', 20)->nullable();
            $table->smallInteger('score')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignId('cosigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cosigned_at')->nullable();
            $table->timestamps();
            $table->index(['encounter_id', 'category', 'recorded_at']);
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->foreignId('from_ward_id')->constrained('wards');
            $table->foreignId('to_ward_id')->nullable()->constrained('wards');
            $table->foreignId('from_team_id')->nullable()->constrained('teams');
            $table->foreignId('to_team_id')->nullable()->constrained('teams');
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['to_ward_id', 'status']);
        });

        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->unsignedSmallInteger('score');
            $table->string('risk_level', 10);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('completed_at');
            $table->timestamp('next_due_at');
            $table->timestamps();
            $table->index(['encounter_id', 'kind', 'completed_at']);
        });

        Schema::create('handover_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('role', 20);
            $table->string('shift', 20);
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('ward_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('for_role', 20);
            $table->timestamp('due_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('done_by')->nullable()->constrained('users');
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ward_tasks');
        Schema::dropIfExists('handover_notes');
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('clinical_entries');
        Schema::dropIfExists('encounters');
        Schema::dropIfExists('patients');
    }
};
