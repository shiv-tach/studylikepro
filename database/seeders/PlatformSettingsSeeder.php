<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Services\PlatformSettings;
use Illuminate\Database\Seeder;

class PlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PlatformSettings::definitions() as $key => $definition) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => (string) $definition['default'],
                    'type' => $definition['type'],
                    'group' => $definition['group'],
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                ],
            );
        }
    }
}
