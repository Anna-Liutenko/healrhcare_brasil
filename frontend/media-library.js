import ApiClient from './api-client.js';

const { createApp } = Vue;

const MediaLibraryApp = {
    data() {
        return {
            // User
            currentUser: null,
            
            // Media files
            mediaFiles: [],
            filteredFiles: [],
            
            // UI state
            isLoading: false,
            error: null,
            successMessage: null,
            
            // Filters
            filterType: 'image',
            searchQuery: '',
            
            // Upload
            uploadProgress: 0,
            isDragging: false,
            
            // Delete
            fileToDelete: null,
            
            // API
            apiClient: new ApiClient()
        };
    },

    computed: {
        totalCount() {
            return this.mediaFiles.length;
        },
        
        imageCount() {
            return this.mediaFiles.filter(f => f.type === 'image').length;
        }
    },

    watch: {
        filterType() {
            this.applyFilters();
        },
        
        searchQuery() {
            this.applyFilters();
        }
    },

    async mounted() {
        console.log('Media Library mounted');
        
        // Check auth
        this.currentUser = await this.checkAuth();
        if (!this.currentUser) {
            console.log('Not authenticated, redirecting to login');
            window.location.href = 'index.html';
            return;
        }

        console.log('Current user:', this.currentUser);

        // Load media
        await this.loadMedia();
    },

    methods: {
        // ==========================================
        // AUTH
        // ==========================================
        
        async checkAuth() {
            try {
                const user = await this.apiClient.getCurrentUser();
                return user;
            } catch (error) {
                console.error('Auth check failed:', error);
                return null;
            }
        },

        async logout() {
            try {
                await this.apiClient.logout();
                window.location.href = 'index.html';
            } catch (error) {
                console.error('Logout failed:', error);
            }
        },

        // ==========================================
        // LOAD MEDIA
        // ==========================================
        
        async loadMedia() {
            this.isLoading = true;
            this.error = null;

            try {
                // Load only image files by default
                    const raw = await this.apiClient.getMedia('image');

                    // Normalize API response to frontend shape
                    // Backend returns fields like file_id, file_url, uploaded_at, etc.
                    this.mediaFiles = (Array.isArray(raw) ? raw : []).map(item => ({
                        id: item.file_id || item.id || item.fileId || item.mediaId || null,
                        filename: item.filename || item.original_filename || item.name || '',
                        url: item.file_url || item.url || item.path || '',
                        type: item.type || 'image',
                        size: item.size || null,
                        human_size: item.human_size || item.humanSize || null,
                        uploaded_at: item.uploaded_at || item.uploadedAt || new Date().toISOString(),
                        // keep original raw item for debugging if needed
                        _raw: item
                    }));

                    this.applyFilters();
                    console.log('Loaded media files:', this.mediaFiles);
            } catch (error) {
                this.error = error.message;
                console.error('Failed to load media:', error);
            } finally {
                this.isLoading = false;
            }
        },

        applyFilters() {
            let files = [...this.mediaFiles];

            // Filter by type
            if (this.filterType !== 'all') {
                files = files.filter(f => f.type === this.filterType);
            }

            // Search by filename
            if (this.searchQuery.trim()) {
                const query = this.searchQuery.toLowerCase();
                files = files.filter(f => 
                    f.filename.toLowerCase().includes(query)
                );
            }

            this.filteredFiles = files;
            console.log('Filtered files:', this.filteredFiles.length, 'of', this.mediaFiles.length);
        },

        // ==========================================
        // UPLOAD
        // ==========================================
        
        triggerFileInput() {
            this.$refs.fileInput.click();
        },

        handleFileSelect(event) {
            const files = Array.from(event.target.files);
            if (files.length > 0) {
                console.log('Files selected:', files.length);
                this.uploadFiles(files);
            }
            // Reset input
            event.target.value = '';
        },

        handleDrop(event) {
            this.isDragging = false;
            const files = Array.from(event.dataTransfer.files);
            
            console.log('Files dropped:', files.length);
            
            // Filter only images
            const imageFiles = files.filter(f => f.type.startsWith('image/'));
            
            if (imageFiles.length > 0) {
                this.uploadFiles(imageFiles);
            } else {
                this.showError('Можно загружать только изображения');
            }
        },

        async uploadFiles(files) {
            for (const file of files) {
                await this.uploadSingleFile(file);
            }
        },

        async uploadSingleFile(file) {
            // Validate file size (5MB max)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                this.showError(`Файл ${file.name} слишком большой (макс. 5MB)`);
                return;
            }

            // Validate file type
            if (!file.type.startsWith('image/')) {
                this.showError(`Файл ${file.name} не является изображением`);
                return;
            }

            try {
                console.log('Uploading file:', file.name);
                this.uploadProgress = 0;

                const result = await this.apiClient.uploadMedia(
                    file,
                    (progress) => {
                        this.uploadProgress = Math.round(progress);
                        console.log('Upload progress:', this.uploadProgress + '%');
                    }
                );

                console.log('Upload successful:', result);
                
                this.uploadProgress = 100;
                
                // Add to list
                const newFile = {
                    id: result.file_id,
                    filename: result.filename,
                    url: result.file_url,
                    type: result.type,
                    size: result.size,
                    human_size: result.human_size,
                    uploaded_at: new Date().toISOString()
                };
                
                this.mediaFiles.unshift(newFile);
                this.applyFilters();

                this.showSuccess(`Файл ${file.name} загружен`);

                // Reset progress
                setTimeout(() => {
                    this.uploadProgress = 0;
                }, 1000);

            } catch (error) {
                console.error('Upload error:', error);
                this.showError(`Ошибка загрузки: ${error.message}`);
                this.uploadProgress = 0;
            }
        },

        // ==========================================
        // DELETE
        // ==========================================
        
        deleteFile(file) {
            console.log('Deleting file:', file.filename);
            this.fileToDelete = file;
        },

        cancelDelete() {
            this.fileToDelete = null;
        },

        async confirmDelete() {
            if (!this.fileToDelete) return;

            const file = this.fileToDelete;
            this.fileToDelete = null;

            try {
                console.log('Confirming delete:', file.id);
                await this.apiClient.deleteMedia(file.id);

                // Remove from list
                this.mediaFiles = this.mediaFiles.filter(f => f.id !== file.id);
                this.applyFilters();

                this.showSuccess(`Файл ${file.filename} удалён`);
                console.log('File deleted successfully');

            } catch (error) {
                console.error('Delete error:', error);
                this.showError(`Ошибка удаления: ${error.message}`);
            }
        },

        // ==========================================
        // ACTIONS
        // ==========================================
        
        selectFile(file) {
            console.log('Selected file:', file);
            // TODO: Implement select mode for editor integration
        },

        async copyUrl(file) {
            const fullUrl = this.getFileUrl(file.url);
            
            try {
                await navigator.clipboard.writeText(fullUrl);
                this.showSuccess('URL скопирован в буфер обмена');
                console.log('URL copied:', fullUrl);
            } catch (err) {
                console.error('Failed to copy URL:', err);
                this.showError('Не удалось скопировать URL');
            }
        },

        // ==========================================
        // HELPERS
        // ==========================================
        
        getFileUrl(url) {
            // If URL is absolute, return as-is
            if (url.startsWith('http')) {
                return url;
            }
            
            // If URL starts with /uploads/, prepend backend base URL
            if (url.startsWith('/uploads/')) {
                return `http://localhost/healthcare-cms-backend/public${url}`;
            }
            
            // If URL starts with /, prepend base URL
            if (url.startsWith('/')) {
                return `http://localhost${url}`;
            }
            
            // Otherwise, assume relative to backend uploads
            return `http://localhost/healthcare-cms-backend/public/uploads/${url}`;
        },

        handleImageError(event) {
            const img = event.target;
            console.warn('Image load error:', img.src);

            // Try alternate URLs before falling back to placeholder
            const fileUrl = img.dataset.fileUrl || '';
            const filename = img.dataset.filename || '';

            // First retry: if URL looks like /uploads/..., try backend public path
            if (!img._retryAttempted1) {
                img._retryAttempted1 = true;
                if (fileUrl.startsWith('/uploads/')) {
                    const alt = `http://localhost/healthcare-cms-backend/public${fileUrl}`;
                    console.log('Retrying image with backend public path:', alt);
                    img.src = alt;
                    return;
                }

                // If fileUrl starts with '/healthcare-cms-backend', try with localhost prefix
                if (fileUrl.startsWith('/healthcare-cms-backend')) {
                    const alt = `http://localhost${fileUrl}`;
                    console.log('Retrying image with localhost+fileUrl:', alt);
                    img.src = alt;
                    return;
                }
            }

            // Second retry: try frontend uploads folder by filename
            if (!img._retryAttempted2) {
                img._retryAttempted2 = true;
                if (filename) {
                    const alt = `http://localhost/healthcare-cms-frontend/uploads/${encodeURIComponent(filename)}`;
                    console.log('Retrying image with frontend uploads path:', alt);
                    img.src = alt;
                    return;
                }
            }

            // Final fallback: placeholder SVG
            img.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect fill="%23eee" width="100%" height="100%"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="%23999" font-size="18">No Image</text></svg>';
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;

            // Less than 1 hour
            if (diff < 3600000) {
                const minutes = Math.floor(diff / 60000);
                return `${minutes} мин назад`;
            }

            // Less than 1 day
            if (diff < 86400000) {
                const hours = Math.floor(diff / 3600000);
                return `${hours} ч назад`;
            }

            // Less than 7 days
            if (diff < 604800000) {
                const days = Math.floor(diff / 86400000);
                return `${days} дн назад`;
            }

            // Format as date
            return date.toLocaleDateString('ru-RU', {
                day: 'numeric',
                month: 'short',
                year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
            });
        },

        showSuccess(message) {
            this.successMessage = message;
            setTimeout(() => {
                this.successMessage = null;
            }, 3000);
        },

        showError(message) {
            this.error = message;
            setTimeout(() => {
                this.error = null;
            }, 5000);
        }
    }
};

createApp(MediaLibraryApp).mount('#app');
