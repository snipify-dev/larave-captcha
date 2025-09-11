@if($enabled && $version && $siteKey)
{{-- Set global JavaScript configuration --}}
<script>
    window.captchaConfig = {!! $getJsConfigJson() !!};
    window.captchaSiteKey = '{{ $siteKey }}';
</script>

{{-- Load Google reCAPTCHA API --}}
@if($getScriptUrl())
<script src="{{ $getScriptUrl() }}" 
        @if($shouldLoadAsync()) async defer @endif
        @if($version === 'v2') 
            onload="window.captchaOnLoad && window.captchaOnLoad()" 
            onerror="window.captchaOnError && window.captchaOnError()"
        @endif>
</script>
@endif

{{-- Load package-specific JavaScript --}}
@if($getPackageScriptUrl())
<script src="{{ $getPackageScriptUrl() }}" defer></script>
@endif

{{-- Initialize captcha when DOM is ready --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ensure captcha configuration is available
        if (!window.captchaConfig) {
            console.warn('Captcha configuration not found');
            return;
        }

        @if($version === 'v3')
            // reCAPTCHA v3 initialization
            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(function() {
                    if (window.CaptchaV3Manager) {
                        window.captchaManager = new window.CaptchaV3Manager();
                    }
                });
            } else {
                // Fallback for when grecaptcha is not loaded
                var checkCount = 0;
                var checkInterval = setInterval(function() {
                    if (typeof grecaptcha !== 'undefined') {
                        clearInterval(checkInterval);
                        grecaptcha.ready(function() {
                            if (window.CaptchaV3Manager) {
                                window.captchaManager = new window.CaptchaV3Manager();
                            }
                        });
                    } else if (checkCount++ > 50) { // Stop after 5 seconds
                        clearInterval(checkInterval);
                        console.warn('Google reCAPTCHA failed to load');
                    }
                }, 100);
            }
        @elseif($version === 'v2')
            // reCAPTCHA v2 initialization
            window.captchaOnLoad = function() {
                if (window.CaptchaV2Manager) {
                    window.captchaManager = new window.CaptchaV2Manager();
                }
            };
            
            window.captchaOnError = function() {
                console.error('Failed to load Google reCAPTCHA');
            };
            
            // If grecaptcha is already loaded
            if (typeof grecaptcha !== 'undefined') {
                window.captchaOnLoad();
            }
        @endif
    });
</script>

{{-- Livewire integration --}}
@if(config('captcha.livewire.enabled', true))
<script>
    document.addEventListener('livewire:init', function() {
        @if(config('captcha.livewire.emit_events', true))
            // Listen for custom captcha events
            @foreach(config('captcha.livewire.refresh_events', ['captcha:refresh']) as $event)
            Livewire.on('{{ $event }}', function() {
                if (window.captchaManager && window.captchaManager.refreshAllTokens) {
                    window.captchaManager.refreshAllTokens();
                }
            });
            @endforeach
        @endif

        // Auto-refresh tokens before they expire
        @if(config('captcha.livewire.auto_refresh', true) && $version === 'v3')
        setInterval(function() {
            if (window.captchaManager && window.captchaManager.refreshAllTokens) {
                window.captchaManager.refreshAllTokens();
            }
        }, {{ config('captcha.livewire.refresh_interval', 110) * 1000 }});
        @endif

        // Refresh tokens after Livewire requests
        Livewire.hook('message.sent', function() {
            setTimeout(function() {
                if (window.captchaManager && window.captchaManager.refreshAllTokens) {
                    window.captchaManager.refreshAllTokens();
                }
            }, 1000);
        });
    });
</script>
@endif

@else
{{-- Captcha is disabled, provide empty configuration for compatibility --}}
<script>
    window.captchaConfig = {
        enabled: false,
        version: false
    };
    window.captchaSiteKey = '';
</script>
@endif