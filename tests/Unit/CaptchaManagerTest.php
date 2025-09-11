<?php

namespace SnipifyDev\LaravelCaptcha\Tests\Unit;

use SnipifyDev\LaravelCaptcha\Tests\TestCase;
use SnipifyDev\LaravelCaptcha\CaptchaManager;
use SnipifyDev\LaravelCaptcha\Services\RecaptchaV2Service;
use SnipifyDev\LaravelCaptcha\Services\RecaptchaV3Service;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaConfigurationException;

class CaptchaManagerTest extends TestCase
{
    protected CaptchaManager $manager;

    public function setUp(): void
    {
        parent::setUp();
        $this->manager = app(CaptchaManager::class);
    }

    /** @test */
    public function it_creates_v2_service_when_configured()
    {
        config()->set('captcha.default_version', 'v2');
        
        $service = $this->manager->version('v2');
        
        $this->assertInstanceOf(RecaptchaV2Service::class, $service);
    }

    /** @test */
    public function it_creates_v3_service_when_configured()
    {
        config()->set('captcha.default_version', 'v3');
        
        $service = $this->manager->version('v3');
        
        $this->assertInstanceOf(RecaptchaV3Service::class, $service);
    }

    /** @test */
    public function it_uses_default_version_when_no_version_specified()
    {
        config()->set('captcha.default_version', 'v3');
        
        $service = $this->manager->version();
        
        $this->assertInstanceOf(RecaptchaV3Service::class, $service);
    }

    /** @test */
    public function it_throws_exception_for_invalid_version()
    {
        $this->expectException(CaptchaConfigurationException::class);
        $this->expectExceptionMessage("Invalid captcha version 'v4'. Supported versions: v2, v3, false");
        
        $this->manager->version('v4');
    }

    /** @test */
    public function it_returns_false_when_captcha_is_disabled()
    {
        config()->set('captcha.default_version', false);
        
        $result = $this->manager->version();
        
        $this->assertFalse($result);
    }

    /** @test */
    public function it_checks_if_captcha_is_enabled()
    {
        config()->set('captcha.default_version', 'v3');
        $this->assertTrue($this->manager->isEnabled());
        
        config()->set('captcha.default_version', false);
        $this->assertFalse($this->manager->isEnabled());
    }

    /** @test */
    public function it_checks_if_specific_action_is_enabled()
    {
        config()->set('captcha.actions.login.version', 'v3');
        config()->set('captcha.actions.register.version', false);
        
        $this->assertTrue($this->manager->isEnabled('login'));
        $this->assertFalse($this->manager->isEnabled('register'));
    }

    /** @test */
    public function it_falls_back_to_default_when_action_not_configured()
    {
        config()->set('captcha.default_version', 'v3');
        
        $this->assertTrue($this->manager->isEnabled('undefined_action'));
    }

    /** @test */
    public function it_gets_site_key_from_config()
    {
        config()->set('captcha.site_key', 'test_key');
        
        $this->assertEquals('test_key', $this->manager->getSiteKey());
    }

    /** @test */
    public function it_gets_version_for_action()
    {
        config()->set('captcha.default_version', 'v3');
        config()->set('captcha.actions.login.version', 'v2');
        
        $this->assertEquals('v2', $this->manager->getVersion('login'));
        $this->assertEquals('v3', $this->manager->getVersion('register'));
    }

    /** @test */
    public function it_caches_service_instances()
    {
        $service1 = $this->manager->version('v3');
        $service2 = $this->manager->version('v3');
        
        $this->assertSame($service1, $service2);
    }
}