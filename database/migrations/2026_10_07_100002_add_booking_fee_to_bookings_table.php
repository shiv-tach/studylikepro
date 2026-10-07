<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The student-facing platform fee charged on top of the lesson price, together
 * with any special-offer discount that was applied when the slot was reserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('booking_fee_minor')->default(0);
            $table->unsignedInteger('booking_fee_discount_minor')->default(0);
            $table->foreignId('booking_fee_promotion_id')
                ->nullable()
                ->constrained('booking_fee_promotions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_fee_promotion_id');
            $table->dropColumn(['booking_fee_minor', 'booking_fee_discount_minor']);
        });
    }
};
