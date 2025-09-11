<?php

namespace SnipifyDev\LaravelCaptcha\View\Components;

use Illuminate\View\Component;
use SnipifyDev\LaravelCaptcha\Facades\Captcha;

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
     * Create a new component instance.
     *
     * @param string|null $version
     * @param bool|null $enabled
     */
    public function __construct(?string $version = null, ?bool $enabled = null)
    {
        $this->version = $version ?: Captcha::getVersion();
        $this->enabled = $enabled ?? Captcha::isEnabled();
        $this->siteKey = $this->enabled ? Captcha::getSiteKey($this->version) : null;
        $this->jsConfig = $this->enabled ? Captcha::getJavaScriptConfig($this->version) : [];
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('captcha::components.captcha-script');
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

        if ($this->version === 'v3') {
            return "https://www.google.com/recaptcha/api.js?render={$this->siteKey}";
        } elseif ($this->version === 'v2') {
            return 'https://www.google.com/recaptcha/api.js';
        }

        return null;
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

        $filename = $this->version === 'v3' ? 'captcha-v3.js' : 'captcha-v2.js';
        return asset("vendor/laravel-captcha/js/{$filename}");
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