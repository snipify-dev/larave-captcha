/**
 * reCAPTCHA Integration for Laravel with Livewire Support
 * Simplified implementation that works seamlessly with both v2 and v3
 */

class RecaptchaManager {
    constructor(config = {}) {
        this.config = {
            version: window.recaptchaConfig?.version || config.version || 'v3',
            siteKey: window.recaptchaConfig?.site_key || config.siteKey,
            enabled: window.recaptchaConfig?.enabled !== false,
            debug: config.debug || false,
            ...config
        };

        this.tokens = new Map();
        this.widgets = new Map();
        this.initialized = false;
        this.livewireIntegrated = false;

        if (!this.config.enabled || !this.config.siteKey) {
            if (this.config.debug) {
                console.warn('[reCAPTCHA] Disabled or missing site key');
            }
            return;
        }

        this.init();
    }

    async init() {
        if (this.initialized) return;

        try {
            // Wait for reCAPTCHA API to load
            await this.waitForRecaptcha();
            
            // Initialize existing fields
            this.initializeFields();
            
            // Set up Livewire integration if available
            this.setupLivewireIntegration();
            
            // Watch for new fields
            this.observeNewFields();
            
            this.initialized = true;
        } catch (error) {
            console.error('[reCAPTCHA] Initialization failed:', error);
        }
    }

    waitForRecaptcha() {
        return new Promise((resolve, reject) => {
            if (typeof grecaptcha !== 'undefined' && grecaptcha.ready) {
                grecaptcha.ready(resolve);
                return;
            }

            let attempts = 0;
            const checkInterval = setInterval(() => {
                attempts++;
                
                if (typeof grecaptcha !== 'undefined' && grecaptcha.ready) {
                    clearInterval(checkInterval);
                    grecaptcha.ready(resolve);
                } else if (attempts > 50) { // 5 seconds
                    clearInterval(checkInterval);
                    reject(new Error('reCAPTCHA failed to load'));
                }
            }, 100);
        });
    }

    initializeFields() {
        // Find all reCAPTCHA fields
        const fields = document.querySelectorAll('[data-recaptcha], .recaptcha-field, [wire\\:model*="captcha"], [wire\\:model*="recaptcha"]');
        fields.forEach(field => this.initializeField(field));
    }

    initializeField(field) {
        if (field.dataset.recaptchaInitialized === 'true') return;

        const config = this.getFieldConfig(field);
        field.dataset.recaptchaInitialized = 'true';


        if (config.version === 'v3') {
            this.initializeV3Field(field, config);
        } else {
            this.initializeV2Field(field, config);
        }
    }

    getFieldConfig(field) {
        return {
            action: field.dataset.recaptchaAction || field.dataset.action || 'default',
            version: field.dataset.recaptchaVersion || this.config.version,
            threshold: parseFloat(field.dataset.recaptchaThreshold || '0.5'),
            isLivewire: this.isLivewireField(field),
            componentId: this.getLivewireComponentId(field),
            wireModel: field.getAttribute('wire:model') || field.getAttribute('wire:model.defer') || field.getAttribute('wire:model.live')
        };
    }

    isLivewireField(field) {
        return field.hasAttribute('wire:model') || 
               field.hasAttribute('wire:model.defer') || 
               field.hasAttribute('wire:model.live') ||
               field.closest('[wire\\:id]') !== null;
    }

    getLivewireComponentId(field) {
        const wireIdElement = field.closest('[wire\\:id]');
        return wireIdElement ? wireIdElement.getAttribute('wire:id') : null;
    }

    initializeV3Field(field, config) {
        // Generate initial token
        this.generateV3Token(field, config.action);

        // Set up auto-refresh (tokens expire after 2 minutes)
        setInterval(() => {
            this.generateV3Token(field, config.action);
        }, 110000); // Refresh every 110 seconds
    }

    generateV3Token(field, action) {
        if (!grecaptcha || !grecaptcha.execute) {
            return;
        }

        grecaptcha.execute(this.config.siteKey, { action })
            .then(token => {
                this.setFieldValue(field, token);
                this.tokens.set(field, {
                    token,
                    timestamp: Date.now(),
                    action
                });
            })
            .catch(error => {
                console.error('[reCAPTCHA] Token generation failed:', error);
            });
    }

    initializeV2Field(field, config) {
        // Create widget container
        let container = field.nextElementSibling;
        if (!container || !container.classList.contains('recaptcha-widget')) {
            container = document.createElement('div');
            container.className = 'recaptcha-widget';
            field.parentNode.insertBefore(container, field.nextSibling);
        }

        // Render widget
        const widgetId = grecaptcha.render(container, {
            sitekey: this.config.siteKey,
            callback: (token) => this.handleV2Response(field, token),
            'expired-callback': () => this.handleV2Expired(field),
            'error-callback': () => this.handleV2Error(field),
            theme: this.config.widget?.theme || 'light',
            size: this.config.widget?.size || 'normal'
        });

        this.widgets.set(field, widgetId);
    }

    handleV2Response(field, token) {
        this.setFieldValue(field, token);
        this.tokens.set(field, {
            token,
            timestamp: Date.now()
        });
    }

    handleV2Expired(field) {
        this.setFieldValue(field, '');
        this.tokens.delete(field);
    }

    handleV2Error(field) {
        this.setFieldValue(field, '');
        this.tokens.delete(field);
        console.error('[reCAPTCHA] v2 widget error');
    }

