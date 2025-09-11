<?php

namespace SnipifyDev\LaravelCaptcha\Tests\Unit;

use SnipifyDev\LaravelCaptcha\Tests\TestCase;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaException;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaConfigurationException;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaValidationException;

class ExceptionsTest extends TestCase
{
    /** @test */
    public function it_creates_base_captcha_exception()
    {
        $exception = new CaptchaException('Test message');
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals([], $exception->getResponseData());
        $this->assertNull($exception->getScore());
        $this->assertNull($exception->getAction());
    }

    /** @test */
    public function it_creates_exception_with_response_data()
    {
        $responseData = ['success' => false, 'score' => 0.3];
        $exception = CaptchaException::fromResponse($responseData, 'Failed verification');
        
        $this->assertEquals('Failed verification', $exception->getMessage());
        $this->assertEquals($responseData, $exception->getResponseData());
        $this->assertEquals(0.3, $exception->getScore());
    }

    /** @test */
    public function it_creates_exception_with_action()
    {
        $responseData = ['action' => 'login'];
        $exception = CaptchaException::fromResponse($responseData);
        
        $this->assertEquals('login', $exception->getAction());
    }

    /** @test */
    public function it_provides_context_for_logging()
    {
        $responseData = ['success' => false, 'score' => 0.3, 'action' => 'login'];
        $exception = CaptchaException::fromResponse($responseData, 'Test failure');
        
        $context = $exception->getContext();
        
        $this->assertArrayHasKey('message', $context);
        $this->assertArrayHasKey('code', $context);
        $this->assertArrayHasKey('response_data', $context);
        $this->assertArrayHasKey('score', $context);
        $this->assertArrayHasKey('action', $context);
        $this->assertEquals('Test failure', $context['message']);
        $this->assertEquals(0.3, $context['score']);
        $this->assertEquals('login', $context['action']);
    }

    /** @test */
    public function it_creates_configuration_exception_for_missing_site_key()
    {
        $exception = CaptchaConfigurationException::missingSiteKey();
        
        $this->assertInstanceOf(CaptchaConfigurationException::class, $exception);
        $this->assertStringContainsString('site key is missing', $exception->getMessage());
        $this->assertStringContainsString('RECAPTCHAV3_SITEKEY', $exception->getMessage());
    }

    /** @test */
    public function it_creates_configuration_exception_for_missing_secret_key()
    {
        $exception = CaptchaConfigurationException::missingSecretKey();
        
        $this->assertInstanceOf(CaptchaConfigurationException::class, $exception);
        $this->assertStringContainsString('secret key is missing', $exception->getMessage());
        $this->assertStringContainsString('RECAPTCHAV3_SECRET', $exception->getMessage());
    }

    /** @test */
    public function it_creates_configuration_exception_for_invalid_version()
    {
        $exception = CaptchaConfigurationException::invalidVersion('v4');
        
        $this->assertInstanceOf(CaptchaConfigurationException::class, $exception);
        $this->assertStringContainsString("Invalid captcha version 'v4'", $exception->getMessage());
        $this->assertStringContainsString('v2, v3, false', $exception->getMessage());
    }

    /** @test */
    public function it_creates_configuration_exception_for_invalid_threshold()
    {
        $exception = CaptchaConfigurationException::invalidThreshold(1.5);
        
        $this->assertInstanceOf(CaptchaConfigurationException::class, $exception);
        $this->assertStringContainsString("Invalid threshold '1.5'", $exception->getMessage());
        $this->assertStringContainsString('between 0.0 and 1.0', $exception->getMessage());
    }

    /** @test */
    public function it_creates_configuration_exception_for_missing_action_config()
    {
        $exception = CaptchaConfigurationException::missingActionConfiguration('custom_action');
        
        $this->assertInstanceOf(CaptchaConfigurationException::class, $exception);
        $this->assertStringContainsString("No threshold configured for action 'custom_action'", $exception->getMessage());
        $this->assertStringContainsString('captcha.v3.thresholds', $exception->getMessage());
    }

    /** @test */
    public function it_creates_configuration_exception_for_unsupported_service()
    {
        $exception = CaptchaConfigurationException::unsupportedService('hcaptcha');
        
        $this->assertInstanceOf(CaptchaConfigurationException::class, $exception);
        $this->assertStringContainsString("Unsupported captcha service 'hcaptcha'", $exception->getMessage());
        $this->assertStringContainsString("only 'recaptcha' is supported", $exception->getMessage());
    }

