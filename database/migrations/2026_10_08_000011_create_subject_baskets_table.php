<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Basket" subject groups inside an education level. In the O/L the optional
 * subjects of Grades 10-11 are split into three categories and a candidate
 * picks one subject from each; a subject belongs to at most one basket.
 *
 * A basket is scoped to one level, so the same table can later carry the A/L
 * streams or any other per-level grouping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_baskets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('education_level_id')->constrained()->cascadeOnDelete();
            $table->string('key', 32);
            $table->string('name', 60);
            $table->string('icon', 16)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['education_level_id', 'key']);
            $table->index(['education_level_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_baskets');
    }
};
