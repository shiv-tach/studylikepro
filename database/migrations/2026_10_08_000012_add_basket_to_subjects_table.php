<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subjects may belong to one basket of their level; null means "not a basket
 * subject". Deleting a basket only detaches its subjects so no syllabus row is
 * ever lost silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('basket_id')->nullable()->after('education_level_id')
                ->constrained('subject_baskets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('basket_id');
        });
    }
};
