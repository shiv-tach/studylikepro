<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The price the invite survey collected for each service the teacher picked,
     * keyed by `App\Enums\ServiceType` value and stored in minor units.
     *
     * Lessons are priced by `hourly_rate_minor`, so this map only holds the other
     * services. The services console uses it to pre-fill a new offering.
     */
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->json('service_rates')->nullable()->after('hourly_rate_minor');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropColumn('service_rates');
        });
    }
};
