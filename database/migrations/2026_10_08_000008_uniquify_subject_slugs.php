<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subject slugs become globally unique. Route model binding resolves a Subject
 * from its slug alone, so two levels owning a subject with the same name
 * ("Mathematics" in Primary and O/L) made every {subject} URL ambiguous — the
 * first match won. Colliding slugs are prefixed with their level key
 * ("mathematics" → "ol-mathematics"), matching CatalogSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $subjects = DB::table('subjects')
            ->leftJoin('education_levels', 'education_levels.id', '=', 'subjects.education_level_id')
            ->orderBy('subjects.id')
            ->get([
                'subjects.id',
                'subjects.slug',
                DB::raw("coalesce(education_levels.key, 'subject') as level_key"),
            ]);

        $seen = [];

        foreach ($subjects as $subject) {
            $slug = $subject->slug;

            if (isset($seen[$slug])) {
                $slug = $subject->level_key.'-'.$slug;

                while (isset($seen[$slug])) {
                    $slug = $subject->level_key.'-'.$slug;
                }

                DB::table('subjects')->where('id', $subject->id)->update(['slug' => $slug]);
            }

            $seen[$slug] = true;
        }

        // MySQL/MariaDB use the composite unique as the foreign key's
        // supporting index, so give the FK its own index before dropping it.
        if (! Schema::hasIndex('subjects', 'subjects_education_level_id_index')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->index('education_level_id');
            });
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique('subjects_education_level_id_slug_unique');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique('subjects_slug_unique');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->unique(['education_level_id', 'slug']);
        });
    }
};
