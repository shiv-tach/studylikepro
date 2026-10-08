<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paper_marking_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->unique()->constrained('service_purchases')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_profile_id')->constrained('teacher_profiles')->cascadeOnDelete();
            $table->foreignId('offering_id')->constrained('service_offerings')->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('status', 30)->default('submitted');
            $table->json('files')->nullable();
            $table->json('marked_files')->nullable();
            $table->text('feedback')->nullable();
            $table->json('marks')->nullable();
            $table->unsignedSmallInteger('max_pages')->nullable();
            $table->unsignedSmallInteger('turnaround_hours')->nullable();
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_profile_id', 'status']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_marking_orders');
    }
};
