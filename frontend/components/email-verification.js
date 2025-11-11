/**
 * Email Verification Component
 * Handles email verification UI and workflow
 * Requires: apiClient in global scope
 */

class EmailVerificationComponent {
    constructor() {
        this.modalElement = null;
        this.isOpen = false;
        this.currentUserEmail = null;
        this.verificationTimeout = null;
    }

    /**
     * Initialize the email verification component
     * Injects modal HTML and sets up event listeners
     */
    async init(container = null) {
        try {
            // Fetch modal HTML
            const response = await fetch('./components/email-verification.html');
            const html = await response.text();

            // Create container if not provided
            if (!container) {
                container = document.createElement('div');
                container.id = 'email-verification-container';
                document.body.appendChild(container);
            }

            // Extract HTML only (remove <style> tags for later injection)
            const styleMatch = html.match(/<style>([\s\S]*?)<\/style>/);
            const styleContent = styleMatch ? styleMatch[1] : '';
            const modalHtml = html.replace(/<style>[\s\S]*?<\/style>/g, '');

            container.innerHTML = modalHtml;

            // Inject styles
            if (styleContent && !document.getElementById('email-verification-styles')) {
                const styleElement = document.createElement('style');
                styleElement.id = 'email-verification-styles';
                styleElement.textContent = styleContent;
                document.head.appendChild(styleElement);
            }

            this.modalElement = document.querySelector('.email-verification-modal');
            this.setupEventListeners();
            console.log('[EmailVerificationComponent] Initialized');
        } catch (error) {
            console.error('[EmailVerificationComponent] Init error:', error);
        }
    }

