<?php

namespace SnipifyDev\LaravelCaptcha\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use SnipifyDev\LaravelCaptcha\Services\RecaptchaService;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaValidationException;

/**
 * Modern Laravel ValidationRule for reCAPTCHA validation
 * 
 * This rule can be used in validation arrays and supports both v2 and v3.
 * Compatible with Laravel 11+ using the ValidationRule interface.
 */
class RecaptchaValidationRule implements ValidationRule
{
    /**
     * The action for v3 captcha validation
     */
    protected ?string $action;

    /**
     * Custom score threshold for v3 validation
     */
    protected ?float $threshold;

    /**
     * Force specific captcha version
     */
    protected ?string $version;

    /**
     * Create a new rule instance.
     *
     * @param string|null $action The action name for v3 validation
     * @param float|null $threshold Custom score threshold for v3
     * @param string|null $version Force specific version ('v2' or 'v3')
     */
    public function __construct(?string $action = null, ?float $threshold = null, ?string $version = null)
    {
        $this->action = $action;
        $this->threshold = $threshold;
        $this->version = $version;
    }

    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed $value
     * @param Closure $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            // Skip validation in certain environments
            if ($this->shouldSkipValidation()) {
                return;
            }

            // Check if token is provided
            if (empty($value) || !is_string($value)) {
                $fail($this->getErrorMessage('required'));
                return;
            }

            // Get the service instance and verify
            $service = app(RecaptchaService::class);

            // Use the unified service which auto-detects version
            $verified = $service->validate($value, $this->action, $this->threshold, $this->version);

            if (!$verified) {
                $fail($this->getErrorMessage('invalid'));
            }
        } catch (CaptchaValidationException $e) {
            $fail($e->getMessage());
        } catch (\Exception $e) {
            $fail($this->getErrorMessage('network_error'));
        }
    }

    /**
     * Create a new rule instance for login forms
     *
     * @param float|null $threshold
     * @return static
     */
    public static function login(?float $threshold = null): self
    {
        return new static('login', $threshold);
    }

    /**
     * Create a new rule instance for registration forms
     *
     * @param float|null $threshold
     * @return static
     */
    public static function register(?float $threshold = null): self
    {
        return new static('register', $threshold);
    }

    /**
     * Create a new rule instance for contact forms
     *
     * @param float|null $threshold
     * @return static
     */
    public static function contact(?float $threshold = null): self
    {
        return new static('contact', $threshold);
    }

    /**
     * Create a new rule instance for comments
     *
     * @param float|null $threshold
     * @return static
     */
    public static function comment(?float $threshold = null): self
    {
        return new static('comment', $threshold);
    }

    /**
     * Create a new rule instance for payments
     *
     * @param float|null $threshold
     * @return static
     */
    public static function payment(?float $threshold = null): self
    {
        return new static('payment', $threshold);
    }

    /**
     * Force v2 validation
     *
     * @return static
     */
    public static function v2(): self
    {
        return new static(null, null, 'v2');
    }

    /**
     * Force v3 validation
     *
     * @param string|null $action
     * @param float|null $threshold
     * @return static
     */
    public static function v3(?string $action = null, ?float $threshold = null): self
    {
        return new static($action, $threshold, 'v3');
    }

    /**
     * Set the action for this rule instance
     *
     * @param string $action
     * @return $this
     */
    public function action(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    /**
     * Set the threshold for this rule instance
     *
     * @param float $threshold
     * @return $this
     */
    public function threshold(float $threshold): self
    {
        $this->threshold = $threshold;
        return $this;
    }

    /**
     * Force the version for this rule instance
     *
     * @param string $version
     * @return $this
     */
    public function version(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Check if validation should be skipped
     *
     * @return bool
     */
    protected function shouldSkipValidation(): bool
    {
        // Skip in testing environment if configured
        if (config('laravel-captcha.testing.enabled') && app()->environment('testing')) {
            return true;
        }

        // Skip in development if fake mode is enabled
        if (config('laravel-captcha.testing.fake_in_development') && app()->environment('local')) {
            return true;
        }

        // Skip if captcha is globally disabled
        if (config('laravel-captcha.default') === false) {
            return true;
        }

        return false;
    }

    /**
     * Get appropriate error message
     *
     * @param string $type
     * @return string
     */
    protected function getErrorMessage(string $type): string
    {
        $messages = [
            'required' => config('laravel-captcha.error_messages.required', 'Please complete the captcha verification.'),
            'invalid' => config('laravel-captcha.error_messages.invalid', 'The captcha verification failed. Please try again.'),
            'score_too_low' => config('laravel-captcha.error_messages.score_too_low', 'reCAPTCHA score too low. Please try again.'),
            'network_error' => config('laravel-captcha.error_messages.network_error', 'Unable to verify captcha. Please try again.')
        ];

        return $messages[$type] ?? $messages['invalid'];
    }
}
