<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->foreignId('grade_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        // The coarse bucket is replaced by grade_id; it stays for rollback
        // safety until the legacy column is dropped, but is no longer required.
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('grade_level')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_id');
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('grade_level')->nullable(false)->change();
        });
    }
};