    /**
     * Setup event listeners for modal buttons
     */
    setupEventListeners() {
        if (!this.modalElement) return;

        // Close button
        const closeBtn = this.modalElement.querySelector('.email-verification-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.close());
        }

        // Verify token button
        const verifyBtn = this.modalElement.querySelector('#btn-verify-token');
        if (verifyBtn) {
            verifyBtn.addEventListener('click', () => this.verifyWithToken());
        }

        // Resend email button
        const resendBtn = this.modalElement.querySelector('#btn-resend-email');
        if (resendBtn) {
            resendBtn.addEventListener('click', () => this.resendEmail());
        }

        // Skip verification button
        const skipBtn = this.modalElement.querySelector('#btn-skip-verification');
        if (skipBtn) {
            skipBtn.addEventListener('click', () => this.close());
        }

        // Token input field
        const tokenInput = this.modalElement.querySelector('.verification-token-field');
        if (tokenInput) {
            tokenInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.verifyWithToken();
                }
            });
        }

        // Keyboard: ESC to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });
    }

    /**
     * Open the email verification modal
     * @param {string} userEmail - User email for context
     */
    async open(userEmail = null) {
        if (!this.modalElement) {
            await this.init();
        }

        this.currentUserEmail = userEmail;
        this.isOpen = true;
        
        // Show modal
        if (this.modalElement) {
            this.modalElement.style.display = 'flex';
        }

        // Prevent background scroll
        document.body.style.overflow = 'hidden';

        // Reset to initial state
        this.resetModal();

        console.log('[EmailVerificationComponent] Opened for:', userEmail);
    }

    /**
     * Close the email verification modal
     */
    close() {
        if (this.modalElement) {
            this.modalElement.style.display = 'none';
        }

        this.isOpen = false;
        document.body.style.overflow = '';

        // Clear any pending timeouts
        if (this.verificationTimeout) {
            clearTimeout(this.verificationTimeout);
            this.verificationTimeout = null;
        }

        console.log('[EmailVerificationComponent] Closed');
    }

    /**
     * Reset modal to initial state
     */
    resetModal() {
        if (!this.modalElement) return;

        // Clear token input
        const tokenInput = this.modalElement.querySelector('.verification-token-field');
        if (tokenInput) {
            tokenInput.value = '';
        }

        // Reset status
        const statusElement = this.modalElement.querySelector('#verification-status-text');
        if (statusElement) {
            statusElement.textContent = 'Ожидание подтверждения...';
        }

        // Remove error state
        this.modalElement.classList.remove('error');

        // Enable buttons
        const buttons = this.modalElement.querySelectorAll('button');
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.classList.remove('loading');
        });
    }

    /**
     * Verify email with token
     */
    async verifyWithToken() {
        if (!this.modalElement) return;

        const tokenInput = this.modalElement.querySelector('.verification-token-field');
        const token = tokenInput.value.trim();

        if (!token) {
            this.showError('Пожалуйста, введите код подтверждения');
            return;
        }

        // Disable button and show loading
        const verifyBtn = this.modalElement.querySelector('#btn-verify-token');
        verifyBtn.disabled = true;
        verifyBtn.classList.add('loading');

        try {
            // Wait for apiClient if not ready
            let apiClient = window.apiClient;
            if (!apiClient) {
                throw new Error('API client not initialized');
            }

            // Call API to verify email with token
            const response = await apiClient.verifyEmail(token);

            if (response.success || response.verified) {
                this.showSuccess('Email успешно подтвержден! ✓');
                
                // Close after 1.5 seconds
                this.verificationTimeout = setTimeout(() => this.close(), 1500);
            } else {
                this.showError(response.message || 'Ошибка при подтверждении email');
            }
        } catch (error) {
            console.error('[EmailVerificationComponent] Verification error:', error);
            this.showError(error.message || 'Ошибка при подтверждении email');
        } finally {
            verifyBtn.disabled = false;
            verifyBtn.classList.remove('loading');
        }
    }

    /**
     * Resend verification email
     */
    async resendEmail() {
        if (!this.modalElement) return;

        const resendBtn = this.modalElement.querySelector('#btn-resend-email');
        resendBtn.disabled = true;
        resendBtn.classList.add('loading');

        try {
            // Wait for apiClient if not ready
            let apiClient = window.apiClient;
            if (!apiClient) {
                throw new Error('API client not initialized');
            }

            const response = await apiClient.resendVerificationEmail();

            if (response.success || response.sent) {
                this.showSuccess('Письмо отправлено! Проверьте вашу почту.');
                
                // Re-enable after 3 seconds
                setTimeout(() => {
                    resendBtn.disabled = false;
                    resendBtn.classList.remove('loading');
                }, 3000);
            } else {
                this.showError(response.message || 'Ошибка при отправке письма');
                resendBtn.disabled = false;
                resendBtn.classList.remove('loading');
            }
        } catch (error) {
            console.error('[EmailVerificationComponent] Resend error:', error);
            this.showError(error.message || 'Ошибка при отправке письма');
            resendBtn.disabled = false;
            resendBtn.classList.remove('loading');
        }
    }

    /**
     * Show error message
     * @param {string} message - Error message to display
     */
    showError(message) {
        if (!this.modalElement) return;

        const statusElement = this.modalElement.querySelector('#verification-status-text');
        if (statusElement) {
            statusElement.textContent = message;
        }

        this.modalElement.classList.add('error');
        const statusContainer = this.modalElement.querySelector('.email-verification-status');
        if (statusContainer) {
            const icon = statusContainer.querySelector('.status-icon');
            if (icon) {
                icon.textContent = '✗';
            }
        }
    }

    /**
     * Show success message
     * @param {string} message - Success message to display
     */
    showSuccess(message) {
        if (!this.modalElement) return;

        const statusElement = this.modalElement.querySelector('#verification-status-text');
        if (statusElement) {
            statusElement.textContent = message;
        }

        this.modalElement.classList.remove('error');
        const statusContainer = this.modalElement.querySelector('.email-verification-status');
        if (statusContainer) {
            statusContainer.classList.add('verified');
            const icon = statusContainer.querySelector('.status-icon');
            if (icon) {
                icon.textContent = '✓';
            }
        }
    }

    /**
     * Check if email is verified and show modal if not
     * @param {boolean} autoShow - Auto-show modal if not verified
     */
    async checkVerificationStatus(autoShow = false) {
        try {
            // Wait for apiClient if not ready
            let apiClient = window.apiClient;
            if (!apiClient) {
                console.warn('[EmailVerificationComponent] API client not initialized yet');
                return null;
            }

            const status = await apiClient.getEmailVerificationStatus();
            
            if (!status.is_verified && autoShow) {
                await this.open(status.email);
            }

            return status;
        } catch (error) {
            console.error('[EmailVerificationComponent] Status check error:', error);
            return null;
        }
    }
}

// Create global instance and initialize
const emailVerificationComponent = new EmailVerificationComponent();

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        emailVerificationComponent.init().catch(err => {
            console.error('[EmailVerificationComponent] Init failed:', err);
        });
    });
} else {
    emailVerificationComponent.init().catch(err => {
        console.error('[EmailVerificationComponent] Init failed:', err);
    });
}

// Export for use
window.EmailVerificationComponent = emailVerificationComponent;
export default emailVerificationComponent;
