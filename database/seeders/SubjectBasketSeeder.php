<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates the subject baskets each education level defines - the O/L's three
 * categories (I, II and III). Idempotent: existing rows, including admin
 * renames, are left untouched; only missing baskets are inserted.
 */
class SubjectBasketSeeder extends Seeder
{
    public function run(): void
    {
        $levelIds = DB::table('education_levels')->pluck('id', 'key');

        foreach (config('studylikepro.education_levels') as $levelKey => $level) {
            $levelId = $levelIds[$levelKey] ?? null;

            if ($levelId === null) {
                continue;
            }

            $sortOrder = 0;

            foreach ($level['baskets'] ?? [] as $basketKey => $basket) {
                $exists = DB::table('subject_baskets')
                    ->where('education_level_id', $levelId)
                    ->where('key', $basketKey)
                    ->exists();

                if (! $exists) {
                    DB::table('subject_baskets')->insert([
                        'education_level_id' => $levelId,
                        'key' => $basketKey,
                        'name' => $basket['name'],
                        'icon' => $basket['icon'] ?? null,
                        'sort_order' => $sortOrder,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $sortOrder++;
            }
        }
    }
}
