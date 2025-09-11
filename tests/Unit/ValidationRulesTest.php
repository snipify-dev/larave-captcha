<?php

namespace SnipifyDev\LaravelCaptcha\Tests\Unit;

use SnipifyDev\LaravelCaptcha\Tests\TestCase;
use SnipifyDev\LaravelCaptcha\Rules\CaptchaRule;
use SnipifyDev\LaravelCaptcha\Facades\Captcha;
use Illuminate\Support\Facades\Validator;

class ValidationRulesTest extends TestCase
{
    /** @test */
    public function it_creates_captcha_rule_instance()
    {
        $rule = new CaptchaRule();
        
        $this->assertInstanceOf(CaptchaRule::class, $rule);
    }

    /** @test */
    public function it_creates_captcha_rule_with_action()
    {
        $rule = new CaptchaRule('login');
        
        $this->assertInstanceOf(CaptchaRule::class, $rule);
    }

    /** @test */
    public function it_creates_captcha_rule_with_action_and_threshold()
    {
        $rule = new CaptchaRule('login', 0.7);
        
        $this->assertInstanceOf(CaptchaRule::class, $rule);
    }

    /** @test */
    public function it_validates_successful_captcha()
    {
        $this->mockSuccessfulCaptchaResponse(0.9, 'login');
        
        $validator = Validator::make(
            ['captcha_token' => 'test_token'],
            ['captcha_token' => new CaptchaRule('login')]
        );
        
        $this->assertTrue($validator->passes());
    }

    /** @test */
    public function it_fails_validation_for_failed_captcha()
    {
        $this->mockFailedCaptchaResponse(['invalid-input-response']);
        
        $validator = Validator::make(
            ['captcha_token' => 'invalid_token'],
            ['captcha_token' => new CaptchaRule('login')]
        );
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('captcha_token', $validator->errors()->toArray());
    }

    /** @test */
    public function it_uses_custom_error_message()
    {
        $this->mockFailedCaptchaResponse(['invalid-input-response']);
        
        $rule = new CaptchaRule('login');
        $validator = Validator::make(
            ['captcha_token' => 'invalid_token'],
            ['captcha_token' => $rule]
        );
        
        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('captcha verification failed', $validator->errors()->first('captcha_token'));
    }

    /** @test */
    public function it_works_with_facade_rule_method()
    {
        $rule = Captcha::rule('login', 0.7);
        
        $this->assertInstanceOf(CaptchaRule::class, $rule);
    }

    /** @test */
    public function it_passes_validation_when_captcha_is_disabled()
    {
        config()->set('captcha.default_version', false);
        
        $validator = Validator::make(
            ['captcha_token' => 'any_token'],
            ['captcha_token' => new CaptchaRule('login')]
        );
        
        $this->assertTrue($validator->passes());
    }

    /** @test */
    public function it_handles_empty_token()
    {
        $validator = Validator::make(
            ['captcha_token' => ''],
            ['captcha_token' => new CaptchaRule('login')]
        );
        
        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('required', $validator->errors()->first('captcha_token'));
    }

    /** @test */
    public function it_handles_null_token()
    {
        $validator = Validator::make(
            ['captcha_token' => null],
            ['captcha_token' => new CaptchaRule('login')]
        );
        
        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_provides_correct_validation_message()
    {
        $rule = new CaptchaRule('login');
        
        // This tests the message() method if it exists in newer Laravel versions
        if (method_exists($rule, 'message')) {
            $message = $rule->message();
            $this->assertIsString($message);
            $this->assertStringContainsString('captcha', strtolower($message));
        } else {
            // For older Laravel versions, the message is handled differently
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function it_integrates_with_laravel_validation_system()
    {
        $this->mockSuccessfulCaptchaResponse(0.9, 'contact');
        
        $data = ['captcha_token' => 'valid_token'];
        $rules = ['captcha_token' => Captcha::rule('contact')];
        
        $validator = Validator::make($data, $rules);
        
        $this->assertTrue($validator->passes());
        $this->assertEmpty($validator->errors()->toArray());
    }
}