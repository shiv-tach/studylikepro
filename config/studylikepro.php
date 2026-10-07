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

    // Users are never asked for a timezone; lessons are booked and shown in Sri Lanka time.
    'default_display_timezone' => 'Asia/Colombo',

    'commission_percent' => (int) env('PLATFORM_COMMISSION_PERCENT', 15),

    // Student-facing booking fee added to every lesson at checkout, in minor
    // units (Rs 100 = 10000). Admins can retune it or waive it via a special
    // offer; both live in the platform settings / offers console.
    'booking_fee_minor' => (int) env('PLATFORM_BOOKING_FEE_MINOR', 10000),

    /*
    |--------------------------------------------------------------------------
    | Teacher onboarding invites
    |--------------------------------------------------------------------------
    |
    | How long an admin-issued onboarding link stays valid. Teacher accounts
    | can only be created through such a link.
    |
    */

    'teacher_invite_expiry_days' => (int) env('TEACHER_INVITE_EXPIRY_DAYS', 7),

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
        'hours' => 'Mon–Sat, 9:00–21:00 (Sri Lanka time)',
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

    'languages' => [
        'Sinhala', 'English', 'Tamil',
    ],

    'verification_document_types' => [
        'id_proof' => 'Government ID',
        'degree' => 'Degree or diploma',
        'certificate' => 'Teaching certificate',
        'other' => 'Other supporting document',
    ],

];
