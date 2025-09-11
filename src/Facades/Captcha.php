<?php

namespace SnipifyDev\LaravelCaptcha\Facades;

use Illuminate\Support\Facades\Facade;
use SnipifyDev\LaravelCaptcha\Services\CaptchaManager;

/**
 * Captcha Facade
 *
 * @method static bool verify(string $token, string $action = 'default', float|null $threshold = null, string|null $version = null)
 * @method static float|null getScore(string $token, string $action = 'default')
 * @method static bool isEnabled(string $action = 'default')
 * @method static \SnipifyDev\LaravelCaptcha\Rules\RecaptchaV2Rule|\SnipifyDev\LaravelCaptcha\Rules\RecaptchaV3Rule rule(string $action = 'default', float|null $threshold = null, string|null $version = null)
 * @method static string|null getSiteKey(string|null $version = null)
 * @method static string|null getSecretKey(string|null $version = null)
 * @method static float getThreshold(string $action = 'default')
 * @method static mixed config(string $key, mixed $default = null)
 * @method static string|false getVersion()
 * @method static bool shouldSkipValidation()
 * @method static void setEnabled(bool $enabled)
 * @method static void setVersion(string $version)
 * @method static array getJavaScriptConfig(string|null $version = null)
 * @method static \SnipifyDev\LaravelCaptcha\Services\RecaptchaV2Service|\SnipifyDev\LaravelCaptcha\Services\RecaptchaV3Service|\SnipifyDev\LaravelCaptcha\Services\NullCaptchaService driver(string|null $driver = null)
 *
 * @see CaptchaManager
 */
class Captcha extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'captcha';
    }
}