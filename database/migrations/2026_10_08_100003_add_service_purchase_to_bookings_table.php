<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('service_purchase_id')
                ->nullable()
                ->after('lesson_id')
                ->constrained('service_purchases')
                ->cascadeOnDelete();
            $table->string('service_type', 30)->nullable()->after('service_purchase_id');

            $table->index(['service_purchase_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_purchase_id');
            $table->dropColumn('service_type');
        });
    }
};
