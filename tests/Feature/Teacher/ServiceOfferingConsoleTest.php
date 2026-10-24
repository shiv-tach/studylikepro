<?php

use App\Enums\ApprovalStatus;
use App\Enums\ServiceType;
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

    $this->profile->subjects()->attach($this->subject->id, ['status' => ApprovalStatus::Approved]);
    $this->profile->lessons()->attach($this->lesson->id);
});

it('shows the services manager with server-rendered grade, subject and lesson options', function () {
    $offering = ServiceOffering::factory()->create([
        'teacher_profile_id' => $this->profile->id,
        'subject_id' => $this->subject->id,
        'lesson_id' => $this->lesson->id,
        'title' => 'Algebra boost',
    ]);

    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.services.index'))
        ->assertOk()
        ->assertSee('Services & pricing')
        ->assertSee('id="teacher-services-config"', false)
        ->assertSee('id="teacher-services-app"', false)
        ->assertSee('Lesson plans')
        ->assertSee('Paper marking')
        ->assertSee('Q&A sessions')
        ->assertSee('Pick a grade')
        // The no-JS twin keeps the real field names, so the fallback posts the
        // same request as the Vue composer.
        ->assertSee('name="grade_id"', false)
        ->assertSee('name="subject_id"', false)
        ->assertSee('name="lesson_id"', false)
        ->assertDontSee('x-for="g in grades"', false);

    $config = teacherServicesConfig($response);

    expect($config['types'])->toHaveCount(4)
        ->and(collect($config['types'])->firstWhere('value', 'lesson')['label'])->toBe('Lesson plan')
        ->and($config['options']['grades'])->not->toBeEmpty()
        ->and($config['options']['subjects'][0]['name'])->toBe($this->subject->name)
        ->and($config['options']['lessons'][0]['id'])->toBe((int) $this->lesson->id)
        ->and($config['offerings'])->toHaveCount(1)
        ->and($config['offerings'][0]['id'])->toBe((int) $offering->id)
        ->and($config['offerings'][0]['title'])->toBe('Algebra boost')
        ->and($config['offerings'][0]['subjectName'])->toBe($this->subject->name)
        ->and($config['offerings'][0]['lessonName'])->toBe($this->lesson->name)
        ->and($config['offerings'][0]['priceLabel'])->toContain(number_format($offering->price_minor / 100, 2))
        ->and($config['offerings'][0]['updateUrl'])->toBe(route('teacher.services.update', $offering))
        ->and($config['offerings'][0]['deleteUrl'])->toBe(route('teacher.services.destroy', $offering))
        ->and($config['offerings'][0]['toggleUrl'])->toBe(route('teacher.services.toggle', $offering))
        ->and($config['urls']['store'])->toBe(route('teacher.services.store'))
        ->and($config['urls']['subjects'])->toBe(route('teacher.subjects.index'));
});

it('only offers approved subjects and their lessons to the composer', function () {
    $pending = Subject::factory()->create();
    $pendingLesson = Lesson::factory()->create(['subject_id' => $pending->id]);

    $this->profile->subjects()->attach($pending->id, ['status' => ApprovalStatus::Pending]);
    $this->profile->lessons()->attach($pendingLesson->id);

    $config = teacherServicesConfig(
        $this->actingAs($this->teacher)->get(route('teacher.services.index'))
    );

    expect(collect($config['options']['subjects'])->pluck('id')->all())->toBe([(int) $this->subject->id])
        ->and(collect($config['options']['lessons'])->pluck('id')->all())->toBe([(int) $this->lesson->id])
        ->and($config['hasSubjects'])->toBeTrue()
        ->and($config['subjectsAwaitingApproval'])->toBeFalse();
});

it('asks a teacher whose subjects are still pending to wait for approval', function () {
    $pendingOnly = User::factory()->teacher()->onboarded()->create();
    $pendingOnly->teacherProfile->subjects()->attach($this->subject->id, ['status' => ApprovalStatus::Pending]);

    $config = teacherServicesConfig(
        $this->actingAs($pendingOnly)->get(route('teacher.services.index'))
    );

    expect($config['hasSubjects'])->toBeFalse()
        ->and($config['subjectsAwaitingApproval'])->toBeTrue()
        ->and($config['options']['subjects'])->toBe([])
        ->and($config['options']['lessons'])->toBe([]);
});

it('refuses a service for a subject that is not approved', function () {
    $pending = Subject::factory()->create();

    $this->profile->subjects()->attach($pending->id, ['status' => ApprovalStatus::Pending]);

    $this->actingAs($this->teacher)
        ->from(route('teacher.services.index'))
        ->post(route('teacher.services.store'), [
            'service_type' => 'qna',
            'title' => 'Unapproved Q&A',
            'price' => '1000',
            'subject_id' => $pending->id,
        ])
        ->assertSessionHasErrors('subject_id');

    expect(ServiceOffering::count())->toBe(0);
});

