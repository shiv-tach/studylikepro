<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The medium of instruction a student follows. The onboarding wizard asks for
 * it after the grade (and its O/L baskets) so teachers know whether the lesson
 * should be taught in Sinhala or English.
 *
 * Nullable because profiles created before the wizard existed have no answer
 * yet; the wizard makes it required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('learning_language', 32)->nullable()->after('grade_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn('learning_language');
        });
    }
};
