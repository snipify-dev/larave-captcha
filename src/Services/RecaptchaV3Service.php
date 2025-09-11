<?php

namespace SnipifyDev\LaravelCaptcha\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Log\LogManager;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaConfigurationException;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaValidationException;

/**
 * Google reCAPTCHA v3 Service
 */
class RecaptchaV3Service
{
    /**
     * The configuration array.
     *
     * @var array
     */
    protected array $config;

    /**
     * The logger instance.
     *
     * @var LogManager
     */
    protected LogManager $logger;

    /**
     * The HTTP client.
     *
     * @var Client
     */
    protected Client $client;

    /**
     * Create a new reCAPTCHA v3 service instance.
     *
     * @param array $config
     * @param LogManager $logger
     * @param Client|null $client
     */
    public function __construct(array $config, LogManager $logger, ?Client $client = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->client = $client ?: new Client();
        
        $this->validateConfiguration();
    }

    /**
     * Verify a reCAPTCHA v3 token.
     *
     * @param string $token
     * @param string $action
     * @param float|null $threshold
     * @return bool
     * @throws CaptchaValidationException
     */
    public function verify(string $token, string $action = 'default', ?float $threshold = null): bool
    {
        try {
            // Skip if in testing/development mode
            if ($this->shouldSkipValidation()) {
                return true;
            }

            // Validate inputs
            $this->validateInputs($token, $action, $threshold);

            // Check cache first if enabled
            if ($this->isCacheEnabled()) {
                $cached = $this->getCachedResult($token);
                if ($cached !== null) {
                    return $cached;
                }
            }

            // Get response from Google
            $response = $this->verifyToken($token, $action);

            // Validate response
            $this->validateResponse($response, $action, $threshold);

            // Cache successful result
            if ($this->isCacheEnabled()) {
                $this->cacheResult($token, true);
            }

            return true;

        } catch (CaptchaValidationException $e) {
            $this->logError('reCAPTCHA v3 validation failed', $e);
            
            // Cache failed result to prevent replay attacks
            if ($this->isCacheEnabled() && $this->config['cache']['cache_failures'] ?? true) {
                $this->cacheResult($token, false, 60); // Cache failures for shorter time
            }
            
            throw $e;
        } catch (\Exception $e) {
            $this->logError('reCAPTCHA v3 verification error', $e);
            throw CaptchaValidationException::networkError($e->getMessage(), $e);
        }
    }

    /**
     * Verify token and return full response.
     *
     * @param string $token
     * @param string $action
     * @return array
     * @throws CaptchaValidationException
     */
    public function verifyToken(string $token, string $action = 'default'): array
    {
        $secretKey = $this->getSecretKey();
        $apiUrl = $this->config['services']['recaptcha']['api_url'];
        $timeout = $this->config['services']['recaptcha']['timeout'] ?? 30;

        try {
            $response = $this->client->post($apiUrl, [
                'form_params' => [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $this->getClientIp(),
                ],
                'timeout' => $timeout,
                'verify' => $this->config['security']['verify_ssl'] ?? true,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!is_array($data)) {
                throw CaptchaValidationException::networkError('Invalid response format from reCAPTCHA API');
            }

            return $data;

        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ($statusCode >= 400 && $statusCode < 500) {
                    throw CaptchaValidationException::invalidConfiguration('api_request', $statusCode);
                }
            }
            
            throw CaptchaValidationException::timeout($timeout);
        }
    }

    /**
     * Get the score for a token.
     *
     * @param string $token
     * @param string $action
     * @return float|null
     */
    public function getScore(string $token, string $action = 'default'): ?float
    {
        try {
            $response = $this->verifyToken($token, $action);
            return $response['score'] ?? null;
        } catch (\Exception $e) {
            $this->logError('Failed to get reCAPTCHA score', $e);
            return null;
        }
    }

    /**
     * Get threshold for an action.
     *
     * @param string $action
     * @param float|null $override
     * @return float
     */
    public function getThreshold(string $action = 'default', ?float $override = null): float
    {
        if ($override !== null) {
            return $this->validateThreshold($override);
        }

        return $this->config['v3']['thresholds'][$action] 
            ?? $this->config['v3']['default_threshold'] 
            ?? 0.5;
    }

    /**
     * Get site key.
     *
     * @return string
     * @throws CaptchaConfigurationException
     */
    public function getSiteKey(): string
    {
        $siteKey = $this->config['services']['recaptcha']['site_key'] ?? null;
        
        if (!$siteKey) {
            throw CaptchaConfigurationException::missingSiteKey();
        }
        
        return $siteKey;
    }

    /**
     * Get secret key.
     *
     * @return string
     * @throws CaptchaConfigurationException
     */
    public function getSecretKey(): string
    {
        $secretKey = $this->config['services']['recaptcha']['secret_key'] ?? null;
        
        if (!$secretKey) {
            throw CaptchaConfigurationException::missingSecretKey();
        }
        
        return $secretKey;
    }

    /**
     * Validate configuration.
     *
     * @throws CaptchaConfigurationException
     */
    protected function validateConfiguration(): void
    {
        // Only validate if captcha is enabled
        if (!($this->config['default'] === 'v3')) {
            return;
        }

        // Skip validation in test mode or if using test keys
        if ($this->shouldSkipValidation() || $this->usingTestKeys()) {
            return;
        }

        $this->getSiteKey(); // Will throw if missing
        $this->getSecretKey(); // Will throw if missing
    }

