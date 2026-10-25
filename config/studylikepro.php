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

    // Student-facing booking fee added to every lesson at checkout. It is
    // priced either as a fixed amount in minor units (Rs 100 = 10000) or as a
    // percentage of the lesson/service price; admins retune both from the
    // platform settings console. Offers can discount or waive the result.
    'booking_fee_type' => env('PLATFORM_BOOKING_FEE_TYPE', 'fixed'),
    'booking_fee_minor' => (int) env('PLATFORM_BOOKING_FEE_MINOR', 10000),
    'booking_fee_percent' => (float) env('PLATFORM_BOOKING_FEE_PERCENT', 0),

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
        // One-time passcodes: sending and checking are bucketed separately so a
        // legitimate resend does not spend the code-check budget.
        'otp' => (int) env('THROTTLE_OTP', 5),
        'otp-verify' => (int) env('THROTTLE_OTP_VERIFY', 10),
        'uploads' => (int) env('THROTTLE_UPLOADS', 30),
        'marking-draft' => (int) env('THROTTLE_MARKING_DRAFT', 120),
        'messages' => (int) env('THROTTLE_MESSAGES', 60),
        'checkout' => (int) env('THROTTLE_CHECKOUT', 15),
        'contact' => (int) env('THROTTLE_CONTACT', 5),
        // The admin SMSLenz connection test sends a real text message.
        'sms-test' => (int) env('THROTTLE_SMS_TEST', 5),
        'webhooks' => (int) env('THROTTLE_WEBHOOKS', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bookings
    |--------------------------------------------------------------------------
    */

    'booking' => [
        'max_advance_days' => 30,
        // The nearest days a teacher profile rail (public and student
        // workspace alike) and the direct-booking time picker show. Only days
        // that actually have an open slot count. The full max_advance_days
        // window is still used to find those days.
        'quick_pick_days' => (int) env('BOOKING_QUICK_PICK_DAYS', 3),
        'hold_ttl_minutes' => 30,
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

    /*
    |--------------------------------------------------------------------------
    | SMS and mobile verification
    |--------------------------------------------------------------------------
    |
    | `provider` selects how passcodes leave the server. `fake` keeps the whole
    | flow offline (local development and tests) and writes the code to the log;
    | `smslenz` uses the live SMSLenz API with the credentials in
    | config/services.php. The OTP policy itself is tunable from
    | /admin/settings (these are only the defaults).
    |
    */

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'fake'),
        'otp_length' => (int) env('SMS_OTP_LENGTH', 6),
        'otp_ttl_minutes' => (int) env('SMS_OTP_TTL_MINUTES', 5),
        'otp_resend_seconds' => (int) env('SMS_OTP_RESEND_SECONDS', 60),
        'otp_max_attempts' => (int) env('SMS_OTP_MAX_ATTEMPTS', 5),
        'otp_max_resends' => (int) env('SMS_OTP_MAX_RESENDS', 3),
        // :code and :minutes are replaced before sending. Keep it inside one
        // SMS page (160 GSM characters) so the passcode is never truncated.
        'otp_message' => env(
            'SMS_OTP_MESSAGE',
            ':app verification code: :code. Valid for :minutes minutes. Do not share this code.'
        ),
    ],

    'reviews' => [
        // How long a student can change their mind about a review.
        'edit_window_days' => (int) env('REVIEW_EDIT_WINDOW_DAYS', 7),
    ],

    'lesson_durations' => [30, 45, 60, 90],

    /*
    |--------------------------------------------------------------------------
    | Service catalog (lesson plans, paper marking, Q&A)
    |--------------------------------------------------------------------------
    |
    | Defaults for the teacher-managed service catalog. Teachers override the
    | price, scope and session options per offering; these values only act as
    | platform-wide bounds and fallbacks.
    |
    */

    'services' => [
        // Lesson plans: a purchased allocation must be consumed before this.
        'plan_expiry_days' => (int) env('SERVICES_PLAN_EXPIRY_DAYS', 90),
        // Prorated refund of the unused balance when a plan expires.
        'refund_unused_balance_on_expiry' => (bool) env('SERVICES_REFUND_UNUSED_BALANCE', true),
        // Fallback session length when an offering does not define one.
        'default_session_minutes' => (int) env('SERVICES_DEFAULT_SESSION_MINUTES', 60),
        // Hard cap on sessions per plan (protects the availability engine).
        'max_sessions_per_plan' => (int) env('SERVICES_MAX_SESSIONS_PER_PLAN', 20),
        // Paper marking SLA fallback and upload limits.
        'marking_default_turnaround_hours' => (int) env('SERVICES_MARKING_TURNAROUND_HOURS', 72),
        'marking_max_files' => (int) env('SERVICES_MARKING_MAX_FILES', 10),
        // A studio-generated PDF may be larger than a student's upload.
        'marking_generated_max_kb' => (int) env('SERVICES_MARKING_GENERATED_MAX_KB', 15360),
        'marking_default_max_pages' => (int) env('SERVICES_MARKING_MAX_PAGES', 20),
        // Q&A sessions reuse the standard lesson durations.
    ],

    'weekdays' => [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        0 => 'Sunday',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sri Lankan Education Structure
    |--------------------------------------------------------------------------
    |
    | Bootstrap definition of the four education levels. The rows live in the
    | `education_levels` and `grades` tables (seeded by EducationLevelSeeder);
    | this config is the reference the seeder and the backfill read.
    |
    | `baskets` groups the optional subjects of a level the way the examination
    | does - in the O/L a Grade 10-11 candidate picks one subject from each of
    | the three categories, while Grades 6-9 pick their Category I subject only
    | - and `basket_grades` lists every grade where that choice happens. Each
    | basket also declares the `grades` it runs in. The basket rows live in
    | `subject_baskets` (seeded by SubjectBasketSeeder) and carry that range as
    | grade_min/grade_max, and every subject points at its basket.
    |
    | A subject is offered to a grade when its level, its basket and its own
    | `grade_band` (see App\Enums\GradeBand) all cover the grade.
    |
    */

    'education_levels' => [
        'primary' => ['name' => 'Primary', 'grade_min' => 1, 'grade_max' => 5, 'icon' => '🎒'],
        'ol' => [
            'name' => 'O/L (Ordinary Level)',
            'grade_min' => 6,
            'grade_max' => 11,
            'icon' => '📘',
            'basket_grades' => [6, 7, 8, 9, 10, 11],
            'baskets' => [
                'category_1' => [
                    'name' => 'Category I',
                    'icon' => '🎨',
                    'description' => 'Aesthetic, music, dancing, literature and drama subjects',
                    'grades' => [6, 7, 8, 9, 10, 11],
                ],
                'category_2' => [
                    'name' => 'Category II',
                    'icon' => '🛠️',
                    'description' => 'Technical, practical and technology subjects',
                    'grades' => [10, 11],
                ],
                'category_3' => [
                    'name' => 'Category III',
                    'icon' => '📚',
                    'description' => 'Academic, commerce and second-language subjects',
                    'grades' => [10, 11],
                ],
            ],
        ],
        'al' => ['name' => 'A/L (Advanced Level)', 'grade_min' => 12, 'grade_max' => 13, 'icon' => '🎓'],
        'other' => ['name' => 'Other', 'grade_min' => null, 'grade_max' => null, 'icon' => '🧭'],
    ],

    // The languages a teacher can teach in.
    'languages' => [
        'Sinhala', 'English', 'Tamil',
    ],

    // The medium of instruction a student follows. Asked in the student
    // onboarding wizard and editable from the learning profile.
    'student_learning_languages' => [
        'Sinhala', 'English',
    ],

    'verification_document_types' => [
        'id_proof' => 'Government ID',
        'degree' => 'Degree or diploma',
        'certificate' => 'Teaching certificate',
        'other' => 'Other supporting document',
    ],

];
