<?php

namespace SnipifyDev\LaravelCaptcha\Tests\Unit;

use SnipifyDev\LaravelCaptcha\Tests\TestCase;
use SnipifyDev\LaravelCaptcha\Services\RecaptchaV2Service;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaValidationException;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaConfigurationException;
use Illuminate\Support\Facades\Http;

class RecaptchaV2ServiceTest extends TestCase
{
    protected RecaptchaV2Service $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = app(RecaptchaV2Service::class);
    }

    /** @test */
    public function it_validates_successful_captcha_response()
    {
        $response = [
            'success' => true,
            'challenge_ts' => now()->toISOString(),
            'hostname' => 'localhost',
        ];

        $this->mockHttpClient($response);
        
        $result = $this->service->verify('test_token');
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_fails_validation_when_google_returns_error()
    {
        $this->mockFailedCaptchaResponse(['invalid-input-response']);
        
        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage('The response parameter is invalid or malformed.');
        
        $this->service->verify('test_token');
    }

    /** @test */
    public function it_fails_validation_when_success_is_false()
    {
        $response = [
            'success' => false,
            'error-codes' => ['timeout-or-duplicate'],
        ];

        $this->mockHttpClient($response);
        
        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage('The response is no longer valid');
        
        $this->service->verify('test_token');
    }

    /** @test */
    public function it_validates_hostname_when_configured()
    {
        config()->set('captcha.v2.verify_hostname', true);
        config()->set('captcha.hostname', 'example.com');
        
        $response = [
            'success' => true,
            'hostname' => 'malicious.com',
            'challenge_ts' => now()->toISOString(),
        ];

        $this->mockHttpClient($response);
        
        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage("Expected hostname 'example.com' but got 'malicious.com'");
        
        $this->service->verify('test_token');
    }

    /** @test */
    public function it_ignores_action_parameter()
    {
        $response = [
            'success' => true,
            'challenge_ts' => now()->toISOString(),
            'hostname' => 'localhost',
        ];

        $this->mockHttpClient($response);
        
        // V2 should ignore action parameter
        $result = $this->service->verify('test_token', 'login');
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_ignores_threshold_parameter()
    {
        $response = [
            'success' => true,
            'challenge_ts' => now()->toISOString(),
            'hostname' => 'localhost',
        ];

        $this->mockHttpClient($response);
        
        // V2 should ignore threshold parameter
        $result = $this->service->verify('test_token', 'login', 0.7);
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_throws_exception_for_missing_site_key()
    {
        config()->set('captcha.site_key', null);
        
        $this->expectException(CaptchaConfigurationException::class);
        $this->expectExceptionMessage('reCAPTCHA site key is missing');
        
        $this->service->verify('test_token');
    }

    /** @test */
    public function it_throws_exception_for_missing_secret_key()
    {
        config()->set('captcha.secret_key', null);
        
        $this->expectException(CaptchaConfigurationException::class);
        $this->expectExceptionMessage('reCAPTCHA secret key is missing');
        
        $this->service->verify('test_token');
    }

    /** @test */
    public function it_handles_network_timeout()
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
        });
        
        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage('Network error: Connection timeout');
        
        $this->service->verify('test_token');
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
        $this->assertEquals('v2', $this->service->getVersion());
    }

    /** @test */
    public function it_handles_multiple_error_codes()
    {
        $this->mockFailedCaptchaResponse(['invalid-input-response', 'timeout-or-duplicate']);
        
        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage('The response parameter is invalid or malformed. The response is no longer valid');
        
        $this->service->verify('test_token');
    }
}