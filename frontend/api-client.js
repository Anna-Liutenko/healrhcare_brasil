/**
 * API Client for Healthcare CMS Backend
 * Handles all communication with PHP backend
 */

import { toPlainObject, blockToAPI, blockFromAPI } from './utils/mappers.js';

// Определяем путь к API в зависимости от окружения
// Production: Apache/XAMPP на localhost:80
// E2E: встроенный сервер PHP на 127.0.0.1:8089
const API_BASE_URL = window.location.hostname === 'localhost'
    ? 'http://localhost/healthcare-cms-backend/public'
    : '/api';

class ApiClient {
    constructor() {
        this.token = this.getToken();
        this.currentUser = null;
        this.requestCounter = 0;
        this.logger = null;
    }

    /**
     * Get auth token from localStorage
     */
    getToken() {
        return localStorage.getItem('cms_auth_token');
    }

    /**
     * Set auth token
     */
    setToken(token) {
        if (token) {
            localStorage.setItem('cms_auth_token', token);
            this.token = token;
        } else {
            localStorage.removeItem('cms_auth_token');
            this.token = null;
        }
    }

    /**
     * Check if user is authenticated
     */
    isAuthenticated() {
        return !!this.token;
    }

    setLogger(loggerFn) {
        this.logger = typeof loggerFn === 'function' ? loggerFn : null;
    }

    log(type, message, payload) {
        if (this.logger) {
            try {
                this.logger(message, type, payload);
            } catch (error) {
                console.warn('Logger callback failed', error);
            }
        }
    }

    /**
     * Make HTTP request to backend
     */
    async request(endpoint, options = {}) {
        const url = `${API_BASE_URL}${endpoint}`;
        const requestId = ++this.requestCounter;
        const method = (options.method || 'GET').toUpperCase();

        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };

        // Add auth token if exists
        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        let body = options.body;
        if (body && headers['Content-Type'] === 'application/json' && typeof body !== 'string') {
            body = JSON.stringify(body);
        }

        const config = {
            ...options,
            method,
            headers,
            body
        };

        const logPayload = () => {
            if (!body) return undefined;
            try {
                return JSON.parse(body);
            } catch (_) {
                return body;
            }
        };

        const payloadForLog = logPayload();
        console.log(`[API ${requestId}] → ${method} ${url}`, payloadForLog);
        this.log('info', `[API ${requestId}] → ${method} ${endpoint}`, payloadForLog);

