<?php

namespace SnipifyDev\LaravelCaptcha\Exceptions;

/**
 * Exception thrown when captcha configuration is invalid
 */
class CaptchaConfigurationException extends CaptchaException
{
    /**
     * Create exception for missing site key
     *
     * @return static
     */
    public static function missingSiteKey(): self
    {
        return new static(
            'reCAPTCHA site key is missing. Please set RECAPTCHAV3_SITEKEY or CAPTCHA_SITE_KEY in your .env file.'
        );
    }

    /**
     * Create exception for missing secret key
     *
     * @return static
     */
    public static function missingSecretKey(): self
    {
        return new static(
            'reCAPTCHA secret key is missing. Please set RECAPTCHAV3_SECRET or CAPTCHA_SECRET_KEY in your .env file.'
        );
    }

    /**
     * Create exception for invalid version
     *
     * @param mixed $version
     * @return static
     */
    public static function invalidVersion($version): self
    {
        $versionStr = is_scalar($version) ? (string) $version : gettype($version);
        return new static(
            "Invalid captcha version '{$versionStr}'. Supported versions: v2, v3, false"
        );
    }

    /**
     * Create exception for invalid threshold
     *
     * @param mixed $threshold
     * @return static
     */
    public static function invalidThreshold($threshold): self
    {
        $thresholdStr = is_scalar($threshold) ? (string) $threshold : gettype($threshold);
        return new static(
            "Invalid threshold '{$thresholdStr}'. Threshold must be a float between 0.0 and 1.0"
        );
    }

    /**
     * Create exception for missing action configuration
     *
     * @param string $action
     * @return static
     */
    public static function missingActionConfiguration(string $action): self
    {
        return new static(
            "No threshold configured for action '{$action}'. Please add it to captcha.v3.thresholds config."
        );
    }

    /**
     * Create exception for unsupported service
     *
     * @param string $service
     * @return static
     */
    public static function unsupportedService(string $service): self
    {
        return new static(
            "Unsupported captcha service '{$service}'. Currently only 'recaptcha' is supported."
        );
    }
}