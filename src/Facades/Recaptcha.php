<?php

namespace SnipifyDev\LaravelCaptcha\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array verify(string $token, ?string $action = null, ?float $threshold = null, ?string $version = null)
 * @method static bool validate(string $token, ?string $action = null, ?float $threshold = null)
 * @method static string|null getSiteKey(?string $version = null)
 * @method static array getJavaScriptConfig(?string $version = null)
 * @method static bool isEnabled()
 * @method static array getActionThresholds()
 * @method static \SnipifyDev\LaravelCaptcha\Rules\Recaptcha rule(?string $action = null, ?float $threshold = null, ?string $version = null)
 * @method static array generateTestData(string $version = 'v3')
 *
 * @see \SnipifyDev\LaravelCaptcha\Services\RecaptchaService
 */
class Recaptcha extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'recaptcha';
    }
}