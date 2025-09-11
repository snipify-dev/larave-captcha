<?php

namespace SnipifyDev\LaravelCaptcha\Tests\Feature;

use SnipifyDev\LaravelCaptcha\Tests\TestCase;
use SnipifyDev\LaravelCaptcha\Traits\WithCaptcha;
use Livewire\Component;

class LivewireTraitTest extends TestCase
{
    /** @test */
    public function it_provides_captcha_rule_method()
    {
        $component = new TestLivewireComponent();
        
        $rule = $component->testCaptchaRule();
        
        $this->assertInstanceOf(\SnipifyDev\LaravelCaptcha\Rules\CaptchaRule::class, $rule);
    }

    /** @test */
    public function it_provides_captcha_rule_with_action()
    {
        $component = new TestLivewireComponent();
        
        $rule = $component->testCaptchaRuleWithAction();
        
        $this->assertInstanceOf(\SnipifyDev\LaravelCaptcha\Rules\CaptchaRule::class, $rule);
    }

    /** @test */
    public function it_provides_captcha_rule_with_action_and_threshold()
    {
        $component = new TestLivewireComponent();
        
        $rule = $component->testCaptchaRuleWithThreshold();
        
        $this->assertInstanceOf(\SnipifyDev\LaravelCaptcha\Rules\CaptchaRule::class, $rule);
    }

    /** @test */
    public function it_provides_refresh_captcha_token_method()
    {
        $component = new TestLivewireComponent();
        
        // This should not throw an exception
        $component->testRefreshCaptchaToken();
        
        $this->assertTrue(true); // If we get here, the method exists and worked
    }

    /** @test */
    public function it_can_validate_captcha_in_livewire_component()
    {
        $this->mockSuccessfulCaptchaResponse(0.9, 'login');
        
        $component = new TestLivewireComponent();
        $component->captcha_token = 'valid_token';
        
        $result = $component->testValidateCaptcha();
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_fails_validation_with_invalid_captcha_in_livewire()
    {
        $this->mockFailedCaptchaResponse(['invalid-input-response']);
        
        $component = new TestLivewireComponent();
        $component->captcha_token = 'invalid_token';
        
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        
        $component->testValidateCaptcha();
    }

    /** @test */
    public function it_works_with_different_actions()
    {
        $this->mockSuccessfulCaptchaResponse(0.9, 'register');
        
        $component = new TestLivewireComponent();
        $component->captcha_token = 'valid_token';
        
        $result = $component->testValidateCaptchaWithAction();
        
        $this->assertTrue($result);
    }
}

// Test component class
class TestLivewireComponent extends Component
{
    use WithCaptcha;

    public $captcha_token;

    public function testCaptchaRule()
    {
        return $this->captchaRule();
    }

    public function testCaptchaRuleWithAction()
    {
        return $this->captchaRule('login');
    }

    public function testCaptchaRuleWithThreshold()
    {
        return $this->captchaRule('login', 0.7);
    }

    public function testRefreshCaptchaToken()
    {
        return $this->refreshCaptchaToken();
    }

    public function testValidateCaptcha()
    {
        $this->validate([
            'captcha_token' => $this->captchaRule('login'),
        ]);
        
        return true;
    }

    public function testValidateCaptchaWithAction()
    {
        $this->validate([
            'captcha_token' => $this->captchaRule('register'),
        ]);
        
        return true;
    }

    public function render()
    {
        return '<div>Test Component</div>';
    }
}