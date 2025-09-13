@props([
    'wireModel' => 'captchaToken',
    'action' => 'default',
    'version' => null,
    'class' => '',
    'id' => null,
    'showError' => false,
    'debug' => false,
    'errorClass' => 'text-sm text-red-500 mt-1',
])

@php
    $version = $version ?? config('laravel-captcha.default', 'v3');
    $fieldId = $id ?? 'captcha-field-' . uniqid();
@endphp

{{-- Hidden field for token storage --}}
<input type="hidden"
    id="{{ $fieldId }}"
    wire:model="{{ $wireModel }}"
    class="{{ $class }}"
    data-captcha-action="{{ $action }}"
    data-captcha-version="{{ $version }}"
    {{ $attributes->except(['wire:model', 'class', 'id']) }}>

{{-- v2 Container (will be populated by JavaScript if needed) --}}
@if ($version === 'v2')
    <div class="recaptcha-container"></div>
@endif

{{-- Debug information (only in debug mode) --}}
@if ($debug)
    <div class="mt-1 text-xs text-gray-500" style="font-family: monospace;">
        <details>
            <summary>Captcha Debug Info</summary>
            <div class="p-2 mt-1 text-xs bg-gray-100 rounded">
                <div><strong>Field ID:</strong> {{ $fieldId }}</div>
                <div><strong>Action:</strong> {{ $action }}</div>
                <div><strong>Version:</strong> {{ $version }}</div>
                <div><strong>Wire Model:</strong> {{ $wireModel }}</div>
                <div><strong>Enabled:</strong> {{ config('laravel-captcha.default') ? 'Yes' : 'No' }}</div>
            </div>
        </details>
    </div>
@endif
