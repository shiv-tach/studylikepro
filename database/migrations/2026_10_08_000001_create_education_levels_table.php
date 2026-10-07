<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_levels', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name', 60);
            $table->unsignedTinyInteger('grade_min')->nullable();
            $table->unsignedTinyInteger('grade_max')->nullable();
            $table->string('icon', 16)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_levels');
    }
};
