<?php

namespace SnipifyDev\LaravelCaptcha\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class Recaptcha implements ValidationRule
{
    protected ?string $action;
    protected ?float $threshold;
    protected ?string $version;

    /**
     * Create a new Recaptcha rule instance.
     *
     * @param string|null $action The action name for v3 validation
     * @param float|null $threshold Custom score threshold for v3 (overrides config)
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

        // Skip validation in testing environment if configured
        if ($this->shouldSkipValidation()) {
            return;
        }

        // Check if captcha is disabled
        if (config('laravel-captcha.default') === false) {
            return;
        }

        // Validate token exists
        if (empty($value)) {
            $fail(config('laravel-captcha.error_messages.required', 'Please complete the reCAPTCHA verification.'));
            return;
        }

        try {
            $version = $this->determineVersion($value);
            
            $verified = $this->verifyToken($value, $version);

            if (!$verified) {
                $errorMessage = $version === 'v3' 
                    ? config('laravel-captcha.error_messages.score_too_low', 'reCAPTCHA score too low. Please try again.')
                    : config('laravel-captcha.error_messages.invalid', 'reCAPTCHA verification failed. Please try again.');
                
                $fail($errorMessage);
            }
        } catch (\Exception $e) {
            $fail(config('laravel-captcha.error_messages.network_error', 'Unable to verify reCAPTCHA. Please try again.'));
        }
    }

    /**
     * Verify the reCAPTCHA token.
     *
     * @param string $token
     * @param string $version
     * @return bool
     * @throws \Exception
     */
    protected function verifyToken(string $token, string $version): bool
    {
        $response = Http::timeout(config('laravel-captcha.timeout', 30))
            ->asForm()
            ->post(config('laravel-captcha.api_url'), [
                'secret' => $this->getSecretKey($version),
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

        if (!$response->successful()) {
            throw new \Exception('reCAPTCHA API request failed: ' . $response->status());
        }

        $data = $response->json();

        if (!($data['success'] ?? false)) {
            return false;
        }

        // For v3, check the score
        if ($version === 'v3') {
            return $this->validateScore($data['score'] ?? 0, $data['action'] ?? '');
        }

        return true;
    }

    /**
     * Validate the reCAPTCHA v3 score.
     *
     * @param float $score
     * @param string $responseAction
     * @return bool
     */
    protected function validateScore(float $score, string $responseAction): bool
    {
        $threshold = $this->getScoreThreshold();
        
        
        // Check if score meets threshold
        if ($score < $threshold) {
            return false;
        }

        // Optional: Verify action matches (if action was specified)
        if ($this->action && $responseAction !== $this->action) {
            // Note: We're not failing on action mismatch as it's often not critical
        }

        return true;
    }

    /**
     * Determine reCAPTCHA version based on token or configuration.
     *
     * @param string $token
     * @return string
     */
    protected function determineVersion(string $token): string
    {
        // If version is explicitly set, use it
        if ($this->version) {
            return $this->version;
        }

        // Auto-detect based on token characteristics
        // v3 tokens are typically longer and contain specific patterns
        if (strlen($token) > 500 && !str_contains($token, '_')) {
            return 'v3';
        }

        // v2 tokens are shorter and may contain underscores
        if (strlen($token) < 500) {
            return 'v2';
        }

        // Fallback to configuration
        return config('laravel-captcha.default', 'v3');
    }

    /**
     * Get the appropriate secret key based on version.
     *
     * @param string $version
     * @return string
     * @throws \Exception
     */
    protected function getSecretKey(string $version): string
    {
        $key = $version === 'v3' 
            ? config('laravel-captcha.secret_key_v3')
            : config('laravel-captcha.secret_key_v2');

        if (empty($key)) {
            throw new \Exception("reCAPTCHA {$version} secret key not configured");
        }

        return $key;
    }

    /**
     * Get the score threshold for v3 validation.
     *
     * @return float
     */
    protected function getScoreThreshold(): float
    {
        // Use explicit threshold if provided
        if ($this->threshold !== null) {
            return $this->threshold;
        }

        // Fallback to default threshold
        return (float) config('laravel-captcha.score_threshold', 0.5);
    }

    /**
     * Check if validation should be skipped.
     *
     * @return bool
     */
    protected function shouldSkipValidation(): bool
    {
        // Skip in testing environment
        if (config('laravel-captcha.testing.enabled') && app()->environment('testing')) {
            return true;
        }

        // Skip in development if fake mode is enabled
        if (config('laravel-captcha.testing.fake_in_development') && app()->environment('local')) {
            return true;
        }

        return false;
    }

    /**
     * Static factory methods for common use cases.
     */
    public static function login(?float $threshold = null): self
    {
        return new self('login', $threshold);
    }

    public static function register(?float $threshold = null): self
    {
        return new self('register', $threshold);
    }

    public static function contact(?float $threshold = null): self
    {
        return new self('contact', $threshold);
    }

    public static function comment(?float $threshold = null): self
    {
        return new self('comment', $threshold);
    }

    public static function payment(?float $threshold = null): self
    {
        return new self('payment', $threshold);
    }

    public static function v2(): self
    {
        return new self(null, null, 'v2');
    }

    public static function v3(?string $action = null, ?float $threshold = null): self
    {
        return new self($action, $threshold, 'v3');
    }
}