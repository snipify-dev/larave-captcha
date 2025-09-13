# Laravel reCAPTCHA Package

A simple, Laravel-native package for integrating Google reCAPTCHA v2 and v3 with both standard forms and Livewire components.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/snipify-dev/laravel-captcha.svg?style=flat-square)](https://packagist.org/packages/snipify-dev/laravel-captcha)
[![Total Downloads](https://img.shields.io/packagist/dt/snipify-dev/laravel-captcha.svg?style=flat-square)](https://packagist.org/packages/snipify-dev/laravel-captcha)

## Features

- 🚀 **Laravel Validation Rules**: Native Laravel validation with ValidationRule support
- ⚡ **Simple Integration**: Works out of the box with minimal configuration
- 🛡️ **Multi-Version Support**: reCAPTCHA v2 and v3
- 🎯 **Livewire Ready**: Built for Livewire components
- 🧪 **Testing Friendly**: Automatically disabled in testing environments

## Requirements

- PHP 8.2+
- Laravel 10.x - 12.x
- Google reCAPTCHA API keys
- Livewire 3.x (for Livewire features)

## Installation

### 1. Install the Package

```bash
composer require snipify-dev/laravel-captcha
```

### 2. Get reCAPTCHA Keys

1. Visit the [Google reCAPTCHA Admin Console](https://www.google.com/recaptcha/admin)
2. Create a new site and choose your reCAPTCHA type
3. Add your domains (including localhost for development)
4. Copy the Site Key and Secret Key

### 3. Configure Environment Variables

Add your reCAPTCHA keys to your `.env` file:

```env
# Choose your default version
RECAPTCHA_VERSION=v3

# reCAPTCHA v3 Keys (recommended)
RECAPTCHAV3_SECRET=your_v3_secret_key_here
RECAPTCHAV3_SITEKEY=your_v3_site_key_here

# reCAPTCHA v2 Keys (optional)
RECAPTCHAV2_SECRET=your_v2_secret_key_here
RECAPTCHAV2_SITEKEY=your_v2_site_key_here
```

### 4. Clear Configuration Cache

```bash
php artisan config:clear
```

That's it! The package is ready to use.

## Basic Usage

### Livewire Component

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

### Livewire View

```blade
<div>
    <form wire:submit.prevent="submit" class="space-y-4">
        <div>
            <label>Name</label>
            <input type="text" wire:model="name">
            @error('name') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>
        
        <div>
            <label>Email</label>
            <input type="email" wire:model="email">
            @error('email') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>
        
        <div>
            <label>Message</label>
            <textarea wire:model="message"></textarea>
            @error('message') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>
        
        {{-- Captcha field --}}
        <x-captcha-livewire-field wire-model="captchaToken" action="contact" />
        @error('captchaToken') <span class="text-red-500">{{ $message }}</span> @enderror
        
        <button type="submit">Submit Form</button>
    </form>
</div>
```

### Traditional Controller

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

## Components

### Livewire Field Component

```blade
<x-captcha-livewire-field 
    wire-model="captchaToken"     {{-- Required: Livewire property --}}
    action="contact"              {{-- Optional: Action for v3 scoring --}}
    version="v3"                  {{-- Optional: Force version (v2/v3) --}}
/>
```

### Include Scripts

Add this to your layout before closing `</body>` tag:

```blade
<x-captcha-script />
```

## Validation Rules

```php
// Basic usage with any action name
new RecaptchaValidationRule('login')
new RecaptchaValidationRule('contact') 
new RecaptchaValidationRule('signup')
new RecaptchaValidationRule('any_action_name')

// Force specific version if needed
new RecaptchaValidationRule('login', null, 'v2')  // Force v2
new RecaptchaValidationRule('login', null, 'v3')  // Force v3
```

## Testing

The package automatically disables validation in testing environments:

```php
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
```

## Troubleshooting

### Common Issues

**"Site key not found" Error**
```bash
php artisan config:clear
# Verify your .env keys are correct
```

**Validation Always Fails**
- Check your secret key is correct in `.env`
- Verify the site key matches your domain
- Enable fake mode for development: `RECAPTCHA_FAKE_DEVELOPMENT=true`

**JavaScript Errors**
- Include `<x-captcha-script />` before `</body>`
- Check browser console for errors

**Livewire Integration**
- Ensure your component has `public $captchaToken = '';`
- Use `wire-model="captchaToken"` in the component

## Configuration

Optionally publish the config file for advanced customization:

```bash
php artisan vendor:publish --tag=recaptcha-config
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.