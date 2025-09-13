<?php

namespace SnipifyDev\LaravelCaptcha\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use SnipifyDev\LaravelCaptcha\Services\RecaptchaService;

class Recaptcha extends Component
{
    public ?string $action;
    public ?string $version;
    public ?float $threshold;
    public string $fieldName;
    public ?string $wireModel;
    public string $fieldId;
    public string $fieldClass;
    public bool $includeScript;
    public bool $showErrors;

    /**
     * Create a new component instance.
     */
    public function __construct(
        ?string $action = null,
        ?string $version = null,
        ?float $threshold = null,
        string $fieldName = 'g-recaptcha-response',
        ?string $wireModel = null,
        ?string $id = null,
        string $class = '',
        bool $includeScript = true,
        bool $showErrors = true
    ) {
        $this->action = $action;
        $this->version = $version ?? config('laravel-captcha.default', 'v3');
        $this->threshold = $threshold;
        $this->fieldName = $wireModel ? 'captchaToken' : $fieldName;
        $this->wireModel = $wireModel;
        $this->fieldId = $id ?? 'recaptcha-' . uniqid();
        $this->fieldClass = trim('recaptcha-field ' . $class);
        $this->includeScript = $includeScript;
        $this->showErrors = $showErrors;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('recaptcha::components.recaptcha');
    }

    /**
     * Get the reCAPTCHA site key.
     */
    public function getSiteKey(): ?string
    {
        $service = app(RecaptchaService::class);
        return $service->getSiteKey($this->version);
    }

    /**
     * Get JavaScript configuration.
     */
    public function getJavaScriptConfig(): array
    {
        $service = app(RecaptchaService::class);
        return $service->getJavaScriptConfig($this->version);
    }

    /**
     * Check if reCAPTCHA is enabled.
     */
    public function isEnabled(): bool
    {
        $service = app(RecaptchaService::class);
        return $service->isEnabled();
    }

    /**
     * Check if this is a Livewire field.
     */
    public function isLivewire(): bool
    {
        return !is_null($this->wireModel);
    }

    /**
     * Get the threshold for this action.
     */
    public function getThreshold(): float
    {
        if ($this->threshold !== null) {
            return $this->threshold;
        }

        return (float) config('laravel-captcha.score_threshold', 0.5);
    }

    /**
     * Get data attributes for the field.
     */
    public function getDataAttributes(): array
    {
        $attributes = [
            'data-recaptcha' => 'true',
            'data-recaptcha-version' => $this->version,
        ];

        if ($this->action) {
            $attributes['data-recaptcha-action'] = $this->action;
        }

        if ($this->version === 'v3') {
            $attributes['data-recaptcha-threshold'] = $this->getThreshold();
        }

        return $attributes;
    }

    /**
     * Get wire:model attribute if this is a Livewire field.
     */
    public function getWireModelAttribute(): ?string
    {
        return $this->wireModel;
    }

    /**
     * Get the error field name for validation errors.
     */
    public function getErrorFieldName(): string
    {
        return $this->isLivewire() ? $this->wireModel : $this->fieldName;
    }

    /**
     * Check if debug mode is enabled.
     */
    public function isDebugMode(): bool
    {
        return config('app.debug', false) && config('laravel-captcha.debug', false);
    }

    /**
     * Get widget configuration for v2.
     */
    public function getWidgetConfig(): array
    {
        return config('laravel-captcha.widget', []);
    }

    /**
     * Get badge configuration for v3.
     */
    public function getBadgeConfig(): array
    {
        return config('laravel-captcha.badge', []);
    }

    /**
     * Check if the widget should be invisible (v2 only).
     */
    public function isInvisible(): bool
    {
        return $this->version === 'v2' && config('laravel-captcha.widget.invisible', false);
    }

    /**
     * Get attributes string for the field element.
     */
    public function getAttributesString(array $additionalAttributes = []): string
    {
        $attributes = array_merge($this->getDataAttributes(), $additionalAttributes);
        
        $parts = [];
        foreach ($attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $parts[] = $key;
                }
            } else {
                $parts[] = $key . '="' . htmlspecialchars($value) . '"';
            }
        }
        
        return implode(' ', $parts);
    }

    /**
     * Generate the reCAPTCHA script tag.
     */
    public function getScriptTag(): string
    {
        if (!$this->includeScript || !$this->isEnabled()) {
            return '';
        }

        $siteKey = $this->getSiteKey();
        if (!$siteKey) {
            return '<!-- reCAPTCHA: Site key not configured -->';
        }

        $config = $this->getJavaScriptConfig();
        $configJson = json_encode($config);

        if ($this->version === 'v3') {
            return <<<HTML
<script>
    window.recaptchaConfig = {$configJson};
</script>
<script src="https://www.google.com/recaptcha/api.js?render={$siteKey}" async defer></script>
HTML;
        } else {
            return <<<HTML
<script>
    window.recaptchaConfig = {$configJson};
</script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
HTML;
        }
    }

    /**
     * Static factory methods for common use cases.
     */
    public static function login(?float $threshold = null): self
    {
        return new self(action: 'login', threshold: $threshold);
    }

    public static function register(?float $threshold = null): self
    {
        return new self(action: 'register', threshold: $threshold);
    }

    public static function contact(?float $threshold = null): self
    {
        return new self(action: 'contact', threshold: $threshold);
    }

    public static function comment(?float $threshold = null): self
    {
        return new self(action: 'comment', threshold: $threshold);
    }

    public static function payment(?float $threshold = null): self
    {
        return new self(action: 'payment', threshold: $threshold);
    }

    public static function v2(): self
    {
        return new self(version: 'v2');
    }

    public static function v3(?string $action = null, ?float $threshold = null): self
    {
        return new self(action: $action, threshold: $threshold, version: 'v3');
    }

    public static function livewire(string $wireModel, ?string $action = null, ?float $threshold = null): self
    {
        return new self(action: $action, threshold: $threshold, wireModel: $wireModel);
    }
}