<?php

use Database\Seeders\EducationLevelSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bootstraps the education level/grade rows and re-maps the existing catalog,
 * teacher, student and booking data onto the new grade dimension.
 *
 * Existing lessons have no grade, so each one is attached to the lowest grade
 * of its subject's level ("canonical" copy) - the admin bulk-copy tools can
 * later duplicate them across the remaining grades.
 */
return new class extends Migration
{
    /** Legacy coarse buckets -> grade numbers they used to cover. */
    private const BUCKET_GRADES = [
        'primary' => [1, 2, 3, 4, 5],
        'middle_school' => [6, 7, 8, 9],
        'high_school' => [10, 11, 12, 13],
        'college' => [12, 13],
        'adult' => [],
    ];

    /** Legacy bucket -> representative grade number (null = "Other"). */
    private const BUCKET_REPRESENTATIVE = [
        'primary' => 5,
        'middle_school' => 9,
        'high_school' => 11,
        'college' => 13,
        'adult' => null,
    ];

    /** Best-effort level for the subjects that already existed. */
    private const SUBJECT_LEVELS = [
        'mathematics' => 'ol',
        'science' => 'ol',
        'physics' => 'ol',
        'chemistry' => 'ol',
        'biology' => 'ol',
        'english' => 'ol',
        'sinhala' => 'ol',
        'tamil' => 'ol',
        'history' => 'ol',
        'buddhism' => 'ol',
        'ict' => 'ol',
        'commerce' => 'ol',
        'geography' => 'ol',
        'computer-science' => 'al',
        'combined-mathematics' => 'al',
        'accounting' => 'al',
        'economics' => 'al',
        'business-studies' => 'al',
        'logic' => 'al',
    ];

    public function up(): void
    {
        (new EducationLevelSeeder)->run();

        $levelIds = DB::table('education_levels')->pluck('id', 'key')->all();
        $grades = $this->gradeMap();

        $this->backfillSubjects($levelIds);

        $subjectLevels = DB::table('subjects')
            ->join('education_levels', 'education_levels.id', '=', 'subjects.education_level_id')
            ->pluck('education_levels.key', 'subjects.id')
            ->all();

        $this->backfillLessons($subjectLevels, $grades);
        $this->remapTeacherGrades($subjectLevels, $grades);
        $this->backfillStudentGrades($grades);
        $this->backfillBookingGrades($grades);
        $this->backfillRequestGrades();
    }

    public function down(): void
    {
        // Restore the legacy bucket values the teacher pivots held before.
        $legacy = [];
        foreach (DB::table('grades')->get(['id', 'number']) as $grade) {
            $legacy[$grade->id] = $this->legacyBucketFor($grade->number);
        }

        foreach (DB::table('teacher_subjects')->get(['id', 'grade_levels']) as $row) {
            $buckets = [];
            foreach (json_decode((string) $row->grade_levels, true) ?: [] as $id) {
                if (isset($legacy[$id]) && ! in_array($legacy[$id], $buckets, true)) {
                    $buckets[] = $legacy[$id];
                }
            }

            DB::table('teacher_subjects')->where('id', $row->id)->update([
                'grade_levels' => json_encode($buckets),
            ]);
        }

        DB::table('subjects')->update(['education_level_id' => null]);
        DB::table('lessons')->update(['grade_id' => null]);
        DB::table('tutoring_requests')->update(['grade_id' => null]);
        DB::table('student_profiles')->update(['grade_id' => null]);
        DB::table('bookings')->update(['learner_grade_id' => null]);
    }

    /**
     * @return array<string, array<int|string, int>>
     */
    private function gradeMap(): array
    {
        $grades = [];

        foreach (DB::table('grades')->get(['id', 'number', 'education_level_id']) as $grade) {
            $grades[$grade->education_level_id][$grade->number ?? 'other'] = $grade->id;
        }

        $levels = [];

        foreach (DB::table('education_levels')->get(['id', 'key']) as $level) {
            $levels[$level->key] = $grades[$level->id] ?? [];
        }

        return $levels;
    }

    /**
     * @param  array<string, int>  $levelIds
     */
    private function backfillSubjects(array $levelIds): void
    {
        foreach (DB::table('subjects')->whereNull('education_level_id')->get(['id', 'slug']) as $subject) {
            $key = self::SUBJECT_LEVELS[$subject->slug] ?? 'other';
            $levelId = $levelIds[$key] ?? $levelIds['other'] ?? null;

            if ($levelId === null) {
                continue;
            }

            DB::table('subjects')->where('id', $subject->id)->update(['education_level_id' => $levelId]);
        }
    }

    /**
     * @param  array<int|string, string>  $subjectLevels
     * @param  array<string, array<int|string, int>>  $grades
     */
    private function backfillLessons(array $subjectLevels, array $grades): void
    {
        foreach (DB::table('lessons')->whereNull('grade_id')->get(['id', 'subject_id']) as $lesson) {
            $levelKey = $subjectLevels[$lesson->subject_id] ?? null;

            if ($levelKey === null) {
                continue;
            }

            $gradeId = $this->firstGradeOf($grades[$levelKey] ?? []);

            if ($gradeId !== null) {
                DB::table('lessons')->where('id', $lesson->id)->update(['grade_id' => $gradeId]);
            }
        }
    }

    /**
     * @param  array<int|string, string>  $subjectLevels
     * @param  array<string, array<int|string, int>>  $grades
     */
    private function remapTeacherGrades(array $subjectLevels, array $grades): void
    {
        foreach (DB::table('teacher_subjects')->get(['id', 'subject_id', 'grade_levels']) as $row) {
            $levelKey = $subjectLevels[$row->subject_id] ?? null;
            $levelGrades = $levelKey !== null ? ($grades[$levelKey] ?? []) : [];

            $mapped = [];

            foreach (json_decode((string) $row->grade_levels, true) ?: [] as $bucket) {
                if (! is_string($bucket)) {
                    continue;
                }

                foreach (self::BUCKET_GRADES[$bucket] ?? [] as $number) {
                    if (isset($levelGrades[$number])) {
                        $mapped[] = $levelGrades[$number];
                    }
                }

                if ($bucket === 'adult' && isset($levelGrades['other'])) {
                    $mapped[] = $levelGrades['other'];
                }
            }

            DB::table('teacher_subjects')->where('id', $row->id)->update([
                'grade_levels' => json_encode(array_values(array_unique($mapped))),
            ]);
        }
    }

    /**
     * @param  array<string, array<int|string, int>>  $grades
     */
    private function backfillStudentGrades(array $grades): void
    {
        foreach (DB::table('student_profiles')->whereNull('grade_id')->get(['id', 'grade_level']) as $profile) {
            $gradeId = $this->representativeGrade($profile->grade_level, $grades);

            if ($gradeId !== null) {
                DB::table('student_profiles')->where('id', $profile->id)->update(['grade_id' => $gradeId]);
            }
        }
    }

    /**
     * @param  array<string, array<int|string, int>>  $grades
     */
    private function backfillBookingGrades(array $grades): void
    {
        foreach (DB::table('bookings')->whereNull('learner_grade_id')->whereNotNull('learner_grade')->get(['id', 'learner_grade']) as $booking) {
            $gradeId = $this->representativeGrade($booking->learner_grade, $grades);

            if ($gradeId !== null) {
                DB::table('bookings')->where('id', $booking->id)->update(['learner_grade_id' => $gradeId]);
            }
        }
    }

    private function backfillRequestGrades(): void
    {
        $requests = DB::table('tutoring_requests')
            ->whereNull('grade_id')
            ->whereNotNull('lesson_id')
            ->get(['id', 'lesson_id', 'student_id']);

        foreach ($requests as $request) {
            $gradeId = DB::table('lessons')->where('id', $request->lesson_id)->value('grade_id')
                ?? DB::table('student_profiles')->where('user_id', $request->student_id)->value('grade_id');

            if ($gradeId !== null) {
                DB::table('tutoring_requests')->where('id', $request->id)->update(['grade_id' => $gradeId]);
            }
        }
    }

    /**
     * @param  array<int|string, int>  $grades
     */
    private function firstGradeOf(array $grades): ?int
    {
        $numbers = array_filter(array_keys($grades), fn ($key) => $key !== 'other');

        if ($numbers === []) {
            return $grades['other'] ?? null;
        }

        sort($numbers);

        return $grades[$numbers[0]] ?? null;
    }

    /**
     * @param  array<string, array<int|string, int>>  $grades
     */
    private function representativeGrade(?string $bucket, array $grades): ?int
    {
        $number = self::BUCKET_REPRESENTATIVE[$bucket] ?? null;

        foreach ($grades as $levelGrades) {
            if ($number !== null && isset($levelGrades[$number])) {
                return $levelGrades[$number];
            }

            if ($number === null && isset($levelGrades['other'])) {
                return $levelGrades['other'];
            }
        }

        return null;
    }

    private function legacyBucketFor(?int $number): string
    {
        return match (true) {
            $number === null => 'adult',
            $number <= 5 => 'primary',
            $number <= 9 => 'middle_school',
            $number <= 11 => 'high_school',
            default => 'college',
        };
    }
};
