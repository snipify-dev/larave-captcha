<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Captcha Version
    |--------------------------------------------------------------------------
    |
    | This option controls the default captcha version that will be used
    | throughout your application. Supported: "v2", "v3", false
    |
    | false - Disables captcha validation entirely
    | "v2" - Uses reCAPTCHA v2 checkbox/invisible
    | "v3" - Uses reCAPTCHA v3 score-based validation
    |
    */
    'default' => env('CAPTCHA_VERSION', 'v3'),

    /*
    |--------------------------------------------------------------------------
    | Skip Captcha in Testing
    |--------------------------------------------------------------------------
    |
    | When true, captcha validation will be skipped during testing.
    | This allows your tests to run without requiring valid captcha tokens.
    |
    */
    'skip_testing' => env('CAPTCHA_SKIP_TESTING', true),

    /*
    |--------------------------------------------------------------------------
    | Fake Captcha in Development
    |--------------------------------------------------------------------------
    |
    | When true and APP_ENV is 'local', captcha validation will always pass.
    | This is useful during development to avoid constant captcha solving.
    |
    */
    'fake_in_development' => env('CAPTCHA_FAKE_DEVELOPMENT', false),

    /*
    |--------------------------------------------------------------------------
    | Services Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the various captcha services and their settings.
    | Currently only Google reCAPTCHA is supported but this structure allows
    | for future expansion to other providers.
    |
    */
    'services' => [
        'recaptcha' => [
            // reCAPTCHA v3 Keys
            'site_key' => env('RECAPTCHAV3_SITEKEY', env('CAPTCHA_SITE_KEY')),
            'secret_key' => env('RECAPTCHAV3_SECRET', env('CAPTCHA_SECRET_KEY')),
            
            // reCAPTCHA v2 Keys (optional, falls back to v3 keys if not set)
            'v2_site_key' => env('RECAPTCHAV2_SITEKEY', env('RECAPTCHAV3_SITEKEY', env('CAPTCHA_SITE_KEY'))),
            'v2_secret_key' => env('RECAPTCHAV2_SECRET', env('RECAPTCHAV3_SECRET', env('CAPTCHA_SECRET_KEY'))),
            
            // API Configuration
            'api_url' => 'https://www.google.com/recaptcha/api/siteverify',
            'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
            'timeout' => 30,
            'verify_ssl' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | reCAPTCHA v3 Configuration
    |--------------------------------------------------------------------------
    |
    | reCAPTCHA v3 uses a score-based system where actions are scored from
    | 0.0 to 1.0. Higher scores indicate more human-like behavior.
    |
    */
    'v3' => [
        // Default score threshold (0.0 to 1.0)
        'default_threshold' => env('RECAPTCHA_SCORE_THRESHOLD', 0.5),

        // Action-specific score thresholds
        'thresholds' => [
            'login' => env('RECAPTCHA_LOGIN_THRESHOLD', 0.5),
            'register' => env('RECAPTCHA_REGISTER_THRESHOLD', 0.7),
            'contact' => env('RECAPTCHA_CONTACT_THRESHOLD', 0.5),
            'comment' => env('RECAPTCHA_COMMENT_THRESHOLD', 0.6),
            'review' => env('RECAPTCHA_REVIEW_THRESHOLD', 0.6),
            'payment' => env('RECAPTCHA_PAYMENT_THRESHOLD', 0.8),
            'api' => env('RECAPTCHA_API_THRESHOLD', 0.7),
            'submit' => env('RECAPTCHA_SUBMIT_THRESHOLD', 0.5),
            'default' => env('RECAPTCHA_DEFAULT_THRESHOLD', 0.5),
        ],

        // Token lifetime in seconds (Google default is 120 seconds)
        'token_lifetime' => 120,

        // Auto-refresh tokens before expiry (seconds before expiry)
        'refresh_before_expiry' => 10,

        // Badge configuration
        'badge' => [
            'hide' => env('RECAPTCHA_HIDE_BADGE', false),
            'position' => env('RECAPTCHA_BADGE_POSITION', 'bottomright'), // bottomright, bottomleft, inline
        ],

        // Additional v3 options
        'enterprise' => env('RECAPTCHA_ENTERPRISE', false),
        'project_id' => env('RECAPTCHA_PROJECT_ID', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | reCAPTCHA v2 Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for reCAPTCHA v2 checkbox and invisible modes.
    |
    */
    'v2' => [
        // Widget appearance
        'theme' => env('RECAPTCHA_V2_THEME', 'light'), // light, dark
        'size' => env('RECAPTCHA_V2_SIZE', 'normal'), // normal, compact
        'type' => env('RECAPTCHA_V2_TYPE', 'image'), // image, audio
        'tabindex' => env('RECAPTCHA_V2_TABINDEX', 0),
        
        // Invisible reCAPTCHA v2
        'invisible' => env('RECAPTCHA_V2_INVISIBLE', false),
        'callback' => env('RECAPTCHA_V2_CALLBACK', null),
        'expired_callback' => env('RECAPTCHA_V2_EXPIRED_CALLBACK', null),
        'error_callback' => env('RECAPTCHA_V2_ERROR_CALLBACK', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which forms should have captcha enabled by default.
    | These can be overridden per form as needed.
    |
    */
    'forms' => [
        'login' => env('CAPTCHA_ENABLE_LOGIN', true),
        'register' => env('CAPTCHA_ENABLE_REGISTER', true),
        'contact' => env('CAPTCHA_ENABLE_CONTACT', true),
        'comment' => env('CAPTCHA_ENABLE_COMMENT', true),
        'review' => env('CAPTCHA_ENABLE_REVIEW', true),
        'payment' => env('CAPTCHA_ENABLE_PAYMENT', true),
        'api' => env('CAPTCHA_ENABLE_API', false),
        'admin' => env('CAPTCHA_ENABLE_ADMIN', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire Integration
    |--------------------------------------------------------------------------
    |
    | Configuration options for Livewire component integration.
    |
    */
    'livewire' => [
        // Enable Livewire support
        'enabled' => env('CAPTCHA_LIVEWIRE_ENABLED', true),
        
        // Auto-refresh tokens in Livewire components
        'auto_refresh' => env('CAPTCHA_LIVEWIRE_AUTO_REFRESH', true),
        
        // Refresh interval in seconds (should be less than token lifetime)
        'refresh_interval' => env('CAPTCHA_LIVEWIRE_REFRESH_INTERVAL', 110),
        
        // Emit Livewire events for token updates
        'emit_events' => env('CAPTCHA_LIVEWIRE_EMIT_EVENTS', true),
        
        // Events to listen for token refresh
        'refresh_events' => [
            'captcha:refresh',
            'captcha:renew',
            'form:reset',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how captcha validation errors are handled.
    |
    */
    'errors' => [
        // Default error messages
        'messages' => [
            'required' => 'The captcha field is required.',
            'invalid' => 'The captcha verification failed. Please try again.',
            'expired' => 'The captcha has expired. Please refresh and try again.',
            'score_too_low' => 'The captcha score is too low. Please try again.',
            'action_mismatch' => 'The captcha action does not match.',
            'hostname_mismatch' => 'The captcha hostname does not match.',
            'timeout' => 'The captcha verification timed out. Please try again.',
            'network_error' => 'Unable to verify captcha due to network error.',
            'invalid_keys' => 'Invalid captcha configuration. Please contact support.',
        ],

        // Log captcha errors
        'log_errors' => env('CAPTCHA_LOG_ERRORS', true),
        
        // Log level for errors
        'log_level' => env('CAPTCHA_LOG_LEVEL', 'warning'),
        
        // Include score in error logs for debugging
        'log_score' => env('CAPTCHA_LOG_SCORE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for captcha verifications to improve performance
    | and reduce API calls to Google's servers.
    |
    */
    'cache' => [
        // Enable caching of successful verifications
        'enabled' => env('CAPTCHA_CACHE_ENABLED', false),
        
        // Cache driver to use (uses app default if null)
        'driver' => env('CAPTCHA_CACHE_DRIVER', null),
        
        // Cache prefix for keys
        'prefix' => env('CAPTCHA_CACHE_PREFIX', 'captcha'),
        
        // Cache TTL in seconds
        'ttl' => env('CAPTCHA_CACHE_TTL', 300), // 5 minutes
        
        // Cache failed attempts to prevent replay attacks
        'cache_failures' => env('CAPTCHA_CACHE_FAILURES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for captcha verifications to prevent abuse.
    |
    */
    'rate_limiting' => [
        // Enable rate limiting
        'enabled' => env('CAPTCHA_RATE_LIMITING', false),
        
        // Maximum attempts per minute per IP
        'max_attempts' => env('CAPTCHA_RATE_LIMIT_ATTEMPTS', 10),
        
        // Decay time in minutes
        'decay_minutes' => env('CAPTCHA_RATE_LIMIT_DECAY', 1),
        
        // Custom rate limit key (uses IP by default)
        'key_generator' => null, // Callback function
    ],

    /*
    |--------------------------------------------------------------------------
    | Development & Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Special configuration options for development and testing environments.
    |
    */
    'development' => [
        // Test keys provided by Google for development
        'test_keys' => [
            'v2' => [
                'site_key' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',
                'secret_key' => '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe',
            ],
            'v3' => [
                'site_key' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',
                'secret_key' => '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe',
            ],
        ],
        
        // Use test keys in development
        'use_test_keys' => env('CAPTCHA_USE_TEST_KEYS', false),
        
        // Debug mode - provides detailed error information
        'debug' => env('CAPTCHA_DEBUG', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Additional security measures for captcha validation.
    |
    */
    'security' => [
        // Verify hostname in responses
        'verify_hostname' => env('CAPTCHA_VERIFY_HOSTNAME', true),
        
        // Expected hostname (uses APP_URL if null)
        'expected_hostname' => env('CAPTCHA_EXPECTED_HOSTNAME', null),
        
        // Verify IP address
        'verify_ip' => env('CAPTCHA_VERIFY_IP', false),
        
        // Allow localhost for development
        'allow_localhost' => env('CAPTCHA_ALLOW_LOCALHOST', true),
        
        // Strict SSL verification
        'verify_ssl' => env('CAPTCHA_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Attributes
    |--------------------------------------------------------------------------
    |
    | Custom HTML attributes for captcha elements.
    |
    */
    'attributes' => [
        // Custom CSS classes
        'css_classes' => [
            'wrapper' => env('CAPTCHA_WRAPPER_CLASS', 'captcha-wrapper'),
            'field' => env('CAPTCHA_FIELD_CLASS', 'captcha-field'),
            'error' => env('CAPTCHA_ERROR_CLASS', 'captcha-error'),
        ],
        
        // Custom HTML attributes for widgets
        'widget_attributes' => [
            // Additional attributes for v2 widget
            'v2' => [],
            // Additional attributes for v3 field
            'v3' => [],
        ],
    ],
];