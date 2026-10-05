<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /**
     * Seed the starter curriculum used by search, matching, and AI classification.
     */
    public function run(): void
    {
        $catalog = [
            ['name' => 'Mathematics', 'icon' => '🔢', 'topics' => ['Algebra', 'Geometry', 'Calculus', 'Trigonometry', 'Statistics']],
            ['name' => 'Physics', 'icon' => '⚛️', 'topics' => ['Mechanics', 'Electricity & Magnetism', 'Thermodynamics', 'Optics', 'Modern Physics']],
            ['name' => 'Chemistry', 'icon' => '🧪', 'topics' => ['Organic Chemistry', 'Inorganic Chemistry', 'Physical Chemistry', 'Chemical Equations', 'Periodic Table']],
            ['name' => 'Biology', 'icon' => '🧬', 'topics' => ['Cell Biology', 'Genetics', 'Human Physiology', 'Ecology', 'Evolution']],
            ['name' => 'English', 'icon' => '📚', 'topics' => ['Grammar', 'Creative Writing', 'Literature', 'Reading Comprehension', 'Spoken English']],
            ['name' => 'Computer Science', 'icon' => '💻', 'topics' => ['Programming Basics', 'Python', 'Web Development', 'Data Structures', 'Algorithms']],
        ];

        foreach ($catalog as $subjectSort => $data) {
            $subject = Subject::query()->firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'icon' => $data['icon'],
                    'is_active' => true,
                    'sort_order' => $subjectSort,
                ]
            );

            foreach ($data['topics'] as $topicSort => $topicName) {
                Topic::query()->firstOrCreate(
                    ['subject_id' => $subject->id, 'slug' => Str::slug($topicName)],
                    [
                        'name' => $topicName,
                        'is_active' => true,
                        'sort_order' => $topicSort,
                    ]
                );
            }
        }
    }
}
