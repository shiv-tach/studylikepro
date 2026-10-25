<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'base_url' => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com/v1'),
        'timeout' => (int) env('RAZORPAY_TIMEOUT', 30),
    ],

    'daily' => [
        'key' => env('DAILY_API_KEY'),
        'base_url' => env('DAILY_BASE_URL', 'https://api.daily.co/v1'),
        'timeout' => (int) env('DAILY_TIMEOUT', 20),
    ],

    /*
    | SMSLenz (Sri Lankan SMS gateway)
    |
    | Credentials from https://smslenz.lk/account/api-key. `sender_id` is the
    | approved sender and is case sensitive; SMSlenzDEMO works for testing.
    | Only the server talks to the provider, so these stay out of the browser.
    */
    'smslenz' => [
        'user_id' => env('SMSLENZ_USER_ID'),
        'api_key' => env('SMSLENZ_API_KEY'),
        'sender_id' => env('SMSLENZ_SENDER_ID', 'SMSlenzDEMO'),
        'base_url' => env('SMSLENZ_BASE_URL', 'https://smslenz.lk/api'),
        'timeout' => (int) env('SMSLENZ_TIMEOUT', 15),
    ],

];
