# Laravel reCAPTCHA Package

A modern, Laravel-native package for integrating Google reCAPTCHA v2 and v3 with both standard forms and Livewire components. Features proper Laravel validation rules and multiple integration approaches.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/snipify-dev/laravel-captcha.svg?style=flat-square)](https://packagist.org/packages/snipify-dev/laravel-captcha)
[![Total Downloads](https://img.shields.io/packagist/dt/snipify-dev/laravel-captcha.svg?style=flat-square)](https://packagist.org/packages/snipify-dev/laravel-captcha)

## ✨ Features

- 🚀 **Laravel Validation Rules**: Native Laravel validation with InvokableRule support
- ⚡ **Multiple Integration Methods**: Choose what works best for your use case
- 🛡️ **Multi-Version Support**: reCAPTCHA v2 (checkbox & invisible) and v3 (score-based)
- 🎯 **Livewire Optimized**: Built for Livewire 3.x with attribute-based validation
- 🧪 **Testing Friendly**: Automatically disabled in testing environments
- 📦 **Zero Configuration**: Works out of the box with sensible defaults
- 🎛️ **Flexible**: String rules, InvokableRules, FormRequests, and Livewire attributes
- 🌩️ **Future Ready**: Cloudflare Turnstile support coming soon

## 🎯 What's New in v2.0

- ✅ **Modern Laravel Validation Rules** - Use familiar `$this->validate()` syntax
- ✅ **InvokableRule Support** - `new RecaptchaValidationRule('login')`
- ✅ **Livewire Attributes** - `#[ValidatesRecaptcha('contact')]`
- ✅ **FormRequest Integration** - Extend `BaseRecaptchaRequest`
- ✅ **String-based Rules** - `'captchaToken' => 'required|recaptcha:login'`
- ✅ **Auto-detection** - Automatically detects v2 vs v3 tokens
- ✅ **Enhanced Error Handling** - Better error messages and logging

## 📋 Requirements

- PHP 8.2+
- Laravel 10.x - 12.x
- Google reCAPTCHA API keys
- Livewire 3.x (for Livewire features)

## 🚀 Installation

### Step 1: Install the Package

```bash
composer require snipify-dev/laravel-captcha
```

### Step 2: Configure Environment Variables

Add your reCAPTCHA keys to your `.env` file:

```env
# Choose your default captcha version
LARAVEL_CAPTCHA_DEFAULT=v3

# reCAPTCHA v3 Keys (recommended)
LARAVEL_CAPTCHA_SECRET_KEY_V3=your_v3_secret_key_here
LARAVEL_CAPTCHA_SITE_KEY_V3=your_v3_site_key_here

# reCAPTCHA v2 Keys (optional)
LARAVEL_CAPTCHA_SECRET_KEY_V2=your_v2_secret_key_here
LARAVEL_CAPTCHA_SITE_KEY_V2=your_v2_site_key_here

# Optional: Score threshold for v3 (default: 0.5)
LARAVEL_CAPTCHA_SCORE_THRESHOLD=0.5
```

### Step 3: Clear Configuration Cache

```bash
php artisan config:clear
```

**That's it! The package auto-registers and is ready to use.**

## 📚 Getting Your reCAPTCHA Keys

1. Visit the [Google reCAPTCHA Admin Console](https://www.google.com/recaptcha/admin)
2. Click "+" to create a new site
3. Choose your reCAPTCHA type:
   - **v3 (Recommended)**: Score-based, invisible to users
   - **v2**: Checkbox "I'm not a robot"
4. Add your domains (including localhost for development)
5. Copy the Site Key and Secret Key to your `.env` file

## ⚡ Quick Start Examples

### 1. Modern Laravel Validation (Recommended)

#### Livewire Component

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use SnipifyDev\LaravelCaptcha\Rules\RecaptchaValidationRule;

class ContactForm extends Component
{
    public $name = '';
    public $email = '';
    public $message = '';
    public $captchaToken = '';
    
    public function submit()
    {
        // Clean Laravel validation with captcha
        $this->validate([
            'name' => 'required|min:3',
            'email' => 'required|email',
            'message' => 'required|min:10',
            'captchaToken' => ['required', new RecaptchaValidationRule('contact')],
        ]);
        
        // Process your form here...
        $this->reset();
        session()->flash('success', 'Message sent successfully!');
    }
    
    public function render()
    {
        return view('livewire.contact-form');
    }
}
```

#### Livewire View

```blade
{{-- resources/views/livewire/contact-form.blade.php --}}
<div>
    @if (session()->has('success'))
        <div class="px-4 py-3 mb-4 text-green-700 bg-green-100 rounded border border-green-400">
            {{ session('success') }}
        </div>
    @endif
    
    <form wire:submit.prevent="submit" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Name</label>
            <input type="text" wire:model="name" class="block px-3 py-2 mt-1 w-full rounded-md border border-gray-300">
            @error('name') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" wire:model="email" class="block px-3 py-2 mt-1 w-full rounded-md border border-gray-300">
            @error('email') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700">Message</label>
            <textarea wire:model="message" rows="3" class="block px-3 py-2 mt-1 w-full rounded-md border border-gray-300"></textarea>
            @error('message') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>
        
        {{-- Simple captcha field --}}
        <x-captcha:livewire-field wire:model="captchaToken" action="contact" />
        @error('captchaToken') <span class="text-red-500">{{ $message }}</span> @enderror
        
        <button type="submit" class="px-4 py-2 w-full text-white bg-blue-500 rounded-md hover:bg-blue-600">
            Submit Form
        </button>
    </form>
</div>
```

#### Traditional Controller

```php
<?php

use Illuminate\Http\Request;
use SnipifyDev\LaravelCaptcha\Rules\RecaptchaValidationRule;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'message' => 'required|string|min:10',
            'g-recaptcha-response' => ['required', new RecaptchaValidationRule('contact')],
        ]);
        
        // Process form...
        return back()->with('success', 'Message sent!');
    }
}
```

### 2. Livewire Attribute-Based Validation

```php
<?php

