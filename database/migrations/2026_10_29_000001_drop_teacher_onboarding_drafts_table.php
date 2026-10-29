<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The invite survey replaced the signed-in onboarding wizard, so there is no
 * half-typed step left to autosave. The old create migration stays for history;
 * this one removes the table from existing databases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('teacher_onboarding_drafts');
    }

    public function down(): void
    {
        Schema::create('teacher_onboarding_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('payload')->nullable();            $table->string('current_step', 32)->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();
        });
    }
};
