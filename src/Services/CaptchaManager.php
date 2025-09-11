<?php

namespace SnipifyDev\LaravelCaptcha\Services;

use Illuminate\Container\Container;
use Illuminate\Support\Manager;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaConfigurationException;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaValidationException;
use SnipifyDev\LaravelCaptcha\Rules\RecaptchaV2Rule;
use SnipifyDev\LaravelCaptcha\Rules\RecaptchaV3Rule;

/**
 * Captcha Manager - Factory for creating captcha service instances
 */
class CaptchaManager extends Manager
{
    /**
     * The application instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * The configuration array.
     *
     * @var array
     */
    protected array $config;

    /**
     * Create a new captcha manager instance.
     *
     * @param Container $container
     * @param array $config
     */
    public function __construct(Container $container, array $config)
    {
        parent::__construct($container);
        $this->container = $container;
        $this->config = $config;
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        $version = $this->config['default'] ?? 'v3';
        
        return match($version) {
            'v2' => 'recaptcha_v2',
            'v3' => 'recaptcha_v3',
            false => 'null',
            default => throw CaptchaConfigurationException::invalidVersion($version),
        };
    }

    /**
     * Create the reCAPTCHA v2 driver.
     *
     * @return RecaptchaV2Service
     */
    protected function createRecaptchaV2Driver(): RecaptchaV2Service
    {
        return $this->container->make(RecaptchaV2Service::class);
    }

    /**
     * Create the reCAPTCHA v3 driver.
     *
     * @return RecaptchaV3Service
     */
    protected function createRecaptchaV3Driver(): RecaptchaV3Service
    {
        return $this->container->make(RecaptchaV3Service::class);
    }

    /**
     * Create the null driver (disabled captcha).
     *
     * @return NullCaptchaService
     */
    protected function createNullDriver(): NullCaptchaService
    {
        return new NullCaptchaService();
    }

    /**
     * Verify a captcha token.
     *
     * @param string $token
     * @param string $action
     * @param float|null $threshold
     * @param string|null $version
     * @return bool
     * @throws CaptchaValidationException
     */
    public function verify(
        string $token,
        string $action = 'default',
        ?float $threshold = null,
        ?string $version = null
    ): bool {
        $driver = $version ? $this->driver($this->getDriverName($version)) : $this->driver();
        
        if ($driver instanceof RecaptchaV3Service) {
            return $driver->verify($token, $action, $threshold);
        }
        
        return $driver->verify($token);
    }

    /**
     * Get the score for a reCAPTCHA v3 token.
     *
     * @param string $token
     * @param string $action
     * @return float|null
     * @throws CaptchaValidationException
     */
    public function getScore(string $token, string $action = 'default'): ?float
    {
        $driver = $this->driver();
        
        if ($driver instanceof RecaptchaV3Service) {
            $response = $driver->verifyToken($token, $action);
            return $response['score'] ?? null;
        }
        
        return null;
    }

    /**
     * Check if captcha is enabled for a specific form/action.
     *
     * @param string $action
     * @return bool
     */
    public function isEnabled(string $action = 'default'): bool
    {
        // Check if captcha is globally disabled
        if (!$this->config['default']) {
            return false;
        }

        // Check if we should skip in testing
        if ($this->shouldSkipValidation()) {
            return false;
        }

        // Check form-specific configuration
        return $this->config['forms'][$action] ?? true;
    }

    /**
     * Get a validation rule instance.
     *
     * @param string $action
     * @param float|null $threshold
     * @param string|null $version
     * @return RecaptchaV2Rule|RecaptchaV3Rule
     */
    public function rule(
        string $action = 'default',
        ?float $threshold = null,
        ?string $version = null
    ): RecaptchaV2Rule|RecaptchaV3Rule {
        $actualVersion = $version ?? $this->config['default'];
        
        return match($actualVersion) {
            'v3' => new RecaptchaV3Rule($action, $threshold),
            'v2' => new RecaptchaV2Rule(),
            default => throw CaptchaConfigurationException::invalidVersion($actualVersion),
        };
    }

