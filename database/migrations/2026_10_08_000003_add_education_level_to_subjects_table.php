<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('education_level_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique('subjects_slug_unique');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->unique(['education_level_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropForeign(['education_level_id']);
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique('subjects_education_level_id_slug_unique');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->unique(['slug']);
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('education_level_id');
        });
    }
};
