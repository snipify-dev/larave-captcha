<?php

namespace SnipifyDev\LaravelCaptcha\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use SnipifyDev\LaravelCaptcha\Services\RecaptchaV2Service;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaValidationException;

/**
 * reCAPTCHA v2 Validation Rule
 * 
 * Supports both Laravel 8.x Rule interface and Laravel 10+ ValidationRule interface
 */
class RecaptchaV2Rule implements Rule, ValidationRule
{
    /**
     * The last error message.
     *
     * @var string|null
     */
    protected ?string $errorMessage = null;

    /**
     * Determine if the validation rule passes (Laravel 8.x compatible).
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        try {
            // Skip validation if captcha is disabled or in testing mode
            if (!$this->isCaptchaEnabled()) {
                return true;
            }

            // Get the service instance
            $service = app(RecaptchaV2Service::class);
            
            // Perform verification
            return $service->verify($value);

        } catch (CaptchaValidationException $e) {
            $this->errorMessage = $e->getMessage();
            return false;
        } catch (\Exception $e) {
            $this->errorMessage = $this->getGenericErrorMessage();
            
            // Log the error if configured
            if (config('captcha.errors.log_errors', true)) {
                logger()->log(
                    config('captcha.errors.log_level', 'warning'),
                    'reCAPTCHA v2 rule error: ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }
            
            return false;
        }
    }

    /**
     * Get the validation error message (Laravel 8.x compatible).
     *
     * @return string
     */
    public function message(): string
    {
        return $this->errorMessage ?: $this->getGenericErrorMessage();
    }

    /**
     * Run the validation rule (Laravel 10+ compatible).
     *
     * @param string $attribute
     * @param mixed $value
     * @param \Closure $fail
     * @return void
     */
    public function validate(string $attribute, $value, \Closure $fail): void
    {
        if (!$this->passes($attribute, $value)) {
            $fail($this->message());
        }
    }

    /**
     * Create a new rule instance.
     *
     * @return static
     */
    public static function make(): self
    {
        return new static();
    }

    /**
     * Check if captcha is enabled and should be validated.
     *
     * @return bool
     */
    protected function isCaptchaEnabled(): bool
    {
        // Check if captcha is globally disabled
        $version = config('captcha.default');
        if ($version !== 'v2') {
            return false;
        }

        // Check if we should skip in testing
        if (config('captcha.skip_testing', true) && app()->environment('testing')) {
            return false;
        }

        // Check if we should fake in development
        if (config('captcha.fake_in_development', false) && app()->environment('local')) {
            return false;
        }

        return true;
    }

    /**
     * Get generic error message.
     *
     * @return string
     */
    protected function getGenericErrorMessage(): string
    {
        return config('captcha.errors.messages.invalid', 'The captcha verification failed. Please try again.');
    }

    /**
     * Get the rule as a string for backward compatibility.
     *
     * @return string
     */
    public function __toString(): string
    {
        return 'recaptcha_v2';
    }
}