<?php

namespace SnipifyDev\LaravelCaptcha\Tests\Unit;

use SnipifyDev\LaravelCaptcha\Tests\TestCase;
use SnipifyDev\LaravelCaptcha\Services\RecaptchaV3Service;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaValidationException;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaConfigurationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class RecaptchaV3ServiceTest extends TestCase
{
    protected RecaptchaV3Service $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = app(RecaptchaV3Service::class);
    }

    /** @test */
    public function it_validates_successful_captcha_response()
    {
        $this->mockSuccessfulCaptchaResponse(0.9, 'login');
        
        $result = $this->service->verify('test_token', 'login');
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_fails_validation_when_score_below_threshold()
    {
        config()->set('captcha.v3.thresholds.login', 0.7);
        $this->mockSuccessfulCaptchaResponse(0.5, 'login');
        
        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage("Captcha score 0.5 is below threshold 0.7 for action 'login'");
        
        $this->service->verify('test_token', 'login');
    }

    /** @test */
    public function it_fails_validation_when_action_mismatch()
    {
        $this->mockSuccessfulCaptchaResponse(0.9, 'register');
        
        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage("Expected action 'login' but got 'register'");
        
        $this->service->verify('test_token', 'login');
    }

    /** @test */
    public function it_fails_validation_when_google_returns_error()
    {
        $this->mockFailedCaptchaResponse(['invalid-input-response']);
        
        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage('The response parameter is invalid or malformed.');
        
        $this->service->verify('test_token', 'login');
    }

    /** @test */
    public function it_uses_custom_threshold_when_provided()
    {
        $this->mockSuccessfulCaptchaResponse(0.6, 'login');
        
        $result = $this->service->verify('test_token', 'login', 0.5);
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_throws_exception_for_missing_site_key()
    {
        config()->set('captcha.site_key', null);
        
        $this->expectException(CaptchaConfigurationException::class);
        $this->expectExceptionMessage('reCAPTCHA site key is missing');
        
        $this->service->verify('test_token', 'login');
    }

    /** @test */
    public function it_throws_exception_for_missing_secret_key()
    {
        config()->set('captcha.secret_key', null);
        
        $this->expectException(CaptchaConfigurationException::class);
        $this->expectExceptionMessage('reCAPTCHA secret key is missing');
        
        $this->service->verify('test_token', 'login');
    }

    /** @test */
    public function it_throws_exception_for_missing_action_configuration()
    {
        config()->set('captcha.v3.thresholds', []);
        config()->set('captcha.v3.default_threshold', null);
        
        $this->expectException(CaptchaConfigurationException::class);
        $this->expectExceptionMessage("No threshold configured for action 'login'");
        
        $this->service->verify('test_token', 'login');
    }

    /** @test */
    public function it_caches_successful_responses_when_enabled()
    {
        config()->set('captcha.cache.enabled', true);
        config()->set('captcha.cache.ttl', 300);
        
        $this->mockSuccessfulCaptchaResponse(0.9, 'login');
        
        // First call should hit the API
        $result1 = $this->service->verify('test_token', 'login');
        
        // Second call should hit cache
        $result2 = $this->service->verify('test_token', 'login');
        
        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertTrue(Cache::has('captcha:test_token'));
    }

    /** @test */
    public function it_does_not_cache_when_disabled()
    {
        config()->set('captcha.cache.enabled', false);
        
        $this->mockSuccessfulCaptchaResponse(0.9, 'login');
        
        $this->service->verify('test_token', 'login');
        
        $this->assertFalse(Cache::has('captcha:test_token'));
    }

    /** @test */
    public function it_handles_network_timeout()
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
        });
        
        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage('Network error: Connection timeout');
        
        $this->service->verify('test_token', 'login');
    }

    /** @test */
    public function it_validates_threshold_range()
    {
        $this->expectException(CaptchaConfigurationException::class);
        $this->expectExceptionMessage("Invalid threshold '1.5'. Threshold must be a float between 0.0 and 1.0");
        
        $this->service->verify('test_token', 'login', 1.5);
    }

    /** @test */
    public function it_returns_site_key()
    {
        config()->set('captcha.site_key', 'test_site_key');
        
        $this->assertEquals('test_site_key', $this->service->getSiteKey());
    }

    /** @test */
    public function it_returns_version()
    {
        $this->assertEquals('v3', $this->service->getVersion());
    }
}