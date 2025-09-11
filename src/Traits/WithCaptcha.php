<?php

namespace SnipifyDev\LaravelCaptcha\Traits;

use SnipifyDev\LaravelCaptcha\Facades\Captcha;
use SnipifyDev\LaravelCaptcha\Rules\RecaptchaV2Rule;
use SnipifyDev\LaravelCaptcha\Rules\RecaptchaV3Rule;

/**
 * Livewire Captcha Trait
 * 
 * Provides convenient methods for integrating captcha with Livewire components
 */
trait WithCaptcha
{
    /**
     * The captcha token property.
     * This should be bound to your form input via wire:model
     *
     * @var string|null
     */
    public $captchaToken;

    /**
     * Initialize captcha properties
     */
    public function initializeWithCaptcha(): void
    {
        if (!property_exists($this, 'captchaToken')) {
            $this->captchaToken = null;
        }
    }

    /**
     * Get the appropriate captcha validation rule
     *
     * @param string $action
     * @param float|null $threshold
     * @param string|null $version
     * @return RecaptchaV2Rule|RecaptchaV3Rule|string
     */
    protected function captchaRule(
        string $action = 'default',
        ?float $threshold = null,
        ?string $version = null
    ): RecaptchaV2Rule|RecaptchaV3Rule|string {
        // Return simple string rule if captcha is disabled
        if (!Captcha::isEnabled($action)) {
            return 'nullable';
        }

        try {
            return Captcha::rule($action, $threshold, $version);
        } catch (\Exception $e) {
            // Fallback to string validation for maximum compatibility
            $actualVersion = $version ?: Captcha::getVersion();
            
            if ($actualVersion === 'v3') {
                $params = $action;
                if ($threshold !== null) {
                    $params .= ",{$threshold}";
                }
                return "required|recaptcha:{$params}";
            } else {
                return 'required|recaptcha';
            }
        }
    }

    /**
     * Get captcha rule for login action
     *
     * @param float|null $threshold
     * @return RecaptchaV2Rule|RecaptchaV3Rule|string
     */
    protected function captchaLoginRule(?float $threshold = null): RecaptchaV2Rule|RecaptchaV3Rule|string
    {
        return $this->captchaRule('login', $threshold);
    }

    /**
     * Get captcha rule for register action
     *
     * @param float|null $threshold
     * @return RecaptchaV2Rule|RecaptchaV3Rule|string
     */
    protected function captchaRegisterRule(?float $threshold = null): RecaptchaV2Rule|RecaptchaV3Rule|string
    {
        return $this->captchaRule('register', $threshold);
    }

    /**
     * Get captcha rule for contact action
     *
     * @param float|null $threshold
     * @return RecaptchaV2Rule|RecaptchaV3Rule|string
     */
    protected function captchaContactRule(?float $threshold = null): RecaptchaV2Rule|RecaptchaV3Rule|string
    {
        return $this->captchaRule('contact', $threshold);
    }

    /**
     * Get captcha rule for comment action
     *
     * @param float|null $threshold
     * @return RecaptchaV2Rule|RecaptchaV3Rule|string
     */
    protected function captchaCommentRule(?float $threshold = null): RecaptchaV2Rule|RecaptchaV3Rule|string
    {
        return $this->captchaRule('comment', $threshold);
    }

    /**
     * Get captcha rule for review action
     *
     * @param float|null $threshold
     * @return RecaptchaV2Rule|RecaptchaV3Rule|string
     */
    protected function captchaReviewRule(?float $threshold = null): RecaptchaV2Rule|RecaptchaV3Rule|string
    {
        return $this->captchaRule('review', $threshold);
    }

    /**
     * Get captcha rule for payment action
     *
     * @param float|null $threshold
     * @return RecaptchaV2Rule|RecaptchaV3Rule|string
     */
    protected function captchaPaymentRule(?float $threshold = null): RecaptchaV2Rule|RecaptchaV3Rule|string
    {
        return $this->captchaRule('payment', $threshold);
    }

    /**
     * Refresh the captcha token (triggers JavaScript refresh)
     */
    public function refreshCaptchaToken(): void
    {
        if (Captcha::config('livewire.emit_events', true)) {
            $this->dispatch('captcha:refresh');
        }
    }

    /**
     * Renew captcha token (alias for refresh)
     */
    public function renewCaptchaToken(): void
    {
        $this->refreshCaptchaToken();
    }

