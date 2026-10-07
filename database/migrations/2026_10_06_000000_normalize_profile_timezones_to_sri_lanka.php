<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The marketplace now runs entirely on Sri Lanka time and no longer asks
     * users for a timezone, so existing rows move to Asia/Colombo.
     */
    public function up(): void
    {
        DB::table('student_profiles')
            ->where('timezone', '!=', 'Asia/Colombo')
            ->update(['timezone' => 'Asia/Colombo']);

        DB::table('teacher_profiles')
            ->where('timezone', '!=', 'Asia/Colombo')
            ->update(['timezone' => 'Asia/Colombo']);
    }

    public function down(): void
    {
        // The previous per-user timezones are not retained, so this is not reversible.
    }
};
