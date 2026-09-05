<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS 6.14 - Notification Management (FR-130 to FR-133).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('channel')->default('in_system'); // FR-132 email|sms|in_system
            $table->string('target_type')->default('all');   // FR-131 all|owner|block|outstanding|user
            $table->string('target_ref')->nullable();
            $table->string('event')->nullable();             // FR-133 automated notification trigger
            $table->string('status')->default('draft');      // draft|sent|failed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel')->default('in_system');
            $table->string('destination')->nullable();       // email address / phone number used
            $table->string('status')->default('pending');    // pending|sent|failed|read
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notifications');
    }
};
