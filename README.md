# Laravel Captcha

A comprehensive Laravel package for integrating Google reCAPTCHA v2 and v3 with support for standard forms and Livewire components.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/snipify-dev/laravel-captcha.svg?style=flat-square)](https://packagist.org/packages/snipify-dev/laravel-captcha)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/snipify-dev/laravel-captcha/run-tests?label=tests)](https://github.com/snipify-dev/laravel-captcha/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/snipify-dev/laravel-captcha/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/snipify-dev/laravel-captcha/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/snipify-dev/laravel-captcha.svg?style=flat-square)](https://packagist.org/packages/snipify-dev/laravel-captcha)

## Features

- 🚀 **Multi-Version Support**: reCAPTCHA v2 (checkbox & invisible) and v3 (score-based)
- ⚡ **Livewire Integration**: Seamless integration with Livewire components including auto-refresh
- 🎨 **Blade Components**: Easy-to-use Blade components for quick implementation
- 🛡️ **Security First**: Built-in protection against common attacks and security best practices
- 🧪 **Testing Friendly**: Skip validation in testing environments with configurable test modes
- 📊 **Comprehensive Logging**: Detailed error logging and debugging capabilities
- 🎛️ **Highly Configurable**: Extensive configuration options for every use case
- 🔄 **Auto-Refresh**: Automatic token refresh for v3 to prevent expiration
- 🌐 **Multi-Form Support**: Handle multiple forms on the same page
- ⚖️ **Laravel Version Support**: Compatible with Laravel 8.x through 12.x

## Requirements

- PHP 8.0+
- Laravel 8.x - 12.x
- GuzzleHTTP 7.0+
- Google reCAPTCHA API keys

## Installation

### Step 1: Install the Package

#### Via Composer (when published to Packagist)

```bash
composer require snipify-dev/laravel-captcha
```

#### Via Local Path (for development)

Add to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/laravel-captcha"
        }
    ],
    "require": {
        "your-vendor/laravel-captcha": "*"
    }
}
```

Then run:

```bash
composer update
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=captcha-config
```

### Step 3: Publish Assets (Optional)

```bash
# Publish views for customization
php artisan vendor:publish --tag=captcha-views

# Publish JavaScript assets
php artisan vendor:publish --tag=captcha-assets
```

### Step 4: Configure Environment Variables

Add your reCAPTCHA keys to your `.env` file:

```env
# Required: Choose your captcha version
CAPTCHA_VERSION=v3

# reCAPTCHA v3 Keys
RECAPTCHAV3_SITEKEY=your_v3_site_key_here
RECAPTCHAV3_SECRET=your_v3_secret_key_here

# reCAPTCHA v2 Keys (optional, will fallback to v3 keys)
RECAPTCHAV2_SITEKEY=your_v2_site_key_here
RECAPTCHAV2_SECRET=your_v2_secret_key_here

# Optional: Score threshold for v3 (0.0 to 1.0, default: 0.5)
RECAPTCHA_SCORE_THRESHOLD=0.5

# Optional: Skip captcha in testing (default: true)
CAPTCHA_SKIP_TESTING=true

# Optional: Fake captcha in development (default: false)
CAPTCHA_FAKE_DEVELOPMENT=false
```

### Step 5: Clear Configuration Cache

```bash
php artisan config:clear
```

## Getting Your reCAPTCHA Keys

1. Visit the [Google reCAPTCHA Admin Console](https://www.google.com/recaptcha/admin)
2. Click "+" to create a new site
3. Choose your reCAPTCHA type:
   - **reCAPTCHA v3**: For score-based validation (recommended)
   - **reCAPTCHA v2**: For checkbox or invisible validation
4. Add your domains (including localhost for development)
5. Copy the **Site Key** and **Secret Key** to your `.env` file

## Quick Start

### Basic Form Implementation

#### 1. Add Captcha to Your Form

```blade
{{-- In your form view --}}
<form method="POST" action="{{ route('contact.store') }}">
    @csrf
    
    <input type="text" name="name" required>
    <input type="email" name="email" required>
    <textarea name="message" required></textarea>
    
    {{-- Add CAPTCHA field --}}
    <x-captcha-field action="contact" />
    
    <button type="submit">Send Message</button>