    /**
     * Reset captcha token and clear any validation errors
     */
    public function resetCaptcha(): void
    {
        $this->captchaToken = null;
        $this->resetErrorBag('captchaToken');
        $this->refreshCaptchaToken();
    }

    /**
     * Validate captcha manually
     *
     * @param string $action
     * @param float|null $threshold
     * @return bool
     */
    public function validateCaptcha(string $action = 'default', ?float $threshold = null): bool
    {
        if (!Captcha::isEnabled($action)) {
            return true;
        }

        try {
            return Captcha::verify($this->captchaToken, $action, $threshold);
        } catch (\Exception $e) {
            $this->addError('captchaToken', 'Captcha verification failed. Please try again.');
            return false;
        }
    }

    /**
     * Get the current captcha score (v3 only)
     *
     * @param string $action
     * @return float|null
     */
    public function getCaptchaScore(string $action = 'default'): ?float
    {
        if (!$this->captchaToken || Captcha::getVersion() !== 'v3') {
            return null;
        }

        try {
            return Captcha::getScore($this->captchaToken, $action);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if captcha is currently enabled
     *
     * @param string $action
     * @return bool
     */
    public function isCaptchaEnabled(string $action = 'default'): bool
    {
        return Captcha::isEnabled($action);
    }

    /**
     * Get the current captcha version
     *
     * @return string|false
     */
    public function getCaptchaVersion(): string|false
    {
        return Captcha::getVersion();
    }

    /**
     * Hook into Livewire's hydrate method to refresh tokens
     */
    public function hydrateWithCaptcha(): void
    {
        // Auto-refresh token after component hydration if configured
        if (Captcha::config('livewire.auto_refresh', true) && Captcha::getVersion() === 'v3') {
            // This will be handled by JavaScript, but we can trigger it if needed
            $this->dispatch('captcha:component-hydrated');
        }
    }

    /**
     * Hook into Livewire's dehydrate method
     */
    public function dehydrateWithCaptcha(): void
    {
        // Clean up or log if needed
        if (Captcha::config('development.debug', false)) {
            logger()->debug('Captcha component dehydrated', [
                'component' => get_class($this),
                'has_token' => !empty($this->captchaToken),
            ]);
        }
    }

    /**
     * Validate captcha before form submission
     * Call this in your form submission methods for additional security
     *
     * @param string $action
     * @param float|null $threshold
     * @return bool
     */
    protected function ensureCaptchaValid(string $action = 'default', ?float $threshold = null): bool
    {
        if (!$this->isCaptchaEnabled($action)) {
            return true;
        }

        if (empty($this->captchaToken)) {
            $this->addError('captchaToken', 'Please complete the captcha verification.');
            return false;
        }

        return $this->validateCaptcha($action, $threshold);
    }

    /**
     * Get validation rules array with captcha included
     *
     * @param array $rules
     * @param string $action
     * @param float|null $threshold
     * @param string $fieldName
     * @return array
     */
    protected function withCaptchaRules(
        array $rules,
        string $action = 'default',
        ?float $threshold = null,
        string $fieldName = 'captchaToken'
    ): array {
        if ($this->isCaptchaEnabled($action)) {
            $rules[$fieldName] = $this->captchaRule($action, $threshold);
        }

        return $rules;
    }

    /**
     * Validate with captcha included
     *
     * @param array $rules
     * @param array $messages
     * @param array $attributes
     * @param string $action
     * @param float|null $threshold
     * @return array
     */
    public function validateWithCaptcha(
        array $rules,
        array $messages = [],
        array $attributes = [],
        string $action = 'default',
        ?float $threshold = null
    ): array {
        $rulesWithCaptcha = $this->withCaptchaRules($rules, $action, $threshold);
        
        return $this->validate($rulesWithCaptcha, $messages, $attributes);
    }

    /**
     * Handle successful form submission (refreshes token)
     */
    protected function onSuccessfulSubmission(): void
    {
        $this->refreshCaptchaToken();
    }

    /**
     * Magic method to handle dynamic captcha rule methods
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Handle dynamic captcha rule methods like captchaCustomActionRule()
        if (str_starts_with($method, 'captcha') && str_ends_with($method, 'Rule')) {
            $action = strtolower(str_replace(['captcha', 'Rule'], '', $method));
            $threshold = $parameters[0] ?? null;
            
            return $this->captchaRule($action, $threshold);
        }

        // Call parent if method exists
        if (method_exists(parent::class, '__call')) {
            return parent::__call($method, $parameters);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
}