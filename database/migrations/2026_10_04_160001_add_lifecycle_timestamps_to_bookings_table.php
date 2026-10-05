<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('expires_at');
            $table->timestamp('started_at')->nullable()->after('confirmed_at');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('reminder_sent_at')->nullable()->after('completed_at');

            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'starts_at']);
            $table->dropColumn(['confirmed_at', 'started_at', 'completed_at', 'reminder_sent_at']);
        });
    }
};
