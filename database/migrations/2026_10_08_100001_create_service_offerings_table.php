<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->string('service_type', 30);
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_minor');
            $table->string('currency', 3)->default('LKR');
            // Lesson plans: total time allocation and session constraints.
            $table->unsignedInteger('allocation_minutes')->nullable();
            $table->unsignedInteger('default_session_minutes')->nullable();
            $table->unsignedSmallInteger('max_sessions')->nullable();
            // Paper marking: per-paper limits and turnaround SLA.
            $table->unsignedSmallInteger('max_pages')->nullable();
            $table->unsignedSmallInteger('turnaround_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['service_type', 'is_active']);
            $table->index(['subject_id', 'is_active']);
            $table->unique(
                ['teacher_profile_id', 'service_type', 'subject_id', 'lesson_id'],
                'service_offerings_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_offerings');
    }
};
