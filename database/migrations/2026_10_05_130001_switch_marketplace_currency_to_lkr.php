<?php

use App\Services\PlatformSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The marketplace switched from Indian rupees to Sri Lankan rupees. New rows
 * already default to LKR; this moves everything recorded before the switch so
 * balances, receipts and exports all read one currency.
 */
return new class extends Migration
{
    /**
     * Tables that carry a currency column.
     */
    private const TABLES = ['bookings', 'payments', 'payouts', 'teacher_earnings'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->where('currency', 'INR')->update(['currency' => 'LKR']);
        }

        DB::table('platform_settings')
            ->where('key', 'currency')
            ->where('value', 'INR')
            ->update(['value' => 'LKR']);

        // Settings are cached forever, so the running app must forget the old copy.
        app(PlatformSettings::class)->flush();
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->where('currency', 'LKR')->update(['currency' => 'INR']);
        }

        DB::table('platform_settings')
            ->where('key', 'currency')
            ->where('value', 'LKR')
            ->update(['value' => 'INR']);

        app(PlatformSettings::class)->flush();
    }
};
