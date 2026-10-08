<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one-line blurb a basket shows next to its name in the subject pickers
 * ("Aesthetic, music, dancing, literature and drama"), so a Grade 10-11
 * student can tell the three O/L categories apart at a glance.
 *
 * The baskets themselves are created by 2026_10_08_000013, which runs before
 * this column exists, so the seeded text is filled in here rather than in
 * SubjectBasketSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_baskets', function (Blueprint $table) {
            $table->string('description', 150)->nullable()->after('name');
        });

        $this->backfillDescriptions();
    }

    public function down(): void
    {
        Schema::table('subject_baskets', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }

    /**
     * Fills the seeded descriptions into baskets created before this column
     * existed; an admin's own text is left alone.
     */
    private function backfillDescriptions(): void
    {
        $levelIds = DB::table('education_levels')->pluck('id', 'key');

        foreach (config('studylikepro.education_levels') as $levelKey => $level) {
            $levelId = $levelIds[$levelKey] ?? null;

            if ($levelId === null) {
                continue;
            }

            foreach ($level['baskets'] ?? [] as $basketKey => $basket) {
                if (! isset($basket['description'])) {
                    continue;
                }

                DB::table('subject_baskets')
                    ->where('education_level_id', $levelId)
                    ->where('key', $basketKey)
                    ->whereNull('description')
                    ->update(['description' => $basket['description']]);
            }
        }
    }
};
