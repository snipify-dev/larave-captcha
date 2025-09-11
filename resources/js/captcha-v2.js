/**
 * reCAPTCHA v2 Manager
 * Handles checkbox and invisible reCAPTCHA v2 widgets
 */
class CaptchaV2Manager {
    constructor() {
        this.config = window.captchaConfig || {};
        this.widgets = new Map();
        this.initialized = false;
        
        this.init();
    }

    /**
     * Initialize the manager
     */
    init() {
        if (!window.captchaConfig?.site_key) {
            this.logError('No site key found for reCAPTCHA v2');
            return;
        }

        if (typeof grecaptcha === 'undefined') {
            this.logError('grecaptcha is not loaded');
            return;
        }

        this.log('Initializing reCAPTCHA v2 Manager');

        this.initialized = true;
        this.initializeAllFields();
        this.setupLivewireIntegration();
        this.log('reCAPTCHA v2 Manager initialized successfully');
    }

    /**
     * Initialize all captcha fields on the page
     */
    initializeAllFields() {
        const fields = document.querySelectorAll('.g-recaptcha:not([data-widget-id])');
        fields.forEach(field => this.initializeField(field.id));

        // Initialize from registered fields
        if (window.captchaV2Fields) {
            window.captchaV2Fields.forEach(fieldConfig => {
                this.initializeV2Field(fieldConfig.id, fieldConfig);
            });
            window.captchaV2Fields = [];
        }
    }

    /**
     * Initialize a specific field
     */
    initializeField(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field || field.dataset.widgetId) {
            return;
        }

