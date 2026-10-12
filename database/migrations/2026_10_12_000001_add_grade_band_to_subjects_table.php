<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which grades of its level a subject is offered to: "all" (the default),
 * "junior" (up to Grade 9) or "senior" (Grades 10-11). An admin picks it in
 * the subject form, and the pickers keep a subject out of the grades it does
 * not cover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('grade_band', 16)->default('all')->after('basket_id');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('grade_band');
        });
    }
};
