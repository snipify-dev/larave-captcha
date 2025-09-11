<?php

namespace SnipifyDev\LaravelCaptcha;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use SnipifyDev\LaravelCaptcha\Services\CaptchaManager;
use SnipifyDev\LaravelCaptcha\Services\RecaptchaV2Service;
use SnipifyDev\LaravelCaptcha\Services\RecaptchaV3Service;
use SnipifyDev\LaravelCaptcha\View\Components\CaptchaField;
use SnipifyDev\LaravelCaptcha\View\Components\CaptchaScript;
use SnipifyDev\LaravelCaptcha\Http\Middleware\VerifyCaptcha;
use SnipifyDev\LaravelCaptcha\Rules\RecaptchaV2Rule;
use SnipifyDev\LaravelCaptcha\Rules\RecaptchaV3Rule;

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
        $this->bootMiddleware();
        $this->bootCommands();
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), 'captcha');

        $this->registerServices();
        $this->registerFacades();
    }

    /**
     * Bootstrap configuration publishing
     */
    protected function bootConfig(): void
    {
        $this->publishes([
            $this->getConfigPath() => config_path('captcha.php'),
        ], 'captcha-config');
    }

    /**
     * Bootstrap view publishing
     */
    protected function bootViews(): void
    {
        $this->loadViewsFrom($this->getViewsPath(), 'captcha');

        $this->publishes([
            $this->getViewsPath() => resource_path('views/vendor/captcha'),
        ], 'captcha-views');
    }

    /**
     * Bootstrap asset publishing
     */
    protected function bootAssets(): void
    {
        $this->publishes([
            $this->getAssetsPath() => public_path('vendor/laravel-captcha'),
        ], 'captcha-assets');
    }

    /**
     * Bootstrap Blade components
     */
    protected function bootBladeComponents(): void
    {
        // Register Blade components with backward compatibility
        if (method_exists(Blade::class, 'component')) {
            Blade::component('captcha-script', CaptchaScript::class);
            Blade::component('captcha-field', CaptchaField::class);
        }

        // For Laravel 7+ anonymous components
        if (method_exists(Blade::class, 'anonymousComponentNamespace')) {
            Blade::anonymousComponentNamespace('captcha', 'captcha');
        }
    }

    /**
     * Bootstrap custom validation rules
     */
    protected function bootValidationRules(): void
    {
        // Extend validator with captcha rules
        Validator::extend('recaptcha', function ($attribute, $value, $parameters, $validator) {
            $version = config('captcha.default');
            
            if (!$version || $this->shouldSkipValidation()) {
                return true;
            }

            $action = $parameters[0] ?? 'default';
            $threshold = isset($parameters[1]) ? (float) $parameters[1] : null;

            try {
                if ($version === 'v3') {
                    $rule = new RecaptchaV3Rule($action, $threshold);
                } else {
                    $rule = new RecaptchaV2Rule();
                }

                return $rule->passes($attribute, $value);
            } catch (\Exception $e) {
                if (config('captcha.errors.log_errors')) {
                    logger()->log(
                        config('captcha.errors.log_level', 'warning'),
                        'Captcha validation error: ' . $e->getMessage(),
                        ['exception' => $e]
                    );
                }
                return false;
            }
        });

        Validator::replacer('recaptcha', function ($message, $attribute, $rule, $parameters) {
            return config('captcha.errors.messages.invalid', $message);
        });
    }

    /**
     * Bootstrap middleware
     */
    protected function bootMiddleware(): void
    {
        // Register middleware with router (Laravel 5.4+)
        if (method_exists($this->app['router'], 'aliasMiddleware')) {
            $this->app['router']->aliasMiddleware('captcha', VerifyCaptcha::class);
        }

        // Backward compatibility for older Laravel versions
        if (method_exists($this->app['router'], 'middleware')) {
            $this->app['router']->middleware('captcha', VerifyCaptcha::class);
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
        // Register reCAPTCHA v2 service
        $this->app->singleton(RecaptchaV2Service::class, function ($app) {
            return new RecaptchaV2Service(
                $app['config']->get('captcha'),
                $app['log']
            );
        });

        // Register reCAPTCHA v3 service
        $this->app->singleton(RecaptchaV3Service::class, function ($app) {
            return new RecaptchaV3Service(
                $app['config']->get('captcha'),
                $app['log']
            );
        });

        // Register captcha manager
        $this->app->singleton(CaptchaManager::class, function ($app) {
            return new CaptchaManager(
                $app,
                $app['config']->get('captcha')
            );
        });

        // Bind the manager to the 'captcha' key for facade
        $this->app->bind('captcha', CaptchaManager::class);
    }

    /**
     * Register facades
     */
    protected function registerFacades(): void
    {
        // The facade is automatically registered via composer.json extra.laravel.aliases
    }

    /**
     * Check if validation should be skipped
     */
    protected function shouldSkipValidation(): bool
    {
        // Skip in testing environment
        if (config('captcha.skip_testing', true) && app()->environment('testing')) {
            return true;
        }

        // Skip in development if fake mode is enabled
        if (config('captcha.fake_in_development', false) && app()->environment('local')) {
            return true;
        }

        return false;
    }

    /**
     * Get the config file path
     */
    protected function getConfigPath(): string
    {
        return __DIR__ . '/../config/captcha.php';
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
            'captcha',
            CaptchaManager::class,
            RecaptchaV2Service::class,
            RecaptchaV3Service::class,
        ];
    }
}