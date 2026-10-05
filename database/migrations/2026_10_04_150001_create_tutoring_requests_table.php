<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutoring_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->string('classification_status')->default('pending');
            $table->decimal('ai_confidence', 4, 3)->nullable();
            $table->json('ai_payload')->nullable();
            $table->string('image_hash', 64)->nullable();
            $table->json('preferred_windows');
            $table->unsignedInteger('budget_minor')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['topic_id', 'status']);
            $table->index('image_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutoring_requests');
    }
};
