<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_minor');
            $table->unsignedInteger('reversed_minor')->default(0);
            $table->string('currency', 3)->default('LKR');
            $table->string('status', 30)->default('pending');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique('booking_id');
            $table->index(['teacher_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_earnings');
    }
};
