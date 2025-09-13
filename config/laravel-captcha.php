<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Configuration
    |--------------------------------------------------------------------------
    |
    | The default reCAPTCHA version to use throughout your application.
    | Supported: "v2", "v3", false (to disable)
    |
    */
    'default' => env('RECAPTCHA_VERSION', 'v3'),

    /*
    |--------------------------------------------------------------------------
    | reCAPTCHA v3 Configuration
    |--------------------------------------------------------------------------
    |
    | Your reCAPTCHA v3 site key and secret key from Google reCAPTCHA admin.
    | Get them from: https://www.google.com/recaptcha/admin
    |
    */
    'site_key_v3' => env('RECAPTCHAV3_SITEKEY'),
    'secret_key_v3' => env('RECAPTCHAV3_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | reCAPTCHA v2 Configuration
    |--------------------------------------------------------------------------
    |
    | Your reCAPTCHA v2 site key and secret key. If not set, v3 keys will be used.
    |
    */
    'site_key_v2' => env('RECAPTCHAV2_SITEKEY', env('RECAPTCHAV3_SITEKEY')),
    'secret_key_v2' => env('RECAPTCHAV2_SECRET', env('RECAPTCHAV3_SECRET')),

    /*
    |--------------------------------------------------------------------------
    | Score Threshold (v3 only)
    |--------------------------------------------------------------------------
    |
    | The minimum score required for reCAPTCHA v3 to pass validation.
    | Range: 0.0 (likely bot) to 1.0 (likely human). Default: 0.5
    |
    */
    'score_threshold' => env('RECAPTCHA_SCORE_THRESHOLD', 0.5),


    /*
    |--------------------------------------------------------------------------
    | Testing Configuration
    |--------------------------------------------------------------------------
    |
    | When enabled, reCAPTCHA validation will be bypassed during testing.
    |
    */
    'testing' => [
        'enabled' => env('RECAPTCHA_SKIP_TESTING', env('APP_ENV') === 'testing'),
        'fake_in_development' => env('RECAPTCHA_FAKE_DEVELOPMENT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | reCAPTCHA API endpoint and timeout settings.
    |
    */
    'api_url' => 'https://www.google.com/recaptcha/api/siteverify',
    'timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Error Messages
    |--------------------------------------------------------------------------
    |
    | Default error messages for validation failures.
    |
    */
    'error_messages' => [
        'required' => 'Please complete the reCAPTCHA verification.',
        'invalid' => 'reCAPTCHA verification failed. Please try again.',
        'score_too_low' => 'reCAPTCHA score too low. Please try again.',
        'network_error' => 'Unable to verify reCAPTCHA. Please try again.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Widget Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the reCAPTCHA widget appearance.
    |
    */
    'widget' => [
        // v2 widget settings
        'v2' => [
            'theme' => env('RECAPTCHA_V2_THEME', 'light'), // light, dark
            'size' => env('RECAPTCHA_V2_SIZE', 'normal'), // normal, compact
        ],
        // v3 badge settings
        'v3' => [
            'hide_badge' => env('RECAPTCHA_HIDE_BADGE', false),
        ],
    ],
];