<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The service type no longer exists in the enum, so any leftover rows
        // would fail to hydrate. Purchases go first because offerings are
        // restricted while they are referenced; payments, earnings, bookings
        // and summaries cascade from the purchase.
        DB::table('service_purchases')->where('service_type', 'summary')->delete();
        DB::table('service_offerings')->where('service_type', 'summary')->delete();

        Schema::dropIfExists('lesson_summaries');
    }

    public function down(): void
    {
        // The lesson-summary service has been removed; rolling back does not
        // restore the table.
    }
};
