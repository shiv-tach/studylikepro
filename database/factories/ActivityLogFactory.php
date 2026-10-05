<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'action' => 'admin.settings.update',
            'description' => 'Updated platform settings',
            'properties' => ['route' => 'admin.settings.update'],
            'created_at' => now(),
        ];
    }
}
