<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding is now two profile steps: "About you" and "Teaching preferences".
 * `completed_at` still means "the profile data is saved", but the wizard needs
 * to know which of the two steps is done so it can resume in the right place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->timestamp('about_completed_at')->nullable()->after('completed_at');
            $table->timestamp('preferences_completed_at')->nullable()->after('about_completed_at');
        });

        // Every profile created before this migration was one single step, so
        // both new steps are already done wherever `completed_at` is set.
        DB::table('teacher_profiles')
            ->whereNotNull('completed_at')
            ->update([
                'about_completed_at' => DB::raw('completed_at'),
                'preferences_completed_at' => DB::raw('completed_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropColumn(['about_completed_at', 'preferences_completed_at']);
        });
    }
};
