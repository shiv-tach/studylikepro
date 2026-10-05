<?php

namespace Tests;

use App\Services\Meetings\FakeMeetingProvider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The offline meeting provider holds static room state; tests must not
        // inherit rooms, attempt counts or a primed failure from each other.
        FakeMeetingProvider::reset();

        $this->seed(RolesAndPermissionsSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
