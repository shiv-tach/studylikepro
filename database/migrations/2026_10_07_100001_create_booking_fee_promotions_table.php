<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Special offers that discount (or waive) the student booking fee while they
 * are running. `discount_value` is a percentage for `percent` offers and an
 * amount in minor units for `fixed` ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_fee_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('discount_type', 20);
            $table->unsignedInteger('discount_value')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_fee_promotions');
    }
};
