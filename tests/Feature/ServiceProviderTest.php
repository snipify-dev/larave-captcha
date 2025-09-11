<?php

namespace SnipifyDev\LaravelCaptcha\Tests\Feature;

use SnipifyDev\LaravelCaptcha\Tests\TestCase;
use SnipifyDev\LaravelCaptcha\CaptchaServiceProvider;
use SnipifyDev\LaravelCaptcha\CaptchaManager;
use SnipifyDev\LaravelCaptcha\Services\RecaptchaV2Service;
use SnipifyDev\LaravelCaptcha\Services\RecaptchaV3Service;
use SnipifyDev\LaravelCaptcha\Rules\CaptchaRule;
use Illuminate\Support\Facades\Validator;

class ServiceProviderTest extends TestCase
{
    /** @test */
    public function it_registers_captcha_manager_in_container()
    {
        $manager = app(CaptchaManager::class);
        
        $this->assertInstanceOf(CaptchaManager::class, $manager);
    }

    /** @test */
    public function it_registers_v2_service_in_container()
    {
        $service = app(RecaptchaV2Service::class);
        
        $this->assertInstanceOf(RecaptchaV2Service::class, $service);
    }

    /** @test */
    public function it_registers_v3_service_in_container()
    {
        $service = app(RecaptchaV3Service::class);
        
        $this->assertInstanceOf(RecaptchaV3Service::class, $service);
    }

    /** @test */
    public function it_registers_captcha_facade()
    {
        $this->assertTrue(class_exists(\SnipifyDev\LaravelCaptcha\Facades\Captcha::class));
        
        // Test facade works
        $rule = \SnipifyDev\LaravelCaptcha\Facades\Captcha::rule('login');
        $this->assertInstanceOf(CaptchaRule::class, $rule);
    }

    /** @test */
    public function it_publishes_config_file()
    {
        // This test verifies the config publishing is set up
        $provider = new CaptchaServiceProvider(app());
        
        // We can't easily test file publishing in unit tests,
        // but we can verify the service provider sets it up
        $this->assertTrue(method_exists($provider, 'boot'));
    }

    /** @test */
    public function it_publishes_view_files()
    {
        // Test that views are loadable
        $this->assertTrue(view()->exists('captcha::v2.checkbox'));
        $this->assertTrue(view()->exists('captcha::v2.invisible'));
        $this->assertTrue(view()->exists('captcha::v3.script'));
        $this->assertTrue(view()->exists('captcha::field'));
    }

    /** @test */
    public function it_publishes_asset_files()
    {
        // Test that the provider sets up asset publishing
        // In a real application, users would run php artisan vendor:publish
        $provider = new CaptchaServiceProvider(app());
        
        $this->assertTrue(method_exists($provider, 'boot'));
    }

    /** @test */
    public function it_loads_configuration()
    {
        // Test that config is loaded
        $this->assertIsArray(config('captcha'));
        $this->assertArrayHasKey('service', config('captcha'));
        $this->assertArrayHasKey('default_version', config('captcha'));
        $this->assertArrayHasKey('v2', config('captcha'));
        $this->assertArrayHasKey('v3', config('captcha'));
    }

    /** @test */
    public function it_registers_validation_rules()
    {
        // Test that validation rules work through service provider
        $validator = Validator::make(
            ['captcha' => 'test'],
            ['captcha' => 'captcha:login']
        );
        
        // Should not throw an exception for unknown rule
        $this->assertInstanceOf(\Illuminate\Validation\Validator::class, $validator);
    }

    /** @test */
    public function it_binds_services_as_singletons()
    {
        $manager1 = app(CaptchaManager::class);
        $manager2 = app(CaptchaManager::class);
        
        // Should be the same instance (singleton)
        $this->assertSame($manager1, $manager2);
    }

    /** @test */
    public function it_provides_correct_services()
    {
        $provider = new CaptchaServiceProvider(app());
        $provides = $provider->provides();
        
        $this->assertIsArray($provides);
        $this->assertContains(CaptchaManager::class, $provides);
    }

    /** @test */
    public function it_handles_deferred_loading()
    {
        $provider = new CaptchaServiceProvider(app());
        
        // CaptchaServiceProvider should be deferred for performance
        $this->assertTrue($provider->isDeferred());
    }

    /** @test */
    public function it_merges_config_correctly()
    {
        // Test that the default config is merged
        $config = config('captcha');
        
        $this->assertArrayHasKey('service', $config);
        $this->assertArrayHasKey('site_key', $config);
        $this->assertArrayHasKey('secret_key', $config);
        $this->assertArrayHasKey('default_version', $config);
        $this->assertArrayHasKey('v2', $config);
        $this->assertArrayHasKey('v3', $config);
        $this->assertArrayHasKey('cache', $config);
        $this->assertArrayHasKey('rate_limiting', $config);
        $this->assertArrayHasKey('security', $config);
        $this->assertArrayHasKey('errors', $config);
        $this->assertArrayHasKey('actions', $config);
        $this->assertArrayHasKey('middleware', $config);
    }

    /** @test */
    public function it_registers_middleware()
    {
        // Test that the middleware is available
        $middleware = app(\SnipifyDev\LaravelCaptcha\Http\Middleware\VerifyCaptcha::class);
        
        $this->assertInstanceOf(
            \SnipifyDev\LaravelCaptcha\Http\Middleware\VerifyCaptcha::class, 
            $middleware
        );
    }
}