use Livewire\Component;
use SnipifyDev\LaravelCaptcha\Attributes\ValidatesRecaptcha;

class LoginForm extends Component
{
    public $email = '';
    public $password = '';
    public $captchaToken = '';
    
    #[ValidatesRecaptcha('login')]
    public function authenticate()
    {
        // This method only runs if captcha validation passes
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);
        
        // Attempt authentication...
    }
    
    public function render()
    {
        return view('livewire.login-form');
    }
}
```

### 3. String-Based Validation Rules

```php
// Basic usage
$request->validate([
    'email' => 'required|email',
    'captchaToken' => 'required|recaptcha:login'
]);

// With custom threshold for v3
$request->validate([
    'email' => 'required|email',
    'captchaToken' => 'required|recaptcha:payment,0.8'
]);

// Force specific version
$request->validate([
    'email' => 'required|email',
    'captchaToken' => 'required|recaptcha:contact,0.5,v2'
]);
```

### 4. FormRequest Classes

```php
<?php

use SnipifyDev\LaravelCaptcha\Http\Requests\BaseRecaptchaRequest;

class ContactRequest extends BaseRecaptchaRequest
{
    protected ?string $captchaAction = 'contact';
    protected ?string $captchaVersion = 'v2';
    
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'message' => 'required|string|min:10',
        ];
        
        return $this->withCaptchaRules($rules);
    }
}

// Use in controller
public function store(ContactRequest $request)
{
    $validated = $request->validated();
    // Process form...
}
```

### 5. Enhanced WithCaptcha Trait

```php
<?php

use Livewire\Component;
use SnipifyDev\LaravelCaptcha\Traits\WithCaptcha;

class ContactForm extends Component
{
    use WithCaptcha;
    
    public $email = '';
    public $message = '';
    