        try {
            const response = await fetch(url, config);
            const rawText = await response.text();
            let data = null;

            if (rawText) {
                try {
                    data = JSON.parse(rawText);
                } catch (parseError) {
                    console.warn(`[API ${requestId}] ! Не удалось распарсить JSON`, parseError);
                    data = rawText;
                }
            }

            console.log(`[API ${requestId}] ← ${response.status}`, data);
            this.log('success', `[API ${requestId}] ← ${response.status} ${endpoint}`, data);

            if (!response.ok) {
                const errorMessage = data?.error?.message || data?.error || data?.message || `HTTP ${response.status}`;
                const error = new Error(errorMessage);
                error.details = data?.error?.details || data;
                this.log('error', `[API ${requestId}] ✖ ${endpoint}`, {
                    status: response.status,
                    message: errorMessage,
                    details: error.details || null
                });
                throw error;
            }
            
            // Check if response contains error even with 200 OK
            if (data && typeof data === 'object' && data.error) {
                const errorMessage = typeof data.error === 'string' ? data.error : (data.error.message || 'Unknown error');
                const error = new Error(errorMessage);
                error.details = data.error.details || null;
                this.log('error', `[API ${requestId}] ✖ ${endpoint}`, {
                    status: response.status,
                    message: errorMessage,
                    details: error.details
                });
                throw error;
            }

            return data;
        } catch (error) {
            console.error(`[API ${requestId}] ✖ Ошибка запроса`, error);
            this.log('error', `[API ${requestId}] ✖ ${endpoint}`, {
                message: error.message,
                details: error.details || null
            });
            throw error;
        }
    }

    // ===== AUTH ENDPOINTS =====

    /**
     * Login
     * POST /api/auth/login
     */
    async login(username, password) {
        const data = await this.request('/api/auth/login', {
            method: 'POST',
            body: { username, password }
        });

        if (data.token) {
            this.setToken(data.token);
            this.currentUser = data.user;
        }

        return data;
    }

    /**
     * Logout
     * POST /api/auth/logout
     */
    async logout() {
        try {
            await this.request('/api/auth/logout', {
                method: 'POST'
            });
        } finally {
            this.setToken(null);
            this.currentUser = null;
        }
    }

    /**
     * Get current user
     * GET /api/auth/me
     * NOTE: API returns user object directly, NOT { user: {...} }
     */
    async getCurrentUser() {
        const data = await this.request('/api/auth/me', {
            method: 'GET'
        });

        this.currentUser = data;
        return data;
    }

    // ===== PAGES ENDPOINTS =====

    /**
     * Get all pages (without blocks)
     * GET /api/pages
     */
    async getPages() {
        const data = await this.request('/api/pages', {
            method: 'GET'
        });

        // Backend returns dates in "YYYY-MM-DD HH:MM:SS" format.
        // Normalize to ISO-like string with 'T' so `new Date()` parses consistently in browsers.
        if (Array.isArray(data)) {
            return data.map((item) => {
                ['createdAt', 'updatedAt', 'publishedAt'].forEach((k) => {
                    if (typeof item[k] === 'string' && item[k].includes(' ')) {
                        // Only replace the first space between date and time
                        item[k] = item[k].replace(' ', 'T');
                    }
                });
                return item;
            });
        }

        return data;
    }

    /**
     * Alias for getPages()
     */
    async getAllPages() {
        return await this.getPages();
    }

    /**
     * Get single page by ID
     * GET /api/pages/:id
     */
    async getPage(pageId) {
        const data = await this.request(`/api/pages/${pageId}`, {
            method: 'GET'
        });

        if (data?.page && Array.isArray(data.page.blocks)) {
            data.page.blocks = data.page.blocks.map(blockFromAPI);
        }

        if (Array.isArray(data?.blocks)) {
            data.blocks = data.blocks.map(blockFromAPI);
        }

        return data;
    }

    /**
     * Create new page
     * POST /api/pages
     */
    async createPage(pageData) {
        const payload = toPlainObject({
            ...pageData,
            blocks: (pageData.blocks || []).map((block) => blockToAPI(block))
        });

        return await this.request('/api/pages', {
            method: 'POST',
            body: payload
        });
    }

    /**
     * Update page
     * PUT /api/pages/:id
     */
    async updatePage(pageId, pageData) {
        const payload = toPlainObject({
            ...pageData,
            blocks: (pageData.blocks || []).map((block) => blockToAPI(block))
        });

        return await this.request(`/api/pages/${pageId}`, {
            method: 'PUT',
            body: payload
        });
    }

    /**
     * Publish page
     * PUT /api/pages/:id/publish
     */
    async publishPage(pageId) {
        return await this.request(`/api/pages/${pageId}/publish`, {
            method: 'PUT'
        });
    }

    /**
     * Delete page
     * DELETE /api/pages/:id
     */
    async deletePage(pageId) {
        return await this.request(`/api/pages/${pageId}`, {
            method: 'DELETE'
        });
    }

    // ===== MEDIA ENDPOINTS =====

    /**
     * Get all media files
     * GET /api/media
     * @param {string} type - Filter by type ('image', 'document', 'all')
     * @returns {Promise<Array>} Array of media files
     */
    async getMedia(type = 'all') {
        const url = type === 'all' 
            ? '/api/media'
            : `/api/media?type=${type}`;
        
        return await this.request(url, {
            method: 'GET'
        });
    }

    /**
     * Upload media file with progress tracking
     * POST /api/media/upload
     * @param {File} file - File object to upload
     * @param {Function} onProgress - Progress callback (optional)
     * @returns {Promise<Object>} Uploaded file data
     */
    async uploadMedia(file, onProgress = null) {
        const formData = new FormData();
        formData.append('file', file);

        const url = `${API_BASE_URL}/api/media/upload`;
        
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();

            // Progress tracking
            if (onProgress && typeof onProgress === 'function') {
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        onProgress(percentComplete);
                    }
                });
            }

            // Load event
            xhr.addEventListener('load', () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        this.log('Upload successful', response);
                        resolve(response);
                    } catch (error) {
                        reject(new Error('Invalid JSON response from server'));
                    }
                } else {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        reject(new Error(errorData.error || 'Upload failed'));
                    } catch {
                        reject(new Error(`Upload failed with status ${xhr.status}`));
                    }
                }
            });

            // Error event
            xhr.addEventListener('error', () => {
                reject(new Error('Network error during upload'));
            });

            // Abort event
            xhr.addEventListener('abort', () => {
                reject(new Error('Upload cancelled'));
            });

            // Send request
            xhr.open('POST', url);
            xhr.withCredentials = true;
            
            // Add auth token if available
            if (this.token) {
                xhr.setRequestHeader('Authorization', `Bearer ${this.token}`);
            }
            
            xhr.send(formData);
        });
    }

    /**
     * Delete media file
     * DELETE /api/media/:id
     * @param {string} fileId - Media file ID
     * @returns {Promise<Object>} Delete confirmation
     */
    async deleteMedia(fileId) {
        return await this.request(`/api/media/${fileId}`, {
            method: 'DELETE'
        });
    }

    // ===== TEMPLATES API =====

    async getAllTemplates() {
        return await this.request('/api/templates', { method: 'GET' });
    }

    async importTemplate(slug) {
        return await this.request(`/api/templates/${slug}/import`, { method: 'POST' });
    }

    // ==========================================
    // MENU API
    // ==========================================

    /**
     * Get public menu items (for website navigation)
     * GET /api/menu/public
     * Returns only published pages with show_in_menu=true
     * @returns {Promise<Array>} Menu items [{label, url, slug, position}]
     */
    async getPublicMenu() {
        const response = await this.request('/api/menu/public', {
            method: 'GET'
        });
        
        if (response.success && Array.isArray(response.data)) {
            return response.data;
        }
        
        return [];
    }

    /**
     * Get all menu items (old Menu Editor API)
     * GET /api/menu
     * @returns {Promise<Array>} Menu items
     */
    async getMenu() {
        const response = await this.request('/api/menu', {
            method: 'GET'
        });
        
        // Map snake_case to camelCase
        if (Array.isArray(response.data)) {
            return response.data.map(item => ({
                id: item.id,
                label: item.label,
                pageId: item.page_id,
                url: item.url,
                position: item.position,
                parentId: item.parent_id,
                createdAt: item.created_at,
                updatedAt: item.updated_at
            }));
        }
        
        return [];
    }

    /**
     * Create menu item
     * POST /api/menu
     * @param {Object} data - Menu item data (snake_case)
     * @returns {Promise<Object>} Created menu item
     */
    async createMenuItem(data) {
        return await this.request('/api/menu', {
            method: 'POST',
            body: data
        });
    }

    /**
     * Update menu item
     * PUT /api/menu/:id
     * @param {string} id - Menu item ID
     * @param {Object} data - Updated data (snake_case)
     * @returns {Promise<Object>} Updated menu item
     */
    async updateMenuItem(id, data) {
        return await this.request(`/api/menu/${id}`, {
            method: 'PUT',
            body: data
        });
    }

    /**
     * Delete menu item
     * DELETE /api/menu/:id
     * @param {string} id - Menu item ID
     * @returns {Promise<Object>} Success response
     */
    async deleteMenuItem(id) {
        return await this.request(`/api/menu/${id}`, {
            method: 'DELETE'
        });
    }

    /**
     * Reorder menu items
     * PUT /api/menu/reorder
     * @param {Array} items - Array of {id, position}
     * @returns {Promise<Object>} Success response
     */
    async reorderMenu(items) {
        return await this.request('/api/menu/reorder', {
            method: 'PUT',
            body: { items }
        });
    }
}

// Export class as default
export default ApiClient;

// Export singleton instance
export const apiClient = new ApiClient();
