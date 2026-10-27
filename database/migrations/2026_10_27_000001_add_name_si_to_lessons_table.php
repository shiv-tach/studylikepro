<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every lesson now carries the official English title plus its Sinhala
 * subtitle. The name column also widens: official unit titles (especially in
 * Sinhala and A/L subjects) run past the old 100 character limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('name', 150)->change();
            $table->string('name_si', 150)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('name_si');
            $table->string('name', 100)->change();
        });
    }
};
