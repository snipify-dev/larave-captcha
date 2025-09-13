<?php

namespace SnipifyDev\LaravelCaptcha\View\Components;

use Illuminate\View\Component;
use SnipifyDev\LaravelCaptcha\Facades\Recaptcha;

/**
 * Captcha Script Component
 * 
 * Loads the appropriate reCAPTCHA script and configuration
 */
class CaptchaScript extends Component
{
    /**
     * The captcha version to use.
     *
     * @var string|null
     */
    public ?string $version;

    /**
     * The site key.
     *
     * @var string|null
     */
    public ?string $siteKey;

    /**
     * Whether captcha is enabled.
     *
     * @var bool
     */
    public bool $enabled;

    /**
     * JavaScript configuration.
     *
     * @var array
     */
    public array $jsConfig;

    /**
     * The Google reCAPTCHA script URL.
     *
     * @var string|null
     */
    public ?string $scriptUrl;

    /**
     * The package JavaScript asset URL.
     *
     * @var string|null
     */
    public ?string $packageScriptUrl;

    /**
     * Create a new component instance.
     *
     * @param string|null $version
     * @param bool|null $enabled
     */
    public function __construct(?string $version = null, ?bool $enabled = null)
    {
        $this->version = $version ?? config('laravel-captcha.default', 'v3');
        $this->enabled = $enabled ?? Recaptcha::isEnabled();
        $this->siteKey = $this->enabled ? Recaptcha::getSiteKey($this->version) : null;
        $this->jsConfig = $this->enabled ? Recaptcha::getJavaScriptConfig($this->version) : [];
        $this->scriptUrl = $this->getScriptUrl();
        $this->packageScriptUrl = $this->getPackageScriptUrl();
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('recaptcha::components.captcha-script');
    }

    /**
     * Get the Google reCAPTCHA script URL.
     *
     * @return string|null
     */
    public function getScriptUrl(): ?string
    {
        if (!$this->enabled || !$this->siteKey) {
            return null;
        }

        // Check if we have mixed versions on the page
        $hasV2 = !empty(config('laravel-captcha.site_key_v2'));
        $hasV3 = !empty(config('laravel-captcha.site_key_v3'));
        
        // If we have both v2 and v3, or if we're using v3, load the v3 script
        if (($hasV2 && $hasV3) || $this->version === 'v3') {
            // v3 script URL with render parameter for the v3 site key
            $v3SiteKey = config('laravel-captcha.site_key_v3');
            if ($v3SiteKey) {
                return "https://www.google.com/recaptcha/api.js?render={$v3SiteKey}";
            }
        }
        
        // Fallback to v2 script for v2 only or when v3 key is not available
        return 'https://www.google.com/recaptcha/api.js';
    }

    /**
     * Get the package JavaScript asset URL.
     *
     * @return string|null
     */
    public function getPackageScriptUrl(): ?string
    {
        if (!$this->enabled || !$this->version) {
            return null;
        }

        return asset("vendor/recaptcha/recaptcha.js");
    }

    /**
     * Check if script should be loaded asynchronously.
     *
     * @return bool
     */
    public function shouldLoadAsync(): bool
    {
        return $this->version === 'v2';
    }

    /**
     * Get JSON-encoded JavaScript configuration.
     *
     * @return string
     */
    public function getJsConfigJson(): string
    {
        return json_encode($this->jsConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }
}
