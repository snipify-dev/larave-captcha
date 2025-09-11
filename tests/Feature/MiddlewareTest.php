<?php

namespace SnipifyDev\LaravelCaptcha\Tests\Feature;

use SnipifyDev\LaravelCaptcha\Tests\TestCase;
use SnipifyDev\LaravelCaptcha\Http\Middleware\VerifyCaptcha;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class MiddlewareTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Register test routes
        Route::post('/test-captcha', function () {
            return response()->json(['success' => true]);
        })->middleware(VerifyCaptcha::class . ':login,0.7');

        Route::post('/test-captcha-default', function () {
            return response()->json(['success' => true]);
        })->middleware(VerifyCaptcha::class);

        Route::get('/test-captcha-get', function () {
            return response()->json(['success' => true]);
        })->middleware(VerifyCaptcha::class);

        Route::post('/api/test-captcha', function () {
            return response()->json(['success' => true]);
        })->middleware(VerifyCaptcha::class);
    }

    /** @test */
    public function it_allows_request_with_valid_captcha()
    {
        $this->mockSuccessfulCaptchaResponse(0.8, 'login');

        $response = $this->postJson('/test-captcha', [
            'captcha_token' => 'valid_token'
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_blocks_request_with_invalid_captcha()
    {
        $this->mockFailedCaptchaResponse(['invalid-input-response']);

        $response = $this->postJson('/test-captcha', [
            'captcha_token' => 'invalid_token'
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'message' => 'Captcha verification failed.',
                    'captcha_error' => true
                ]);
    }

    /** @test */
    public function it_blocks_request_with_low_score()
    {
        config()->set('captcha.v3.thresholds.login', 0.7);
        $this->mockSuccessfulCaptchaResponse(0.5, 'login');

        $response = $this->postJson('/test-captcha', [
            'captcha_token' => 'low_score_token'
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'captcha_error' => true
                ]);
    }

    /** @test */
    public function it_blocks_request_with_missing_token()
    {
        $response = $this->postJson('/test-captcha', []);

        $response->assertStatus(422)
                ->assertJson([
                    'captcha_error' => true
                ]);
    }

    /** @test */
    public function it_skips_verification_when_captcha_disabled()
    {
        config()->set('captcha.default_version', false);

        $response = $this->postJson('/test-captcha-default', []);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_skips_get_requests_by_default()
    {
        $response = $this->getJson('/test-captcha-get');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_verifies_get_requests_when_configured()
    {
        config()->set('captcha.middleware.verify_get_requests', true);
        $this->mockSuccessfulCaptchaResponse(0.9, 'default');

        $response = $this->getJson('/test-captcha-get?captcha_token=valid_token');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_handles_api_requests_with_json_response()
    {
        $this->mockFailedCaptchaResponse(['invalid-input-response']);

        $response = $this->postJson('/api/test-captcha', [
            'captcha_token' => 'invalid_token'
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'message' => 'Captcha verification failed.',
                    'errors' => [
                        'captcha' => ['The response parameter is invalid or malformed.']
                    ],
                    'captcha_error' => true
                ]);
    }

    /** @test */
    public function it_tries_multiple_field_names()
    {
        $this->mockSuccessfulCaptchaResponse(0.9, 'default');

        // Test with g-recaptcha-response (v2 default)
        $response = $this->postJson('/test-captcha-default', [
            'g-recaptcha-response' => 'valid_token'
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_logs_captcha_failures_when_enabled()
    {
        config()->set('captcha.errors.log_errors', true);
        $this->mockFailedCaptchaResponse(['invalid-input-response']);

        // Capture logs
        $logMessages = [];
        $this->app['log']->listen(function ($level, $message, $context) use (&$logMessages) {
            $logMessages[] = compact('level', 'message', 'context');
        });

        $response = $this->postJson('/test-captcha', [
            'captcha_token' => 'invalid_token'
        ]);

        $response->assertStatus(422);
        
        // Check that failure was logged
        $this->assertNotEmpty($logMessages);
        $this->assertStringContainsString('Captcha middleware validation failed', $logMessages[0]['message'] ?? '');
    }

    /** @test */
    public function it_handles_network_errors_gracefully()
    {
        // Mock a network error
        $this->app->bind(\Illuminate\Http\Client\Factory::class, function () {
            $factory = \Mockery::mock(\Illuminate\Http\Client\Factory::class);
            $factory->shouldReceive('timeout')
                   ->andThrow(new \Illuminate\Http\Client\ConnectionException('Network error'));
            return $factory;
        });

        $response = $this->postJson('/test-captcha', [
            'captcha_token' => 'any_token'
        ]);

        $response->assertStatus(500)
                ->assertJson([
                    'captcha_error' => true
                ]);
    }

    /** @test */
    public function it_handles_form_requests_with_redirect()
    {
        $this->mockFailedCaptchaResponse(['invalid-input-response']);

        $response = $this->post('/test-captcha', [
            'captcha_token' => 'invalid_token'
        ]);

        // Should trigger ValidationException which results in redirect
        $response->assertStatus(302);
    }

    /** @test */
    public function it_respects_custom_field_name_parameter()
    {
        Route::post('/test-custom-field', function () {
            return response()->json(['success' => true]);
        })->middleware(VerifyCaptcha::class . ':default,null,custom_captcha_field');

        $this->mockSuccessfulCaptchaResponse(0.9, 'default');

        $response = $this->postJson('/test-custom-field', [
            'custom_captcha_field' => 'valid_token'
        ]);

        $response->assertStatus(200);
    }
}