<?php

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\ServiceOffering;
use App\Models\ServicePurchase;
use App\Models\Subject;
use App\Models\User;

beforeEach(function () {
    $this->teacher = User::factory()->teacher()->onboarded()->create();
    $this->profile = $this->teacher->teacherProfile;

    $this->subject = Subject::factory()->create();
    $this->lesson = Lesson::factory()->create(['subject_id' => $this->subject->id]);
    $this->gradeId = (int) $this->lesson->grade_id;

    $this->profile->subjects()->attach($this->subject->id);
    $this->profile->lessons()->attach($this->lesson->id);
});

it('shows the services manager with server-rendered grade, subject and lesson options', function () {
    ServiceOffering::factory()->create([
        'teacher_profile_id' => $this->profile->id,
        'subject_id' => $this->subject->id,
        'lesson_id' => $this->lesson->id,
    ]);

    $this->actingAs($this->teacher)
        ->get(route('teacher.services.index'))
        ->assertOk()
        ->assertSee('Services & pricing')
        ->assertSee('Lesson plans')
        ->assertSee('Paper marking')
        ->assertSee('Q&A sessions')
        ->assertSee('Grade')
        ->assertSee('Pick a grade')
        // The cascade options are rendered server-side so the selects are never
        // empty, with data attributes for the Alpine visibility filter.
        ->assertSee('>'.$this->lesson->grade->label.'<', false)
        ->assertSee($this->subject->name)
        ->assertSee($this->lesson->name)
        ->assertSee('data-grades=', false)
        ->assertSee('data-subject=', false)
        ->assertSee('name="grade_id"', false)
        ->assertSee('x-ref="subjectSelect"', false)
        ->assertSee('x-ref="lessonSelect"', false)
        ->assertDontSee('x-for="g in grades"', false);
});

it('creates every service type and converts rupees to minor units', function () {
    $payloads = [
        [
            [
                'service_type' => 'lesson',
                'title' => 'O/L Maths plan',
                'price' => '8000',
                'subject_id' => $this->subject->id,
                'lesson_id' => $this->lesson->id,
                'grade_id' => $this->gradeId,
                'allocation_minutes' => 240,
                'default_session_minutes' => 60,
                'max_sessions' => 8,
            ],
            [
                'service_type' => 'lesson',
                'price_minor' => 800000,
                'allocation_minutes' => 240,
                'default_session_minutes' => 60,
                'max_sessions' => 8,
                'lesson_id' => $this->lesson->id,
                'grade_id' => $this->gradeId,
            ],
        ],
        [
            [
                'service_type' => 'summary',
                'title' => 'Algebra summary',
                'price' => '1500',
                'subject_id' => $this->subject->id,
                'lesson_id' => $this->lesson->id,
                'grade_id' => $this->gradeId,
            ],
            [
                'service_type' => 'summary',
                'price_minor' => 150000,
                'allocation_minutes' => null,
                'max_sessions' => null,
                'grade_id' => $this->gradeId,
            ],
        ],
        [
            [
                'service_type' => 'paper_marking',
                'title' => 'O/L paper marking',
                'price' => '500',
                'subject_id' => $this->subject->id,
                'max_pages' => 20,
                'turnaround_hours' => 72,
            ],
            [
                'service_type' => 'paper_marking',
                'price_minor' => 50000,
                'max_pages' => 20,
                'turnaround_hours' => 72,
                'lesson_id' => null,
                'max_sessions' => null,
            ],
        ],
        [
            [
                'service_type' => 'paper_discussion',
                'title' => 'Paper walkthrough',
                'price' => '2000',
                'subject_id' => $this->subject->id,
                'default_session_minutes' => 60,
            ],
            [
                'service_type' => 'paper_discussion',
                'price_minor' => 200000,
                'default_session_minutes' => 60,
                'max_sessions' => 1,
                'lesson_id' => null,
            ],
        ],
        [
            [
                'service_type' => 'qna',
                'title' => 'Drop-in Q&A',
                'price' => '1000',
                'subject_id' => $this->subject->id,
                'default_session_minutes' => 45,
            ],
            [
                'service_type' => 'qna',
                'price_minor' => 100000,
                'default_session_minutes' => 45,
                'max_sessions' => 1,
                'lesson_id' => null,
            ],
        ],
    ];

    foreach ($payloads as [$payload, $expected]) {
        $this->actingAs($this->teacher)
            ->post(route('teacher.services.store'), $payload)
            ->assertRedirect(route('teacher.services.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('service_offerings', $expected + [
            'teacher_profile_id' => $this->profile->id,
            'subject_id' => $this->subject->id,
        ]);
    }

    expect(ServiceOffering::count())->toBe(5);
});