    public function submit()
    {
        // Simple captcha validation
        $this->validateCaptcha('contact');
        
        // Or validate with other fields
        $this->validateWithCaptcha([
            'email' => 'required|email',
            'message' => 'required|min:10'
        ], [], [], 'contact');
        
        // Process form...
        $this->resetCaptcha();
    }
}
```

## 🧰 Factory Methods & Shortcuts

The `RecaptchaValidationRule` provides convenient factory methods:

```php
// Common actions
RecaptchaValidationRule::login()           // For login forms
RecaptchaValidationRule::register()        // For registration forms  
RecaptchaValidationRule::contact()         // For contact forms
RecaptchaValidationRule::comment()         // For comment forms
RecaptchaValidationRule::payment()         // For payment forms

// Version-specific
RecaptchaValidationRule::v2()              // Force v2 validation
RecaptchaValidationRule::v3('action', 0.7) // Force v3 with threshold

// Method chaining
RecaptchaValidationRule::login()
    ->threshold(0.8)
    ->version('v3');

// Livewire attributes
#[ValidatesRecaptcha::login()]
#[ValidatesRecaptcha::v2('contact')]
#[ValidatesRecaptcha::v3('payment', 0.9)]
```

## 🎛️ Available Components

### Livewire Field Component

```blade
<x-captcha:livewire-field 
    wire:model="captchaToken"     {{-- Required: Livewire property --}}
    action="contact"              {{-- Optional: Action for v3 scoring --}}
    version="v3"                  {{-- Optional: Force version --}}
    :threshold="0.7"              {{-- Optional: Custom threshold --}}
    class="my-class"              {{-- Optional: CSS classes --}}
/>
```

### HTML Field Component (for regular forms)

```blade
<x-captcha:html-field 
    name="g-recaptcha-response"   {{-- Optional: Field name --}}
    action="contact"              {{-- Optional: Action for v3 --}}
    version="v2"                  {{-- Optional: Force version --}}
/>
```

### Script Component

```blade
{{-- Include before closing </body> tag --}}
<x-captcha:script />
```

## ⚙️ Configuration

Publish the config file for advanced customization:

### Basic Configuration

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=captcha-config
```

```php
// config/captcha.php
return [
    // Choose version: 'v2', 'v3', or false to disable
    'default' => env('CAPTCHA_VERSION', 'v3'),
    
    // Automatically disabled in testing
    'testing' => [
        'enabled' => env('CAPTCHA_TESTING', env('APP_ENV') === 'testing'),
    ],
    
    // API keys
    'services' => [
        'recaptcha' => [
            'site_key' => env('RECAPTCHAV3_SITEKEY'),
            'secret_key' => env('RECAPTCHAV3_SECRET'),
            'v2_site_key' => env('RECAPTCHAV2_SITEKEY'),
            'v2_secret_key' => env('RECAPTCHAV2_SECRET'),
        ],
    ],
    
    // v3 specific settings
    'v3' => [
        'score_threshold' => env('RECAPTCHA_SCORE_THRESHOLD', 0.5),
        'actions' => [
            'login' => 0.5,
            'register' => 0.7,
            'contact' => 0.5,
            'payment' => 0.8,
        ],
    ],
];
```

## 🧰 WithCaptcha Trait Methods

The `WithCaptcha` trait provides these convenient methods:

```php
// Get validation rules with captcha included
protected function withCaptchaRules(array $rules, string $action = 'default'): array

// Get individual captcha rule
protected function captchaRule(string $action = 'default', ?float $threshold = null)

// Reset captcha after form submission
public function resetCaptcha(): void

// Refresh captcha token (for error recovery)
public function refreshCaptchaToken(): void

// Check if captcha is enabled
public function isCaptchaEnabled(): bool

// Get current captcha version
public function getCaptchaVersion(): string

// Validate form with captcha (backward compatibility)
public function validateWithCaptcha(array $rules, array $messages = [], array $attributes = [], string $action = 'default'): array
```

## 🔧 Available Components

### Livewire Field Component

