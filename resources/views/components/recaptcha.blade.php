{{-- 
    Adaptive reCAPTCHA Component
    Works with both standard forms and Livewire components
    Supports both reCAPTCHA v2 and v3
--}}

@if($isEnabled())
    @if($version === 'v3')
        {{-- reCAPTCHA v3 Hidden Field --}}
        <input type="hidden"
            name="{{ $fieldName }}"
            id="{{ $fieldId }}"
            class="{{ $fieldClass }}"
            @if($isLivewire()) wire:model="{{ $wireModel }}" @endif
            {!! $getAttributesString() !!}
            autocomplete="off" />

        {{-- v3 Badge Configuration --}}
        @if($getBadgeConfig()['hide'] ?? false)
            <style>
                .grecaptcha-badge { 
                    visibility: hidden; 
                }
            </style>
        @endif
    @else
        {{-- reCAPTCHA v2 Visible Widget --}}
        <div class="recaptcha-container">
            @if($isInvisible())
                {{-- Invisible v2 Widget --}}
                <div id="{{ $fieldId }}_widget" 
                     class="g-recaptcha"
                     data-sitekey="{{ $getSiteKey() }}"
                     data-size="invisible"
                     data-callback="recaptchaCallback{{ str_replace(['-', '_'], '', $fieldId) }}"
                     data-expired-callback="recaptchaExpired{{ str_replace(['-', '_'], '', $fieldId) }}"
                     data-error-callback="recaptchaError{{ str_replace(['-', '_'], '', $fieldId) }}"
                     {!! $getAttributesString($getWidgetConfig()) !!}>
                </div>
            @else
                {{-- Standard v2 Checkbox Widget --}}
                <div id="{{ $fieldId }}_widget" 
                     class="g-recaptcha"
                     data-sitekey="{{ $getSiteKey() }}"
                     data-callback="recaptchaCallback{{ str_replace(['-', '_'], '', $fieldId) }}"
                     data-expired-callback="recaptchaExpired{{ str_replace(['-', '_'], '', $fieldId) }}"
                     data-error-callback="recaptchaError{{ str_replace(['-', '_'], '', $fieldId) }}"
                     {!! $getAttributesString($getWidgetConfig()) !!}>
                </div>
            @endif
        </div>

        {{-- Hidden field to store the response --}}
        <input type="hidden"
            name="{{ $fieldName }}"
            id="{{ $fieldId }}"
            class="{{ $fieldClass }}"
            @if($isLivewire()) wire:model="{{ $wireModel }}" @endif
            autocomplete="off" />

        {{-- v2 Callback Functions --}}
        <script>
            (function() {
                var fieldId = '{{ $fieldId }}';
                var cleanId = '{{ str_replace(['-', '_'], '', $fieldId) }}';
                
                // Success callback
                window['recaptchaCallback' + cleanId] = function(token) {
                    var field = document.getElementById(fieldId);
                    if (field) {
                        field.value = token;
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                };
                
                // Expired callback
                window['recaptchaExpired' + cleanId] = function() {
                    var field = document.getElementById(fieldId);
                    if (field) {
                        field.value = '';
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                };
                
                // Error callback
                window['recaptchaError' + cleanId] = function() {
                    var field = document.getElementById(fieldId);
                    if (field) {
                        field.value = '';
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                };
            })();
        </script>
    @endif

    {{-- Error Display --}}
    @if($showErrors)
        @error($getErrorFieldName())
            <div class="recaptcha-error text-sm text-red-600 mt-1">
                {{ $message }}
            </div>
        @enderror
    @endif

    {{-- Debug Information --}}
    @if($isDebugMode())
        <details class="recaptcha-debug mt-2 text-xs text-gray-500">
            <summary>reCAPTCHA Debug Info</summary>
            <div class="mt-1 p-2 bg-gray-100 rounded">
                <div><strong>Field ID:</strong> {{ $fieldId }}</div>
                <div><strong>Version:</strong> {{ $version }}</div>
                <div><strong>Action:</strong> {{ $action ?? 'default' }}</div>
                <div><strong>Threshold:</strong> {{ $getThreshold() }}</div>
                <div><strong>Field Name:</strong> {{ $fieldName }}</div>
                <div><strong>Livewire:</strong> {{ $isLivewire() ? 'Yes' : 'No' }}</div>
                @if($isLivewire())
                    <div><strong>Wire Model:</strong> {{ $wireModel }}</div>
                @endif
                <div><strong>Site Key:</strong> {{ substr($getSiteKey() ?? 'Not set', 0, 20) }}...</div>
            </div>
        </details>
    @endif

    {{-- Include Scripts --}}
    @if($includeScript)
        {!! $getScriptTag() !!}
        
        {{-- Include our JavaScript manager --}}
        <script src="{{ asset('vendor/recaptcha/recaptcha.js') }}" defer></script>
    @endif
@else
    {{-- reCAPTCHA is disabled --}}
    @if($isDebugMode())
        <div class="recaptcha-disabled text-xs text-gray-400 p-2 border border-gray-200 rounded">
            reCAPTCHA is disabled (testing mode or not configured)
        </div>
    @endif
@endif