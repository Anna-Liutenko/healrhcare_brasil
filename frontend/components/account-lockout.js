/**
 * Account Lockout Component
 * Displays account lockout modal with countdown timer
 */

class AccountLockoutComponent {
    constructor() {
        this.modalElement = null;
        this.isOpen = false;
        this.unlockAt = null;
        this.countdownInterval = null;
    }

    /**
     * Initialize the account lockout component
     * Injects modal HTML and sets up event listeners
     */
    async init(container = null) {
        try {
            // Fetch modal HTML
            const response = await fetch('./components/account-lockout.html');
            const html = await response.text();

            // Create container if not provided
            if (!container) {
                container = document.createElement('div');
                container.id = 'account-lockout-container';
                document.body.appendChild(container);
            }

            // Extract HTML and styles
            const styleMatch = html.match(/<style>([\s\S]*?)<\/style>/);
            const styleContent = styleMatch ? styleMatch[1] : '';
            const modalHtml = html.replace(/<style>[\s\S]*?<\/style>/g, '');

            container.innerHTML = modalHtml;

            // Inject styles
            if (styleContent && !document.getElementById('account-lockout-styles')) {
                const styleElement = document.createElement('style');
                styleElement.id = 'account-lockout-styles';
                styleElement.textContent = styleContent;
                document.head.appendChild(styleElement);
            }

            this.modalElement = document.querySelector('.account-lockout-modal');
            this.setupEventListeners();
            console.log('[AccountLockoutComponent] Initialized');
        } catch (error) {
            console.error('[AccountLockoutComponent] Init error:', error);
        }
    }

    /**
     * Setup event listeners for modal buttons
     */
    setupEventListeners() {
        if (!this.modalElement) return;

        // Understand button
        const understandBtn = this.modalElement.querySelector('#btn-understand');
        if (understandBtn) {
            understandBtn.addEventListener('click', () => this.close());
        }

        // Prevent closing by clicking background
        this.modalElement.addEventListener('click', (e) => {
            if (e.target === this.modalElement) {
                // Don't close - user must wait
                this.shake();
            }
        });

        // Prevent ESC key closing
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                e.preventDefault();
                this.shake();
            }
        });
    }

    /**
     * Open the lockout modal
     * @param {string|Date} unlockAt - When account will be unlocked
     * @param {number} timeRemaining - Seconds remaining (optional)
     */
    async open(unlockAt, timeRemaining = null) {
        if (!this.modalElement) {
            await this.init();
        }

        // Calculate unlock time
        if (timeRemaining) {
            this.unlockAt = new Date(Date.now() + timeRemaining * 1000);
        } else if (unlockAt) {
            this.unlockAt = new Date(unlockAt);
        } else {
            this.unlockAt = new Date(Date.now() + 15 * 60 * 1000); // Default 15 minutes
        }

        this.isOpen = true;
        
        // Show modal
        if (this.modalElement) {
            this.modalElement.classList.add('active');
        }

        // Prevent background scroll
        document.body.style.overflow = 'hidden';

        // Start countdown
        this.startCountdown();

        console.log('[AccountLockoutComponent] Opened, unlock at:', this.unlockAt);
    }

    /**
     * Close the lockout modal
     */
    close() {
        if (this.modalElement) {
            this.modalElement.classList.remove('active');
        }

        this.isOpen = false;
        document.body.style.overflow = '';

        // Clear countdown
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }

        console.log('[AccountLockoutComponent] Closed');
    }

    /**
     * Start countdown timer
     */
    startCountdown() {
        // Clear existing interval
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }

        const timerElement = this.modalElement?.querySelector('#lockout-timer');
        if (!timerElement) return;

        const updateTimer = () => {
            const now = new Date();
            const diff = this.unlockAt - now;

            if (diff <= 0) {
                // Unlock time reached
                timerElement.textContent = '00:00';
                clearInterval(this.countdownInterval);
                this.countdownInterval = null;
                
                // Auto-close and reload
                setTimeout(() => {
                    this.close();
                    window.location.reload();
                }, 1000);
                return;
            }

            // Calculate minutes and seconds
            const totalSeconds = Math.floor(diff / 1000);
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;

            // Format as MM:SS
            const formatted = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            timerElement.textContent = formatted;
        };

        // Update immediately and every second
        updateTimer();
        this.countdownInterval = setInterval(updateTimer, 1000);
    }

    /**
     * Shake animation when user tries to close
     */
    shake() {
        if (!this.modalElement) return;

        const card = this.modalElement.querySelector('.lockout-card');
        if (card) {
            card.style.animation = 'none';
            setTimeout(() => {
                card.style.animation = 'lockoutShake 0.4s ease-in-out';
            }, 10);
        }
    }

    /**
     * Check if modal is currently open
     * @returns {boolean}
     */
    isLocked() {
        return this.isOpen;
    }

    /**
     * Get remaining time in seconds
     * @returns {number}
     */
    getRemainingTime() {
        if (!this.unlockAt) return 0;
        const diff = this.unlockAt - new Date();
        return Math.max(0, Math.floor(diff / 1000));
    }
}

// Create global instance and initialize
const accountLockoutComponent = new AccountLockoutComponent();

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        accountLockoutComponent.init().catch(err => {
            console.error('[AccountLockoutComponent] Init failed:', err);
        });
    });
} else {
    accountLockoutComponent.init().catch(err => {
        console.error('[AccountLockoutComponent] Init failed:', err);
    });
}

// Export for use
window.AccountLockoutComponent = accountLockoutComponent;
export default accountLockoutComponent;
