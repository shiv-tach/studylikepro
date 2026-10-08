<?php

use Database\Seeders\SubjectBasketSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bootstraps the O/L basket rows and maps the O/L subjects that already exist
 * onto them (best effort by slug, level prefix included).
 *
 * The full official basket subject list ships with CatalogSeeder, which is
 * idempotent and adds the missing subjects (Grades 10-11) on the next seed.
 */
return new class extends Migration
{
    /** Existing O/L subjects -> the basket they belong to. */
    private const SUBJECT_BASKETS = [
        'ict' => 'category_2',
        'health-physical-education' => 'category_2',
        'geography' => 'category_3',
        'commerce' => 'category_3',
    ];

    public function up(): void
    {
        (new SubjectBasketSeeder)->run();

        $levelId = DB::table('education_levels')->where('key', 'ol')->value('id');

        if ($levelId === null) {
            return;
        }

        $basketIds = DB::table('subject_baskets')
            ->where('education_level_id', $levelId)
            ->pluck('id', 'key');

        foreach (self::SUBJECT_BASKETS as $slug => $basketKey) {
            $basketId = $basketIds[$basketKey] ?? null;

            if ($basketId === null) {
                continue;
            }

            DB::table('subjects')
                ->where('education_level_id', $levelId)
                ->whereIn('slug', [$slug, 'ol-'.$slug])
                ->whereNull('basket_id')
                ->update(['basket_id' => $basketId]);
        }
    }

    public function down(): void
    {
        // Baskets are reference data; the rows themselves are dropped with the
        // table in 2026_10_08_000012.
    }
};