```blade
<x-captcha::livewire-field 
    wire-model="captchaToken"     {{-- Required: Livewire model --}}
    action="contact"              {{-- Optional: Action name for scoring --}}
    version="v3"                  {{-- Optional: Force specific version --}}
    :show-error="true"            {{-- Optional: Show error messages --}}
    class="custom-class"          {{-- Optional: Additional CSS classes --}}
/>
```

### Script Component

```blade
<x-captcha::script />
```

## 🧪 Testing

The package automatically disables validation in testing environments:

```php
// In your tests
public function test_contact_form_submission()
{
    $response = $this->post('/contact', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'message' => 'Test message',
        'captchaToken' => 'fake-token', // Ignored in testing
    ]);
    
    $response->assertRedirect()->assertSessionHas('success');
}

// For Livewire tests
public function test_livewire_form()
{
    Livewire::test(ContactForm::class)
        ->set('name', 'John')
        ->set('email', 'john@test.com')
        ->set('message', 'Hello world')
        ->set('captchaToken', 'fake-token')
        ->call('submit')
        ->assertHasNoErrors();
}
```

## 🌍 Version Support

### Google reCAPTCHA v3 ✅
- **Score-based validation** (0.0 to 1.0)
- **Action-specific scoring** for better accuracy
- **Invisible to users** - no interaction required
- **Better user experience** - no clicking required

### Google reCAPTCHA v2 ✅  
- **Checkbox validation** - "I'm not a robot"
- **Invisible v2** - triggered programmatically
- **Fallback support** for older implementations
- **Visual confirmation** - users know they completed it

### Cloudflare Turnstile 🔜 Coming Soon
- **Privacy-focused** alternative to Google reCAPTCHA
- **Better performance** and user experience  
- **GDPR compliant** - no user data sent to Google
- **Same API** - drop-in replacement when available

## 🐛 Troubleshooting

### Common Issues

#### 1. "Site key not found" Error
```bash
php artisan config:clear
# Verify your .env keys are correct
```

#### 2. Validation Always Fails
```env
# Check your secret key is correct
LARAVEL_CAPTCHA_SECRET_KEY_V3=correct_secret_here

# Or enable fake mode for development
LARAVEL_CAPTCHA_FAKE_IN_DEVELOPMENT=true
```

#### 3. JavaScript Errors
- Include `<x-captcha:script />` before `</body>`
- Check browser console for detailed errors
- Verify site key matches your domain

#### 4. Livewire Property Missing
```php
// Make sure your component has the captcha property
public $captchaToken = '';
```

### Debug Mode

Enable detailed logging:

```env
LARAVEL_CAPTCHA_LOG_ERRORS=true
LARAVEL_CAPTCHA_LOG_LEVEL=debug
```

## 📈 Migration Guide

### From Manual Validation

**Before:**
```php
$captchaRule = new Recaptcha('login', null);
$captchaError = null;
$captchaRule->validate('captchaToken', $this->captchaToken, function ($message) use (&$captchaError) {
    $captchaError = $message;
});

if ($captchaError) {
    $this->addError('captchaToken', $captchaError);
    return;
}
```

**After:**
```php
$this->validate([
    'captchaToken' => ['required', new RecaptchaValidationRule('login')]
]);
```

### From Old Package Versions

1. **Update validation syntax** - Use new `RecaptchaValidationRule`
2. **Update environment variables** - New naming convention
3. **Update component usage** - New component names
4. **Remove manual error handling** - Now handled by Laravel

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 🛡️ Security

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

## 🚀 Why Choose This Package?

- ✅ **Laravel Native** - Uses proper Laravel validation patterns
- ✅ **Multiple Approaches** - Choose what fits your architecture  
- ✅ **Well Tested** - Production-ready with comprehensive tests
- ✅ **Future Proof** - Built for modern Laravel and Livewire
- ✅ **Developer Friendly** - Extensive documentation and examples
- ✅ **Performance Optimized** - Minimal overhead and smart caching