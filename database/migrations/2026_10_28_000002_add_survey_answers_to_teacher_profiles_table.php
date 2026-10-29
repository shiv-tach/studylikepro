<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The answers of the invite survey, kept on the profile so the admin review
     * and the workspace consoles can read them after the guest account exists.
     *
     * - `tutoring_levels`  the level keys the teacher said they tutor for (`ol`, `al` or both).
     * - `service_interests` the `App\Enums\ServiceType` values they said they want to sell.
     * - `government_verified_at` when our team confirmed the government-teacher proof; the
     *   public badge only ever renders from this timestamp, never from the claim itself.
     */
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->json('tutoring_levels')->nullable()->after('languages');
            $table->json('service_interests')->nullable()->after('tutoring_levels');
            $table->timestamp('government_verified_at')->nullable()->after('approved_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropColumn(['tutoring_levels', 'service_interests', 'government_verified_at']);
        });
    }
};