it('lets a Q&A scope to a single lesson without a subject', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.services.store'), [
            'service_type' => 'qna',
            'title' => 'Lesson-specific Q&A',
            'price' => '900',
            'lesson_id' => $this->lesson->id,
        ])
        ->assertRedirect(route('teacher.services.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('service_offerings', [
        'service_type' => 'qna',
        'lesson_id' => $this->lesson->id,
        'subject_id' => null,
        'max_sessions' => 1,
    ]);
});

it('requires a lesson for a lesson plan', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.services.store'), [
            'service_type' => 'lesson',
            'title' => 'Missing lesson',
            'price' => '8000',
            'subject_id' => $this->subject->id,
            'grade_id' => $this->gradeId,
            'allocation_minutes' => 240,
        ])
        ->assertSessionHasErrors('lesson_id');

    expect(ServiceOffering::count())->toBe(0);
});

it('requires a grade for a lesson plan', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.services.store'), [
            'service_type' => 'lesson',
            'title' => 'Missing grade',
            'price' => '8000',
            'subject_id' => $this->subject->id,
            'lesson_id' => $this->lesson->id,
            'allocation_minutes' => 240,
        ])
        ->assertSessionHasErrors('grade_id');

    expect(ServiceOffering::count())->toBe(0);
});

it('rejects a lesson from a different grade than the one picked', function () {
    $otherLesson = Lesson::factory()->create(['subject_id' => $this->subject->id]);
    $this->profile->lessons()->attach($otherLesson->id);

    $this->actingAs($this->teacher)
        ->post(route('teacher.services.store'), [
            'service_type' => 'lesson',
            'title' => 'Grade mismatch',
            'price' => '8000',
            'subject_id' => $this->subject->id,
            'lesson_id' => $otherLesson->id,
            'grade_id' => $this->gradeId,
            'allocation_minutes' => 240,
        ])
        ->assertSessionHasErrors('grade_id');

    expect(ServiceOffering::count())->toBe(0);
});

it('rejects a lesson plan allocation under 30 minutes', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.services.store'), [
            'service_type' => 'lesson',
            'title' => 'Tiny plan',
            'price' => '8000',
            'subject_id' => $this->subject->id,
            'lesson_id' => $this->lesson->id,
            'grade_id' => $this->gradeId,
            'allocation_minutes' => 10,
        ])
        ->assertSessionHasErrors('allocation_minutes');

    expect(ServiceOffering::count())->toBe(0);
});

it('requires a turnaround time for paper marking', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.services.store'), [
            'service_type' => 'paper_marking',
            'title' => 'Marking',
            'price' => '500',
            'subject_id' => $this->subject->id,
        ])
        ->assertSessionHasErrors('turnaround_hours');

    expect(ServiceOffering::count())->toBe(0);
});

it('requires a lesson or a subject for Q&A', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.services.store'), [
            'service_type' => 'qna',
            'title' => 'Nowhere Q&A',
            'price' => '1000',
        ])
        ->assertSessionHasErrors('subject_id');

    expect(ServiceOffering::count())->toBe(0);
});

it('rejects a duplicate offering for the same type and scope', function () {
    ServiceOffering::factory()->create([
        'teacher_profile_id' => $this->profile->id,
        'subject_id' => $this->subject->id,
        'lesson_id' => $this->lesson->id,
        'grade_id' => $this->gradeId,
    ]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.services.store'), [
            'service_type' => 'summary',
            'title' => 'Another summary',
            'price' => '1200',
            'subject_id' => $this->subject->id,
            'lesson_id' => $this->lesson->id,
            'grade_id' => $this->gradeId,
        ])
        ->assertSessionHasErrors('service_type');

    expect(ServiceOffering::count())->toBe(1);
});

