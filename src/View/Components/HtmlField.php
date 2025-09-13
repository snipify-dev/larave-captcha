<?php

namespace SnipifyDev\LaravelCaptcha\View\Components;

use Illuminate\View\Component;
use SnipifyDev\LaravelCaptcha\Facades\Recaptcha;

/**
 * HTML Form Captcha Field Component
 * 
 * For regular HTML forms (non-Livewire)
 */
class HtmlField extends Component
{
    /**
     * The field name attribute.
     *
     * @var string
     */
    public string $name;

    /**
     * The captcha action.
     *
     * @var string|null
     */
    public ?string $action;

    /**
     * The captcha version.
     *
     * @var string|null
     */
    public ?string $version;

    /**
     * The field CSS class.
     *
     * @var string
     */
    public string $fieldClass;

    /**
     * Whether to show validation errors.
     *
     * @var bool
     */
    public bool $showError;

    /**
     * Whether to show debug information.
     *
     * @var bool
     */
    public bool $debug;

    /**
     * Error CSS class.
     *
     * @var string
     */
    public string $errorClass;

    /**
     * Field HTML ID.
     *
     * @var string|null
     */
    public ?string $id;

    /**
     * Create a new component instance.
     *
     * @param string $name
     * @param string|null $action
     * @param string|null $version
     * @param string $fieldClass
     * @param bool $showError
     * @param bool $debug
     * @param string $errorClass
     * @param string|null $id
     */
    public function __construct(
        string $name = 'captchaToken',
        ?string $action = 'default',
        ?string $version = null,
        string $fieldClass = '',
        bool $showError = false,
        bool $debug = false,
        string $errorClass = 'text-sm text-red-500 mt-1',
        ?string $id = null
    ) {
        $this->name = $name;
        $this->action = $action;
        $this->version = $version ?? config('laravel-captcha.default', 'v3');
        $this->fieldClass = $fieldClass;
        $this->showError = $showError;
        $this->debug = $debug;
        $this->errorClass = $errorClass;
        $this->id = $id ?? 'captcha-field-' . uniqid();
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('recaptcha::components.html-field');
    }
}