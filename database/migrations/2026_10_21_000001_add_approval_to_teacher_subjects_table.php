<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_subjects', function (Blueprint $table) {
            // A subject a teacher picks during onboarding starts pending and is
            // approved by the admin alongside the verification review.
            $table->string('status')->default('pending')->after('grade_rates');
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->index('status');
        });

        // Every row written before this feature existed was implicitly
        // approved (there was no approval step to go through).
        DB::table('teacher_subjects')->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('teacher_subjects', function (Blueprint $table) {
            $table->dropColumn(['status', 'approved_at']);
        });
    }
};