    /** @test */
    public function it_creates_validation_exception_from_error_codes()
    {
        $errorCodes = ['invalid-input-response', 'timeout-or-duplicate'];
        $responseData = ['error-codes' => $errorCodes];
        
        $exception = CaptchaValidationException::fromErrorCodes($errorCodes, $responseData);
        
        $this->assertInstanceOf(CaptchaValidationException::class, $exception);
        $this->assertStringContainsString('invalid or malformed', $exception->getMessage());
        $this->assertStringContainsString('no longer valid', $exception->getMessage());
        $this->assertEquals($responseData, $exception->getResponseData());
    }

    /** @test */
    public function it_creates_validation_exception_for_score_threshold_not_met()
    {
        $exception = CaptchaValidationException::scoreThresholdNotMet(0.3, 0.5, 'login', ['score' => 0.3]);
        
        $this->assertInstanceOf(CaptchaValidationException::class, $exception);
        $this->assertStringContainsString('score 0.3 is below threshold 0.5', $exception->getMessage());
        $this->assertStringContainsString("action 'login'", $exception->getMessage());
        $this->assertEquals(0.3, $exception->getScore());
        $this->assertEquals('login', $exception->getAction());
    }

    /** @test */
    public function it_creates_validation_exception_for_action_mismatch()
    {
        $exception = CaptchaValidationException::actionMismatch('login', 'register', ['action' => 'register']);
        
        $this->assertInstanceOf(CaptchaValidationException::class, $exception);
        $this->assertStringContainsString("Expected action 'login' but got 'register'", $exception->getMessage());
        $this->assertEquals('register', $exception->getAction());
    }

    /** @test */
    public function it_creates_validation_exception_for_hostname_mismatch()
    {
        $exception = CaptchaValidationException::hostnameMismatch('localhost', 'example.com', ['hostname' => 'example.com']);
        
        $this->assertInstanceOf(CaptchaValidationException::class, $exception);
        $this->assertStringContainsString("Expected hostname 'localhost' but got 'example.com'", $exception->getMessage());
    }

    /** @test */
    public function it_creates_validation_exception_for_network_error()
    {
        $previous = new \Exception('Connection failed');
        $exception = CaptchaValidationException::networkError('Connection timeout', $previous);
        
        $this->assertInstanceOf(CaptchaValidationException::class, $exception);
        $this->assertStringContainsString('Network error: Connection timeout', $exception->getMessage());
        $this->assertEquals($previous, $exception->getPrevious());
    }

    /** @test */
    public function it_creates_validation_exception_for_timeout()
    {
        $exception = CaptchaValidationException::timeout(30);
        
        $this->assertInstanceOf(CaptchaValidationException::class, $exception);
        $this->assertStringContainsString('timed out after 30 seconds', $exception->getMessage());
    }

    /** @test */
    public function it_creates_validation_exception_for_missing_configuration()
    {
        $exception = CaptchaValidationException::missingConfiguration('site_key');
        
        $this->assertInstanceOf(CaptchaValidationException::class, $exception);
        $this->assertStringContainsString('Missing captcha configuration: site_key', $exception->getMessage());
    }

    /** @test */
    public function it_creates_validation_exception_for_invalid_configuration()
    {
        $exception = CaptchaValidationException::invalidConfiguration('threshold', 'invalid');
        
        $this->assertInstanceOf(CaptchaValidationException::class, $exception);
        $this->assertStringContainsString('Invalid captcha configuration for threshold: invalid', $exception->getMessage());
    }

    /** @test */
    public function it_handles_unknown_error_codes()
    {
        $errorCodes = ['unknown-error-code'];
        $exception = CaptchaValidationException::fromErrorCodes($errorCodes);
        
        $this->assertStringContainsString('Unknown error: unknown-error-code', $exception->getMessage());
    }

    /** @test */
    public function it_handles_non_scalar_values_in_exceptions()
    {
        $exception = CaptchaConfigurationException::invalidVersion(['array']);
        
        $this->assertStringContainsString('array', $exception->getMessage());
    }

    /** @test */
    public function it_provides_error_code_constants()
    {
        $errorCodes = CaptchaValidationException::ERROR_CODES;
        
        $this->assertIsArray($errorCodes);
        $this->assertArrayHasKey('missing-input-secret', $errorCodes);
        $this->assertArrayHasKey('invalid-input-secret', $errorCodes);
        $this->assertArrayHasKey('missing-input-response', $errorCodes);
        $this->assertArrayHasKey('invalid-input-response', $errorCodes);
        $this->assertArrayHasKey('score-threshold-not-met', $errorCodes);
        $this->assertArrayHasKey('action-mismatch', $errorCodes);
        $this->assertArrayHasKey('hostname-mismatch', $errorCodes);
    }
}