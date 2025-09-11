<?php

namespace SnipifyDev\LaravelCaptcha\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Log\LogManager;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaConfigurationException;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaValidationException;

/**
 * Google reCAPTCHA v2 Service
 */
class RecaptchaV2Service
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
     * Create a new reCAPTCHA v2 service instance.
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
     * Verify a reCAPTCHA v2 response.
     *
     * @param string $response
     * @return bool
     * @throws CaptchaValidationException
     */
    public function verify(string $response): bool
    {
        try {
            // Skip if in testing/development mode
            if ($this->shouldSkipValidation()) {
                return true;
            }

            // Validate input
            $this->validateInput($response);

            // Check cache first if enabled
            if ($this->isCacheEnabled()) {
                $cached = $this->getCachedResult($response);
                if ($cached !== null) {
                    return $cached;
                }
            }

            // Get response from Google
            $apiResponse = $this->verifyResponse($response);

            // Validate response
            $this->validateApiResponse($apiResponse);

            // Cache successful result
            if ($this->isCacheEnabled()) {
                $this->cacheResult($response, true);
            }

            return true;

        } catch (CaptchaValidationException $e) {
            $this->logError('reCAPTCHA v2 validation failed', $e);
            
            // Cache failed result to prevent replay attacks
            if ($this->isCacheEnabled() && $this->config['cache']['cache_failures'] ?? true) {
                $this->cacheResult($response, false, 60); // Cache failures for shorter time
            }
            
            throw $e;
        } catch (\Exception $e) {
            $this->logError('reCAPTCHA v2 verification error', $e);
            throw CaptchaValidationException::networkError($e->getMessage(), $e);
        }
    }

    /**
     * Verify response and return full API response.
     *
     * @param string $response
     * @return array
     * @throws CaptchaValidationException
     */
    public function verifyResponse(string $response): array
    {
        $secretKey = $this->getSecretKey();
        $apiUrl = $this->config['services']['recaptcha']['verify_url'] 
            ?? $this->config['services']['recaptcha']['api_url'];
        $timeout = $this->config['services']['recaptcha']['timeout'] ?? 30;

        try {
            $httpResponse = $this->client->post($apiUrl, [
                'form_params' => [
                    'secret' => $secretKey,
                    'response' => $response,
                    'remoteip' => $this->getClientIp(),
                ],
                'timeout' => $timeout,
                'verify' => $this->config['security']['verify_ssl'] ?? true,
            ]);

            $data = json_decode($httpResponse->getBody()->getContents(), true);

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
     * Get site key.
     *
     * @return string
     * @throws CaptchaConfigurationException
     */
    public function getSiteKey(): string
    {
        $siteKey = $this->config['services']['recaptcha']['v2_site_key'] ?? null;
        
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
        $secretKey = $this->config['services']['recaptcha']['v2_secret_key'] ?? null;
        
        if (!$secretKey) {
            throw CaptchaConfigurationException::missingSecretKey();
        }
        
        return $secretKey;
    }

    /**
     * Get widget configuration.
     *
     * @return array
     */
    public function getWidgetConfig(): array
    {
        return [
            'sitekey' => $this->getSiteKey(),
            'theme' => $this->config['v2']['theme'] ?? 'light',
            'size' => $this->config['v2']['size'] ?? 'normal',
            'type' => $this->config['v2']['type'] ?? 'image',
            'tabindex' => $this->config['v2']['tabindex'] ?? 0,
            'callback' => $this->config['v2']['callback'] ?? null,
            'expired-callback' => $this->config['v2']['expired_callback'] ?? null,
            'error-callback' => $this->config['v2']['error_callback'] ?? null,
        ];
    }

    /**
     * Check if invisible mode is enabled.
     *
     * @return bool
     */
    public function isInvisible(): bool
    {
        return $this->config['v2']['invisible'] ?? false;
    }

    /**
     * Validate configuration.
     *
     * @throws CaptchaConfigurationException
     */
    protected function validateConfiguration(): void
    {
        // Only validate if captcha is enabled
        if (!($this->config['default'] === 'v2')) {
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
     * Validate input response.
     *
     * @param string $response
     * @throws CaptchaValidationException
     */
    protected function validateInput(string $response): void
    {
        if (empty($response)) {
            throw new CaptchaValidationException(
                $this->config['errors']['messages']['required'] ?? 'The captcha field is required.'
            );
        }
    }

    /**
     * Validate API response.
     *
     * @param array $response
     * @throws CaptchaValidationException
     */
    protected function validateApiResponse(array $response): void
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

        // Check challenge timestamp if available
        if (isset($response['challenge_ts'])) {
            $this->validateTimestamp($response['challenge_ts']);
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
     * Validate challenge timestamp.
     *
     * @param string $timestamp
     * @throws CaptchaValidationException
     */
    protected function validateTimestamp(string $timestamp): void
    {
        $challengeTime = strtotime($timestamp);
        $currentTime = time();
        $maxAge = 300; // 5 minutes

        if (($currentTime - $challengeTime) > $maxAge) {
            throw new CaptchaValidationException(
                $this->config['errors']['messages']['expired'] ?? 'The captcha has expired. Please try again.'
            );
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

        $testSiteKey = $this->config['development']['test_keys']['v2']['site_key'] ?? null;
        $currentSiteKey = $this->config['services']['recaptcha']['v2_site_key'] ?? null;

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
     * @param string $response
     * @return bool|null
     */
    protected function getCachedResult(string $response): ?bool
    {
        try {
            $key = $this->getCacheKey($response);
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
     * @param string $response
     * @param bool $result
     * @param int|null $ttl
     */
    protected function cacheResult(string $response, bool $result, ?int $ttl = null): void
    {
        try {
            $key = $this->getCacheKey($response);
            $ttl = $ttl ?? ($this->config['cache']['ttl'] ?? 300);
            
            Cache::driver($this->config['cache']['driver'] ?? null)->put($key, $result, $ttl);
        } catch (\Exception $e) {
            $this->logError('Cache write error', $e);
        }
    }

    /**
     * Get cache key for response.
     *
     * @param string $response
     * @return string
     */
    protected function getCacheKey(string $response): string
    {
        $prefix = $this->config['cache']['prefix'] ?? 'captcha';
        return "{$prefix}:v2:" . hash('sha256', $response);
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

        $this->logger->log($level, $message, $context);
    }
}