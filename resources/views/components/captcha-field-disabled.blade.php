{{-- Disabled/Hidden Captcha Field --}}
@if(config('laravel-captcha.development.debug', false) && app()->environment(['local', 'testing']))
<div class="captcha-disabled" style="font-size: 11px; color: #999; padding: 5px; border: 1px dashed #ddd;">
    Debug: Captcha is disabled
    @if(!config('laravel-captcha.default'))
        (globally disabled)
    @elseif(app()->environment('testing') && config('laravel-captcha.skip_testing', true))
        (skipped in testing)
    @elseif(app()->environment('local') && config('laravel-captcha.fake_in_development', false))
        (fake mode in development)
    @endif
</div>
@endif

{{-- Always provide the hidden field for form compatibility --}}
<input type="hidden" name="{{ $name ?? 'captcha_token' }}" value="disabled" autocomplete="off" />