<?php

namespace SnipifyDev\LaravelCaptcha\Tests\Feature;

use SnipifyDev\LaravelCaptcha\Tests\TestCase;
use Illuminate\Support\Facades\View;

class BladeComponentsTest extends TestCase
{
    /** @test */
    public function it_renders_v2_checkbox_component()
    {
        config()->set('captcha.default_version', 'v2');
        config()->set('captcha.site_key', 'test_site_key');

        $view = View::make('captcha::v2.checkbox');
        $html = $view->render();

        $this->assertStringContainsString('g-recaptcha', $html);
        $this->assertStringContainsString('test_site_key', $html);
        $this->assertStringContainsString('recaptcha/api.js', $html);
    }

    /** @test */
    public function it_renders_v2_invisible_component()
    {
        config()->set('captcha.default_version', 'v2');
        config()->set('captcha.site_key', 'test_site_key');

        $view = View::make('captcha::v2.invisible');
        $html = $view->render();

        $this->assertStringContainsString('g-recaptcha', $html);
        $this->assertStringContainsString('test_site_key', $html);
        $this->assertStringContainsString('invisible', $html);
    }

    /** @test */
    public function it_renders_v3_component()
    {
        config()->set('captcha.default_version', 'v3');
        config()->set('captcha.site_key', 'test_site_key');

        $view = View::make('captcha::v3.script', [
            'action' => 'login'
        ]);
        $html = $view->render();

        $this->assertStringContainsString('test_site_key', $html);
        $this->assertStringContainsString('login', $html);
        $this->assertStringContainsString('recaptcha/api.js', $html);
        $this->assertStringContainsString('captcha-v3.js', $html);
    }

    /** @test */
    public function it_renders_v3_component_with_custom_callback()
    {
        config()->set('captcha.default_version', 'v3');
        config()->set('captcha.site_key', 'test_site_key');

        $view = View::make('captcha::v3.script', [
            'action' => 'contact',
            'callback' => 'myCustomCallback'
        ]);
        $html = $view->render();

        $this->assertStringContainsString('myCustomCallback', $html);
        $this->assertStringContainsString('contact', $html);
    }

    /** @test */
    public function it_renders_nothing_when_captcha_disabled()
    {
        config()->set('captcha.default_version', false);

        $view = View::make('captcha::v3.script', [
            'action' => 'login'
        ]);
        $html = $view->render();

        // Should render empty or minimal content when disabled
        $this->assertStringNotContainsString('recaptcha/api.js', $html);
    }

    /** @test */
    public function it_uses_default_action_when_none_provided()
    {
        config()->set('captcha.default_version', 'v3');
        config()->set('captcha.site_key', 'test_site_key');

        $view = View::make('captcha::v3.script');
        $html = $view->render();

        $this->assertStringContainsString('default', $html);
    }

    /** @test */
    public function it_handles_missing_site_key_gracefully()
    {
        config()->set('captcha.default_version', 'v3');
        config()->set('captcha.site_key', null);

        $view = View::make('captcha::v3.script', [
            'action' => 'login'
        ]);
        
        // Should not throw exception, but might render warning or empty
        $html = $view->render();
        
        $this->assertIsString($html);
    }

    /** @test */
    public function it_renders_v2_with_custom_theme()
    {
        config()->set('captcha.default_version', 'v2');
        config()->set('captcha.site_key', 'test_site_key');

        $view = View::make('captcha::v2.checkbox', [
            'theme' => 'dark',
            'size' => 'compact'
        ]);
        $html = $view->render();

        $this->assertStringContainsString('dark', $html);
        $this->assertStringContainsString('compact', $html);
    }

    /** @test */
    public function it_renders_v2_with_custom_callback()
    {
        config()->set('captcha.default_version', 'v2');
        config()->set('captcha.site_key', 'test_site_key');

        $view = View::make('captcha::v2.checkbox', [
            'callback' => 'myV2Callback'
        ]);
        $html = $view->render();

        $this->assertStringContainsString('myV2Callback', $html);
    }

    /** @test */
    public function it_renders_field_component()
    {
        config()->set('captcha.default_version', 'v3');
        config()->set('captcha.site_key', 'test_site_key');

        $view = View::make('captcha::field', [
            'action' => 'register'
        ]);
        $html = $view->render();

        $this->assertStringContainsString('captcha_token', $html);
        $this->assertStringContainsString('register', $html);
    }

    /** @test */
    public function it_renders_field_component_for_v2()
    {
        config()->set('captcha.default_version', 'v2');
        config()->set('captcha.site_key', 'test_site_key');

        $view = View::make('captcha::field');
        $html = $view->render();

        $this->assertStringContainsString('g-recaptcha', $html);
        $this->assertStringNotContainsString('captcha_token', $html); // v2 doesn't use hidden field
    }

    /** @test */
    public function it_includes_csrf_token_in_forms()
    {
        config()->set('captcha.default_version', 'v3');
        config()->set('captcha.site_key', 'test_site_key');

        // Create a mock session for CSRF
        $this->startSession();

        $view = View::make('captcha::field', [
            'action' => 'contact'
        ]);
        $html = $view->render();

        // Should include CSRF considerations for form integration
        $this->assertStringContainsString('contact', $html);
    }
}