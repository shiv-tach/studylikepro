<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the teacher has seen the "You're live" screen. Null means the
     * celebration is still owed, so approval can be announced at the moment it
     * actually happens instead of at submission.
     */
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->timestamp('approved_seen_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropColumn('approved_seen_at');
        });
    }
};
