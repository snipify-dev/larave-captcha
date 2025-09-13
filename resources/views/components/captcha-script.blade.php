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
                    this.pendingFields = new Set();
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
                        // For v3, also generate token immediately if field is empty
                        if (!field.value) {
                            setTimeout(() => {
                                this.generateV3Token(field, action).catch(console.error);
                            }, 100);
                        }
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

                    // Remove from pending fields if it's there
                    this.pendingFields.delete(field);
                }

                generateV3Token(field, action) {
                    return new Promise((resolve, reject) => {
                        if (this.pendingFields.has(field)) {
                            resolve(field.value);
                            return;
                        }

                        this.pendingFields.add(field);

                        grecaptcha.ready(() => {
                            // Get the correct site key for v3
                            const v3SiteKey = this.getV3SiteKey() || this.siteKey;
                            
                            
                            // Try to use v3 execute method first
                            if (typeof grecaptcha.execute === 'function') {
                                // For v3 script loaded with ?render=sitekey, we use the site key that was rendered
                                grecaptcha.execute(v3SiteKey, { action: action })
                                    .then(token => {
                                        field.value = token;
                                        field.dispatchEvent(new Event('input', { bubbles: true }));
                                        this.pendingFields.delete(field);
                                        resolve(token);
                                    })
                                    .catch(error => {
                                        console.error('v3 token generation failed:', error);
                                        this.pendingFields.delete(field);
                                        reject(error);
                                    });
                            } else {
                                // For v3 with v2 script, we need to render an invisible widget
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
                                        this.pendingFields.delete(field);
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
                    // Find the parent form or Livewire component
                    const form = field.closest('form');
                    const livewireComponent = field.closest('[wire\\:id]');

                    if (!form && !livewireComponent) {
                        console.error('v3 captcha field must be inside a form or Livewire component');
                        return;
                    }

                    // Mark field as v3 and store action
                    field.dataset.v3Action = action;
                    field.dataset.v3Intercepted = 'true';

                    // For Livewire forms, use wire:submit interception
                    if (form && form.hasAttribute('wire:submit') || form && form.hasAttribute('wire:submit.prevent')) {
                        if (!form.dataset.captchaIntercepted) {
                            form.dataset.captchaIntercepted = 'true';

                            // Store original wire:submit value
                            const originalWireSubmit = form.getAttribute('wire:submit') || form.getAttribute('wire:submit.prevent');

                            // Add our interceptor
                            form.addEventListener('submit', async (event) => {
                                const v3Fields = form.querySelectorAll('[data-captcha-version="v3"]');

                                if (v3Fields.length > 0) {
                                    // Check if tokens are already generated
                                    const needsToken = Array.from(v3Fields).some(v3Field => !v3Field.value);

                                    if (needsToken) {
                                        // Prevent submission until tokens are generated
                                        event.preventDefault();
                                        event.stopPropagation();

                                        try {
                                            // Generate tokens for all v3 fields
                                            const promises = Array.from(v3Fields).map(v3Field => {
                                                const fieldAction = v3Field.dataset.v3Action || 'default';
                                                return window.SimpleCaptcha.generateV3Token(v3Field, fieldAction);
                                            });

                                            await Promise.all(promises);

                                            // Manually trigger Livewire method after token generation
                                            const livewireId = livewireComponent ? livewireComponent.getAttribute('wire:id') : null;
                                            if (livewireId && originalWireSubmit) {
                                                // Call the Livewire method directly
                                                Livewire.find(livewireId).call(originalWireSubmit.replace('prevent', '').trim());
                                            }
                                        } catch (error) {
                                            console.error('Captcha token generation failed:', error);
                                            // Allow form to proceed anyway
                                            const livewireId = livewireComponent ? livewireComponent.getAttribute('wire:id') : null;
                                            if (livewireId && originalWireSubmit) {
                                                Livewire.find(livewireId).call(originalWireSubmit.replace('prevent', '').trim());
                                            }
                                        }
                                    }
                                }
                            }, true);
                        }
                    }
                    // For regular forms
                    else if (form && !form.dataset.captchaIntercepted) {
                        form.dataset.captchaIntercepted = 'true';

                        form.addEventListener('submit', async (event) => {
                            const v3Fields = form.querySelectorAll('[data-captcha-version="v3"]');

                            if (v3Fields.length > 0) {
                                const needsToken = Array.from(v3Fields).some(v3Field => !v3Field.value);

                                if (needsToken) {
                                    event.preventDefault();
                                    event.stopPropagation();

                                    try {
                                        const promises = Array.from(v3Fields).map(v3Field => {
                                            const fieldAction = v3Field.dataset.v3Action || 'default';
                                            return window.SimpleCaptcha.generateV3Token(v3Field, fieldAction);
                                        });

                                        await Promise.all(promises);

                                        // Re-submit the form
                                        form.submit();
                                    } catch (error) {
                                        console.error('Captcha token generation failed:', error);
                                        form.submit();
                                    }
                                }
                            }
                        }, true);
                    }
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

            // Livewire integration - reinitialize captcha after Livewire updates
            function reinitializeCaptcha() {
                if (window.SimpleCaptcha) {
                    setTimeout(() => {
                        window.SimpleCaptcha.initializeFields();
                        
                        // Also check for empty v3 fields and regenerate tokens
                        const v3Fields = document.querySelectorAll('[data-captcha-version="v3"]');
                        v3Fields.forEach((field, index) => {
                            if (!field.value || field.value.trim() === '') {
                                const action = field.dataset.captchaAction || 'default';
                                window.SimpleCaptcha.generateV3Token(field, action).catch(console.error);
                            }
                        });
                    }, 150);
                }
            }

            // Proper Livewire 3 hooks with correct signatures
            if (typeof Livewire !== 'undefined' && Livewire.hook) {
                // Component initialization hook
                Livewire.hook('component.init', ({
                    component,
                    cleanup
                }) => {
                    reinitializeCaptcha();
                });

                // Global morphed hook - fires after all DOM updates
                Livewire.hook('morphed', ({
                    el,
                    component
                }) => {
                    reinitializeCaptcha();
                });

                // Element-specific update hook (correct signature)
                Livewire.hook('morph.updated', ({
                    el,
                    component
                }) => {
                    // Check if this element or its children contain captcha fields
                    if (el && (el.querySelector('[data-captcha-version]') || el.dataset?.captchaVersion)) {
                        setTimeout(reinitializeCaptcha, 100);
                    }
                });

                // Hook to handle form submissions with v3 captcha
                Livewire.hook('commit', ({
                    component,
                    commit,
                    respond,
                    succeed,
                    fail
                }) => {
                    succeed(({
                        snapshot,
                        effect
                    }) => {
                        // Check if there are validation errors
                        if (snapshot?.memo?.errors && Object.keys(snapshot.memo.errors).length > 0) {
                            setTimeout(reinitializeCaptcha, 200);
                        }
                    });
                });
            }

            // Listen for custom captcha refresh events
            document.addEventListener('livewire:dispatch', (e) => {
                if (e.detail && e.detail.name === 'captcha-refresh') {
                    reinitializeCaptcha();
                }
            });

            // Direct window event listener for manual refresh
            window.addEventListener('captcha-refresh', () => {
                reinitializeCaptcha();
            });

            // Additional modal-specific initialization
            // Check for new captcha fields periodically (useful for modals)
            let modalCheckInterval = setInterval(() => {
                if (window.SimpleCaptcha) {
                    const v3Fields = document.querySelectorAll('[data-captcha-version="v3"]:not([data-captcha-initialized="true"])');
                    if (v3Fields.length > 0) {
                        v3Fields.forEach(field => {
                            window.SimpleCaptcha.initField(field);
                        });
                    }
                }
            }, 1000);

            // Stop checking after 30 seconds to avoid memory leaks
            setTimeout(() => {
                clearInterval(modalCheckInterval);
            }, 30000);
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
