/**
 * CSRF Token Handler
 * Manages CSRF token lifecycle for security
 * 
 * CSRF tokens are stored in HttpOnly cookies by backend
 * This module extracts and manages the token for API requests
 */

class CsrfHandler {
    constructor() {
        this.tokenName = 'XSRF-TOKEN';
        this.headerName = 'X-CSRF-TOKEN';
        this.formFieldName = '_csrf';
    }

    /**
     * Get CSRF token from cookie
     * @returns {string|null} Token value or null if not found
     */
    getTokenFromCookie() {
        const name = this.tokenName + '=';
        const decodedCookie = decodeURIComponent(document.cookie);
        const cookieArray = decodedCookie.split(';');

        for (let cookie of cookieArray) {
            cookie = cookie.trim();
            if (cookie.indexOf(name) === 0) {
                return cookie.substring(name.length);
            }
        }

        return null;
    }

    /**
     * Get CSRF token (tries cookie first, then form field)
     * @returns {string|null} Token value or null if not found
     */
    getToken() {
        // First try cookie
        let token = this.getTokenFromCookie();
        if (token) {
            return token;
        }

        // Fallback: try form field
        const formField = document.querySelector(`input[name="${this.formFieldName}"]`);
        if (formField) {
            return formField.value;
        }

        return null;
    }

    /**
     * Add CSRF token to request headers
     * @param {Object} headers - Request headers object to modify
     * @returns {Object} Modified headers object
     */
    addTokenToHeaders(headers = {}) {
        const token = this.getToken();
        if (token) {
            headers[this.headerName] = token;
        }
        return headers;
    }

    /**
     * Add CSRF token to form data
     * @param {FormData} formData - FormData object to modify
     * @returns {FormData} Modified FormData object
     */
    addTokenToFormData(formData) {
        const token = this.getToken();
        if (token) {
            formData.append(this.formFieldName, token);
        }
        return formData;
    }

    /**
     * Create a hidden form field with CSRF token
     * Useful for HTML forms that don't use fetch API
     * @returns {HTMLInputElement} Hidden input field with CSRF token
     */
    createFormField() {
        const field = document.createElement('input');
        field.type = 'hidden';
        field.name = this.formFieldName;
        field.value = this.getToken() || '';
        return field;
    }

    /**
     * Refresh CSRF token (usually called after login)
     * Makes a request to backend to get a fresh token
     * @async
     * @returns {Promise<string|null>} New token or null if failed
     */
    async refreshToken() {
        try {
            const response = await fetch('/api/csrf-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                console.log('[CSRF] Token refreshed successfully');
                return this.getToken(); // Return newly set cookie
            } else {
                console.warn('[CSRF] Token refresh failed:', response.status);
                return null;
            }
        } catch (error) {
            console.error('[CSRF] Token refresh error:', error);
            return null;
        }
    }

    /**
     * Check if CSRF protection is needed for this request
     * @param {string} method - HTTP method (GET, POST, PUT, DELETE, etc.)
     * @returns {boolean} True if CSRF token should be included
     */
    isProtectedMethod(method) {
        const protectedMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];
        return protectedMethods.includes((method || '').toUpperCase());
    }

    /**
     * Log CSRF status (for debugging)
     */
    logStatus() {
        const token = this.getToken();
        const hasToken = !!token;
        const tokenPreview = token ? token.substring(0, 8) + '...' : 'none';

        console.log(`[CSRF] Status:`, {
            hasToken,
            tokenPreview,
            cookieToken: this.getTokenFromCookie() ? 'found' : 'not found'
        });
    }
}

// Create singleton instance
const csrfHandler = new CsrfHandler();

// Export for use in modules
export default csrfHandler;

// Also export to global scope for backward compatibility
if (typeof window !== 'undefined') {
    window.csrfHandler = csrfHandler;
}
