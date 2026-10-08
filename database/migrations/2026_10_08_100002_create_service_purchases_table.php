<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offering_id')->constrained('service_offerings')->restrictOnDelete();
            $table->string('service_type', 30);
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('pending_payment');
            $table->unsignedInteger('price_minor');
            $table->unsignedTinyInteger('commission_percent');
            $table->unsignedInteger('platform_fee_minor');
            $table->unsignedInteger('teacher_payout_minor');
            $table->unsignedInteger('booking_fee_minor')->default(0);
            $table->unsignedInteger('booking_fee_discount_minor')->default(0);
            $table->foreignId('booking_fee_promotion_id')->nullable()->constrained('booking_fee_promotions')->nullOnDelete();
            $table->string('currency', 3)->default('LKR');
            // Lesson plans: time allocation bookkeeping.
            $table->unsignedInteger('allocation_minutes')->nullable();
            $table->unsignedInteger('used_minutes')->default(0);
            $table->unsignedSmallInteger('max_sessions')->nullable();
            // Paper-marking SLA snapshot (per order convenience copy).
            $table->unsignedSmallInteger('turnaround_hours')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['teacher_profile_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_purchases');
    }
};
