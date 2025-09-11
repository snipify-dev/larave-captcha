<?php

namespace SnipifyDev\LaravelCaptcha\Services;

/**
 * Null Captcha Service - Used when captcha is disabled
 */
class NullCaptchaService
{
    /**
     * Always return true for verification.
     *
     * @param string $token
     * @param string $action
     * @param float|null $threshold
     * @return bool
     */
    public function verify(string $token, string $action = 'default', ?float $threshold = null): bool
    {
        return true;
    }

    /**
     * Always return null for score.
     *
     * @param string $token
     * @param string $action
     * @return float|null
     */
    public function getScore(string $token, string $action = 'default'): ?float
    {
        return null;
    }

    /**
     * Return empty site key.
     *
     * @return string
     */
    public function getSiteKey(): string
    {
        return '';
    }

    /**
     * Return empty secret key.
     *
     * @return string
     */
    public function getSecretKey(): string
    {
        return '';
    }

    /**
     * Handle any method call by returning appropriate null values.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Return appropriate null values based on method name
        if (str_contains($method, 'verify') || str_contains($method, 'validate')) {
            return true;
        }

        if (str_contains($method, 'score') || str_contains($method, 'threshold')) {
            return null;
        }

        if (str_contains($method, 'key')) {
            return '';
        }

        if (str_contains($method, 'config')) {
            return [];
        }

        return null;
    }
}