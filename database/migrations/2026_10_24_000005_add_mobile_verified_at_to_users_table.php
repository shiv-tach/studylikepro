<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records when a mobile number was proved by SMS passcode.
 *
 * Registration verifies the number before the account is created, so new rows
 * always carry this stamp. Accounts that existed before SMS verification are
 * treated as trusted: their mobile was never challenged, and leaving the column
 * null would wrongly mark them unverified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('mobile_verified_at')->nullable()->after('mobile');
        });

        DB::table('users')
            ->whereNotNull('mobile')
            ->update(['mobile_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mobile_verified_at');
        });
    }
};
