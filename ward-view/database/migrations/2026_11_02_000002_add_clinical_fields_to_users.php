<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('nurse')->after('email');
            $table->foreignId('ward_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->after('ward_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropConstrainedForeignId('ward_id');
            $table->dropColumn('role');
        });
    }
};
