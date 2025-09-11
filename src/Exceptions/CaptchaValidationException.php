<?php

namespace SnipifyDev\LaravelCaptcha\Exceptions;

/**
 * Exception thrown when captcha validation fails
 */
class CaptchaValidationException extends CaptchaException
{
    /**
     * The validation error codes from Google
     */
    public const ERROR_CODES = [
        'missing-input-secret' => 'The secret parameter is missing.',
        'invalid-input-secret' => 'The secret parameter is invalid or malformed.',
        'missing-input-response' => 'The response parameter is missing.',
        'invalid-input-response' => 'The response parameter is invalid or malformed.',
        'bad-request' => 'The request is invalid or malformed.',
        'timeout-or-duplicate' => 'The response is no longer valid: either is too old or has been used previously.',
        'hostname-mismatch' => 'The hostname in the response does not match your domain.',
        'score-threshold-not-met' => 'The score is below the required threshold.',
        'action-mismatch' => 'The action in the response does not match the expected action.',
        'challenge-timeout' => 'The challenge timeout has been exceeded.',
        'invalid-keys' => 'Invalid site key or secret key.',
    ];

    /**
     * Create exception for specific error codes
     *
     * @param array $errorCodes
     * @param array $responseData
     * @return static
     */
    public static function fromErrorCodes(array $errorCodes, array $responseData = []): self
    {
        $messages = [];
        
        foreach ($errorCodes as $code) {
            $messages[] = self::ERROR_CODES[$code] ?? "Unknown error: {$code}";
        }
        
        $message = implode(' ', $messages);
        
        return static::fromResponse($responseData, $message);
    }

    /**
     * Create exception for low score
     *
     * @param float $score
     * @param float $threshold
     * @param string $action
     * @param array $responseData
     * @return static
     */
    public static function scoreThresholdNotMet(
        float $score,
        float $threshold,
        string $action = 'default',
        array $responseData = []
    ): self {
        $message = "Captcha score {$score} is below threshold {$threshold} for action '{$action}'";
        
        $exception = static::fromResponse($responseData, $message);
        $exception->setScore($score);
        $exception->setAction($action);
        
        return $exception;
    }

    /**
     * Create exception for action mismatch
     *
     * @param string $expected
     * @param string $actual
     * @param array $responseData
     * @return static
     */
    public static function actionMismatch(string $expected, string $actual, array $responseData = []): self
    {
        $message = "Expected action '{$expected}' but got '{$actual}'";
        
        $exception = static::fromResponse($responseData, $message);
        $exception->setAction($actual);
        
        return $exception;
    }

    /**
     * Create exception for hostname mismatch
     *
     * @param string $expected
     * @param string $actual
     * @param array $responseData
     * @return static
     */
    public static function hostnameMismatch(string $expected, string $actual, array $responseData = []): self
    {
        $message = "Expected hostname '{$expected}' but got '{$actual}'";
        
        return static::fromResponse($responseData, $message);
    }

    /**
     * Create exception for network errors
     *
     * @param string $message
     * @param Exception|null $previous
     * @return static
     */
    public static function networkError(string $message, ?\Exception $previous = null): self
    {
        return new static("Network error: {$message}", 0, $previous);
    }

    /**
     * Create exception for timeout
     *
     * @param int $timeout
     * @return static
     */
    public static function timeout(int $timeout): self
    {
        return new static("Captcha verification timed out after {$timeout} seconds");
    }

    /**
     * Create exception for missing configuration
     *
     * @param string $key
     * @return static
     */
    public static function missingConfiguration(string $key): self
    {
        return new static("Missing captcha configuration: {$key}");
    }

    /**
     * Create exception for invalid configuration
     *
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public static function invalidConfiguration(string $key, $value): self
    {
        $valueStr = is_scalar($value) ? (string) $value : gettype($value);
        return new static("Invalid captcha configuration for {$key}: {$valueStr}");
    }
}