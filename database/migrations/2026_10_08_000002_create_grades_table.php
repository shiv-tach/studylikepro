<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('education_level_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number')->nullable();
            $table->string('label', 40);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['education_level_id', 'number']);
            $table->index(['education_level_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
