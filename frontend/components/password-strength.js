/**
 * Password Strength Indicator Component
 * Displays real-time password strength feedback
 */

import { apiClient } from '../api-client.js';

export default class PasswordStrengthIndicator {
    constructor(containerSelector) {
        this.container = document.querySelector(containerSelector);
        if (!this.container) {
            console.warn(`PasswordStrengthIndicator: Container not found: ${containerSelector}`);
            return;
        }

        this.scoreElement = this.container.querySelector('.password-strength-score');
        this.barElement = this.container.querySelector('.password-strength-bar');
        this.requirementElements = this.container.querySelectorAll('.password-requirement');
        this.messageElement = this.container.querySelector('.password-strength-message');

        this.strengthLevels = {
            0: { label: 'Very Weak', class: 'very-weak', score: 0 },
            1: { label: 'Weak', class: 'weak', score: 40 },
            2: { label: 'Fair', class: 'fair', score: 60 },
            3: { label: 'Strong', class: 'strong', score: 80 },
            4: { label: 'Very Strong', class: 'very-strong', score: 100 }
        };

        this.currentStrength = 0;
        this.currentScore = 0;
    }

    /**
     * Update password strength display
     * @param {string} password - Password to check
     * @async
     */
    async update(password) {
        if (!password) {
            this.reset();
            return;
        }

        try {
            // Call backend API for validation
            const response = await apiClient.checkPasswordRequirements(password);

            if (response.success && response.data) {
                this.display(response.data);
            }
        } catch (error) {
            console.error('Password strength check failed:', error);
            // Show error but don't prevent user from continuing
            this.showMessage('Unable to check password requirements', 'error');
        }
    }

    /**
     * Display password strength based on API response
     * @param {Object} data - Response data from API
     */
    display(data) {
        const { requirements, strength_level, strength_score } = data;

        if (!requirements || !Array.isArray(requirements)) {
            return;
        }

        // Update requirement checkmarks
        requirements.forEach(req => {
            const element = this.container.querySelector(
                `.password-requirement[data-requirement="${req.requirement}"]`
            );

            if (element) {
                if (req.met) {
                    element.classList.remove('unmet');
                    element.classList.add('met');
                } else {
                    element.classList.remove('met');
                    element.classList.add('unmet');
                }
            }
        });

        // Update strength level
        this._updateStrengthDisplay(strength_level, strength_score);

        // Show success message if all requirements met
        const allMet = requirements.every(r => r.met);
        if (allMet) {
            this.showMessage('✓ Password meets all requirements', 'success');
        } else {
            const unmet = requirements.filter(r => !r.met);
            if (unmet.length > 0) {
                const unmetText = unmet.map(r => r.description).join(', ');
                this.showMessage(`Missing: ${unmetText}`, 'warning');
            }
        }
    }

    /**
     * Update strength level display
     * @private
     */
    _updateStrengthDisplay(strengthLevel, score) {
        // Map strength level string to level number
        const levelMap = {
            'very-weak': 0,
            'weak': 1,
            'fair': 2,
            'strong': 3,
            'very-strong': 4
        };

        const level = levelMap[strengthLevel] ?? 0;
        const strengthInfo = this.strengthLevels[level];

        // Update score element
        if (this.scoreElement) {
            this.scoreElement.textContent = strengthInfo.label;
            this.scoreElement.className = `password-strength-score ${strengthInfo.class}`;
        }

        // Update meter bar
        if (this.barElement) {
            this.barElement.className = `password-strength-bar ${strengthInfo.class}`;
            this.barElement.style.width = `${strengthInfo.score}%`;
        }

        this.currentStrength = level;
        this.currentScore = score;
    }

    /**
     * Show status message
     * @private
     */
    showMessage(text, type = 'info') {
        if (!this.messageElement) {
            return;
        }

        this.messageElement.textContent = text;
        this.messageElement.className = `password-strength-message show ${type}`;

        // Auto-hide after 5 seconds
        setTimeout(() => {
            this.messageElement.classList.remove('show');
        }, 5000);
    }

    /**
     * Reset to initial state
     */
    reset() {
        // Reset score
        if (this.scoreElement) {
            this.scoreElement.textContent = 'Not checked';
            this.scoreElement.className = 'password-strength-score';
        }

        // Reset bar
        if (this.barElement) {
            this.barElement.className = 'password-strength-bar';
            this.barElement.style.width = '0%';
        }

        // Reset all requirements to unmet
        this.requirementElements.forEach(el => {
            el.classList.remove('met');
            el.classList.add('unmet');
        });

        // Clear message
        if (this.messageElement) {
            this.messageElement.classList.remove('show');
        }

        this.currentStrength = 0;
        this.currentScore = 0;
    }

    /**
     * Check if password is valid (meets all requirements)
     * @returns {boolean}
     */
    isValid() {
        return Array.from(this.requirementElements).every(el => {
            return el.classList.contains('met');
        });
    }

    /**
     * Get current strength level (0-4)
     * @returns {number}
     */
    getStrengthLevel() {
        return this.currentStrength;
    }

    /**
     * Get current strength score (0-100)
     * @returns {number}
     */
    getStrengthScore() {
        return this.currentScore;
    }
}

// Export to global if needed
if (typeof window !== 'undefined') {
    window.PasswordStrengthIndicator = PasswordStrengthIndicator;
}
