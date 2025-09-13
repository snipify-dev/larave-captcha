/**
 * Simple reCAPTCHA Integration for Livewire
 * Minimal approach without complex event systems or auto-refresh
 */

class SimpleCaptcha {
    constructor() {
        this.siteKey = window.captchaConfig?.site_key || window.captchaSiteKey;
        this.version = window.captchaConfig?.version || 'v3';
        this.initialized = false;
        this.pendingFields = new Set();

        if (!this.siteKey) {
            return;
        }

        this.init();
    }

    init() {
        if (this.initialized) return;

        // Wait for reCAPTCHA to load
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
        fields.forEach(field => this.initField(field));
    }

    initField(field) {
        if (field.dataset.captchaInitialized === 'true') return;

        const action = field.dataset.captchaAction || field.dataset.action || 'default';
        const version = field.dataset.captchaVersion || this.version;

        field.dataset.captchaInitialized = 'true';

        if (version === 'v3') {
            this.generateV3Token(field, action);
        } else {
            this.renderV2Widget(field);
        }
    }

    generateV3Token(field, action) {
        if (this.pendingFields.has(field)) return;

        this.pendingFields.add(field);

        grecaptcha.ready(() => {
            grecaptcha.execute(this.siteKey, { action: action })
                .then(token => {
                    field.value = token;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    this.pendingFields.delete(field);
                })
                .catch(error => {
                    console.error('[SimpleCaptcha] Token generation failed:', error);
                    this.pendingFields.delete(field);
                });
        });
    }

    renderV2Widget(field) {
        // Create container for v2 widget
        let container = field.parentElement.querySelector('.recaptcha-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'recaptcha-container';
            field.parentElement.appendChild(container);
        }

        grecaptcha.ready(() => {
            grecaptcha.render(container, {
                sitekey: this.siteKey,
                callback: (token) => {
                    field.value = token;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                },
                'expired-callback': () => {
                    field.value = '';
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        });
    }

    observeNewFields() {
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === 1) {
                        // Check if the node itself is a captcha field
                        if (node.dataset && node.dataset.captchaVersion) {
                            this.initField(node);
                        }
                        // Check for captcha fields within the added node
                        const fields = node.querySelectorAll && node.querySelectorAll('[data-captcha-version]');
                        if (fields) {
                            fields.forEach(field => this.initField(field));
                        }
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Public methods for manual token refresh
    refresh(fieldOrSelector) {
        const field = typeof fieldOrSelector === 'string'
            ? document.querySelector(fieldOrSelector)
            : fieldOrSelector;

        if (!field) return;

        const action = field.dataset.captchaAction || field.dataset.action || 'default';
        const version = field.dataset.captchaVersion || this.version;

        if (version === 'v3') {
            this.generateV3Token(field, action);
        } else {
            grecaptcha.reset();
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.SimpleCaptcha = new SimpleCaptcha();
    });
} else {
    window.SimpleCaptcha = new SimpleCaptcha();
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SimpleCaptcha;
}