<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the bootstrap administrator from env (or local demo fallback).
     */
    public function run(): void
    {
        $email = config('studylikepro.admin.email');
        $password = config('studylikepro.admin.password');

        if (filled($email) && filled($password)) {
            // Use the credentials provided by the environment.
        } elseif (app()->environment('local')) {
            $email = 'admin@studylikepro.test';
            $password = 'password';
        } else {
            return;
        }

        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Studylikepro Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole(User::ROLE_ADMIN);
    }
}
