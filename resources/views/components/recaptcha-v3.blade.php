{{-- reCAPTCHA v3 Hidden Field --}}
<div class="{{ $wrapperClass }}" data-captcha-version="v3" data-captcha-action="{{ $action }}">
    <input type="hidden" 
           name="{{ $name }}" 
           id="{{ $id }}"
           class="{{ $fieldClass }} captcha-v3-field"
           {!! $getAttributesString($getV3DataAttributes()) !!}
           data-threshold="{{ $getThreshold() }}"
           autocomplete="off"
           @if(config('captcha.development.debug', false))
           data-debug="true"
           @endif
    />
    
    {{-- Error display element (optional) --}}
    @error($name)
        <div class="{{ config('captcha.attributes.css_classes.error', 'captcha-error') }}">
            {{ $message }}
        </div>
    @enderror
    
    {{-- Debug information (only in debug mode) --}}
    @if(config('captcha.development.debug', false) && app()->environment(['local', 'testing']))
        <div class="captcha-debug" style="font-size: 11px; color: #666; margin-top: 2px;">
            Debug: reCAPTCHA v3 | Action: {{ $action }} | Threshold: {{ $getThreshold() }}
        </div>
    @endif
</div>

{{-- Inline JavaScript for immediate initialization --}}
<script>
(function() {
    // Ensure the field is initialized if captcha manager is already loaded
    if (window.captchaManager && window.captchaManager.initializeField) {
        var field = document.getElementById('{{ $id }}');
        if (field) {
            window.captchaManager.initializeField(field);
        }
    }
    
    // Register field for later initialization
    window.captchaPendingFields = window.captchaPendingFields || [];
    window.captchaPendingFields.push('{{ $id }}');
})();
</script>