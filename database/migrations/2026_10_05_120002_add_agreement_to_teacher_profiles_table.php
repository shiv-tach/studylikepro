<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            // Teachers must accept the terms and the teacher agreement before an
            // admin reviews their documents; the version records what they saw.
            $table->dateTime('agreement_accepted_at')->nullable()->after('completed_at');
            $table->string('agreement_version', 20)->nullable()->after('agreement_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropColumn(['agreement_accepted_at', 'agreement_version']);
        });
    }
};
