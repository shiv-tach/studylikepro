<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One draft row per teacher. The payload keeps the answers of every wizard step
 * that is not saved yet, so a reload, a closed tab or a dropped connection can
 * restore the form the teacher was working on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_onboarding_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('payload')->nullable();
            $table->string('current_step', 32)->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_onboarding_drafts');
    }
};
