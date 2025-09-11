<?php

namespace SnipifyDev\LaravelCaptcha\View\Components;

use Illuminate\View\Component;
use SnipifyDev\LaravelCaptcha\Facades\Captcha;

/**
 * Captcha Field Component
 * 
 * Renders the appropriate captcha field based on version
 */
class CaptchaField extends Component
{
    /**
     * The captcha version to use.
     *
     * @var string|false
     */
    public string|false $version;

    /**
     * The action for v3 captcha.
     *
     * @var string
     */
    public string $action;

    /**
     * The field ID.
     *
     * @var string
     */
    public string $id;

    /**
     * The field name.
     *
     * @var string
     */
    public string $name;

    /**
     * The site key.
     *
     * @var string|null
     */
    public ?string $siteKey;

    /**
     * Whether captcha is enabled.
     *
     * @var bool
     */
    public bool $enabled;

    /**
     * Widget configuration for v2.
     *
     * @var array
     */
    public array $widgetConfig;

    /**
     * CSS classes for the wrapper.
     *
     * @var string
     */
    public string $wrapperClass;

    /**
     * CSS classes for the field.
     *
     * @var string
     */
    public string $fieldClass;

    /**
     * Additional HTML attributes.
     *
     * @var array
     */
    public array $attributes;

    /**
     * Create a new component instance.
     *
     * @param string $action
     * @param string|null $id
     * @param string $name
     * @param string|null $version
     * @param string|null $class
     * @param string|null $wrapperClass
     * @param array $attributes
     */
    public function __construct(
        string $action = 'default',
        ?string $id = null,
        string $name = 'captcha_token',
        ?string $version = null,
        ?string $class = null,
        ?string $wrapperClass = null,
        array $attributes = []
    ) {
        $this->version = $version ?: Captcha::getVersion();
        $this->action = $action;
        $this->id = $id ?: 'captcha-field-' . uniqid();
        $this->name = $name;
        $this->enabled = Captcha::isEnabled($action);
        $this->siteKey = $this->enabled ? Captcha::getSiteKey($this->version) : null;
        $this->attributes = $attributes;

        // CSS classes
        $this->wrapperClass = $wrapperClass ?: Captcha::config('attributes.css_classes.wrapper', 'captcha-wrapper');
        $this->fieldClass = $class ?: Captcha::config('attributes.css_classes.field', 'captcha-field');

        // Widget configuration for v2
        $this->widgetConfig = $this->getWidgetConfiguration();
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        if (!$this->enabled || !$this->version) {
            return view('captcha::components.captcha-field-disabled');
        }

        if ($this->version === 'v3') {
            return view('captcha::components.recaptcha-v3');
        } elseif ($this->version === 'v2') {
            return view('captcha::components.recaptcha-v2');
        }

        return view('captcha::components.captcha-field-disabled');
    }

    /**
     * Get widget configuration for v2.
     *
     * @return array
     */
    protected function getWidgetConfiguration(): array
    {
        if ($this->version !== 'v2' || !$this->enabled) {
            return [];
        }

        $config = [
            'sitekey' => $this->siteKey,
            'theme' => Captcha::config('v2.theme', 'light'),
            'size' => Captcha::config('v2.size', 'normal'),
            'type' => Captcha::config('v2.type', 'image'),
            'tabindex' => Captcha::config('v2.tabindex', 0),
        ];

        // Add callback functions if configured
        if ($callback = Captcha::config('v2.callback')) {
            $config['callback'] = $callback;
        }

        if ($expiredCallback = Captcha::config('v2.expired_callback')) {
            $config['expired-callback'] = $expiredCallback;
        }

        if ($errorCallback = Captcha::config('v2.error_callback')) {
            $config['error-callback'] = $errorCallback;
        }

        // Add custom attributes from package config
        $customAttributes = Captcha::config('attributes.widget_attributes.v2', []);
        return array_merge($config, $customAttributes, $this->attributes);
    }

    /**
     * Get HTML attributes as string.
     *
     * @param array $additionalAttributes
     * @return string
     */
    public function getAttributesString(array $additionalAttributes = []): string
    {
        $attributes = array_merge($this->widgetConfig, $additionalAttributes);
        
        $html = [];
        foreach ($attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $html[] = $key;
                }
            } elseif ($value !== null && $value !== '') {
                $html[] = $key . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        return implode(' ', $html);
    }

    /**
     * Get data attributes for v3 field.
     *
     * @return array
     */
    public function getV3DataAttributes(): array
    {
        $attributes = [
            'data-action' => $this->action,
            'data-sitekey' => $this->siteKey,
        ];

        // Add custom attributes from package config
        $customAttributes = Captcha::config('attributes.widget_attributes.v3', []);
        return array_merge($attributes, $customAttributes);
    }

    /**
     * Check if this is an invisible v2 captcha.
     *
     * @return bool
     */
    public function isInvisible(): bool
    {
        return $this->version === 'v2' && Captcha::config('v2.invisible', false);
    }

    /**
     * Get the threshold for this action (v3 only).
     *
     * @return float
     */
    public function getThreshold(): float
    {
        return Captcha::getThreshold($this->action);
    }
}