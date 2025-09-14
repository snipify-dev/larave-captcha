@if (isset($enabled) && isset($version) && isset($siteKey) && $enabled && $version && $siteKey)
    {{-- Load Google reCAPTCHA API --}}
    @once('captcha-google-script')
        <script src="{{ $scriptUrl }}"
            async
            defer></script>
    @endonce

    {{-- Simple inline captcha implementation --}}
    @once('simple-captcha-inline')
        <script>
            window.captchaConfig = {!! $jsConfig ? json_encode($jsConfig) : '{}' !!};
            window.captchaSiteKey = '{{ $siteKey }}';

            class SimpleCaptcha {
                constructor() {
                    this.siteKey = window.captchaConfig?.site_key || window.captchaSiteKey;
                    this.version = window.captchaConfig?.version || '{{ $version }}';
                    this.initialized = false;
                    this.widgetIds = new Map(); // Track widget IDs for proper cleanup

                    if (!this.siteKey) return;
                    this.init();
                }

                init() {
                    if (this.initialized) return;

                    if (typeof grecaptcha === 'undefined') {
                        setTimeout(() => this.init(), 100);
                        return;
                    }

                    this.initialized = true;
                    this.initializeFields();
                    this.observeNewFields();
                }

                initializeFields() {
                    const fields = document.querySelectorAll('[data-captcha-version]');
                    fields.forEach(field => {
                        this.initField(field);
                    });
                }

                initField(field) {
                    const action = field.dataset.captchaAction || 'default';
                    // Always use the field's specified version, fallback to global only if not set
                    const version = field.dataset.captchaVersion || this.version;

                    // Clean up any existing captcha widgets for this field
                    this.cleanupField(field);

                    field.dataset.captchaInitialized = 'true';

                    if (version === 'v3') {
                        this.setupV3FormInterception(field, action);
                        // Pure JIT: No initial token generation - tokens generated only on form submission
                    } else {
                        this.renderV2Widget(field);
                    }
                }

                cleanupField(field) {
                    // Get widget ID if it exists
                    const fieldId = field.id;
                    const widgetId = this.widgetIds.get(fieldId);

                    // Remove widget using grecaptcha API if available
                    if (typeof grecaptcha !== 'undefined' && widgetId !== undefined) {
                        try {
                            grecaptcha.reset(widgetId);
                        } catch (e) {
                            // Widget may have been destroyed already, continue with container removal
                        }
                        this.widgetIds.delete(fieldId);
                    }

                    // Remove any existing recaptcha containers
                    const container = field.parentElement.querySelector('.recaptcha-container');
                    if (container) {
                        container.remove();
                    }

                    // Reset the field state
                    field.dataset.captchaInitialized = 'false';
                    field.value = '';
                }

                generateV3Token(field, action) {
                    return new Promise((resolve, reject) => {
                        grecaptcha.ready(() => {
                            // Get the correct site key for v3
                            const v3SiteKey = this.getV3SiteKey() || this.siteKey;

                            // Always generate fresh token using v3 execute method
                            if (typeof grecaptcha.execute === 'function') {
                                grecaptcha.execute(v3SiteKey, { action: action })
                                    .then(token => {
                                        field.value = token;
                                        field.dispatchEvent(new Event('input', { bubbles: true }));
                                        resolve(token);
                                    })
                                    .catch(error => {
                                        console.error('v3 token generation failed:', error);
                                        reject(error);
                                    });
                            } else {
                                // Fallback: render invisible widget for v3 with v2 script
                                const container = document.createElement('div');
                                container.style.visibility = 'hidden';
                                field.parentElement.appendChild(container);

                                grecaptcha.render(container, {
                                    sitekey: v3SiteKey,
                                    size: 'invisible',
                                    callback: (token) => {
                                        field.value = token;
                                        field.dispatchEvent(new Event('input', { bubbles: true }));
                                        container.remove();
                                        resolve(token);
                                    }
                                });

                                // Trigger the invisible widget
                                setTimeout(() => grecaptcha.execute(), 100);
                            }
                        });
                    });
                }

                setupV3FormInterception(field, action) {
                    // No longer attach individual form listeners
                    // All form submission handling is done via global event delegation
                    // This method just marks the field as initialized
                    const form = field.closest('form');
                    
                    if (!form) {
                        console.error('v3 captcha field must be inside a form');
                        return;
                    }

                    // Mark form as having captcha fields for the global handler
                    form.dataset.hasCaptchaFields = 'true';
                }

                renderV2Widget(field) {
                    let container = field.parentElement.querySelector('.recaptcha-container');
                    if (!container) {
                        container = document.createElement('div');
                        container.className = 'recaptcha-container';
                        field.parentElement.appendChild(container);
                    }

                    grecaptcha.ready(() => {
                        try {
                            // Get the correct site key for v2
                            const v2SiteKey = field.dataset.sitekey || this.getV2SiteKey() || this.siteKey;

                            const widgetId = grecaptcha.render(container, {
                                sitekey: v2SiteKey,
                                callback: (token) => {
                                    field.value = token;
                                    field.dispatchEvent(new Event('input', {
                                        bubbles: true
                                    }));
                                },
                                'expired-callback': () => {
                                    field.value = '';
                                    field.dispatchEvent(new Event('input', {
                                        bubbles: true
                                    }));
                                }
                            });

                            // Store widget ID for proper cleanup
                            if (field.id && widgetId !== undefined) {
                                this.widgetIds.set(field.id, widgetId);
                            }
                        } catch (error) {
                            console.error('Error rendering captcha:', error);
                        }
                    });
                }

                getV2SiteKey() {
                    // Get v2 site key from config if available
                    return window.captchaConfig?.site_key_v2 || null;
                }

                getV3SiteKey() {
                    // Get v3 site key from config if available
                    return window.captchaConfig?.site_key_v3 || null;
                }

                observeNewFields() {
                    new MutationObserver(mutations => {
                        mutations.forEach(mutation => {
                            mutation.addedNodes.forEach(node => {
                                if (node.nodeType === 1) {
                                    if (node.dataset?.captchaVersion) {
                                        this.initField(node);
                                    }
                                    node.querySelectorAll?.('[data-captcha-version]').forEach(field => this.initField(field));
                                }
                            });
                        });
                    }).observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }

                refresh(fieldOrSelector) {
                    const field = typeof fieldOrSelector === 'string' ? document.querySelector(fieldOrSelector) : fieldOrSelector;
                    if (!field) return;

                    const action = field.dataset.captchaAction || 'default';
                    const version = field.dataset.captchaVersion || this.version;

                    if (version === 'v3') {
                        this.generateV3Token(field, action);
                    } else {
                        // For v2, reinitialize the entire field
                        this.initField(field);
                    }
                }
            }

            // Initialize when ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => window.SimpleCaptcha = new SimpleCaptcha());
            } else {
                window.SimpleCaptcha = new SimpleCaptcha();
            }

            // Simplified field initialization - no listener management needed
            function initializeFields(targetElement = null, forceReinit = false) {
                if (!window.SimpleCaptcha) return;

                setTimeout(() => {
                    const searchScope = targetElement || document;
                    
                    if (forceReinit) {
                        // Force reinitialization - reset all fields in scope
                        const allFields = searchScope.querySelectorAll('[data-captcha-version]');
                        allFields.forEach(field => {
                            field.dataset.captchaInitialized = 'false';
                            window.SimpleCaptcha.initField(field);
                        });
                    } else {
                        // Normal initialization - only new fields
                        const newFields = searchScope.querySelectorAll('[data-captcha-version]:not([data-captcha-initialized="true"])');
                        newFields.forEach(field => {
                            window.SimpleCaptcha.initField(field);
                        });
                    }
                }, 50);
            }

            // Livewire integration - handle DOM updates after morphing
            if (typeof Livewire !== 'undefined' && Livewire.hook) {
                Livewire.hook('morphed', ({ el }) => {
                    if (el && (el.querySelector('[data-captcha-version]') || el.dataset?.captchaVersion)) {
                        initializeFields(el, false);
                    }
                });
            }

            // Global form submission handler using event delegation
            document.addEventListener('submit', async (event) => {
                const form = event.target;
                
                // Check if this form has captcha fields
                if (!form.dataset.hasCaptchaFields) {
                    return; // Not a captcha form, let it submit normally
                }
                
                // Prevent default submission
                event.preventDefault();
                event.stopPropagation();
                
                const v3Fields = form.querySelectorAll('[data-captcha-version="v3"]');
                
                if (v3Fields.length === 0) {
                    return; // No v3 fields, let form submit normally
                }
                
                try {
                    // Always clear existing tokens and generate fresh ones
                    for (const v3Field of v3Fields) {
                        v3Field.value = ''; // Clear old token
                        const fieldAction = v3Field.dataset.captchaAction || 'default';
                        await window.SimpleCaptcha.generateV3Token(v3Field, fieldAction);
                    }

                    // Handle form submission based on type
                    if (form.hasAttribute('wire:submit') || form.hasAttribute('wire:submit.prevent')) {
                        // Livewire form submission
                        const method = form.getAttribute('wire:submit') || form.getAttribute('wire:submit.prevent');
                        const livewireComponent = form.closest('[wire\\:id]');
                        
                        if (livewireComponent && method) {
                            const livewireId = livewireComponent.getAttribute('wire:id');
                            const component = Livewire.find(livewireId);
                            component.call(method.replace('.prevent', '').trim());
                        }
                    } else {
                        // Regular form submission - create new form to avoid infinite loop
                        const formData = new FormData(form);
                        const newForm = document.createElement('form');
                        newForm.method = form.method || 'POST';
                        newForm.action = form.action || '';
                        
                        // Copy all form data
                        for (let [key, value] of formData.entries()) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = value;
                            newForm.appendChild(input);
                        }
                        
                        document.body.appendChild(newForm);
                        newForm.submit();
                        document.body.removeChild(newForm);
                    }
                } catch (error) {
                    console.error('Captcha token generation failed:', error);
                    
                    // Fallback: try to submit anyway
                    if (form.hasAttribute('wire:submit') || form.hasAttribute('wire:submit.prevent')) {
                        const method = form.getAttribute('wire:submit') || form.getAttribute('wire:submit.prevent');
                        const livewireComponent = form.closest('[wire\\:id]');
                        
                        if (livewireComponent && method) {
                            const livewireId = livewireComponent.getAttribute('wire:id');
                            const component = Livewire.find(livewireId);
                            component.call(method.replace('.prevent', '').trim());
                        }
                    } else {
                        // Let the original form submit
                        setTimeout(() => {
                            const formData = new FormData(form);
                            const newForm = document.createElement('form');
                            newForm.method = form.method || 'POST';
                            newForm.action = form.action || '';
                            
                            for (let [key, value] of formData.entries()) {
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = key;
                                input.value = value;
                                newForm.appendChild(input);
                            }
                            
                            document.body.appendChild(newForm);
                            newForm.submit();
                            document.body.removeChild(newForm);
                        }, 100);
                    }
                }
            }, true);

            // Manual field reinitialization (triggered by validation errors)
            window.addEventListener('captcha-refresh', () => {
                initializeFields(null, true); // Force full reinitialization
            });
        </script>
    @endonce
@else
    {{-- Captcha is disabled --}}
    @once('captcha-disabled')
        <script>
            window.captchaConfig = {
                enabled: false
            };
        </script>
    @endonce
@endif
