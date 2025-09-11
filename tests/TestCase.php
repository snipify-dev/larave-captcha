<?php

namespace SnipifyDev\LaravelCaptcha\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use SnipifyDev\LaravelCaptcha\CaptchaServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        config()->set('captcha.service', 'recaptcha');
        config()->set('captcha.site_key', 'test_site_key');
        config()->set('captcha.secret_key', 'test_secret_key');
        config()->set('captcha.default_version', 'v3');
    }

    protected function getPackageProviders($app)
    {
        return [
            CaptchaServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Captcha' => \SnipifyDev\LaravelCaptcha\Facades\Captcha::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $app['config']->set('captcha.site_key', 'test_site_key');
        $app['config']->set('captcha.secret_key', 'test_secret_key');
    }

    protected function mockSuccessfulCaptchaResponse($score = 0.9, $action = 'default')
    {
        $response = [
            'success' => true,
            'score' => $score,
            'action' => $action,
            'challenge_ts' => now()->toISOString(),
            'hostname' => 'localhost',
        ];

        $this->mockHttpClient($response);
        
        return $response;
    }

    protected function mockFailedCaptchaResponse($errorCodes = ['invalid-input-response'])
    {
        $response = [
            'success' => false,
            'error-codes' => $errorCodes,
        ];

        $this->mockHttpClient($response);
        
        return $response;
    }

    protected function mockHttpClient($response)
    {
        $mock = \Mockery::mock(\Illuminate\Http\Client\PendingRequest::class);
        $mock->shouldReceive('post')
             ->andReturn(new \Illuminate\Http\Client\Response(
                 new \GuzzleHttp\Psr7\Response(200, [], json_encode($response))
             ));

        app()->bind(\Illuminate\Http\Client\Factory::class, function () use ($mock) {
            $factory = \Mockery::mock(\Illuminate\Http\Client\Factory::class);
            $factory->shouldReceive('timeout')->andReturn($mock);
            return $factory;
        });
    }
}