    /**
     * Get site key for the current or specified version.
     *
     * @param string|null $version
     * @return string|null
     */
    public function getSiteKey(?string $version = null): ?string
    {
        $actualVersion = $version ?? $this->config['default'];
        
        return match($actualVersion) {
            'v3' => $this->config['services']['recaptcha']['site_key'] ?? null,
            'v2' => $this->config['services']['recaptcha']['v2_site_key'] ?? null,
            default => null,
        };
    }

    /**
     * Get secret key for the current or specified version.
     *
     * @param string|null $version
     * @return string|null
     */
    public function getSecretKey(?string $version = null): ?string
    {
        $actualVersion = $version ?? $this->config['default'];
        
        return match($actualVersion) {
            'v3' => $this->config['services']['recaptcha']['secret_key'] ?? null,
            'v2' => $this->config['services']['recaptcha']['v2_secret_key'] ?? null,
            default => null,
        };
    }

    /**
     * Get threshold for an action.
     *
     * @param string $action
     * @return float
     */
    public function getThreshold(string $action = 'default'): float
    {
        return $this->config['v3']['thresholds'][$action] 
            ?? $this->config['v3']['default_threshold'] 
            ?? 0.5;
    }

    /**
     * Get configuration value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function config(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Get current version.
     *
     * @return string|false
     */
    public function getVersion(): string|false
    {
        return $this->config['default'];
    }

    /**
     * Check if we're in testing mode and should skip validation.
     *
     * @return bool
     */
    public function shouldSkipValidation(): bool
    {
        // Skip in testing environment
        if (($this->config['skip_testing'] ?? true) && app()->environment('testing')) {
            return true;
        }

        // Skip in development if fake mode is enabled
        if (($this->config['fake_in_development'] ?? false) && app()->environment('local')) {
            return true;
        }

        return false;
    }

    /**
     * Enable or disable captcha temporarily.
     *
     * @param bool $enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void
    {
        $this->config['default'] = $enabled ? ($this->config['default'] ?: 'v3') : false;
    }

    /**
     * Set the version temporarily.
     *
     * @param string $version
     * @return void
     * @throws CaptchaConfigurationException
     */
    public function setVersion(string $version): void
    {
        if (!in_array($version, ['v2', 'v3'])) {
            throw CaptchaConfigurationException::invalidVersion($version);
        }
        
        $this->config['default'] = $version;
    }

    /**
     * Generate JavaScript configuration.
     *
     * @param string|null $version
     * @return array
     */
    public function getJavaScriptConfig(?string $version = null): array
    {
        $actualVersion = $version ?? $this->config['default'];
        $siteKey = $this->getSiteKey($actualVersion);
        
        $config = [
            'version' => $actualVersion,
            'site_key' => $siteKey,
            'enabled' => $this->isEnabled(),
        ];

        if ($actualVersion === 'v3') {
            $config['badge'] = $this->config['v3']['badge'] ?? [];
            $config['refresh_interval'] = $this->config['livewire']['refresh_interval'] ?? 110;
        } elseif ($actualVersion === 'v2') {
            $config['theme'] = $this->config['v2']['theme'] ?? 'light';
            $config['size'] = $this->config['v2']['size'] ?? 'normal';
            $config['type'] = $this->config['v2']['type'] ?? 'image';
        }

        return $config;
    }

    /**
     * Get driver name for version.
     *
     * @param string $version
     * @return string
     */
    protected function getDriverName(string $version): string
    {
        return match($version) {
            'v2' => 'recaptcha_v2',
            'v3' => 'recaptcha_v3',
            default => throw CaptchaConfigurationException::invalidVersion($version),
        };
    }

    /**
     * Magic method to handle calls to specific service methods.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}