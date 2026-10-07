<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase F: the coarse Sri Lankan-external buckets are gone for good. A grade id
 * (`student_profiles.grade_id`, `bookings.learner_grade_id`) is the only source
 * of grade information now; migration 000007 already backfilled the rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('student_profiles', 'grade_level')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->dropColumn('grade_level');
            });
        }

        if (Schema::hasColumn('bookings', 'learner_grade')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('learner_grade');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('student_profiles', 'grade_level')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->string('grade_level')->nullable()->after('user_id');
            });
        }

        if (! Schema::hasColumn('bookings', 'learner_grade')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('learner_grade', 40)->nullable()->after('learner_name');
            });
        }
    }
};
