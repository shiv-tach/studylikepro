<?php

namespace App\Enums;

/**
 * The services teachers sell through the marketplace catalog.
 */
enum ServiceType: string
{
    case Lesson = 'lesson';
    case Summary = 'summary';
    case PaperMarking = 'paper_marking';
    case PaperDiscussion = 'paper_discussion';
    case Qna = 'qna';

    public function label(): string
    {
        return match ($this) {
            self::Lesson => 'Lesson plan',
            self::Summary => 'Lesson summary',
            self::PaperMarking => 'Paper marking',
            self::PaperDiscussion => 'Paper discussion',
            self::Qna => 'Q&A session',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Lesson => 'Lesson plans',
            self::Summary => 'Lesson summaries',
            self::PaperMarking => 'Paper marking',
            self::PaperDiscussion => 'Paper discussions',
            self::Qna => 'Q&A sessions',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Lesson => '🎓',
            self::Summary => '📝',
            self::PaperMarking => '✅',
            self::PaperDiscussion => '💬',
            self::Qna => '❓',
        };
    }

    /**
     * Whether this service is delivered as a live video session.
     */
    public function isLiveSession(): bool
    {
        return in_array($this, [self::Lesson, self::PaperDiscussion, self::Qna], true);
    }

    /**
     * Whether the offering must be scoped to a single lesson.
     */
    public function requiresLesson(): bool
    {
        return in_array($this, [self::Lesson, self::Summary], true);
    }

    /**
     * Whether the offering may be scoped to either a lesson or a subject.
     */
    public function allowsLessonOrSubject(): bool
    {
        return $this === self::Qna;
    }

    /**
     * Whether the offering must be scoped to a subject only.
     */
    public function requiresSubject(): bool
    {
        return in_array($this, [self::PaperMarking, self::PaperDiscussion], true);
    }
}
