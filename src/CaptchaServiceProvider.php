<?php

namespace SnipifyDev\LaravelCaptcha;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use SnipifyDev\LaravelCaptcha\Services\RecaptchaService;
use SnipifyDev\LaravelCaptcha\View\Components\Recaptcha;
use SnipifyDev\LaravelCaptcha\Rules\Recaptcha as RecaptchaRule;
use SnipifyDev\LaravelCaptcha\Rules\RecaptchaValidationRule;

class CaptchaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->bootConfig();
        $this->bootViews();
        $this->bootAssets();
        $this->bootBladeComponents();
        $this->bootValidationRules();
        $this->bootCommands();
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), 'laravel-captcha');

        $this->registerServices();
        $this->registerFacades();
    }

    /**
     * Bootstrap configuration publishing
     */
    protected function bootConfig(): void
    {
        $this->publishes([
            $this->getConfigPath() => config_path('laravel-captcha.php'),
        ], 'recaptcha-config');
    }

    /**
     * Bootstrap view publishing
     */
    protected function bootViews(): void
    {
        $this->loadViewsFrom($this->getViewsPath(), 'recaptcha');

        $this->publishes([
            $this->getViewsPath() => resource_path('views/vendor/recaptcha'),
        ], 'recaptcha-views');
    }

    /**
     * Bootstrap asset publishing
     */
    protected function bootAssets(): void
    {
        $this->publishes([
            $this->getAssetsPath() => public_path('vendor/recaptcha'),
        ], 'recaptcha-assets');
    }

    /**
     * Bootstrap Blade components
     */
    protected function bootBladeComponents(): void
    {
        // Register captcha components
        $this->loadViewComponentsAs('captcha', [
            'script' => \SnipifyDev\LaravelCaptcha\View\Components\CaptchaScript::class,
            'livewire-field' => \SnipifyDev\LaravelCaptcha\View\Components\LivewireField::class,
            'html-field' => \SnipifyDev\LaravelCaptcha\View\Components\HtmlField::class,
        ]);
        
        // Register simplified Blade component
        $this->loadViewComponentsAs('recaptcha', [
            'field' => Recaptcha::class,
        ]);
        
        // Also register without namespace for convenience
        Blade::component('recaptcha', Recaptcha::class);
    }

    /**
     * Bootstrap custom validation rules
     */
    protected function bootValidationRules(): void
    {
        // Register the string-based validation rule for backward compatibility
        Validator::extend('recaptcha', function ($attribute, $value, $parameters, $validator) {
            $action = !empty($parameters[0]) ? $parameters[0] : null;
            $threshold = !empty($parameters[1]) ? (float) $parameters[1] : null;
            $version = !empty($parameters[2]) ? $parameters[2] : null;

            $rule = new RecaptchaValidationRule($action, $threshold, $version);
            
            try {
                $errorMessage = null;
                $rule($attribute, $value, function($message) use (&$errorMessage) {
                    $errorMessage = $message;
                });
                
                // If there's an error message, it failed validation
                return $errorMessage === null;
            } catch (\Exception $e) {
                // Log the exception for debugging
                logger()->error('reCAPTCHA validation exception', [
                    'attribute' => $attribute,
                    'error' => $e->getMessage(),
                    'action' => $action,
                    'version' => $version
                ]);
                return false;
            }
        });

        // Make the rule implicit (validates even when field is empty)
        Validator::extendImplicit('recaptcha', function ($attribute, $value, $parameters, $validator) {
            return app()->call([$this, 'validateRecaptcha'], compact('attribute', 'value', 'parameters', 'validator'));
        });

        // Register custom error message replacer
        Validator::replacer('recaptcha', function ($message, $attribute, $rule, $parameters) {
            $action = !empty($parameters[0]) ? $parameters[0] : null;
            $version = !empty($parameters[2]) ? $parameters[2] : null;
            
            // Get appropriate error message based on context
            if ($version === 'v3') {
                return config('laravel-captcha.errors.messages.score_too_low', 'reCAPTCHA score too low. Please try again.');
            }
            
            return config('laravel-captcha.errors.messages.invalid', 'reCAPTCHA verification failed. Please try again.');
        });
    }

    /**
     * Validate reCAPTCHA using the new rule (for implicit validation)
     */
    protected function validateRecaptcha($attribute, $value, $parameters, $validator)
    {
        $action = !empty($parameters[0]) ? $parameters[0] : null;
        $threshold = !empty($parameters[1]) ? (float) $parameters[1] : null;
        $version = !empty($parameters[2]) ? $parameters[2] : null;

        $rule = new RecaptchaValidationRule($action, $threshold, $version);
        
        try {
            $errorMessage = null;
            $rule($attribute, $value, function($message) use (&$errorMessage) {
                $errorMessage = $message;
            });
            
            return $errorMessage === null;
        } catch (\Exception $e) {
            logger()->error('reCAPTCHA implicit validation exception', [
                'attribute' => $attribute,
                'error' => $e->getMessage(),
                'action' => $action,
                'version' => $version
            ]);
            return false;
        }
    }


    /**
     * Bootstrap Artisan commands
     */
    protected function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            // Register commands here if needed in the future
        }
    }

    /**
     * Register services
     */
    protected function registerServices(): void
    {
        // Register the simplified reCAPTCHA service
        $this->app->singleton(RecaptchaService::class, function ($app) {
            return new RecaptchaService();
        });

        // Bind the service to 'recaptcha' key for facade
        $this->app->bind('recaptcha', RecaptchaService::class);
    }

    /**
     * Register facades
     */
    protected function registerFacades(): void
    {
        // The facade is automatically registered via composer.json extra.laravel.aliases
    }

    /**
     * Get the config file path
     */
    protected function getConfigPath(): string
    {
        return __DIR__ . '/../config/laravel-captcha.php';
    }

    /**
     * Get the views path
     */
    protected function getViewsPath(): string
    {
        return __DIR__ . '/../resources/views';
    }

    /**
     * Get the assets path
     */
    protected function getAssetsPath(): string
    {
        return __DIR__ . '/../resources/js';
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'recaptcha',
            RecaptchaService::class,
        ];
    }
}