it('allows the same subject service at two different grades', function () {
    $gradeTwo = Grade::factory()->create();
    $otherLesson = Lesson::factory()->create([
        'subject_id' => $this->subject->id,
        'grade_id' => $gradeTwo->id,
    ]);
    $this->profile->lessons()->attach($otherLesson->id);

    $this->actingAs($this->teacher)
        ->post(route('teacher.services.store'), [
            'service_type' => 'paper_marking',
            'title' => 'Grade A marking',
            'price' => '500',
            'subject_id' => $this->subject->id,
            'grade_id' => $this->gradeId,
            'turnaround_hours' => 72,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->teacher)
        ->post(route('teacher.services.store'), [
            'service_type' => 'paper_marking',
            'title' => 'Grade B marking',
            'price' => '700',
            'subject_id' => $this->subject->id,
            'grade_id' => $gradeTwo->id,
            'turnaround_hours' => 72,
        ])
        ->assertSessionHasNoErrors();

    expect(ServiceOffering::where('service_type', 'paper_marking')->count())->toBe(2);
});

it('updates an offering', function () {
    $offering = ServiceOffering::factory()->create([
        'teacher_profile_id' => $this->profile->id,
        'subject_id' => $this->subject->id,
        'lesson_id' => $this->lesson->id,
    ]);

    $this->actingAs($this->teacher)
        ->put(route('teacher.services.update', $offering), [
            'service_type' => 'summary',
            'title' => 'Renamed summary',
            'price' => '2500',
            'subject_id' => $this->subject->id,
            'lesson_id' => $this->lesson->id,
            'grade_id' => $this->gradeId,
        ])
        ->assertRedirect(route('teacher.services.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('service_offerings', [
        'id' => $offering->id,
        'title' => 'Renamed summary',
        'price_minor' => 250000,
        'grade_id' => $this->gradeId,
    ]);
});

it('toggles an offering on and off', function () {
    $offering = ServiceOffering::factory()->create(['teacher_profile_id' => $this->profile->id]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.services.toggle', $offering))
        ->assertRedirect(route('teacher.services.index'));

    expect($offering->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->teacher)
        ->post(route('teacher.services.toggle', $offering))
        ->assertRedirect(route('teacher.services.index'));

    expect($offering->fresh()->is_active)->toBeTrue();
});

it('deletes an offering without purchases', function () {
    $offering = ServiceOffering::factory()->create(['teacher_profile_id' => $this->profile->id]);

    $this->actingAs($this->teacher)
        ->delete(route('teacher.services.destroy', $offering))
        ->assertRedirect(route('teacher.services.index'))
        ->assertSessionHas('status', 'offering-deleted');

    $this->assertDatabaseMissing('service_offerings', ['id' => $offering->id]);
});

it('keeps an offering that already has purchases', function () {
    $offering = ServiceOffering::factory()->create([
        'teacher_profile_id' => $this->profile->id,
        'subject_id' => $this->subject->id,
        'lesson_id' => $this->lesson->id,
    ]);

    ServicePurchase::factory()->forOffering($offering)->create();

    $this->actingAs($this->teacher)
        ->delete(route('teacher.services.destroy', $offering))
        ->assertRedirect(route('teacher.services.index'))
        ->assertSessionHas('status', 'offering-has-purchases');

    $this->assertDatabaseHas('service_offerings', ['id' => $offering->id]);
});

it('forbids another teacher from updating or deleting an offering', function () {
    $offering = ServiceOffering::factory()->create(['teacher_profile_id' => $this->profile->id]);

    $intruder = User::factory()->teacher()->onboarded()->create();
    // Valid input for a teacher who teaches the scope — only the policy stands in the way.
    $intruder->teacherProfile->subjects()->attach($this->subject->id);
    $intruder->teacherProfile->lessons()->attach($this->lesson->id);

    $this->actingAs($intruder)
        ->put(route('teacher.services.update', $offering), [
            'service_type' => 'summary',
            'title' => 'Hijacked',
            'price' => '100',
            'subject_id' => $this->subject->id,
            'lesson_id' => $this->lesson->id,
            'grade_id' => $this->gradeId,
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('teacher.services.destroy', $offering))
        ->assertForbidden();

    $this->assertDatabaseHas('service_offerings', ['id' => $offering->id]);
});

it('blocks students from the services console', function () {
    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)
        ->get(route('teacher.services.index'))
        ->assertForbidden();
});
