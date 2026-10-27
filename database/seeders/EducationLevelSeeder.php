<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates the Sri Lankan education levels the catalog seeds - O/L (Grades
 * 6-11) and A/L (Grades 12-13) - and their grades. Idempotent: existing rows -
 * including any admin renames - are left untouched, only missing rows are
 * inserted.
 */
class EducationLevelSeeder extends Seeder
{
    public function run(): void
    {
        $sortOrder = 0;

        foreach (config('studylikepro.education_levels') as $key => $level) {
            $levelId = DB::table('education_levels')->where('key', $key)->value('id');

            if ($levelId === null) {
                $levelId = DB::table('education_levels')->insertGetId([
                    'key' => $key,
                    'name' => $level['name'],
                    'grade_min' => $level['grade_min'],
                    'grade_max' => $level['grade_max'],
                    'icon' => $level['icon'],
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $grades = array_map(
                fn (int $number) => ['number' => $number, 'label' => "Grade {$number}", 'sort_order' => $number],
                range($level['grade_min'], $level['grade_max']),
            );

            foreach ($grades as $grade) {
                $exists = DB::table('grades')
                    ->where('education_level_id', $levelId)
                    ->where('number', $grade['number'])
                    ->exists();

                if (! $exists) {
                    DB::table('grades')->insert([
                        'education_level_id' => $levelId,
                        'number' => $grade['number'],
                        'label' => $grade['label'],
                        'sort_order' => $grade['sort_order'],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $sortOrder++;
        }
    }
}