    setFieldValue(field, value) {
        const oldValue = field.value;
        field.value = value;

        // Trigger events for form validation and Livewire
        if (oldValue !== value) {
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    setupLivewireIntegration() {
        if (!window.Livewire || this.livewireIntegrated) return;


        // Hook into Livewire's commit lifecycle for v3
        if (this.config.version === 'v3') {
            Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
                this.handleLivewireCommit(component, commit, respond, succeed, fail);
            });
        }

        // Listen for Livewire component updates
        Livewire.hook('message.processed', (message, component) => {
            // Re-initialize fields in updated components
            setTimeout(() => {
                const container = document.querySelector(`[wire\\:id="${component.id}"]`);
                if (container) {
                    const fields = container.querySelectorAll('[data-recaptcha], [wire\\:model*="captcha"], [wire\\:model*="recaptcha"]');
                    fields.forEach(field => {
                        if (field.dataset.recaptchaInitialized !== 'true') {
                            this.initializeField(field);
                        }
                    });
                }
            }, 100);
        });

        this.livewireIntegrated = true;
    }

    handleLivewireCommit(component, commit, respond, succeed, fail) {
        // Only handle form submissions (methods that likely submit forms)
        const submissionMethods = ['submit', 'save', 'store', 'update', 'create', 'send'];
        const hasSubmission = commit.payload?.calls?.some(call => 
            submissionMethods.some(method => call.method?.toLowerCase().includes(method))
        );

        if (!hasSubmission) {
            return respond(succeed);
        }

        // Find reCAPTCHA fields in this component
        const container = document.querySelector(`[wire\\:id="${component.id}"]`);
        if (!container) {
            return respond(succeed);
        }

        const recaptchaFields = container.querySelectorAll('[wire\\:model*="captcha"], [wire\\:model*="recaptcha"]');
        if (recaptchaFields.length === 0) {
            return respond(succeed);
        }


        // Generate fresh v3 tokens before submission
        if (this.config.version === 'v3') {
            const promises = Array.from(recaptchaFields).map(field => {
                const config = this.getFieldConfig(field);
                return this.generateV3TokenPromise(field, config.action);
            });

            Promise.all(promises)
                .then(() => {
                    respond(succeed);
                })
                .catch(error => {
                    console.error('[reCAPTCHA] Token generation failed for Livewire:', error);
                    respond(succeed); // Continue anyway to avoid blocking the form
                });
        } else {
            respond(succeed);
        }
    }

    generateV3TokenPromise(field, action) {
        return new Promise((resolve, reject) => {
            grecaptcha.execute(this.config.siteKey, { action })
                .then(token => {
                    this.setFieldValue(field, token);
                    this.tokens.set(field, {
                        token,
                        timestamp: Date.now(),
                        action
                    });
                    resolve(token);
                })
                .catch(reject);
        });
    }

    observeNewFields() {
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === 1) { // Element node
                        // Check if the node itself is a reCAPTCHA field
                        if (this.isRecaptchaField(node)) {
                            this.initializeField(node);
                        }
                        
                        // Check for reCAPTCHA fields within the added node
                        const fields = node.querySelectorAll ? 
                            node.querySelectorAll('[data-recaptcha], .recaptcha-field, [wire\\:model*="captcha"], [wire\\:model*="recaptcha"]') : 
                            [];
                        fields.forEach(field => this.initializeField(field));
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    isRecaptchaField(element) {
        return element.hasAttribute?.('data-recaptcha') ||
               element.classList?.contains('recaptcha-field') ||
               element.hasAttribute?.('wire:model') && 
               (element.getAttribute('wire:model').includes('captcha') || 
                element.getAttribute('wire:model').includes('recaptcha'));
    }

    // Public API methods
    refresh(fieldSelector = null) {
        if (fieldSelector) {
            const field = typeof fieldSelector === 'string' ? 
                document.querySelector(fieldSelector) : fieldSelector;
            if (field) {
                const config = this.getFieldConfig(field);
                if (config.version === 'v3') {
                    this.generateV3Token(field, config.action);
                } else {
                    const widgetId = this.widgets.get(field);
                    if (widgetId !== undefined) {
                        grecaptcha.reset(widgetId);
                    }
                }
            }
        } else {
            // Refresh all fields
            this.tokens.clear();
            this.initializeFields();
        }
    }

    getToken(field) {
        const tokenData = this.tokens.get(field);
        return tokenData?.token;
    }

    isTokenValid(field) {
        const tokenData = this.tokens.get(field);
        if (!tokenData) return false;

        // Tokens are valid for 2 minutes
        const age = Date.now() - tokenData.timestamp;
        return age < 120000;
    }


    destroy() {
        this.tokens.clear();
        this.widgets.clear();
        this.initialized = false;
        this.livewireIntegrated = false;
    }
}

// Auto-initialize when DOM is ready
function initializeRecaptcha() {
    if (window.RecaptchaManager) return; // Already initialized

    window.RecaptchaManager = new RecaptchaManager(window.recaptchaConfig || {});
}

// Initialize immediately if DOM is ready, otherwise wait
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeRecaptcha);
} else {
    initializeRecaptcha();
}

// Re-initialize after Livewire navigates (if using wire:navigate)
document.addEventListener('livewire:navigated', initializeRecaptcha);

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RecaptchaManager;
}