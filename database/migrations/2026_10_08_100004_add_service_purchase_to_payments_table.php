<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('service_purchase_id')
                ->nullable()
                ->after('booking_id')
                ->constrained('service_purchases')
                ->cascadeOnDelete();

            $table->index(['service_purchase_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_purchase_id');
        });
    }
};