</form>

{{-- Include CAPTCHA scripts (add to your layout) --}}
<x-captcha-script />
```

#### 2. Validate in Your Controller

```php
<?php

use Illuminate\Http\Request;
use YourVendor\LaravelCaptcha\Rules\RecaptchaV3Rule;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'message' => 'required|string|min:10',
            'captcha_token' => ['required', new RecaptchaV3Rule('contact')],
        ]);
        
        // Process your form...
    }
}
```

### Livewire Component Implementation

#### 1. Create Your Livewire Component

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use YourVendor\LaravelCaptcha\Traits\WithCaptcha;

class ContactForm extends Component
{
    use WithCaptcha;
    
    public $name;
    public $email;
    public $message;
    
    public function submit()
    {
        $this->validate([
            'name' => 'required|min:3',
            'email' => 'required|email',
            'message' => 'required|min:10',
            'captchaToken' => $this->captchaRule('contact'),
        ]);
        
        // Process the form...
        
        // Refresh token for next submission
        $this->refreshCaptchaToken();
        
        session()->flash('message', 'Message sent successfully!');
    }
    
    public function render()
    {
        return view('livewire.contact-form');
    }
}
```

#### 2. Create the Livewire View

```blade
{{-- resources/views/livewire/contact-form.blade.php --}}
<form wire:submit.prevent="submit">
    <div>
        <label for="name">Name</label>
        <input wire:model="name" type="text" id="name" required>
        @error('name') <span class="error">{{ $message }}</span> @enderror
    </div>
    
    <div>
        <label for="email">Email</label>
        <input wire:model="email" type="email" id="email" required>
        @error('email') <span class="error">{{ $message }}</span> @enderror
    </div>
    
    <div>
        <label for="message">Message</label>
        <textarea wire:model="message" id="message" required></textarea>
        @error('message') <span class="error">{{ $message }}</span> @enderror
    </div>
    
    {{-- Hidden captcha field for v3 --}}
    <input type="hidden" wire:model="captchaToken" class="captcha-v3-field" data-action="contact">
    @error('captchaToken') <span class="error">{{ $message }}</span> @enderror
    
    <button type="submit">Send Message</button>
</form>
```

#### 3. Include Scripts in Your Layout

```blade
{{-- In your layout file --}}
<x-captcha-script />
```

## Configuration

The package offers extensive configuration options. Here are the most important ones:

### Basic Configuration

```php
// config/captcha.php

return [
    // Choose version: 'v2', 'v3', or false to disable
    'default' => env('CAPTCHA_VERSION', 'v3'),
    
    // Skip captcha in testing
    'skip_testing' => env('CAPTCHA_SKIP_TESTING', true),
    
    // Fake captcha in development
    'fake_in_development' => env('CAPTCHA_FAKE_DEVELOPMENT', false),
    
    // API keys
    'services' => [
        'recaptcha' => [
            'site_key' => env('RECAPTCHAV3_SITEKEY'),
            'secret_key' => env('RECAPTCHAV3_SECRET'),
            'v2_site_key' => env('RECAPTCHAV2_SITEKEY'),
            'v2_secret_key' => env('RECAPTCHAV2_SECRET'),
        ],
    ],
];
```

### reCAPTCHA v3 Configuration

```php
'v3' => [
    'default_threshold' => 0.5,
    
    // Different thresholds for different actions
    'thresholds' => [
        'login' => 0.5,
        'register' => 0.7,
        'contact' => 0.5,
        'payment' => 0.8,
    ],
    
    'badge' => [
        'hide' => false,
        'position' => 'bottomright', // bottomright, bottomleft, inline
    ],
],
```

### reCAPTCHA v2 Configuration