        this.initializeV2Field(fieldId);
    }

    /**
     * Initialize v2 field with configuration
     */
    initializeV2Field(fieldId, fieldConfig = null) {
        const field = document.getElementById(fieldId);
        if (!field) {
            this.logError(`Field not found: ${fieldId}`);
            return;
        }

        if (field.dataset.widgetId) {
            this.log(`Field ${fieldId} already initialized`);
            return;
        }

        try {
            const config = fieldConfig?.config || this.getFieldConfig(field);
            
            // Set up callbacks
            const callbacks = this.setupCallbacks(fieldId, fieldConfig);
            Object.assign(config, callbacks);

            this.log(`Initializing v2 field: ${fieldId}`, config);

            const widgetId = grecaptcha.render(fieldId, config);
            
            field.dataset.widgetId = widgetId;
            this.widgets.set(fieldId, {
                widgetId,
                config,
                fieldConfig,
                initialized: true
            });

            this.log(`Field ${fieldId} initialized with widget ID: ${widgetId}`);

        } catch (error) {
            this.logError(`Failed to initialize field ${fieldId}:`, error);
        }
    }

    /**
     * Get field configuration from DOM attributes
     */
    getFieldConfig(field) {
        const config = {
            sitekey: field.dataset.sitekey || window.captchaConfig?.site_key,
            theme: field.dataset.theme || this.config.theme || 'light',
            size: field.dataset.size || this.config.size || 'normal',
            type: field.dataset.type || this.config.type || 'image',
            tabindex: parseInt(field.dataset.tabindex) || this.config.tabindex || 0
        };

        // Handle invisible mode
        if (field.dataset.size === 'invisible' || this.config.invisible) {
            config.size = 'invisible';
        }

        return config;
    }

    /**
     * Setup callback functions for a field
     */
    setupCallbacks(fieldId, fieldConfig) {
        const cleanFieldId = fieldId.replace(/[^a-zA-Z0-9]/g, '_');
        const isInvisible = fieldConfig?.invisible || false;

        return {
            callback: (token) => {
                this.onCallback(fieldId, token);
                
                // Call custom callback if exists
                const customCallback = window[`captcha_callback_${cleanFieldId}`];
                if (typeof customCallback === 'function') {
                    customCallback(token);
                }
            },
            'expired-callback': () => {
                this.onExpired(fieldId);
                
                // Call custom expired callback if exists
                const customExpired = window[`captcha_expired_${cleanFieldId}`];
                if (typeof customExpired === 'function') {
                    customExpired();
                }
            },
            'error-callback': () => {
                this.onError(fieldId);
                
                // Call custom error callback if exists
                const customError = window[`captcha_error_${cleanFieldId}`];
                if (typeof customError === 'function') {
                    customError();
                }
            }
        };
    }

    /**
     * Handle successful captcha completion
     */
    onCallback(fieldId, token) {
        this.log(`Captcha completed for field: ${fieldId}`);
        
        const responseFieldId = fieldId + '_response';
        const responseField = document.getElementById(responseFieldId);
        
        if (responseField) {
            responseField.value = token;
            this.dispatchFieldEvents(responseField);
        }

        // Store token info
        const widgetInfo = this.widgets.get(fieldId);
        if (widgetInfo) {
            widgetInfo.token = token;
            widgetInfo.timestamp = Date.now();
        }

        // Dispatch custom event
        document.dispatchEvent(new CustomEvent('captcha:completed', {
            detail: { fieldId, token }
        }));
    }

    /**
     * Handle captcha expiration
     */
    onExpired(fieldId) {
        this.log(`Captcha expired for field: ${fieldId}`);
        
        const responseFieldId = fieldId + '_response';
        const responseField = document.getElementById(responseFieldId);
        
        if (responseField) {
            responseField.value = '';
            this.dispatchFieldEvents(responseField);
        }

        // Clear token info
        const widgetInfo = this.widgets.get(fieldId);
        if (widgetInfo) {
            widgetInfo.token = null;
            widgetInfo.timestamp = null;
        }

        // Dispatch custom event
        document.dispatchEvent(new CustomEvent('captcha:expired', {
            detail: { fieldId }
        }));
    }

    /**
     * Handle captcha error
     */
    onError(fieldId) {
        this.logError(`Captcha error for field: ${fieldId}`);
        
        const responseFieldId = fieldId + '_response';
        const responseField = document.getElementById(responseFieldId);
        
        if (responseField) {
            responseField.value = '';
            this.dispatchFieldEvents(responseField);
        }

        // Dispatch custom event
        document.dispatchEvent(new CustomEvent('captcha:error', {
            detail: { fieldId }
        }));
    }

    /**
     * Reset a specific captcha
     */
    reset(fieldId) {
        const widgetInfo = this.widgets.get(fieldId);
        if (widgetInfo && typeof grecaptcha !== 'undefined') {
            try {
                grecaptcha.reset(widgetInfo.widgetId);
                this.log(`Reset captcha for field: ${fieldId}`);
                
                // Clear response field
                const responseFieldId = fieldId + '_response';
                const responseField = document.getElementById(responseFieldId);
                if (responseField) {
                    responseField.value = '';
                    this.dispatchFieldEvents(responseField);
                }
                
            } catch (error) {
                this.logError(`Failed to reset captcha for field ${fieldId}:`, error);
            }
        }
    }

    /**
     * Execute invisible captcha
     */
    execute(fieldId) {
        const widgetInfo = this.widgets.get(fieldId);
        if (widgetInfo && typeof grecaptcha !== 'undefined') {
            try {
                grecaptcha.execute(widgetInfo.widgetId);
                this.log(`Executed invisible captcha for field: ${fieldId}`);
            } catch (error) {
                this.logError(`Failed to execute captcha for field ${fieldId}:`, error);
            }
        }
    }

    /**
     * Get response for a field
     */
    getResponse(fieldId) {
        const widgetInfo = this.widgets.get(fieldId);
        if (widgetInfo && typeof grecaptcha !== 'undefined') {
            try {
                return grecaptcha.getResponse(widgetInfo.widgetId);
            } catch (error) {
                this.logError(`Failed to get response for field ${fieldId}:`, error);
                return '';
            }
        }
        return '';
    }

    /**
     * Reset all captchas
     */
    resetAll() {
        this.widgets.forEach((widgetInfo, fieldId) => {
            this.reset(fieldId);
        });
    }

    /**
     * Setup Livewire integration
     */
    setupLivewireIntegration() {
        if (!window.Livewire) {
            return;
        }

        this.log('Setting up Livewire integration');

        // Listen for custom refresh events
        const refreshEvents = this.config.refresh_events || ['captcha:refresh', 'captcha:renew'];
        refreshEvents.forEach(eventName => {
            Livewire.on(eventName, () => {
                this.log(`Received ${eventName} event`);
                this.resetAll();
            });
        });

        // Initialize new fields after Livewire morphing
        Livewire.hook('element.updated', (el, component) => {
            const newFields = el.querySelectorAll('.g-recaptcha:not([data-widget-id])');
            newFields.forEach(field => this.initializeField(field.id));
        });

        this.log('Livewire integration setup complete');
    }

    /**
     * Dispatch events for field updates
     */
    dispatchFieldEvents(field) {
        // Standard input event for validation
        field.dispatchEvent(new Event('input', { bubbles: true }));

        // Change event for Livewire
        if (window.Livewire) {
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    /**
     * Get widget info for field
     */
    getWidgetInfo(fieldId) {
        return this.widgets.get(fieldId);
    }

    /**
     * Get all field statistics
     */
    getStats() {
        return {
            initialized: this.initialized,
            widgetsCount: this.widgets.size,
            config: this.config
        };
    }

    /**
     * Log message if debug is enabled
     */
    log(message, ...args) {
        if (this.config.debug || window.captchaConfig?.debug) {
            console.log('[reCAPTCHA v2]', message, ...args);
        }
    }

    /**
     * Log error message
     */
    logError(message, ...args) {
        console.error('[reCAPTCHA v2]', message, ...args);
    }

    /**
     * Destroy the manager
     */
    destroy() {
        this.widgets.clear();
        this.initialized = false;
        this.log('Manager destroyed');
    }
}

// Global initialization
window.CaptchaV2Manager = CaptchaV2Manager;

// Auto-initialize if DOM is ready and config is available
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        if (window.captchaConfig?.enabled && window.captchaConfig?.version === 'v2') {
            if (typeof grecaptcha !== 'undefined') {
                window.captchaManager = new CaptchaV2Manager();
            }
        }
    });
} else {
    // DOM already loaded
    if (window.captchaConfig?.enabled && window.captchaConfig?.version === 'v2') {
        if (typeof grecaptcha !== 'undefined') {
            window.captchaManager = new CaptchaV2Manager();
        }
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CaptchaV2Manager;
}