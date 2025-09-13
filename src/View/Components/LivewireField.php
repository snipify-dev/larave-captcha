<?php

namespace SnipifyDev\LaravelCaptcha\View\Components;

use Illuminate\View\Component;
use SnipifyDev\LaravelCaptcha\Facades\Recaptcha;

/**
 * Livewire Field Component
 * 
 * Specialized component for Livewire integration with enhanced features
 */
class LivewireField extends Component
{
    /**
     * The wire:model attribute value.
     *
     * @var string
     */
    public string $wireModel;

    /**
     * The captcha action.
     *
     * @var string
     */
    public string $action;

    /**
     * The captcha version to use.
     *
     * @var string|false
     */
    public string|false $version;

    /**
     * The field ID.
     *
     * @var string
     */
    public string $fieldId;

    /**
     * CSS classes for the field.
     *
     * @var string
     */
    public string $fieldClass;

    /**
     * Whether to show error messages.
     *
     * @var bool
     */
    public bool $showError;

    /**
     * CSS classes for error display.
     *
     * @var string
     */
    public string $errorClass;

    /**
     * Whether to enable Alpine.js integration.
     *
     * @var bool
     */
    public bool $alpineIntegration;

    /**
     * Placeholder text for the field.
     *
     * @var string
     */
    public string $placeholder;

    /**
     * Whether captcha is enabled.
     *
     * @var bool
     */
    public bool $enabled;

    /**
     * The site key.
     *
     * @var string|null
     */
    public ?string $siteKey;

    /**
     * Additional HTML attributes.
     *
     * @var array
     */
    public array $customAttributes;

    /**
     * Whether to show debug information.
     *
     * @var bool
     */
    public bool $debug;

    /**
     * Create a new component instance.
     *
     * @param string $wireModel
     * @param string $action
     * @param string|null $version
     * @param string|null $class
     * @param string|null $id
     * @param bool $showError
     * @param string $errorClass
     * @param bool $alpineIntegration
     * @param string $placeholder
     * @param array $attributes
     */
    public function __construct(
        string $wireModel = 'captchaToken',
        string $action = 'default',
        ?string $version = null,
        ?string $class = null,
        ?string $id = null,
        bool $showError = true,
        string $errorClass = 'text-sm text-red-500 mt-1',
        bool $alpineIntegration = false,
        string $placeholder = 'Captcha token will be generated automatically',
        bool $debug = false,
        array $attributes = []
    ) {
        $this->wireModel = $wireModel;
        $this->action = $action;
        $this->version = $version ?? config('laravel-captcha.default', 'v3');
        $this->fieldId = $id ?: 'captcha-field-' . uniqid();
        $this->showError = $showError;
        $this->errorClass = $errorClass;
        $this->alpineIntegration = $alpineIntegration;
        $this->placeholder = $placeholder;
        $this->customAttributes = $attributes;

        // Check if captcha is enabled
        $this->enabled = Recaptcha::isEnabled();
        $this->siteKey = $this->enabled ? Recaptcha::getSiteKey($this->version) : null;

        // Set CSS classes
        $baseClass = $class ?: 'captcha-v3-field';
        $versionClass = $this->version === 'v3' ? 'captcha-v3-field' : 'captcha-v2-field';
        $this->fieldClass = trim($baseClass . ' ' . $versionClass);
        $this->debug = $debug;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('recaptcha::components.livewire-field');
    }

    /**
     * Get data attributes for the field.
     *
     * @return array
     */
    public function getDataAttributes(): array
    {
        $attributes = [
            'data-action' => $this->action,
            'data-captcha-action' => $this->action,
            'data-captcha-version' => $this->version,
        ];

        if ($this->siteKey) {
            $attributes['data-sitekey'] = $this->siteKey;
        }

        return $attributes;
    }

    /**
     * Get Alpine.js attributes.
     *
     * @return array
     */
    public function getAlpineAttributes(): array
    {
        if (!$this->alpineIntegration) {
            return [];
        }

        return [
            'x-data' => json_encode([
                'token' => '@entangle("' . $this->wireModel . '")',
                'error' => null,
                'initialized' => false,
            ]),
            'x-init' => "
                \$watch('token', value => {
                    if (value && !initialized) {
                        initialized = true;
                        \$dispatch('captcha:token-received', { token: value, action: '{$this->action}' });
                    }
                });
                
                // Listen for captcha events
                \$el.addEventListener('captcha:token-updated', (e) => {
                    token = e.detail.token;
                    error = null;
                });
                
                \$el.addEventListener('captcha:error', (e) => {
                    error = e.detail.error || 'Captcha verification failed';
                    token = '';
                });
            ",
        ];
    }

    /**
     * Get all HTML attributes as array.
     *
     * @return array
     */
    public function getAllAttributes(): array
    {
        $attributes = array_merge(
            [
                'type' => 'hidden',
                'id' => $this->fieldId,
                'wire:model' => $this->wireModel,
                'class' => $this->fieldClass,
                'placeholder' => $this->placeholder,
            ],
            $this->getDataAttributes(),
            $this->getAlpineAttributes(),
            $this->customAttributes
        );

        return $attributes;
    }

    /**
     * Check if debug mode is enabled.
     *
     * @return bool
     */
    public function isDebugEnabled(): bool
    {
        return config('app.debug', false);
    }

    /**
     * Get debug information.
     *
     * @return array
     */
    public function getDebugInfo(): array
    {
        return [
            'field_id' => $this->fieldId,
            'action' => $this->action,
            'version' => $this->version,
            'wire_model' => $this->wireModel,
            'alpine_integration' => $this->alpineIntegration,
            'enabled' => $this->enabled,
            'site_key' => $this->siteKey ? 'Set' : 'Not Set',
        ];
    }

    /**
     * Check if development indicator should be shown.
     *
     * @return bool
     */
    public function shouldShowIndicator(): bool
    {
        return config('app.debug', false);
    }
}