```php
'v2' => [
    'theme' => 'light', // light, dark
    'size' => 'normal', // normal, compact
    'type' => 'image', // image, audio
    'invisible' => false, // Enable invisible reCAPTCHA
],
```

### Form-specific Settings

```php
'forms' => [
    'login' => true,
    'register' => true,
    'contact' => true,
    'payment' => true,
    // Add your custom actions here
],
```

## Usage Examples

### Using the Facade

```php
use YourVendor\LaravelCaptcha\Facades\Captcha;

// Check if captcha is enabled
if (Captcha::isEnabled('login')) {
    // Perform validation
}

// Verify a token manually
$isValid = Captcha::verify($token, 'login', 0.7);

// Get score for v3 (returns null for v2)
$score = Captcha::getScore($token, 'login');

// Get site key
$siteKey = Captcha::getSiteKey();

// Switch version temporarily
Captcha::setVersion('v2');
```

### Using Validation Rules

```php
use YourVendor\LaravelCaptcha\Rules\RecaptchaV3Rule;
use YourVendor\LaravelCaptcha\Rules\RecaptchaV2Rule;

// v3 with custom threshold
'captcha' => ['required', RecaptchaV3Rule::login(0.8)],

// v3 with different actions
'captcha' => ['required', RecaptchaV3Rule::payment()],
'captcha' => ['required', RecaptchaV3Rule::contact()],
'captcha' => ['required', RecaptchaV3Rule::register()],

// v2 rule
'captcha' => ['required', RecaptchaV2Rule::make()],

// Using string validation (Laravel 8.x style)
'captcha' => 'required|recaptcha:contact,0.7',
```

### Using Middleware

```php
// In your routes
Route::post('/api/submit', [ApiController::class, 'submit'])
    ->middleware('captcha:api,0.8');

// In your controller constructor
public function __construct()
{
    $this->middleware('captcha:payment,0.9')->only(['store', 'update']);
}
```

### Custom Blade Components

```blade
{{-- Basic usage --}}
<x-captcha-field />

{{-- With custom action and name --}}
<x-captcha-field action="review" name="recaptcha_response" />

{{-- With custom CSS classes --}}
<x-captcha-field 
    action="payment" 
    class="my-captcha-field"
    wrapper-class="my-wrapper"
/>

{{-- Force specific version --}}
<x-captcha-field action="contact" version="v2" />

{{-- For v2 with custom attributes --}}
<x-captcha-field 
    version="v2" 
    :attributes="['data-theme' => 'dark', 'data-size' => 'compact']"
/>
```

### Multiple Forms on Same Page

```blade
{{-- Login form --}}
<form id="login-form">
    <input type="email" name="email">
    <input type="password" name="password">
    <x-captcha-field action="login" id="login-captcha" />
    <button type="submit">Login</button>
</form>

{{-- Register form --}}
<form id="register-form">
    <input type="text" name="name">
    <input type="email" name="email">
    <input type="password" name="password">
    <x-captcha-field action="register" id="register-captcha" />
    <button type="submit">Register</button>
</form>

{{-- Include scripts once --}}
<x-captcha-script />
```

## Livewire Integration

### WithCaptcha Trait

The `WithCaptcha` trait provides convenient methods for Livewire components:

```php
use YourVendor\LaravelCaptcha\Traits\WithCaptcha;

class MyComponent extends Component
{
    use WithCaptcha;
    
    public function someAction()
    {
        $this->validate([
            'captchaToken' => $this->captchaRule('action_name'),
        ]);
        
        // Refresh token after successful action
        $this->refreshCaptchaToken();
    }
    
    // Manual token refresh
    public function refreshToken()
    {
        $this->refreshCaptchaToken();
    }
}
```

### Livewire Events

```php
// In your Livewire component
$this->dispatch('captcha:refresh');

// Listen in JavaScript
Livewire.on('captcha:refresh', () => {
    // Token will be automatically refreshed
});
```

### Auto-refresh Configuration

