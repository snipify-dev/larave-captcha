/**
 * reCAPTCHA v3 Manager
 * Handles token generation, auto-refresh, and Livewire integration
 */
class CaptchaV3Manager {
    constructor() {
        this.siteKey = window.captchaSiteKey || window.captchaConfig?.site_key;
        this.config = window.captchaConfig || {};
        this.tokens = new Map();
        this.refreshInterval = (this.config.refresh_interval || 110) * 1000; // Convert to milliseconds
        this.initialized = false;
        
        this.init();
    }

    /**
     * Initialize the manager
     */
    init() {
        if (!this.siteKey) {
            this.logError('No site key found for reCAPTCHA v3');
            return;
        }

        if (typeof grecaptcha === 'undefined') {
            this.logError('grecaptcha is not loaded');
            return;
        }

        this.log('Initializing reCAPTCHA v3 Manager');

        grecaptcha.ready(() => {
            this.initialized = true;
            this.initializeAllFields();
            this.setupLivewireIntegration();
            this.startAutoRefresh();
            this.log('reCAPTCHA v3 Manager initialized successfully');
        });
    }

    /**
     * Initialize all captcha fields on the page
     */
    initializeAllFields() {
        const fields = document.querySelectorAll('.captcha-v3-field');
        fields.forEach(field => this.initializeField(field));

        // Initialize pending fields if any
        if (window.captchaPendingFields) {
            window.captchaPendingFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    this.initializeField(field);
                }
            });
            window.captchaPendingFields = [];
        }
    }

    /**
     * Initialize a specific field
     */
    initializeField(field) {
        if (!field || field.dataset.initialized) {
            return;
        }

        const action = field.dataset.action || 'default';
        const fieldId = field.id || 'captcha-' + Math.random().toString(36).substr(2, 9);
        
        if (!field.id) {
            field.id = fieldId;
        }

        this.log(`Initializing field: ${fieldId} for action: ${action}`);

        this.generateToken(action, field)
            .then(() => {
                field.dataset.initialized = 'true';
                this.log(`Field ${fieldId} initialized successfully`);
            })
            .catch(error => {
                this.logError(`Failed to initialize field ${fieldId}:`, error);
            });
    }

    /**
     * Generate token for specific action and field
     */
    async generateToken(action, field) {
        if (!this.initialized) {
            throw new Error('reCAPTCHA v3 Manager not initialized');
        }

        try {
            this.log(`Generating token for action: ${action}`);
            const token = await grecaptcha.execute(this.siteKey, { action });
            
            if (field) {
                field.value = token;
                this.tokens.set(field.id, {
                    token,
                    timestamp: Date.now(),
                    action
                });

                // Dispatch events for form validation and Livewire
                this.dispatchFieldEvents(field);
            }

            this.log(`Token generated successfully for action: ${action}`);
            return token;

        } catch (error) {
            this.logError('Failed to generate token:', error);
            throw error;
        }
    }

    /**
     * Refresh all tokens
     */
    async refreshAllTokens() {
        if (!this.initialized) {
            return;
        }

        this.log('Refreshing all tokens');

        const fields = document.querySelectorAll('.captcha-v3-field[data-initialized="true"]');
        const promises = Array.from(fields).map(field => {
            const action = field.dataset.action || 'default';
            return this.generateToken(action, field).catch(error => {
                this.logError(`Failed to refresh token for field ${field.id}:`, error);
            });
        });

        try {
            await Promise.all(promises);
            this.log('All tokens refreshed successfully');
        } catch (error) {
            this.logError('Some tokens failed to refresh:', error);
        }
    }

    /**
     * Refresh token for specific field
     */
    async refreshFieldToken(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) {
            this.logError(`Field not found: ${fieldId}`);
            return;
        }

        const action = field.dataset.action || 'default';
        await this.generateToken(action, field);
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
                this.refreshAllTokens();
            });
        });

        // Refresh tokens after Livewire requests
        Livewire.hook('message.sent', (message, component) => {
            setTimeout(() => {
                this.refreshAllTokens();
            }, 1000);
        });

        // Initialize new fields after Livewire morphing
        Livewire.hook('element.updated', (el, component) => {
            const newFields = el.querySelectorAll('.captcha-v3-field:not([data-initialized])');
            newFields.forEach(field => this.initializeField(field));
        });

        this.log('Livewire integration setup complete');
    }

    /**
     * Start auto-refresh timer
     */
    startAutoRefresh() {
        if (!this.config.auto_refresh) {
            return;
        }

        this.log(`Starting auto-refresh every ${this.refreshInterval / 1000} seconds`);

        setInterval(() => {
            this.refreshAllTokens();
        }, this.refreshInterval);
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

        // Custom captcha event
        field.dispatchEvent(new CustomEvent('captcha:token-updated', {
            bubbles: true,
            detail: {
                fieldId: field.id,
                action: field.dataset.action,
                timestamp: Date.now()
            }
        }));
    }

    /**
     * Get token info for field
     */
    getTokenInfo(fieldId) {
        return this.tokens.get(fieldId);
    }

    /**
     * Check if token is expired
     */
    isTokenExpired(fieldId, maxAge = 120000) { // 2 minutes default
        const tokenInfo = this.tokens.get(fieldId);
        if (!tokenInfo) {
            return true;
        }
        return (Date.now() - tokenInfo.timestamp) > maxAge;
    }

    /**
     * Get all field statistics
     */
    getStats() {
        return {
            initialized: this.initialized,
            fieldsCount: document.querySelectorAll('.captcha-v3-field').length,
            tokensCount: this.tokens.size,
            config: this.config
        };
    }

    /**
     * Log message if debug is enabled
     */
    log(message, ...args) {
        if (this.config.debug || window.captchaConfig?.debug) {
            console.log('[reCAPTCHA v3]', message, ...args);
        }
    }

    /**
     * Log error message
     */
    logError(message, ...args) {
        console.error('[reCAPTCHA v3]', message, ...args);
    }

    /**
     * Destroy the manager
     */
    destroy() {
        this.tokens.clear();
        this.initialized = false;
        this.log('Manager destroyed');
    }
}

// Global initialization
window.CaptchaV3Manager = CaptchaV3Manager;

// Auto-initialize if DOM is ready and config is available
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        if (window.captchaConfig?.enabled && window.captchaConfig?.version === 'v3') {
            if (typeof grecaptcha !== 'undefined') {
                window.captchaManager = new CaptchaV3Manager();
            }
        }
    });
} else {
    // DOM already loaded
    if (window.captchaConfig?.enabled && window.captchaConfig?.version === 'v3') {
        if (typeof grecaptcha !== 'undefined') {
            window.captchaManager = new CaptchaV3Manager();
        }
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CaptchaV3Manager;
}