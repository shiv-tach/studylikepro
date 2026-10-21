<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            // Set when the teacher asks to teach a subject that already exists
            // in the catalog; null when they propose a brand-new subject.
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            // The level (O/L, A/L, ...) the proposed subject belongs to.
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('proposed_name')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('teacher_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_requests');
    }
};
