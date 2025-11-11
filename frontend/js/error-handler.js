/**
 * Global Error Handler for API Errors
 * Handles specific error types like rate limiting, account lockout, etc.
 */

class ErrorHandler {
    constructor() {
        this.errorCallbacks = {};
        this.retryQueues = new Map();
    }

    /**
     * Register callback for specific error code
     * @param {string} code - Error code (RATE_LIMITED, ACCOUNT_LOCKED, etc.)
     * @param {Function} callback - Callback function(error)
     */
    on(code, callback) {
        if (!this.errorCallbacks[code]) {
            this.errorCallbacks[code] = [];
        }
        this.errorCallbacks[code].push(callback);
    }

    /**
     * Handle error based on code and status
     * @param {Error} error - Error object
     * @param {string} endpoint - API endpoint
     * @param {Object} context - Additional context (e.g., Vue app, form)
     */
    handle(error, endpoint, context = {}) {
        // Log error
        console.error(`[ErrorHandler] ${error.code || error.status}: ${error.message}`, { endpoint, error });

        // Handle specific error codes
        if (error.code === 'RATE_LIMITED' || error.status === 429) {
            this._handleRateLimited(error, endpoint, context);
        } else if (error.code === 'ACCOUNT_LOCKED' || error.status === 403) {
            this._handleAccountLocked(error, endpoint, context);
        } else if (error.status === 401) {
            this._handleUnauthorized(error, endpoint, context);
        } else if (error.status === 400) {
            this._handleValidationError(error, endpoint, context);
        }

        // Call registered callbacks
        if (error.code && this.errorCallbacks[error.code]) {
            this.errorCallbacks[error.code].forEach(callback => {
                try {
                    callback(error, endpoint, context);
                } catch (e) {
                    console.error('[ErrorHandler] Callback failed:', e);
                }
            });
        }
    }

    /**
     * Handle rate limiting error (429)
     * @private
     */
    _handleRateLimited(error, endpoint, context) {
        const retryAfter = error.retryAfter || 60;
        console.warn(`⏱ Rate limited. Retry after ${retryAfter}s`);

        // Show notification if Vue app available
        if (context.app && context.app.$notify) {
            context.app.$notify({
                type: 'warning',
                message: `Too many requests. Please wait ${retryAfter} seconds before trying again.`,
                duration: (retryAfter + 3) * 1000
            });
        }

        // Disable button if available
        if (context.button) {
            context.button.disabled = true;
            context.button.textContent = `Try again in ${retryAfter}s`;

            // Re-enable after delay
            const countdown = setInterval(() => {
                retryAfter--;
                context.button.textContent = `Try again in ${retryAfter}s`;

                if (retryAfter <= 0) {
                    clearInterval(countdown);
                    context.button.disabled = false;
                    context.button.textContent = context.button.dataset.originalText || 'Submit';
                }
            }, 1000);
        }
    }

    /**
     * Handle account locked error (403)
     * @private
     */
    _handleAccountLocked(error, endpoint, context) {
        console.warn(`🔒 Account locked`);

        const unlockAt = error.details?.data?.unlock_at || error.unlock_at;
        const timeRemaining = error.details?.data?.time_remaining || error.time_remaining;

        // Show notification if Vue app available
        if (context.app && context.app.showNotification) {
            context.app.showNotification(`Аккаунт временно заблокирован. ${timeRemaining ? `Разблокировка через ${Math.ceil(timeRemaining / 60)} минут.` : 'Попробуйте позже.'}`, 'error');
        }

        // Show lockout modal
        if (window.AccountLockoutComponent) {
            window.AccountLockoutComponent.open(unlockAt, timeRemaining);
        } else if (context.showLockoutModal) {
            context.showLockoutModal(unlockAt, timeRemaining);
        }
    }

    /**
     * Handle unauthorized error (401)
     * @private
     */
    _handleUnauthorized(error, endpoint, context) {
        console.warn(`🔐 Unauthorized`);

        if (context.app && context.app.$notify) {
            context.app.$notify({
                type: 'error',
                message: 'Session expired. Please log in again.',
                duration: 5000
            });
        }

        // Redirect to login
        if (context.redirectToLogin) {
            context.redirectToLogin();
        } else {
            window.location.href = '/index.html?redirect=' + encodeURIComponent(window.location.pathname);
        }
    }

    /**
     * Handle validation error (400)
     * @private
     */
    _handleValidationError(error, endpoint, context) {
        console.warn(`⚠️ Validation error`);

        const errors = error.details?.errors || [error.message];

        if (context.app && context.app.$notify) {
            context.app.$notify({
                type: 'error',
                message: errors[0] || 'Invalid input. Please check your data.',
                duration: 5000
            });
        }

        // Show field-specific errors if available
        if (context.form && error.details?.errors) {
            Object.entries(error.details.errors).forEach(([field, message]) => {
                if (context.form[field]) {
                    context.form[field].error = message;
                }
            });
        }
    }

    /**
     * Queue request for retry after rate limit expires
     * @param {Function} requestFn - Function that makes the request
     * @param {number} retryAfter - Seconds to wait before retry
     * @param {string} queueId - Queue identifier
     */
    queueForRetry(requestFn, retryAfter, queueId = 'default') {
        if (!this.retryQueues.has(queueId)) {
            this.retryQueues.set(queueId, []);
        }

        this.retryQueues.get(queueId).push(requestFn);

        if (this.retryQueues.get(queueId).length === 1) {
            // First item in queue, schedule retry
            setTimeout(() => {
                const queue = this.retryQueues.get(queueId) || [];
                while (queue.length > 0) {
                    const fn = queue.shift();
                    try {
                        fn();
                    } catch (e) {
                        console.error('[ErrorHandler] Retry failed:', e);
                    }
                }
                this.retryQueues.delete(queueId);
            }, retryAfter * 1000);
        }
    }

    /**
     * Format error message for display
     * @param {Error} error - Error object
     * @returns {string} User-friendly error message
     */
    formatMessage(error) {
        if (error.code === 'RATE_LIMITED') {
            return `Too many requests. Try again in ${error.retryAfter || 60} seconds.`;
        }
        if (error.code === 'ACCOUNT_LOCKED') {
            return 'Your account has been locked. Please try again later.';
        }
        if (error.status === 401) {
            return 'Session expired. Please log in again.';
        }
        if (error.status === 403) {
            return 'You do not have permission to perform this action.';
        }
        if (error.status === 404) {
            return 'Resource not found.';
        }
        if (error.status === 500) {
            return 'Server error. Please try again later.';
        }
        return error.message || 'An error occurred. Please try again.';
    }
}

// Create singleton
const errorHandler = new ErrorHandler();

// Export
export default errorHandler;
if (typeof window !== 'undefined') {
    window.errorHandler = errorHandler;
}
