<?php

namespace SnipifyDev\LaravelCaptcha\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use SnipifyDev\LaravelCaptcha\Services\RecaptchaV3Service;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaValidationException;

/**
 * reCAPTCHA v3 Validation Rule
 * 
 * Supports both Laravel 8.x Rule interface and Laravel 10+ ValidationRule interface
 */
class RecaptchaV3Rule implements Rule, ValidationRule
{
    /**
     * The action for this captcha.
     *
     * @var string
     */
    protected string $action;

    /**
     * The score threshold.
     *
     * @var float|null
     */
    protected ?float $threshold;

    /**
     * The last error message.
     *
     * @var string|null
     */
    protected ?string $errorMessage = null;

    /**
     * Create a new rule instance.
     *
     * @param string $action
     * @param float|null $threshold
     */
    public function __construct(string $action = 'default', ?float $threshold = null)
    {
        $this->action = $action;
        $this->threshold = $threshold;
    }

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
            $service = app(RecaptchaV3Service::class);
            
            // Perform verification
            return $service->verify($value, $this->action, $this->threshold);

        } catch (CaptchaValidationException $e) {
            $this->errorMessage = $e->getMessage();
            return false;
        } catch (\Exception $e) {
            $this->errorMessage = $this->getGenericErrorMessage();
            
            // Log the error if configured
            if (config('captcha.errors.log_errors', true)) {
                logger()->log(
                    config('captcha.errors.log_level', 'warning'),
                    'reCAPTCHA v3 rule error: ' . $e->getMessage(),
                    ['exception' => $e, 'action' => $this->action]
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
     * Set the action for this rule.
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
     * Set the threshold for this rule.
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
     * Get the action.
     *
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Get the threshold.
     *
     * @return float|null
     */
    public function getThreshold(): ?float
    {
        return $this->threshold;
    }

    /**
     * Create a new rule instance with action.
     *
     * @param string $action
     * @param float|null $threshold
     * @return static
     */
    public static function for(string $action, ?float $threshold = null): self
    {
        return new static($action, $threshold);
    }

    /**
     * Create a login action rule.
     *
     * @param float|null $threshold
     * @return static
     */
    public static function login(?float $threshold = null): self
    {
        return new static('login', $threshold);
    }

    /**
     * Create a register action rule.
     *
     * @param float|null $threshold
     * @return static
     */
    public static function register(?float $threshold = null): self
    {
        return new static('register', $threshold);
    }

    /**
     * Create a contact action rule.
     *
     * @param float|null $threshold
     * @return static
     */
    public static function contact(?float $threshold = null): self
    {
        return new static('contact', $threshold);
    }

    /**
     * Create a comment action rule.
     *
     * @param float|null $threshold
     * @return static
     */
    public static function comment(?float $threshold = null): self
    {
        return new static('comment', $threshold);
    }

    /**
     * Create a review action rule.
     *
     * @param float|null $threshold
     * @return static
     */
    public static function review(?float $threshold = null): self
    {
        return new static('review', $threshold);
    }

    /**
     * Create a payment action rule.
     *
     * @param float|null $threshold
     * @return static
     */
    public static function payment(?float $threshold = null): self
    {
        return new static('payment', $threshold);
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
        if ($version !== 'v3') {
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

        // Check if this action is enabled
        $actionEnabled = config("captcha.forms.{$this->action}");
        if ($actionEnabled === false) {
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
        return 'recaptcha_v3:' . $this->action . ($this->threshold ? ":{$this->threshold}" : '');
    }
}