```php
// config/captcha.php
'livewire' => [
    'enabled' => true,
    'auto_refresh' => true,
    'refresh_interval' => 110, // seconds
    'emit_events' => true,
],
```

## Testing

### Skip Captcha in Tests

```php
// In your test
public function test_form_submission()
{
    config(['captcha.skip_testing' => true]);
    
    $response = $this->post('/contact', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'message' => 'Test message',
        'captcha_token' => 'any_value', // Will be ignored
    ]);
    
    $response->assertStatus(200);
}
```

### Using Test Keys

```php
// Set test keys in your testing environment
config([
    'captcha.development.use_test_keys' => true,
    'captcha.services.recaptcha.site_key' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',
    'captcha.services.recaptcha.secret_key' => '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe',
]);
```

## Error Handling

### Custom Error Messages

```php
// config/captcha.php
'errors' => [
    'messages' => [
        'required' => 'Please complete the captcha verification.',
        'invalid' => 'Captcha verification failed. Please try again.',
        'score_too_low' => 'Security check failed. Please try again.',
        'expired' => 'Captcha has expired. Please refresh and try again.',
    ],
],
```

### Exception Handling

```php
use YourVendor\LaravelCaptcha\Exceptions\CaptchaValidationException;

try {
    Captcha::verify($token, 'login');
} catch (CaptchaValidationException $e) {
    logger()->error('Captcha validation failed', [
        'message' => $e->getMessage(),
        'score' => $e->getScore(),
        'action' => $e->getAction(),
        'response_data' => $e->getResponseData(),
    ]);
}
```

## Advanced Features

### Caching

Enable caching to reduce API calls:

```php
// config/captcha.php
'cache' => [
    'enabled' => true,
    'driver' => null, // Uses default cache driver
    'ttl' => 300, // 5 minutes
    'cache_failures' => true,
],
```

### Rate Limiting

Prevent abuse with rate limiting:

```php
'rate_limiting' => [
    'enabled' => true,
    'max_attempts' => 10,
    'decay_minutes' => 1,
],
```

### Security Options

```php
'security' => [
    'verify_hostname' => true,
    'expected_hostname' => null, // Uses APP_URL if null
    'verify_ip' => false,
    'allow_localhost' => true,
],
```

## Troubleshooting

### Common Issues

#### 1. "Site key not found" Error

```bash
# Check your environment variables
php artisan config:clear
php artisan cache:clear

# Verify your .env file has the correct keys
RECAPTCHAV3_SITEKEY=your_actual_site_key
RECAPTCHAV3_SECRET=your_actual_secret_key
```

#### 2. JavaScript Not Loading

```blade
{{-- Ensure you're including the script component --}}
<x-captcha-script />

{{-- Check browser console for JavaScript errors --}}
{{-- Verify assets are published --}}
php artisan vendor:publish --tag=captcha-assets --force
```

#### 3. Livewire Integration Issues

```php
// Make sure Livewire is properly configured
// Check that wire:model is bound to captchaToken

public $captchaToken; // This property must exist
```

#### 4. Validation Always Failing

```php
// Check if you're in testing mode
config(['captcha.skip_testing' => true]);

// Or enable fake mode for development
config(['captcha.fake_in_development' => true]);

// Verify your secret key is correct
```

#### 5. Tokens Expiring Too Quickly

```php
// Increase refresh interval (v3 tokens expire after 2 minutes)
config(['captcha.livewire.refresh_interval' => 110]); // Refresh every 110 seconds
```

### Debug Mode

Enable debug mode to see detailed information:

```env
CAPTCHA_DEBUG=true
```

This will show:
- Field initialization logs
- Token generation details
- Error information
- Configuration details

### Getting Help

1. Check the browser console for JavaScript errors
2. Enable debug mode for detailed logging
3. Verify your reCAPTCHA keys are correct
4. Test with Google's test keys first
5. Check Laravel logs for detailed error messages

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Your Name](https://github.com/your-username)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.