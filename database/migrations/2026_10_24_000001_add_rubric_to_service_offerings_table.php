<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A paper-marking offering may define the rubric its studio grades
     * against. It is nullable on purpose: an offering without criteria keeps
     * the original free-form marks rows.
     */
    public function up(): void
    {
        Schema::table('service_offerings', function (Blueprint $table) {
            $table->json('rubric')->nullable()->after('turnaround_hours');
        });
    }

    public function down(): void
    {
        Schema::table('service_offerings', function (Blueprint $table) {
            $table->dropColumn('rubric');
        });
    }
};
