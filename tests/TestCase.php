<?php

namespace Tests;

use App\Services\Meetings\FakeMeetingProvider;
use App\Services\Sms\FakeSmsGateway;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The offline providers hold static state; tests must not inherit
        // rooms, attempt counts, sent messages or a primed failure from each
        // other.
        FakeMeetingProvider::reset();
        FakeSmsGateway::reset();

        $this->seed(RolesAndPermissionsSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
