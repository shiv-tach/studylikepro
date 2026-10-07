<?php

use App\Services\PlatformSettings;

if (! function_exists('platform_settings')) {
    /**
     * Runtime-tunable marketplace settings (commission, booking fee, hold TTL,
     * refund policy).
     */
    function platform_settings(): PlatformSettings
    {
        return app(PlatformSettings::class);
    }
}
