<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_subjects', function (Blueprint $table) {
            // Explicit rates per grade, keyed by grade id and stored in minor
            // units, e.g. {"6": 150000, "10": 200000}. A grade without an
            // entry falls back to rate_per_hour_minor, then the base rate.
            $table->json('grade_rates')->nullable()->after('rate_per_hour_minor');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_subjects', function (Blueprint $table) {
            $table->dropColumn('grade_rates');
        });
    }
};
