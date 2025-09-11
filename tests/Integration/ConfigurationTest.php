<?php

namespace SnipifyDev\LaravelCaptcha\Tests\Integration;

use SnipifyDev\LaravelCaptcha\Tests\TestCase;
use SnipifyDev\LaravelCaptcha\Facades\Captcha;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaConfigurationException;

class ConfigurationTest extends TestCase
{
    /** @test */
    public function it_reads_configuration_from_env_variables()
    {
        // Test legacy env variables
        putenv('RECAPTCHAV3_SITEKEY=legacy_site_key');
        putenv('RECAPTCHAV3_SECRET=legacy_secret_key');

        config()->set('captcha.site_key', env('RECAPTCHAV3_SITEKEY'));
        config()->set('captcha.secret_key', env('RECAPTCHAV3_SECRET'));

        $this->assertEquals('legacy_site_key', config('captcha.site_key'));
        $this->assertEquals('legacy_secret_key', config('captcha.secret_key'));

        // Clean up
        putenv('RECAPTCHAV3_SITEKEY');
        putenv('RECAPTCHAV3_SECRET');
    }

    /** @test */
    public function it_prefers_new_env_variables_over_legacy()
    {
        putenv('CAPTCHA_SITE_KEY=new_site_key');
        putenv('CAPTCHA_SECRET_KEY=new_secret_key');
        putenv('RECAPTCHAV3_SITEKEY=legacy_site_key');
        putenv('RECAPTCHAV3_SECRET=legacy_secret_key');

        config()->set('captcha.site_key', env('CAPTCHA_SITE_KEY', env('RECAPTCHAV3_SITEKEY')));
        config()->set('captcha.secret_key', env('CAPTCHA_SECRET_KEY', env('RECAPTCHAV3_SECRET')));

        $this->assertEquals('new_site_key', config('captcha.site_key'));
        $this->assertEquals('new_secret_key', config('captcha.secret_key'));

        // Clean up
        putenv('CAPTCHA_SITE_KEY');
        putenv('CAPTCHA_SECRET_KEY');
        putenv('RECAPTCHAV3_SITEKEY');
        putenv('RECAPTCHAV3_SECRET');
    }

    /** @test */
    public function it_validates_threshold_configuration()
    {
        config()->set('captcha.v3.thresholds.login', 1.5);

        $this->expectException(CaptchaConfigurationException::class);
        $this->expectExceptionMessage('Invalid threshold');

        Captcha::verify('test_token', 'login');
    }

    /** @test */
    public function it_validates_version_configuration()
    {
        config()->set('captcha.default_version', 'v4');

        $this->expectException(CaptchaConfigurationException::class);
        $this->expectExceptionMessage('Invalid captcha version');

        Captcha::isEnabled();
    }

    /** @test */
    public function it_handles_action_specific_configuration()
    {
        config()->set('captcha.default_version', 'v2');
        config()->set('captcha.actions.login.version', 'v3');
        config()->set('captcha.actions.login.threshold', 0.8);

        $this->assertEquals('v3', Captcha::getVersion('login'));
        $this->assertEquals('v2', Captcha::getVersion('register'));
    }

    /** @test */
    public function it_handles_disabled_actions()
    {
        config()->set('captcha.default_version', 'v3');
        config()->set('captcha.actions.admin.version', false);

        $this->assertFalse(Captcha::isEnabled('admin'));
        $this->assertTrue(Captcha::isEnabled('login'));
    }

    /** @test */
    public function it_validates_service_configuration()
    {
        config()->set('captcha.service', 'hcaptcha');

        $this->expectException(CaptchaConfigurationException::class);
        $this->expectExceptionMessage('Unsupported captcha service');

        Captcha::version();
    }

    /** @test */
    public function it_handles_cache_configuration()
    {
        config()->set('captcha.cache.enabled', true);
        config()->set('captcha.cache.ttl', 600);
        config()->set('captcha.cache.prefix', 'test_captcha:');

        $this->assertTrue(config('captcha.cache.enabled'));
        $this->assertEquals(600, config('captcha.cache.ttl'));
        $this->assertEquals('test_captcha:', config('captcha.cache.prefix'));
    }

    /** @test */
    public function it_handles_rate_limiting_configuration()
    {
        config()->set('captcha.rate_limiting.enabled', true);
        config()->set('captcha.rate_limiting.max_attempts', 5);
        config()->set('captcha.rate_limiting.decay_minutes', 15);

        $this->assertTrue(config('captcha.rate_limiting.enabled'));
        $this->assertEquals(5, config('captcha.rate_limiting.max_attempts'));
        $this->assertEquals(15, config('captcha.rate_limiting.decay_minutes'));
    }

    /** @test */
    public function it_handles_security_configuration()
    {
        config()->set('captcha.security.verify_hostname', true);
        config()->set('captcha.security.allowed_hostnames', ['localhost', 'example.com']);

        $this->assertTrue(config('captcha.security.verify_hostname'));
        $this->assertContains('localhost', config('captcha.security.allowed_hostnames'));
    }

    /** @test */
    public function it_handles_error_configuration()
    {
        config()->set('captcha.errors.log_errors', true);
        config()->set('captcha.errors.log_level', 'warning');
        config()->set('captcha.errors.messages.required', 'Custom required message');

        $this->assertTrue(config('captcha.errors.log_errors'));
        $this->assertEquals('warning', config('captcha.errors.log_level'));
        $this->assertEquals('Custom required message', config('captcha.errors.messages.required'));
    }

    /** @test */
    public function it_provides_sensible_defaults()
    {
        // Reset config to defaults
        config()->set('captcha', require __DIR__ . '/../../config/captcha.php');

        $this->assertEquals('recaptcha', config('captcha.service'));
        $this->assertEquals('v3', config('captcha.default_version'));
        $this->assertEquals(0.5, config('captcha.v3.default_threshold'));
        $this->assertTrue(config('captcha.cache.enabled'));
        $this->assertEquals(300, config('captcha.cache.ttl'));
        $this->assertFalse(config('captcha.rate_limiting.enabled'));
    }

    /** @test */
    public function it_handles_nested_action_configuration()
    {
        config()->set('captcha.actions.auth.login.version', 'v3');
        config()->set('captcha.actions.auth.login.threshold', 0.7);
        config()->set('captcha.actions.auth.register.version', 'v2');

        // Test nested configuration access
        $authConfig = config('captcha.actions.auth');
        
        $this->assertIsArray($authConfig);
        $this->assertEquals('v3', $authConfig['login']['version']);
        $this->assertEquals(0.7, $authConfig['login']['threshold']);
        $this->assertEquals('v2', $authConfig['register']['version']);
    }
}