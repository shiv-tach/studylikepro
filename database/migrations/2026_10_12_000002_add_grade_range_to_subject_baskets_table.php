<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The grades a basket runs in: the O/L's Category I starts in Grade 6 (the
 * junior grades pick from that basket alone), Categories II and III in Grade
 * 10. A null range means every grade of the level.
 *
 * Baskets that already exist get the range of their catalog config, so a
 * seeded installation keeps behaviour in step with a fresh one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_baskets', function (Blueprint $table) {
            $table->unsignedTinyInteger('grade_min')->nullable()->after('description');
            $table->unsignedTinyInteger('grade_max')->nullable()->after('grade_min');
        });

        $this->backfillGradeRanges();
    }

    public function down(): void
    {
        Schema::table('subject_baskets', function (Blueprint $table) {
            $table->dropColumn(['grade_min', 'grade_max']);
        });
    }

    /**
     * Fills the configured grade range into baskets created before these
     * columns existed; an admin's own values are left alone.
     */
    private function backfillGradeRanges(): void
    {
        $baskets = DB::table('subject_baskets')
            ->join('education_levels', 'education_levels.id', '=', 'subject_baskets.education_level_id')
            ->whereNull('subject_baskets.grade_min')
            ->whereNull('subject_baskets.grade_max')
            ->get([
                'subject_baskets.id',
                'subject_baskets.key',
                'education_levels.key as level_key',
            ]);

        foreach ($baskets as $basket) {
            $grades = config("studylikepro.education_levels.{$basket->level_key}.baskets.{$basket->key}.grades");

            if (! is_array($grades) || $grades === []) {
                continue;
            }

            DB::table('subject_baskets')->where('id', $basket->id)->update([
                'grade_min' => min($grades),
                'grade_max' => max($grades),
            ]);
        }
    }
};
