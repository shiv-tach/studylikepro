<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_offerings', function (Blueprint $table) {
            $table->foreignId('grade_id')
                ->nullable()
                ->after('lesson_id')
                ->constrained()
                ->nullOnDelete();
        });

        // Lesson-scoped offerings already imply a grade through their lesson;
        // copy it over so the grade scope is explicit everywhere.
        $rows = DB::table('service_offerings')
            ->join('lessons', 'lessons.id', '=', 'service_offerings.lesson_id')
            ->select('service_offerings.id as offering_id', 'lessons.grade_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('service_offerings')
                ->where('id', $row->offering_id)
                ->update(['grade_id' => $row->grade_id]);
        }

        // Teachers can now price per grade within one subject (e.g. Grade 10
        // paper marking vs Grade 11 paper marking), so the scope key includes
        // the grade. Multiple NULL grades coexist on MySQL and SQLite.
        Schema::table('service_offerings', function (Blueprint $table) {
            // On MySQL the teacher FK is backed by the scope unique index (it
            // is the leftmost-prefix match), so give it its own index before
            // the unique can be replaced.
            $table->index('teacher_profile_id');
            $table->dropUnique('service_offerings_scope_unique');
            $table->unique(
                ['teacher_profile_id', 'service_type', 'subject_id', 'lesson_id', 'grade_id'],
                'service_offerings_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('service_offerings', function (Blueprint $table) {
            $table->index('teacher_profile_id');
            $table->dropUnique('service_offerings_scope_unique');
            $table->unique(
                ['teacher_profile_id', 'service_type', 'subject_id', 'lesson_id'],
                'service_offerings_scope_unique'
            );
            $table->dropConstrainedForeignId('grade_id');
        });
    }
};
