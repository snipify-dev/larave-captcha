{{-- reCAPTCHA v2 Widget --}}
<div class="{{ $wrapperClass }}" data-captcha-version="v2">
    @if($isInvisible())
        {{-- Invisible reCAPTCHA v2 --}}
        <div id="{{ $id }}" 
             class="{{ $fieldClass }} g-recaptcha" 
             data-sitekey="{{ $siteKey }}"
             data-size="invisible"
             {!! $getAttributesString() !!}>
        </div>
        
        {{-- Hidden input to store the response --}}
        <input type="hidden" name="{{ $name }}" id="{{ $id }}_response" />
        
        {{-- Trigger button (optional, can be customized) --}}
        <button type="button" 
                class="captcha-trigger-btn" 
                onclick="grecaptcha.execute({{ $id }}_widget);"
                style="display: none;">
            Verify Captcha
        </button>
    @else
        {{-- Standard reCAPTCHA v2 Checkbox --}}
        <div id="{{ $id }}" 
             class="{{ $fieldClass }} g-recaptcha" 
             data-sitekey="{{ $siteKey }}"
             {!! $getAttributesString() !!}>
        </div>
        
        {{-- Hidden input to store the response --}}
        <input type="hidden" name="{{ $name }}" id="{{ $id }}_response" />
    @endif
    
    {{-- Error display element --}}
    @error($name)
        <div class="{{ config('laravel-captcha.attributes.css_classes.error', 'captcha-error') }}">
            {{ $message }}
        </div>
    @enderror
    
    {{-- Debug information (only in debug mode) --}}
    @if(config('laravel-captcha.development.debug', false) && app()->environment(['local', 'testing']))
        <div class="captcha-debug" style="font-size: 11px; color: #666; margin-top: 2px;">
            Debug: reCAPTCHA v2 | Theme: {{ $widgetConfig['theme'] ?? 'light' }} | Size: {{ $widgetConfig['size'] ?? 'normal' }}
            @if($isInvisible()) | Mode: Invisible @endif
        </div>
    @endif
</div>

{{-- Inline JavaScript for v2 integration --}}
<script>
(function() {
    var fieldId = '{{ $id }}';
    var responseFieldId = '{{ $id }}_response';
    var isInvisible = {{ $isInvisible() ? 'true' : 'false' }};
    
    // Callback functions
    window['captcha_callback_' + fieldId.replace(/[^a-zA-Z0-9]/g, '_')] = function(token) {
        var responseField = document.getElementById(responseFieldId);
        if (responseField) {
            responseField.value = token;
            
            // Trigger input event for form validation
            responseField.dispatchEvent(new Event('input', { bubbles: true }));
            
            // Trigger change event for Livewire
            if (window.Livewire) {
                responseField.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    };
    
    window['captcha_expired_' + fieldId.replace(/[^a-zA-Z0-9]/g, '_')] = function() {
        var responseField = document.getElementById(responseFieldId);
        if (responseField) {
            responseField.value = '';
            responseField.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };
    
    window['captcha_error_' + fieldId.replace(/[^a-zA-Z0-9]/g, '_')] = function() {
        console.error('reCAPTCHA error for field: ' + fieldId);
        var responseField = document.getElementById(responseFieldId);
        if (responseField) {
            responseField.value = '';
            responseField.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };
    
    // Register field for initialization
    window.captchaV2Fields = window.captchaV2Fields || [];
    window.captchaV2Fields.push({
        id: fieldId,
        responseId: responseFieldId,
        invisible: isInvisible,
        config: {!! json_encode($widgetConfig) !!}
    });
    
    // Initialize immediately if grecaptcha is ready
    if (window.grecaptcha && window.captchaManager && window.captchaManager.initializeV2Field) {
        window.captchaManager.initializeV2Field(fieldId);
    }
})();
</script>