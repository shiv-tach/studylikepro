<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_summaries', function (Blueprint $table) {
            $table->id();
            // One personalised summary per purchased summary service.
            $table->foreignId('purchase_id')->unique()->constrained('service_purchases')->cascadeOnDelete();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('content')->nullable();
            // draft → delivered; delivery completes the purchase.
            $table->string('status', 30)->default('draft');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_summaries');
    }
};
