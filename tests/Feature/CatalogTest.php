<?php

use App\Models\Subject;
use App\Models\Topic;
use Database\Seeders\CatalogSeeder;

test('the public catalog lists active subjects with topic counts', function () {
    $subject = Subject::factory()->create(['name' => 'Mathematics', 'icon' => '🔢']);
    Topic::factory()->for($subject)->count(3)->create();
    Subject::factory()->inactive()->create(['name' => 'Hidden Subject']);

    $this->get(route('catalog.subjects.index'))
        ->assertOk()
        ->assertSee('Mathematics')
        ->assertSee('3 topics')
        ->assertDontSee('Hidden Subject');
});

test('a subject page lists its active topics', function () {
    $subject = Subject::factory()->create(['name' => 'Physics']);
    Topic::factory()->for($subject)->create(['name' => 'Mechanics']);
    Topic::factory()->for($subject)->inactive()->create(['name' => 'Secret Topic']);

    $this->get(route('catalog.subjects.show', $subject))
        ->assertOk()
        ->assertSee('Mechanics')
        ->assertDontSee('Secret Topic');
});

test('inactive subjects are not publicly accessible', function () {
    $subject = Subject::factory()->inactive()->create();

    $this->get(route('catalog.subjects.show', $subject))->assertNotFound();
});

test('the catalog seeder is idempotent', function () {
    $this->seed(CatalogSeeder::class);
    $this->seed(CatalogSeeder::class);

    expect(Subject::query()->count())->toBe(6)
        ->and(Topic::query()->count())->toBe(30);
});
