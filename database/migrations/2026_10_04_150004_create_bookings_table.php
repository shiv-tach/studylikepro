<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tutoring_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('pending_payment');
            $table->unsignedInteger('price_minor');
            $table->unsignedTinyInteger('commission_percent');
            $table->unsignedInteger('platform_fee_minor');
            $table->unsignedInteger('teacher_payout_minor');
            $table->string('currency', 3)->default('LKR');
            $table->string('learner_name')->nullable();
            $table->string('learner_grade', 40)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('meeting_provider')->nullable();
            $table->string('meeting_url')->nullable();
            $table->timestamps();

            $table->index(['teacher_profile_id', 'starts_at']);
            $table->index(['student_id', 'starts_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
