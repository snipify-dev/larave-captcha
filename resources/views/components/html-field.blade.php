@props([
    'name' => 'captchaToken',
    'action' => 'default',
    'version' => null,
    'fieldClass' => '',
    'showError' => false,
    'debug' => false,
    'errorClass' => 'text-sm text-red-500 mt-1',
    'id' => null,
])

@php
    $version = $version ?? config('laravel-captcha.version', 'v3');
    $fieldId = $id ?? 'captcha-field-' . uniqid();
    
    // Convert string boolean values to actual booleans
    $debug = filter_var($debug, FILTER_VALIDATE_BOOLEAN);
    $showError = filter_var($showError, FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="{{ $fieldClass }}">
    {{-- Hidden field for token storage --}}
    <input type="hidden"
        id="{{ $fieldId }}"
        name="{{ $name }}"
        value="{{ old($name) }}"
        data-captcha-action="{{ $action }}"
        data-captcha-version="{{ $version }}"
        {{ $attributes->except(['name', 'action', 'version', 'field-class', 'show-error', 'debug', 'error-class', 'id']) }}>

    {{-- v2 Container (will be populated by JavaScript if needed) --}}
    @if ($version === 'v2')
        <div class="recaptcha-container"></div>
    @endif

    {{-- Show validation errors if enabled --}}
    @if ($showError)
        @error($name)
            <div class="{{ $errorClass }}">{{ $message }}</div>
        @enderror
    @endif

    {{-- Debug information (only in debug mode) --}}
    @if ($debug)
        <div class="mt-1 text-xs text-gray-500" style="font-family: monospace;">
            <details>
                <summary>Captcha Debug Info</summary>
                <div class="p-2 mt-1 text-xs bg-gray-100 rounded">
                    <div><strong>Field ID:</strong> {{ $fieldId }}</div>
                    <div><strong>Field Name:</strong> {{ $name }}</div>
                    <div><strong>Action:</strong> {{ $action }}</div>
                    <div><strong>Version:</strong> {{ $version }}</div>
                    <div><strong>Enabled:</strong> {{ config('laravel-captcha.version') ? 'Yes' : 'No' }}</div>
                </div>
            </details>
        </div>
    @endif
</div>
