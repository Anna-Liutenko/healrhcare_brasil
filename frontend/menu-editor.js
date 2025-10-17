import ApiClient from './api-client.js';

const { createApp } = Vue;

const MenuEditorApp = {
    data() {
        return {
            // User
            currentUser: null,
            
            // Menu items
            menuItems: [],
            pages: [],  // Список страниц для dropdown
            
            // UI state
            isLoading: false,
            error: null,
            successMessage: null,
            
            // Form
            showForm: false,
            editingItem: null,
            isSaving: false,
            formData: {
                label: '',
                linkType: 'page',  // 'page' or 'external'
                pageId: '',
                url: '',
                position: null
            },
            
            // Delete
            itemToDelete: null,
            
            // Drag & Drop
            draggedItem: null,
            
            // API
            apiClient: new ApiClient()
        };
    },

    computed: {
        sortedMenuItems() {
            return [...this.menuItems].sort((a, b) => a.position - b.position);
        }
    },

    async mounted() {
        console.log('Menu Editor mounted');
        
        // Check auth
        this.currentUser = await this.checkAuth();
        if (!this.currentUser) {
            console.log('Not authenticated, redirecting to login');
            window.location.href = 'index.html';
            return;
        }

        console.log('Current user:', this.currentUser);

        // Load data
        await Promise.all([
            this.loadMenu(),
            this.loadPages()
        ]);
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
        // LOAD DATA
        // ==========================================
        
        async loadMenu() {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await this.apiClient.getMenu();
                this.menuItems = response;
                console.log('Loaded menu items:', this.menuItems);
            } catch (error) {
                // If menu is empty, that's ok - don't show error
                if (error.message.includes('not found')) {
                    this.menuItems = [];
                    console.log('Menu is empty (new installation)');
                } else {
                    this.error = error.message;
                    console.error('Failed to load menu:', error);
                }
            } finally {
                this.isLoading = false;
            }
        },

        async loadPages() {
            try {
                const response = await this.apiClient.getPages();
                // Filter only published pages
                this.pages = response.filter(p => p.status === 'published');
                console.log('Loaded pages:', this.pages.length);
            } catch (error) {
                console.error('Failed to load pages:', error);
                // Don't show error to user - pages list can be empty
                this.pages = [];
            }
        },

        // ==========================================
        // CRUD OPERATIONS
        // ==========================================
        
        showCreateForm() {
            this.editingItem = null;
            this.resetForm();
            this.showForm = true;
        },

        editItem(item) {
            this.editingItem = item;
            this.formData = {
                label: item.label,
                linkType: item.pageId ? 'page' : 'external',
                pageId: item.pageId || '',
                url: item.url || '',
                position: item.position
            };
            this.showForm = true;
        },

        async saveItem() {
            this.isSaving = true;

            try {
                const data = {
                    label: this.formData.label,
                    page_id: this.formData.linkType === 'page' ? this.formData.pageId : null,
                    url: this.formData.linkType === 'external' ? this.formData.url : null,
                    position: this.formData.position !== null && this.formData.position !== '' 
                        ? this.formData.position 
                        : this.menuItems.length
                };

                if (this.editingItem) {
                    // Update
                    await this.apiClient.updateMenuItem(this.editingItem.id, data);
                    this.showSuccess('Пункт меню обновлён');
                } else {
                    // Create
                    await this.apiClient.createMenuItem(data);
                    this.showSuccess('Пункт меню создан');
                }

                this.showForm = false;
                await this.loadMenu();
            } catch (error) {
                this.showError('Ошибка сохранения: ' + error.message);
                console.error('Save failed:', error);
            } finally {
                this.isSaving = false;
            }
        },

        deleteItem(item) {
            this.itemToDelete = item;
        },

        async confirmDelete() {
            try {
                await this.apiClient.deleteMenuItem(this.itemToDelete.id);
                this.showSuccess('Пункт меню удалён');
                this.itemToDelete = null;
                await this.loadMenu();
            } catch (error) {
                this.showError('Ошибка удаления: ' + error.message);
                console.error('Delete failed:', error);
            }
        },

        cancelDelete() {
            this.itemToDelete = null;
        },

        cancelForm() {
            this.showForm = false;
            this.editingItem = null;
            this.resetForm();
        },

        resetForm() {
            this.formData = {
                label: '',
                linkType: 'page',
                pageId: '',
                url: '',
                position: null
            };
        },

        // ==========================================
        // DRAG & DROP
        // ==========================================
        
        handleDragStart(event, item) {
            this.draggedItem = item;
            event.dataTransfer.effectAllowed = 'move';
            event.target.classList.add('dragging');
        },

        handleDragOver(event) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
        },

        async handleDrop(event, targetItem) {
            event.preventDefault();
            
            if (!this.draggedItem || this.draggedItem.id === targetItem.id) {
                return;
            }

            // Reorder logic
            const items = [...this.menuItems];
            const draggedIndex = items.findIndex(i => i.id === this.draggedItem.id);
            const targetIndex = items.findIndex(i => i.id === targetItem.id);

            // Remove dragged item
            const [removed] = items.splice(draggedIndex, 1);
            
            // Insert at new position
            items.splice(targetIndex, 0, removed);

            // Update positions
            items.forEach((item, index) => {
                item.position = index;
            });

            this.menuItems = items;

            // Save new order to backend
            try {
                await this.apiClient.reorderMenu(items.map(i => ({ id: i.id, position: i.position })));
                this.showSuccess('Порядок сохранён');
            } catch (error) {
                this.showError('Ошибка сохранения порядка: ' + error.message);
                console.error('Reorder failed:', error);
                await this.loadMenu();  // Reload on error
            }
        },

        handleDragEnd(event) {
            event.target.classList.remove('dragging');
            this.draggedItem = null;
        },

        // ==========================================
        // HELPERS
        // ==========================================
        
        getPageTitle(pageId) {
            const page = this.pages.find(p => p.id === pageId);
            return page ? page.title : 'Страница не найдена';
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

createApp(MenuEditorApp).mount('#app');
