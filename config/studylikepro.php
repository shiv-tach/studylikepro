<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap Administrator
    |--------------------------------------------------------------------------
    |
    | Used by AdminUserSeeder to create the first administrator account.
    | When these values are missing, seeding a production database is
    | skipped entirely; local environments fall back to demo accounts.
    |
    */

    'admin' => [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Marketplace lists (used for validation and UI selects)
    |--------------------------------------------------------------------------
    */

    // The marketplace trades in Sri Lankan rupees. Amounts are stored in minor
    // units (cents) and formatted on the way out by PlatformSettings::formatMinor.
    'currency' => env('PLATFORM_CURRENCY', 'LKR'),

    'currency_symbol' => env('PLATFORM_CURRENCY_SYMBOL', 'RS:'),

    'default_display_timezone' => 'Asia/Kolkata',

    'commission_percent' => (int) env('PLATFORM_COMMISSION_PERCENT', 15),

    /*
    |--------------------------------------------------------------------------
    | Support & legal
    |--------------------------------------------------------------------------
    |
    | Where contact-form messages go and which policy version a teacher agrees
    | to when they submit documents for verification.
    |
    */

    'support' => [
        'email' => env('SUPPORT_EMAIL', env('ADMIN_EMAIL', 'support@studylikepro.test')),
        'phone' => env('SUPPORT_PHONE', '+91 90000 00000'),
        'hours' => 'Mon–Sat, 9:00–21:00 IST',
        'policy_version' => env('POLICY_VERSION', '2026-10-01'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */

    'security' => [
        // Content-Security-Policy is on by default; set to false if a provider
        // you rely on is blocked and you cannot extend the policy below.
        'csp_enabled' => (bool) env('SECURITY_CSP_ENABLED', true),
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits (requests per minute)
    |--------------------------------------------------------------------------
    */

    'throttle' => [
        'login' => (int) env('THROTTLE_LOGIN', 20),
        'register' => (int) env('THROTTLE_REGISTER', 10),
        'requests' => (int) env('THROTTLE_REQUESTS', 10),
        'uploads' => (int) env('THROTTLE_UPLOADS', 30),
        'messages' => (int) env('THROTTLE_MESSAGES', 60),
        'checkout' => (int) env('THROTTLE_CHECKOUT', 15),
        'contact' => (int) env('THROTTLE_CONTACT', 5),
        'webhooks' => (int) env('THROTTLE_WEBHOOKS', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tutoring requests
    |--------------------------------------------------------------------------
    */

    'requests' => [
        'max_attachments' => 3,
        'open_for_days' => 7,
        'hold_ttl_minutes' => 30,
        'daily_submission_limit' => (int) env('REQUEST_DAILY_LIMIT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bookings
    |--------------------------------------------------------------------------
    */

    'booking' => [
        'max_advance_days' => 30,
        'student_cancel_window_hours' => (int) env('BOOKING_CANCEL_WINDOW_HOURS', 24),
        'teacher_start_early_minutes' => 30,
        'auto_complete_grace_minutes' => (int) env('BOOKING_AUTO_COMPLETE_GRACE_MINUTES', 20),
        'reminder_lead_minutes' => (int) env('BOOKING_REMINDER_LEAD_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | `gateway` selects the payment provider. `fake` keeps the whole checkout
    | flow offline (local development and tests); `razorpay` uses the live API
    | with the credentials from config/services.php.
    |
    */

    'payments' => [
        'gateway' => env('PAYMENTS_GATEWAY', 'fake'),
        // Secret the offline gateway uses to sign the webhooks it emits locally.
        'fake_webhook_secret' => env('FAKE_PAYMENT_WEBHOOK_SECRET', 'studylikepro-fake-webhook-secret'),
        // Orders older than this are worth re-checking with the provider.
        'reconcile_after_minutes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Live classrooms
    |--------------------------------------------------------------------------
    |
    | `provider` selects how lesson rooms are created. `fake` keeps the whole
    | flow offline (local development and tests); `daily` uses the Daily.co
    | API with the credentials from config/services.php.
    |
    */

    'meeting' => [
        'provider' => env('MEETING_PROVIDER', 'fake'),
        // How long before the start time (and after the end time) joins are accepted.
        'join_opens_before_minutes' => (int) env('MEETING_JOIN_OPENS_MINUTES', 15),
        'join_closes_after_minutes' => (int) env('MEETING_JOIN_CLOSES_MINUTES', 30),
        // How long the provider keeps the room alive after the lesson ends, to
        // cover overruns and recordings. The app closes joins much earlier.
        'room_ttl_after_minutes' => (int) env('MEETING_ROOM_TTL_MINUTES', 240),
        // Attempts (including the first) at room creation before giving up.
        'provision_attempts' => (int) env('MEETING_PROVISION_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    */

    'reviews' => [
        // How long a student can change their mind about a review.
        'edit_window_days' => (int) env('REVIEW_EDIT_WINDOW_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI topic classification
    |--------------------------------------------------------------------------
    */

    'ai' => [
        'min_confidence' => (float) env('AI_MIN_CONFIDENCE', 0.55),
        'cache_ttl_minutes' => 60,
    ],

    'lesson_durations' => [30, 45, 60, 90],

    'weekdays' => [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        0 => 'Sunday',
    ],

    'grade_levels' => [
        'primary' => 'Primary school',
        'middle_school' => 'Middle school',
        'high_school' => 'High school',
        'college' => 'College / University',
        'adult' => 'Adult learner',
    ],

    'timezones' => [
        'Asia/Kolkata' => 'India (IST, UTC+5:30)',
        'Asia/Dubai' => 'Gulf (GST, UTC+4)',
        'Asia/Karachi' => 'Pakistan (PKT, UTC+5)',
        'Asia/Dhaka' => 'Bangladesh (BST, UTC+6)',
        'Asia/Kathmandu' => 'Nepal (NPT, UTC+5:45)',
        'Asia/Colombo' => 'Sri Lanka (UTC+5:30)',
        'Asia/Singapore' => 'Singapore (UTC+8)',
        'Asia/Kuala_Lumpur' => 'Malaysia (UTC+8)',
        'Asia/Jakarta' => 'Indonesia (UTC+7)',
        'Asia/Manila' => 'Philippines (UTC+8)',
        'Asia/Hong_Kong' => 'Hong Kong (UTC+8)',
        'Asia/Shanghai' => 'China (UTC+8)',
        'Asia/Tokyo' => 'Japan (UTC+9)',
        'Asia/Seoul' => 'South Korea (UTC+9)',
        'Australia/Sydney' => 'Australia (Sydney)',
        'Europe/London' => 'United Kingdom (UTC+0)',
        'Europe/Paris' => 'Central Europe (UTC+1)',
        'Europe/Berlin' => 'Germany (UTC+1)',
        'Europe/Moscow' => 'Moscow (UTC+3)',
        'Africa/Cairo' => 'Egypt (UTC+2)',
        'Africa/Lagos' => 'Nigeria (UTC+1)',
        'Africa/Nairobi' => 'Kenya (UTC+3)',
        'America/New_York' => 'US East (UTC-5)',
        'America/Chicago' => 'US Central (UTC-6)',
        'America/Denver' => 'US Mountain (UTC-7)',
        'America/Los_Angeles' => 'US West (UTC-8)',
        'America/Sao_Paulo' => 'Brazil (UTC-3)',
        'UTC' => 'UTC',
    ],

    'languages' => [
        'English', 'Hindi', 'Tamil', 'Telugu', 'Malayalam', 'Kannada',
        'Marathi', 'Bengali', 'Gujarati', 'Punjabi', 'Urdu', 'Arabic',
        'Spanish', 'French', 'German', 'Mandarin',
    ],

    'verification_document_types' => [
        'id_proof' => 'Government ID',
        'degree' => 'Degree or diploma',
        'certificate' => 'Teaching certificate',
        'other' => 'Other supporting document',
    ],

];
