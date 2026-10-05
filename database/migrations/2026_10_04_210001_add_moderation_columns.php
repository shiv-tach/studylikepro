<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('notification_preferences');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
        });

        Schema::table('disputes', function (Blueprint $table) {
            // Who the report is about (the counterpart of the reporter).
            $table->foreignId('against_id')->nullable()->after('raised_by')->constrained('users')->nullOnDelete();
            // How support closed it, and the refund it produced when money moved.
            $table->string('resolution', 40)->nullable()->after('status');
            $table->foreignId('refund_id')->nullable()->after('resolution')->constrained('refunds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('against_id');
            $table->dropConstrainedForeignId('refund_id');
            $table->dropColumn('resolution');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};
