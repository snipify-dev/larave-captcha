<?php

namespace SnipifyDev\LaravelCaptcha\Services;

use Illuminate\Support\Facades\Http;

class RecaptchaService
{
    /**
     * Verify a reCAPTCHA token.
     *
     * @param string $token
     * @param string|null $action
     * @param float|null $threshold
     * @param string|null $version
     * @return array
     * @throws \Exception
     */
    public function verify(string $token, ?string $action = null, ?float $threshold = null, ?string $version = null): array
    {
        // Skip validation in testing/development if configured
        if ($this->shouldSkipValidation()) {
            return [
                'success' => true,
                'score' => 0.9,
                'action' => $action ?? 'test',
                'version' => $version ?? config('laravel-captcha.default', 'v3'),
                'skipped' => true,
            ];
        }

        // Auto-detect version if not specified
        $detectedVersion = $version ?? $this->determineVersion($token);
        
        // Make API request
        $response = Http::timeout(config('laravel-captcha.timeout', 30))
            ->asForm()
            ->post(config('laravel-captcha.api_url'), [
                'secret' => $this->getSecretKey($detectedVersion),
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

        if (!$response->successful()) {
            throw new \Exception('reCAPTCHA API request failed: ' . $response->status());
        }

        $data = $response->json();
        $result = [
            'success' => $data['success'] ?? false,
            'version' => $detectedVersion,
            'error_codes' => $data['error-codes'] ?? [],
            'challenge_ts' => $data['challenge_ts'] ?? null,
            'hostname' => $data['hostname'] ?? null,
        ];

        // Add v3-specific data
        if ($detectedVersion === 'v3') {
            $result['score'] = $data['score'] ?? 0;
            $result['action'] = $data['action'] ?? '';
            
            // Validate score if threshold is provided
            if ($threshold !== null || $action !== null) {
                $scoreThreshold = $threshold ?? $this->getScoreThreshold($action);
                $result['score_valid'] = ($result['score'] ?? 0) >= $scoreThreshold;
                $result['threshold'] = $scoreThreshold;
            }
        }


        return $result;
    }

    /**
     * Quick validation method (returns boolean).
     *
     * @param string $token
     * @param string|null $action
     * @param float|null $threshold
     * @return bool
     */
    public function validate(string $token, ?string $action = null, ?float $threshold = null, ?string $version = null): bool
    {
        try {
            $result = $this->verify($token, $action, $threshold, $version);
            
            if (!$result['success']) {
                return false;
            }

            // For v3, check score if available
            if (isset($result['score_valid'])) {
                return $result['score_valid'];
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get site key for JavaScript rendering.
     *
     * @param string|null $version
     * @return string|null
     */
    public function getSiteKey(?string $version = null): ?string
    {
        $actualVersion = $version ?? config('laravel-captcha.default', 'v3');
        
        return $actualVersion === 'v3'
            ? config('laravel-captcha.site_key_v3')
            : config('laravel-captcha.site_key_v2');
    }

    /**
     * Get JavaScript configuration for frontend.
     *
     * @param string|null $version
     * @return array
     */
    public function getJavaScriptConfig(?string $version = null): array
    {
        $actualVersion = $version ?? config('laravel-captcha.default', 'v3');
        
        $config = [
            'version' => $actualVersion,
            'site_key' => $this->getSiteKey($actualVersion),
            'site_key_v2' => $this->getSiteKey('v2'),
            'site_key_v3' => $this->getSiteKey('v3'),
            'enabled' => !$this->shouldSkipValidation(),
        ];

        if ($actualVersion === 'v3') {
            $config['badge'] = config('laravel-captcha.badge', []);
        } else {
            $config['widget'] = config('laravel-captcha.widget', []);
        }

        return $config;
    }

    /**
     * Check if reCAPTCHA is enabled for the current environment.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return config('laravel-captcha.default') !== false && !$this->shouldSkipValidation();
    }

    /**
     * Get available actions with their thresholds.
     *
     * @return array
     */
    public function getActionThresholds(): array
    {
        return config('laravel-captcha.action_thresholds', []);
    }

    /**
     * Determine reCAPTCHA version based on token characteristics.
     *
     * @param string $token
     * @return string
     */
    protected function determineVersion(string $token): string
    {
        // v3 tokens are typically longer and don't contain underscores
        if (strlen($token) > 500 && !str_contains($token, '_')) {
            return 'v3';
        }

        // v2 tokens are shorter
        if (strlen($token) < 500) {
            return 'v2';
        }

        // Fallback to configuration
        return config('laravel-captcha.default', 'v3');
    }

    /**
     * Get the secret key for the specified version.
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
     * Get score threshold for an action.
     *
     * @param string|null $action
     * @return float
     */
    protected function getScoreThreshold(?string $action = null): float
    {
        if ($action) {
            $actionThreshold = config("laravel-captcha.action_thresholds.{$action}");
            if ($actionThreshold !== null) {
                return (float) $actionThreshold;
            }
        }

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
        if (config('laravel-captcha.skip_testing') && app()->environment('testing')) {
            return true;
        }

        // Skip in development if fake mode is enabled
        if (config('laravel-captcha.fake_in_development') && app()->environment('local')) {
            return true;
        }

        return false;
    }

    /**
     * Create a validation rule instance.
     *
     * @param string|null $action
     * @param float|null $threshold
     * @param string|null $version
     * @return \SnipifyDev\LaravelCaptcha\Rules\Recaptcha
     */
    public function rule(?string $action = null, ?float $threshold = null, ?string $version = null): \SnipifyDev\LaravelCaptcha\Rules\Recaptcha
    {
        return new \SnipifyDev\LaravelCaptcha\Rules\Recaptcha($action, $threshold, $version);
    }

    /**
     * Generate test data for development/testing.
     *
     * @param string $version
     * @return array
     */
    public function generateTestData(string $version = 'v3'): array
    {
        if ($version === 'v3') {
            return [
                'token' => 'test-token-' . str_repeat('x', 500),
                'success' => true,
                'score' => 0.9,
                'action' => 'test',
            ];
        }

        return [
            'token' => 'test-token-' . str_repeat('x', 100),
            'success' => true,
        ];
    }
}