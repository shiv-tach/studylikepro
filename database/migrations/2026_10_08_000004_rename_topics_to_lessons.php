<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the catalog "topics" to "lessons" and gives every lesson a grade.
 *
 * MariaDB 10.4 has no `ALTER TABLE ... RENAME COLUMN` syntax, so column
 * renames go through `CHANGE COLUMN` there; SQLite (the test database) uses
 * the native rename. Foreign keys and indexes are recreated with their
 * canonical names on MySQL/MariaDB, where the engine keeps the old names.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->renameTopicsToLessons();
        $this->rebuildTeacherPivot();
        $this->rebuildStudentPivot();
        $this->renameTutoringRequestTopic();
        $this->renameBookingTopic();
    }

    public function down(): void
    {
        // Rename the table back first: on MySQL/MariaDB the foreign keys that
        // point at it follow automatically, and the steps below re-add their
        // constraints against the restored "topics" name.
        $this->renameLessonsToTopics();
        $this->renameBookingTopicBack();
        $this->renameTutoringRequestTopicBack();
        $this->rebuildStudentPivotBack();
        $this->rebuildTeacherPivotBack();
    }

    private function renameTopicsToLessons(): void
    {
        Schema::rename('topics', 'lessons');

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('grade_id')->nullable()->after('subject_id')->constrained()->restrictOnDelete();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('slug');
        });

        if ($this->isMysql()) {
            DB::statement('ALTER TABLE `lessons` DROP FOREIGN KEY `topics_subject_id_foreign`');
        }

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropUnique('topics_subject_id_slug_unique');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->unique(['subject_id', 'grade_id', 'slug']);
        });

        if ($this->isMysql()) {
            DB::statement('ALTER TABLE `lessons` ADD CONSTRAINT `lessons_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE');
        }
    }

    private function renameLessonsToTopics(): void
    {
        if ($this->isMysql()) {
            DB::statement('ALTER TABLE `lessons` DROP FOREIGN KEY `lessons_subject_id_foreign`');
        }

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropUnique('lessons_subject_id_grade_id_slug_unique');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_id');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::rename('lessons', 'topics');

        Schema::table('topics', function (Blueprint $table) {
            $table->unique(['subject_id', 'slug']);
        });

        if ($this->isMysql()) {
            DB::statement('ALTER TABLE `topics` ADD CONSTRAINT `topics_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE');
        }
    }

    private function rebuildTeacherPivot(): void
    {
        Schema::create('teacher_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['teacher_profile_id', 'lesson_id']);
        });

        DB::table('teacher_lessons')->insertUsing(
            ['teacher_profile_id', 'lesson_id', 'created_at', 'updated_at'],
            DB::table('teacher_topics')->select('teacher_profile_id', 'topic_id', 'created_at', 'updated_at'),
        );

        Schema::drop('teacher_topics');
    }

    private function rebuildTeacherPivotBack(): void
    {
        Schema::create('teacher_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['teacher_profile_id', 'topic_id']);
        });

        DB::table('teacher_topics')->insertUsing(
            ['teacher_profile_id', 'topic_id', 'created_at', 'updated_at'],
            DB::table('teacher_lessons')->select('teacher_profile_id', 'lesson_id', 'created_at', 'updated_at'),
        );

        Schema::drop('teacher_lessons');
    }

    private function rebuildStudentPivot(): void
    {
        Schema::create('student_lesson_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id']);
        });

        DB::table('student_lesson_interests')->insertUsing(
            ['user_id', 'lesson_id', 'created_at', 'updated_at'],
            DB::table('student_topic_interests')->select('user_id', 'topic_id', 'created_at', 'updated_at'),
        );

        Schema::drop('student_topic_interests');
    }

    private function rebuildStudentPivotBack(): void
    {
        Schema::create('student_topic_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'topic_id']);
        });

        DB::table('student_topic_interests')->insertUsing(
            ['user_id', 'topic_id', 'created_at', 'updated_at'],
            DB::table('student_lesson_interests')->select('user_id', 'lesson_id', 'created_at', 'updated_at'),
        );

        Schema::drop('student_lesson_interests');
    }

    private function renameTutoringRequestTopic(): void
    {
        if ($this->isMysql()) {
            DB::statement('ALTER TABLE `tutoring_requests` DROP FOREIGN KEY `tutoring_requests_topic_id_foreign`');
        }

        $this->renameColumn('tutoring_requests', 'topic_id', 'lesson_id', nullable: true);

        Schema::table('tutoring_requests', function (Blueprint $table) {
            $table->foreignId('grade_id')->nullable()->after('subject_id')->constrained()->nullOnDelete();
        });

        Schema::table('tutoring_requests', function (Blueprint $table) {
            $table->dropIndex('tutoring_requests_topic_id_status_index');
        });

        Schema::table('tutoring_requests', function (Blueprint $table) {
            $table->index(['lesson_id', 'status']);
        });

        if ($this->isMysql()) {
            DB::statement('ALTER TABLE `tutoring_requests` ADD CONSTRAINT `tutoring_requests_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL');
        }
    }

    private function renameTutoringRequestTopicBack(): void
    {
        if ($this->isMysql()) {
            DB::statement('ALTER TABLE `tutoring_requests` DROP FOREIGN KEY `tutoring_requests_lesson_id_foreign`');
        }

        Schema::table('tutoring_requests', function (Blueprint $table) {
            $table->dropIndex('tutoring_requests_lesson_id_status_index');
        });

        $this->renameColumn('tutoring_requests', 'lesson_id', 'topic_id', nullable: true);

        Schema::table('tutoring_requests', function (Blueprint $table) {
            $table->index(['topic_id', 'status']);
        });

        Schema::table('tutoring_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_id');
        });

        if ($this->isMysql()) {
            DB::statement('ALTER TABLE `tutoring_requests` ADD CONSTRAINT `tutoring_requests_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL');
        }
    }

    private function renameBookingTopic(): void
    {
        if ($this->isMysql()) {
            DB::statement('ALTER TABLE `bookings` DROP FOREIGN KEY `bookings_topic_id_foreign`');
        }

        $this->renameColumn('bookings', 'topic_id', 'lesson_id', nullable: true);

        if ($this->isMysql()) {
            DB::statement('DROP INDEX `bookings_topic_id_foreign` ON `bookings`');
            DB::statement('ALTER TABLE `bookings` ADD CONSTRAINT `bookings_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL');
        }
    }

    private function renameBookingTopicBack(): void
    {
        if ($this->isMysql()) {
            DB::statement('ALTER TABLE `bookings` DROP FOREIGN KEY `bookings_lesson_id_foreign`');
            DB::statement('DROP INDEX `bookings_lesson_id_foreign` ON `bookings`');
        }

        $this->renameColumn('bookings', 'lesson_id', 'topic_id', nullable: true);

        if ($this->isMysql()) {
            DB::statement('ALTER TABLE `bookings` ADD CONSTRAINT `bookings_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL');
        }
    }

    private function renameColumn(string $table, string $from, string $to, bool $nullable): void
    {
        if ($this->isMysql()) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` CHANGE `%s` `%s` BIGINT UNSIGNED %s',
                $table,
                $from,
                $to,
                $nullable ? 'NULL' : 'NOT NULL',
            ));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($from, $to) {
            $blueprint->renameColumn($from, $to);
        });
    }

    private function isMysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }
};