it('reopens the create composer with the posted type after a rejected save', function () {
    $this->actingAs($this->teacher)
        ->from(route('teacher.services.index'))
        ->post(route('teacher.services.store'), [
            'service_type' => 'lesson',
            'title' => 'Missing lesson',
            'price' => '8000',
            'subject_id' => $this->subject->id,
            'grade_id' => $this->gradeId,
            'allocation_minutes' => 240,
        ])
        ->assertSessionHasErrors('lesson_id');

    $config = teacherServicesConfig(
        $this->actingAs($this->teacher)->get(route('teacher.services.index'))
    );

    expect($config['openComposer'])->toBe(['mode' => 'create', 'type' => 'lesson'])
        ->and($config['editingOfferingId'])->toBeNull()
        ->and($config['form']['type'])->toBe('lesson')
        ->and($config['form']['values']['title'])->toBe('Missing lesson')
        ->and($config['errors']['lesson_id'])->not->toBeEmpty();
});

it('scopes a rejected edit to the offering that was being edited', function () {
    $offering = ServiceOffering::factory()->create([
        'teacher_profile_id' => $this->profile->id,
        'subject_id' => $this->subject->id,
        'lesson_id' => $this->lesson->id,
    ]);

    $this->actingAs($this->teacher)
        ->from(route('teacher.services.index'))
        ->put(route('teacher.services.update', $offering), [
            'service_type' => 'qna',
            'title' => '',
            'price' => '2500',
            'subject_id' => $this->subject->id,
            'lesson_id' => $this->lesson->id,
            'grade_id' => $this->gradeId,
            'editing_offering' => $offering->id,
        ])
        ->assertSessionHasErrors('title');

    $config = teacherServicesConfig(
        $this->actingAs($this->teacher)->get(route('teacher.services.index'))
    );

    expect($config['openComposer'])->toBe(['mode' => 'edit', 'offeringId' => (int) $offering->id])
        ->and($config['editingOfferingId'])->toBe((int) $offering->id)
        ->and($config['errors']['title'])->not->toBeEmpty();
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

    expect(ServiceOffering::count())->toBe(4);
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
    ServiceOffering::factory()->forType(ServiceType::Qna)->create([
        'teacher_profile_id' => $this->profile->id,
        'subject_id' => $this->subject->id,
        'lesson_id' => null,
        'grade_id' => $this->gradeId,
    ]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.services.store'), [
            'service_type' => 'qna',
            'title' => 'Another Q&A',
            'price' => '1200',
            'subject_id' => $this->subject->id,
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
            'service_type' => 'qna',
            'title' => 'Renamed Q&A',
            'price' => '2500',
            'subject_id' => $this->subject->id,
            'lesson_id' => $this->lesson->id,
            'grade_id' => $this->gradeId,
        ])
        ->assertRedirect(route('teacher.services.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('service_offerings', [
        'id' => $offering->id,
        'title' => 'Renamed Q&A',
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
    $intruder->teacherProfile->subjects()->attach($this->subject->id, ['status' => ApprovalStatus::Approved]);
    $intruder->teacherProfile->lessons()->attach($this->lesson->id);

    $this->actingAs($intruder)
        ->put(route('teacher.services.update', $offering), [
            'service_type' => 'qna',
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

it('saves a paper-marking rubric and normalises its keys and maxima', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.services.store'), [
            'service_type' => 'paper_marking',
            'title' => 'Rubric-marked O/L paper',
            'price' => '800',
            'subject_id' => $this->subject->id,
            'max_pages' => 10,
            'turnaround_hours' => 48,
            'rubric' => [
                ['title' => 'Conceptual accuracy', 'max' => '25', 'description' => '2NF/3NF'],
                ['title' => 'SQL syntax', 'max' => '40'],
            ],
        ])
        ->assertRedirect(route('teacher.services.index'));

    $offering = ServiceOffering::query()->latest('id')->firstOrFail();

    expect($offering->rubricCriteria())->toHaveCount(2)
        ->and($offering->rubricCriteria()[0]['key'])->toBe('c1')
        ->and($offering->rubricCriteria()[0]['max'])->toBe(25.0)
        ->and($offering->rubricCriteria()[1]['key'])->toBe('c2')
        ->and($offering->rubricCriteria()[1]['description'])->toBeNull();
});

it('rejects a rubric row without a maximum', function () {
    $this->actingAs($this->teacher)
        ->from(route('teacher.services.index'))
        ->post(route('teacher.services.store'), [
            'service_type' => 'paper_marking',
            'title' => 'Broken rubric',
            'price' => '800',
            'subject_id' => $this->subject->id,
            'rubric' => [
                ['title' => 'Conceptual accuracy'],
            ],
        ])
        ->assertSessionHasErrors('rubric.0.max');

    expect(ServiceOffering::query()->count())->toBe(0);
});

it('drops a rubric posted on a non-marking service', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.services.store'), [
            'service_type' => 'qna',
            'title' => 'Drop-in Q&A',
            'price' => '1000',
            'subject_id' => $this->subject->id,
            'default_session_minutes' => 45,
            'rubric' => [
                ['title' => 'Should not survive', 'max' => 10],
            ],
        ])
        ->assertRedirect(route('teacher.services.index'));

    $offering = ServiceOffering::query()->latest('id')->firstOrFail();

    expect($offering->rubric)->toBeNull()
        ->and($offering->rubricCriteria())->toBe([]);
});