    /**
     * Validate inputs.
     *
     * @param string $token
     * @param string $action
     * @param float|null $threshold
     * @throws CaptchaValidationException
     */
    protected function validateInputs(string $token, string $action, ?float $threshold): void
    {
        if (empty($token)) {
            throw new CaptchaValidationException(
                $this->config['errors']['messages']['required'] ?? 'The captcha field is required.'
            );
        }

        if ($threshold !== null) {
            $this->validateThreshold($threshold);
        }
    }

    /**
     * Validate threshold value.
     *
     * @param float $threshold
     * @return float
     * @throws CaptchaConfigurationException
     */
    protected function validateThreshold(float $threshold): float
    {
        if ($threshold < 0.0 || $threshold > 1.0) {
            throw CaptchaConfigurationException::invalidThreshold($threshold);
        }
        
        return $threshold;
    }

    /**
     * Validate API response.
     *
     * @param array $response
     * @param string $action
     * @param float|null $threshold
     * @throws CaptchaValidationException
     */
    protected function validateResponse(array $response, string $action, ?float $threshold): void
    {
        // Check if request was successful
        if (!($response['success'] ?? false)) {
            $errorCodes = $response['error-codes'] ?? ['unknown-error'];
            throw CaptchaValidationException::fromErrorCodes($errorCodes, $response);
        }

        // Validate hostname if configured
        if ($this->config['security']['verify_hostname'] ?? true) {
            $this->validateHostname($response);
        }

        // Validate action
        $responseAction = $response['action'] ?? null;
        if ($responseAction && $responseAction !== $action) {
            throw CaptchaValidationException::actionMismatch($action, $responseAction, $response);
        }

        // Validate score
        $score = $response['score'] ?? null;
        if ($score !== null) {
            $requiredThreshold = $this->getThreshold($action, $threshold);
            
            if ($score < $requiredThreshold) {
                throw CaptchaValidationException::scoreThresholdNotMet(
                    $score,
                    $requiredThreshold,
                    $action,
                    $response
                );
            }
        }
    }

    /**
     * Validate hostname in response.
     *
     * @param array $response
     * @throws CaptchaValidationException
     */
    protected function validateHostname(array $response): void
    {
        $responseHostname = $response['hostname'] ?? null;
        
        if (!$responseHostname) {
            return; // No hostname in response
        }

        $expectedHostname = $this->config['security']['expected_hostname'] 
            ?? parse_url(config('app.url'), PHP_URL_HOST);

        // Allow localhost in development
        if ($this->config['security']['allow_localhost'] ?? true) {
            if (in_array($responseHostname, ['localhost', '127.0.0.1', '::1'])) {
                return;
            }
        }

        if ($responseHostname !== $expectedHostname) {
            throw CaptchaValidationException::hostnameMismatch($expectedHostname, $responseHostname, $response);
        }
    }

    /**
     * Check if validation should be skipped.
     *
     * @return bool
     */
    protected function shouldSkipValidation(): bool
    {
        return (($this->config['skip_testing'] ?? true) && app()->environment('testing'))
            || (($this->config['fake_in_development'] ?? false) && app()->environment('local'));
    }

    /**
     * Check if using test keys.
     *
     * @return bool
     */
    protected function usingTestKeys(): bool
    {
        if (!($this->config['development']['use_test_keys'] ?? false)) {
            return false;
        }

        $testSiteKey = $this->config['development']['test_keys']['v3']['site_key'] ?? null;
        $currentSiteKey = $this->config['services']['recaptcha']['site_key'] ?? null;

        return $testSiteKey && $currentSiteKey === $testSiteKey;
    }

    /**
     * Check if caching is enabled.
     *
     * @return bool
     */
    protected function isCacheEnabled(): bool
    {
        return $this->config['cache']['enabled'] ?? false;
    }

    /**
     * Get cached result.
     *
     * @param string $token
     * @return bool|null
     */
    protected function getCachedResult(string $token): ?bool
    {
        try {
            $key = $this->getCacheKey($token);
            $cached = Cache::driver($this->config['cache']['driver'] ?? null)->get($key);
            
            return is_bool($cached) ? $cached : null;
        } catch (\Exception $e) {
            $this->logError('Cache read error', $e);
            return null;
        }
    }

    /**
     * Cache result.
     *
     * @param string $token
     * @param bool $result
     * @param int|null $ttl
     */
    protected function cacheResult(string $token, bool $result, ?int $ttl = null): void
    {
        try {
            $key = $this->getCacheKey($token);
            $ttl = $ttl ?? ($this->config['cache']['ttl'] ?? 300);
            
            Cache::driver($this->config['cache']['driver'] ?? null)->put($key, $result, $ttl);
        } catch (\Exception $e) {
            $this->logError('Cache write error', $e);
        }
    }

    /**
     * Get cache key for token.
     *
     * @param string $token
     * @return string
     */
    protected function getCacheKey(string $token): string
    {
        $prefix = $this->config['cache']['prefix'] ?? 'captcha';
        return "{$prefix}:v3:" . hash('sha256', $token);
    }

    /**
     * Get client IP address.
     *
     * @return string|null
     */
    protected function getClientIp(): ?string
    {
        return request()->ip() ?? null;
    }

    /**
     * Log error.
     *
     * @param string $message
     * @param \Exception $exception
     */
    protected function logError(string $message, \Exception $exception): void
    {
        if (!($this->config['errors']['log_errors'] ?? true)) {
            return;
        }

        $level = $this->config['errors']['log_level'] ?? 'warning';
        $context = ['exception' => $exception];

        // Add score to context if available and configured
        if (($this->config['errors']['log_score'] ?? false) && $exception instanceof CaptchaValidationException) {
            $score = $exception->getScore();
            if ($score !== null) {
                $context['score'] = $score;
            }
        }

        $this->logger->log($level, $message, $context);
    }
}