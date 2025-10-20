import { blockDefinitions } from './blocks.js';
import { pageTemplates } from './templates.js';
import ApiClient from './api-client.js';
import { blockToAPI, blockFromAPI, generateSlug, toPlainObject } from './utils/mappers.js';
import { validateSlug } from './utils/validators.js';

const { createApp } = Vue;

const app = createApp({
    data() {
        return {
            // API & Auth
            apiClient: null,
            currentUser: null,
            currentPageId: null,
            isEditMode: false,
            showLoginModal: false,
            loginForm: {
                username: '',
                password: ''
            },

            // Page Data
            pageData: {
                title: '',
                slug: '',
                type: 'regular',
                status: 'draft',
                seoTitle: '',
                seoDescription: '',
                seoKeywords: ''
            },
            autoGenerateSlug: true, // Ð¤Ð»Ð°Ð³ Ð°Ð²ÑÐ¾Ð³ÐµÐ½ÐµÑÐ°ÑÐ¸Ð¸ slug

            // Menu / Navigation settings for the current page (editor-only model)
            pageSettings: {
                showInMenu: false,
                menuPosition: null,
                menuTitle: ''
            },

            blocks: [],
            selectedBlockIndex: null,
            selectedBlock: null,
            showTemplatesModal: false,
            showGalleryModal: false,
            previewBlock: null,
            notification: null,
            activeTab: 'page',
            blockDefinitions: blockDefinitions,
            pageTemplates: pageTemplates,

            // Gallery
            galleryImages: [],
            selectedGalleryImage: null,
            currentImageField: null,
            currentArrayContext: null,
            uploadProgress: null,

            // Drag & Drop
            draggedBlockType: null,
            isDraggingFromLibrary: false,
            draggedBlockIndex: null,
            dragOverBlockIndex: null,

            // Article Editor
            showArticleEditor: false,
            quillInstance: null,
            articleHtml: '',
            pendingImageContainer: null,

            globalSettings: {
                header: {
                    logoText: 'Healthcare Hacks Brazil',
                    navItems: [
                        { text: 'ÐÐ»Ð°Ð²Ð½Ð°Ñ', link: '#' },
                        { text: 'ÐÐ°Ð¹Ð´Ñ', link: '#' },
                        { text: 'ÐÐ»Ð¾Ð³', link: '#' },
                        { text: 'ÐÐ¾Ñ', link: '#' }
                    ]
                },
                footer: {
                    logoText: 'Healthcare Hacks Brazil',
                    copyrightText: 'Â© 2025 ÐÐ½Ð½Ð° ÐÑÑÐµÐ½ÐºÐ¾ (Anna Liutenko). ÐÑÐµ Ð¿ÑÐ°Ð²Ð° Ð·Ð°ÑÐ¸ÑÐµÐ½Ñ.',
                    privacyLink: '#privacy',
                    privacyLinkText: 'ÐÐ¾Ð»Ð¸ÑÐ¸ÐºÐ° ÐºÐ¾Ð½ÑÐ¸Ð´ÐµÐ½ÑÐ¸Ð°Ð»ÑÐ½Ð¾ÑÑÐ¸'
                },
                cookieBanner: {
                    enabled: true,
                    message: 'ÐÑ Ð¸ÑÐ¿Ð¾Ð»ÑÐ·ÑÐµÐ¼ cookie Ð´Ð»Ñ ÑÐ»ÑÑÑÐµÐ½Ð¸Ñ ÑÐ°Ð±Ð¾ÑÑ ÑÐ°Ð¹ÑÐ°. ÐÑÐ¾Ð´Ð¾Ð»Ð¶Ð°Ñ Ð¸ÑÐ¿Ð¾Ð»ÑÐ·Ð¾Ð²Ð°ÑÑ ÑÐ°Ð¹Ñ, Ð²Ñ ÑÐ¾Ð³Ð»Ð°ÑÐ°ÐµÑÐµÑÑ Ñ Ð½Ð°ÑÐµÐ¹ ÐÐ¾Ð»Ð¸ÑÐ¸ÐºÐ¾Ð¹ ÐºÐ¾Ð½ÑÐ¸Ð´ÐµÐ½ÑÐ¸Ð°Ð»ÑÐ½Ð¾ÑÑÐ¸.',
                    acceptText: 'ÐÑÐ¸Ð½ÑÑÑ',
                    detailsText: 'ÐÐ¾Ð´ÑÐ¾Ð±Ð½ÐµÐµ'
                }
            },

            // Debug Panel
            debugPanelEnabled: typeof window !== 'undefined' ? window.__ENABLE_DEBUG_PANEL !== false : true,
            debugPanelCollapsed: false,
            debugMessages: [],
            debugMaxMessages: 200
        };
    },

    async created() {
        this.apiClient = new ApiClient();
        this.apiClient.setLogger((message, type = 'info', payload = null) => {
            this.debugMsg(message, type, payload);
        });
        this.debugMsg('ÐÐ½Ð¸ÑÐ¸Ð°Ð»Ð¸Ð·Ð°ÑÐ¸Ñ ÑÐµÐ´Ð°ÐºÑÐ¾ÑÐ°', 'info');
        // Store auth promise to wait in mounted()
        this._authPromise = this.checkAuth();
        await this._authPromise;
    },

    // Initialize Trusted Types policy for editor (if supported)
    beforeMount() {
        this.initTrustedTypesPolicy?.();
    },

    async mounted() {
        // CRITICAL: Wait for auth to complete before checking currentUser
        await this._authPromise;
        
        const urlParams = new URLSearchParams(window.location.search);
        const pageId = urlParams.get('id');

        if (pageId) {
            this.debugMsg('ÐÐ±Ð½Ð°ÑÑÐ¶ÐµÐ½ Ð¿Ð°ÑÐ°Ð¼ÐµÑÑ id Ð² URL Ð¿ÑÐ¸ Ð¼Ð¾Ð½ÑÐ¸ÑÐ¾Ð²Ð°Ð½Ð¸Ð¸', 'info', { pageId });
            
            // ÐÐ¾ÑÐ»Ðµ await this._authPromise currentUser Ð³Ð°ÑÐ°Ð½ÑÐ¸ÑÐ¾Ð²Ð°Ð½Ð½Ð¾ ÑÑÑÐ°Ð½Ð¾Ð²Ð»ÐµÐ½
            if (this.currentUser && !this.showLoginModal) {
                this.debugMsg('ÐÐ¾Ð»ÑÐ·Ð¾Ð²Ð°ÑÐµÐ»Ñ Ð°Ð²ÑÐ¾ÑÐ¸Ð·Ð¾Ð²Ð°Ð½, Ð·Ð°Ð³ÑÑÐ¶Ð°ÐµÐ¼ ÑÑÑÐ°Ð½Ð¸ÑÑ', 'info', { pageId });
                await this.loadPageFromAPI(pageId);
            } else {
                // ÐÑÐ»Ð¸ Ð¿Ð¾Ð»ÑÐ·Ð¾Ð²Ð°ÑÐµÐ»Ñ ÐÐ Ð°Ð²ÑÐ¾ÑÐ¸Ð·Ð¾Ð²Ð°Ð½, Ð¶Ð´ÑÐ¼ Ð»Ð¾Ð³Ð¸Ð½Ð°
                // loadPageFromAPI Ð±ÑÐ´ÐµÑ Ð²ÑÐ·Ð²Ð°Ð½ ÐÐÐ£Ð¢Ð Ð login() Ð¿Ð¾ÑÐ»Ðµ ÑÑÐ¿ÐµÑÐ½Ð¾Ð³Ð¾ Ð²ÑÐ¾Ð´Ð°
                this.debugMsg('ÐÐ¾Ð»ÑÐ·Ð¾Ð²Ð°ÑÐµÐ»Ñ Ð½Ðµ Ð°Ð²ÑÐ¾ÑÐ¸Ð·Ð¾Ð²Ð°Ð½, Ð¾Ð¶Ð¸Ð´Ð°Ð½Ð¸Ðµ Ð²ÑÐ¾Ð´Ð°. Ð¡ÑÑÐ°Ð½Ð¸ÑÐ° Ð±ÑÐ´ÐµÑ Ð·Ð°Ð³ÑÑÐ¶ÐµÐ½Ð° Ð¿Ð¾ÑÐ»Ðµ Ð»Ð¾Ð³Ð¸Ð½Ð°.', 'info', { pageId });
            }
        }

        // Initialize inline editor toggle (Stage 1)
        this.$nextTick(() => {
            const toggleBtn = document.getElementById('toggleInlineMode');
            if (toggleBtn) {
                const enableLabel = toggleBtn.dataset.inlineEnableLabel || 'ð Enable Inline Editing';
                const disableLabel = toggleBtn.dataset.inlineDisableLabel || 'ð« Disable Inline Editing';

                // initialize text according to current state
                toggleBtn.textContent = this._inlineModeEnabled ? disableLabel : enableLabel;

                toggleBtn.addEventListener('click', () => {
                    if (!this._inlineManager) {
                        const previewEl = document.querySelector('.preview-wrapper');
                        const pid = new URLSearchParams(window.location.search).get('id');
                        this._inlineManager = new window.InlineEditorManager(previewEl, pid);
                    }

                    if (!this._inlineModeEnabled) {
                        this._inlineManager.enableInlineMode();
                        this._inlineModeEnabled = true;
                        toggleBtn.textContent = disableLabel;
                        toggleBtn.classList.add('btn-danger');
                        toggleBtn.setAttribute('aria-pressed', 'true');
                    } else {
                        this._inlineManager.disableInlineMode();
                        this._inlineModeEnabled = false;
                        toggleBtn.textContent = enableLabel;
                        toggleBtn.classList.remove('btn-danger');
                        toggleBtn.setAttribute('aria-pressed', 'false');
                    }
                });
            }

            // Keyboard shortcuts: undo/redo
            document.addEventListener('keydown', (e) => {
                if (!this._inlineModeEnabled || !this._inlineManager || !this._inlineManager.activeElement) return;

                if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
                    e.preventDefault();
                    this._inlineManager.undo();
                } else if ((e.ctrlKey || e.metaKey) && (e.shiftKey && (e.key === 'Z' || e.key === 'z'))) {
                    e.preventDefault();
                    this._inlineManager.redo();
                }
            });
        });
    },

    updated() {
        // ÐÐ½Ð¸ÑÐ¸Ð°Ð»Ð¸Ð·Ð°ÑÐ¸Ñ Quill Ð¿Ð¾ÑÐ»Ðµ Ð¾ÑÐºÑÑÑÐ¸Ñ ÑÐµÐ´Ð°ÐºÑÐ¾ÑÐ° ÑÑÐ°ÑÐµÐ¹
        this.$nextTick(() => {
            if (this.showArticleEditor && !this.quillInstance) {
                this.initQuillEditor();
            }
        });
    },

    computed: {
        notificationStyle() {
            if (!this.notification) {
                return {};
            }

            if (!this.debugPanelEnabled) {
                return { bottom: '2rem', right: '2rem' };
            }

            return {
                bottom: '2rem',
                right: 'calc(360px + 3rem)'
            };
        }
    },

    watch: {
        'pageData.status'(newVal) {
            // Ensure pages not published cannot remain in the menu
            if (newVal !== 'published') {
                if (this.pageSettings && this.pageSettings.showInMenu) {
                    this.debugMsg('Ð¡ÑÐ°ÑÑÑ ÑÑÑÐ°Ð½Ð¸ÑÑ Ð¸Ð·Ð¼ÐµÐ½ÑÐ½ Ð½Ð° Ð½Ðµ Ð¾Ð¿ÑÐ±Ð»Ð¸ÐºÐ¾Ð²Ð°Ð½Ð½ÑÐ¹ â ÑÐ½Ð¸Ð¼Ð°ÐµÐ¼ ÑÐ»Ð°Ð¶Ð¾Ðº Ð¿Ð¾ÐºÐ°Ð·Ð° Ð² Ð¼ÐµÐ½Ñ', 'info', { status: newVal });
                }
                this.pageSettings.showInMenu = false;
            }
        }
    },

    methods: {
        // ===== UTILITIES =====

        generateBlockId() {
            return `tmp_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
        },

        // ===== DEBUG PANEL =====

        debugMsg(message, type = 'info', payload = null) {
            const consoleMethod = type === 'error'
                ? 'error'
                : type === 'warning'
                    ? 'warn'
                    : 'log';

            if (payload !== null && payload !== undefined) {
                console[consoleMethod](`[DEBUG] ${message}`, payload);
            } else {
                console[consoleMethod](`[DEBUG] ${message}`);
            }

            if (!this.debugPanelEnabled) {
                return;
            }

            const entry = {
                id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                time: new Date().toLocaleTimeString('ru-RU'),
                type,
                message,
                payload: this.stringifyPayload(payload)
            };

            this.debugMessages.push(entry);
            if (this.debugMessages.length > this.debugMaxMessages) {
                this.debugMessages.splice(0, this.debugMessages.length - this.debugMaxMessages);
            }

            if (!this.debugPanelCollapsed) {
                this.$nextTick(() => this.scrollDebugPanelToBottom());
            }
        },

        stringifyPayload(payload) {
            if (payload === null || payload === undefined) {
                return null;
            }

            if (typeof payload === 'string') {
                return payload.length > 2000 ? `${payload.slice(0, 2000)}â¦` : payload;
            }

            try {
                const plain = toPlainObject(payload);
                const json = JSON.stringify(plain, null, 2);
                return json.length > 2000 ? `${json.slice(0, 2000)}â¦` : json;
            } catch (error) {
                console.warn('ÐÐµ ÑÐ´Ð°Ð»Ð¾ÑÑ ÑÐµÑÐ¸Ð°Ð»Ð¸Ð·Ð¾Ð²Ð°ÑÑ payload Ð´Ð»Ñ debug panel', error);
                return String(payload);
            }
        },

        toggleDebugPanel() {
            this.debugPanelCollapsed = !this.debugPanelCollapsed;
            if (!this.debugPanelCollapsed) {
                this.$nextTick(() => this.scrollDebugPanelToBottom());
            }
        },

        clearDebugLog() {
            this.debugMessages = [];
            this.debugMsg('ÐÐ¾Ð³Ð¸ Ð¾ÑÐ¸ÑÐµÐ½Ñ', 'info');
        },

        scrollDebugPanelToBottom() {
            const container = this.$refs.debugPanelBody;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        // ===== BLOCK MANAGEMENT =====

        addBlock(blockDef) {
            const newBlock = {
                id: this.generateBlockId(),
                type: blockDef.type,
                customName: '', // ÐÑÑÑÐ¾Ðµ = Ð¸ÑÐ¿Ð¾Ð»ÑÐ·Ð¾Ð²Ð°ÑÑ Ð½Ð°Ð·Ð²Ð°Ð½Ð¸Ðµ Ð¿Ð¾ ÑÐ¼Ð¾Ð»ÑÐ°Ð½Ð¸Ñ
                data: JSON.parse(JSON.stringify(blockDef.defaultData))
            };

            this.blocks.push(newBlock);
            this.selectedBlockIndex = this.blocks.length - 1;
            this.selectedBlock = this.blocks[this.selectedBlockIndex];
            this.activeTab = 'block';

            this.showNotification('ÐÐ»Ð¾Ðº Ð´Ð¾Ð±Ð°Ð²Ð»ÐµÐ½', 'success');
            this.debugMsg('ÐÐ¾Ð±Ð°Ð²Ð»ÐµÐ½ Ð±Ð»Ð¾Ðº Ð¸Ð· Ð±Ð¸Ð±Ð»Ð¸Ð¾ÑÐµÐºÐ¸', 'info', { type: blockDef.type });
        },

        selectBlock(index) {
            this.selectedBlockIndex = index;
            this.selectedBlock = this.blocks[index];
            this.activeTab = 'block';
            this.debugMsg('ÐÑÐ±ÑÐ°Ð½ Ð±Ð»Ð¾Ðº', 'info', { index, type: this.selectedBlock?.type });
        },

        moveBlockUp(index) {
            if (index === 0) return;
            const blocks = this.blocks;
            [blocks[index - 1], blocks[index]] = [blocks[index], blocks[index - 1]];
            this.selectedBlockIndex = index - 1;
            this.debugMsg('ÐÐ»Ð¾Ðº Ð¿ÐµÑÐµÐ¼ÐµÑÑÐ½ Ð²Ð²ÐµÑÑ', 'info', { from: index, to: index - 1 });
        },

        moveBlockDown(index) {
            if (index === this.blocks.length - 1) return;
            const blocks = this.blocks;
            [blocks[index], blocks[index + 1]] = [blocks[index + 1], blocks[index]];
            this.selectedBlockIndex = index + 1;
            this.debugMsg('ÐÐ»Ð¾Ðº Ð¿ÐµÑÐµÐ¼ÐµÑÑÐ½ Ð²Ð½Ð¸Ð·', 'info', { from: index, to: index + 1 });
        },

        duplicateBlock(index) {
            const blockCopy = JSON.parse(JSON.stringify(this.blocks[index]));
            blockCopy.id = this.generateBlockId();
            this.blocks.splice(index + 1, 0, blockCopy);
            this.showNotification('ÐÐ»Ð¾Ðº Ð¿ÑÐ¾Ð´ÑÐ±Ð»Ð¸ÑÐ¾Ð²Ð°Ð½', 'success');
            this.debugMsg('ÐÐ»Ð¾Ðº Ð¿ÑÐ¾Ð´ÑÐ±Ð»Ð¸ÑÐ¾Ð²Ð°Ð½', 'success', { index });
        },

        removeBlock(index) {
            if (confirm('Ð£Ð´Ð°Ð»Ð¸ÑÑ ÑÑÐ¾Ñ Ð±Ð»Ð¾Ðº?')) {
                this.blocks.splice(index, 1);
                if (this.selectedBlockIndex === index) {
                    this.selectedBlock = null;
                    this.selectedBlockIndex = null;
                }
                this.showNotification('ÐÐ»Ð¾Ðº ÑÐ´Ð°Ð»ÑÐ½', 'success');
                this.debugMsg('ÐÐ»Ð¾Ðº ÑÐ´Ð°Ð»ÑÐ½', 'warning', { index });
            }
        },

        // ===== DRAG & DROP =====

        onLibraryBlockDragStart(event, blockDef) {
            this.draggedBlockType = blockDef;
            this.isDraggingFromLibrary = true;

            // ÐÐ¾Ð±Ð°Ð²Ð»ÑÐµÐ¼ ÐºÐ»Ð°ÑÑ Ð´Ð»Ñ Ð²Ð¸Ð·ÑÐ°Ð»ÑÐ½Ð¾Ð³Ð¾ feedback
            event.target.classList.add('dragging');

            // Ð£ÑÑÐ°Ð½Ð°Ð²Ð»Ð¸Ð²Ð°ÐµÐ¼ Ð´Ð°Ð½Ð½ÑÐµ Ð´Ð»Ñ Ð¿ÐµÑÐµÐ½Ð¾ÑÐ°
            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData('text/plain', blockDef.type);
        },

        onLibraryBlockDragEnd(event) {
            // Ð£Ð±Ð¸ÑÐ°ÐµÐ¼ ÐºÐ»Ð°ÑÑ dragging
            event.target.classList.remove('dragging');
            this.isDraggingFromLibrary = false;
            this.draggedBlockType = null;
        },

        onPreviewDragOver(event) {
            if (this.isDraggingFromLibrary) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';

                // ÐÐ¾Ð±Ð°Ð²Ð»ÑÐµÐ¼ ÐºÐ»Ð°ÑÑ Ð´Ð»Ñ Ð¿Ð¾Ð´ÑÐ²ÐµÑÐºÐ¸ Ð¾Ð±Ð»Ð°ÑÑÐ¸
                const previewArea = event.currentTarget;
                previewArea.classList.add('drag-over');
            }
        },

        onPreviewDragLeave(event) {
            // Ð£Ð±Ð¸ÑÐ°ÐµÐ¼ Ð¿Ð¾Ð´ÑÐ²ÐµÑÐºÑ Ð¾Ð±Ð»Ð°ÑÑÐ¸
            const previewArea = event.currentTarget;
            previewArea.classList.remove('drag-over');
        },

        onPreviewDrop(event) {
            event.preventDefault();

            // Ð£Ð±Ð¸ÑÐ°ÐµÐ¼ Ð¿Ð¾Ð´ÑÐ²ÐµÑÐºÑ
            const previewArea = event.currentTarget;
            previewArea.classList.remove('drag-over');

            if (this.isDraggingFromLibrary && this.draggedBlockType) {
                // ÐÐ¾Ð±Ð°Ð²Ð»ÑÐµÐ¼ Ð±Ð»Ð¾Ðº
                this.addBlock(this.draggedBlockType);
                this.showNotification(`ÐÐ»Ð¾Ðº "${this.draggedBlockType.name}" Ð´Ð¾Ð±Ð°Ð²Ð»ÐµÐ½`, 'success');
            }

            this.isDraggingFromLibrary = false;
            this.draggedBlockType = null;
        },

        // Drag & Drop Ð´Ð»Ñ ÑÐ¾ÑÑÐ¸ÑÐ¾Ð²ÐºÐ¸ Ð±Ð»Ð¾ÐºÐ¾Ð²
        onBlockDragStart(event, index) {
            this.draggedBlockIndex = index;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', index.toString());
        },

        onBlockDragEnd(event) {
            this.draggedBlockIndex = null;
            this.dragOverBlockIndex = null;

            // Ð£Ð±Ð¸ÑÐ°ÐµÐ¼ Ð²ÑÐµ ÐºÐ»Ð°ÑÑÑ Ð¿Ð¾Ð´ÑÐ²ÐµÑÐºÐ¸
            document.querySelectorAll('.block-item').forEach(el => {
                el.classList.remove('drag-over-top', 'drag-over-bottom');
            });
        },

        onBlockDragOver(event, targetIndex) {
            event.preventDefault();

            if (this.draggedBlockIndex === null || this.draggedBlockIndex === targetIndex) {
                return;
            }

            event.dataTransfer.dropEffect = 'move';

            // Ð£Ð±Ð¸ÑÐ°ÐµÐ¼ Ð²ÑÐµ ÐºÐ»Ð°ÑÑÑ Ð¿Ð¾Ð´ÑÐ²ÐµÑÐºÐ¸
            document.querySelectorAll('.block-item').forEach(el => {
                el.classList.remove('drag-over-top', 'drag-over-bottom');
            });

            // ÐÐ¿ÑÐµÐ´ÐµÐ»ÑÐµÐ¼ ÐºÑÐ´Ð° Ð²ÑÑÐ°Ð²Ð»ÑÑÑ: ÑÐ²ÐµÑÑÑ Ð¸Ð»Ð¸ ÑÐ½Ð¸Ð·Ñ
            const targetElement = event.currentTarget;
            const rect = targetElement.getBoundingClientRect();
            const midpoint = rect.top + rect.height / 2;

            if (event.clientY < midpoint) {
                // ÐÑÑÐ°Ð²ÐºÐ° ÑÐ²ÐµÑÑÑ
                targetElement.classList.add('drag-over-top');
                this.dragOverBlockIndex = targetIndex;
            } else {
                // ÐÑÑÐ°Ð²ÐºÐ° ÑÐ½Ð¸Ð·Ñ
                targetElement.classList.add('drag-over-bottom');
                this.dragOverBlockIndex = targetIndex + 1;
            }
        },

        onBlockDrop(event, targetIndex) {
            event.preventDefault();
            event.stopPropagation();

            if (this.draggedBlockIndex === null || this.draggedBlockIndex === targetIndex) {
                return;
            }

            // ÐÐ¿ÑÐµÐ´ÐµÐ»ÑÐµÐ¼ Ð¿Ð¾Ð·Ð¸ÑÐ¸Ñ Ð²ÑÑÐ°Ð²ÐºÐ¸
            const targetElement = event.currentTarget;
            const rect = targetElement.getBoundingClientRect();
            const midpoint = rect.top + rect.height / 2;

            let insertIndex;
            if (event.clientY < midpoint) {
                insertIndex = targetIndex;
            } else {
                insertIndex = targetIndex + 1;
            }

            // ÐÐµÑÐµÐ¼ÐµÑÐ°ÐµÐ¼ Ð±Ð»Ð¾Ðº
            const draggedBlock = this.blocks[this.draggedBlockIndex];
            this.blocks.splice(this.draggedBlockIndex, 1);

            // ÐÐ¾ÑÑÐµÐºÑÐ¸ÑÑÐµÐ¼ Ð¸Ð½Ð´ÐµÐºÑ ÐµÑÐ»Ð¸ ÑÐ´Ð°Ð»Ð¸Ð»Ð¸ ÑÐ»ÐµÐ¼ÐµÐ½Ñ Ð²ÑÑÐµ
            if (this.draggedBlockIndex < insertIndex) {
                insertIndex--;
            }

            this.blocks.splice(insertIndex, 0, draggedBlock);

            // ÐÐ±Ð½Ð¾Ð²Ð»ÑÐµÐ¼ Ð²ÑÐ±ÑÐ°Ð½Ð½ÑÐ¹ Ð±Ð»Ð¾Ðº
            this.selectedBlockIndex = insertIndex;
            this.selectedBlock = this.blocks[insertIndex];

            // Ð£Ð±Ð¸ÑÐ°ÐµÐ¼ ÐºÐ»Ð°ÑÑÑ Ð¿Ð¾Ð´ÑÐ²ÐµÑÐºÐ¸
            document.querySelectorAll('.block-item').forEach(el => {
                el.classList.remove('drag-over-top', 'drag-over-bottom');
            });

            this.draggedBlockIndex = null;
            this.dragOverBlockIndex = null;

            this.showNotification('ÐÐ»Ð¾Ðº Ð¿ÐµÑÐµÐ¼ÐµÑÑÐ½', 'success');
        },

        applyTemplate(template) {
            if (confirm(`ÐÑÐ¸Ð¼ÐµÐ½Ð¸ÑÑ ÑÐ°Ð±Ð»Ð¾Ð½ "${template.name}"?\n\nÐ¢ÐµÐºÑÑÐ¸Ðµ Ð±Ð»Ð¾ÐºÐ¸ Ð±ÑÐ´ÑÑ Ð·Ð°Ð¼ÐµÐ½ÐµÐ½Ñ.`)) {
                this.blocks = JSON.parse(JSON.stringify(template.blocks));

                // ÐÐ¾Ð±Ð°Ð²Ð»ÑÐµÐ¼ customName ÐµÑÐ»Ð¸ ÐµÐ³Ð¾ Ð½ÐµÑ
                this.blocks.forEach(block => {
                    if (!block.hasOwnProperty('customName')) {
                        block.customName = '';
                    }
                    if (!block.id) {
                        block.id = this.generateBlockId();
                    }
                });

                this.showTemplatesModal = false;
                this.selectedBlock = null;
                this.selectedBlockIndex = null;
                this.showNotification(`â¨ Ð¨Ð°Ð±Ð»Ð¾Ð½ "${template.name}" Ð¿ÑÐ¸Ð¼ÐµÐ½ÑÐ½!`, 'success');
            }
        },

        // ===== BLOCK PREVIEW =====

        showBlockPreview(blockDef) {
            this.previewBlock = blockDef;
        },

        renderPreviewBlock() {
            if (!this.previewBlock) return '';

            const tempBlock = {
                id: this.generateBlockId(),
                type: this.previewBlock.type,
                data: JSON.parse(JSON.stringify(this.previewBlock.defaultData))
            };

            return this.renderBlock(tempBlock);
        },

        // ===== ARTICLE EDITOR =====

        async openArticleEditor() {
            this.showArticleEditor = true;

            // ÐÑÐ¾Ð²ÐµÑÑÐµÐ¼, ÐµÑÑÑ Ð»Ð¸ ÑÐ¶Ðµ ÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð½Ð°Ñ ÑÑÐ°ÑÑÑ Ð² text-block
            const textBlock = this.blocks.find(b => b.type === 'text-block' && b.data.containerStyle === 'article');
            if (textBlock && textBlock.data.content) {
                this.articleHtml = textBlock.data.content;
            }

            // ÐÐµÐ½ÑÐµÐ¼ URL
            window.history.pushState({}, '', window.location.pathname + '#article-editor');

            this.showNotification('ÐÑÐºÑÑÐ²Ð°Ñ ÑÐµÐ´Ð°ÐºÑÐ¾Ñ ÑÑÐ°ÑÐµÐ¹...', 'success');
        },


        closeArticleEditor() {
            if (confirm('ÐÐ°ÐºÑÑÑÑ ÑÐµÐ´Ð°ÐºÑÐ¾Ñ? ÐÐµÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð½ÑÐµ Ð¸Ð·Ð¼ÐµÐ½ÐµÐ½Ð¸Ñ Ð±ÑÐ´ÑÑ Ð¿Ð¾ÑÐµÑÑÐ½Ñ.')) {
                this.quillInstance = null;
                this.showArticleEditor = false;

                // ÐÐµÐ½ÑÐµÐ¼ URL Ð¾Ð±ÑÐ°ÑÐ½Ð¾
                window.history.pushState({}, '', window.location.pathname);
            }
        },

        async saveArticleAndClose() {
            if (this.quillInstance) {
                try {
                    // ÐÐ¾Ð»ÑÑÐ°ÐµÐ¼ HTML Ð¸Ð· Quill
                    this.articleHtml = this.quillInstance.root.innerHTML;

                    // ÐÐ¾Ð½Ð²ÐµÑÑÐ¸ÑÑÐµÐ¼ HTML Ð² Ð½Ð°ÑÐ¸ Ð±Ð»Ð¾ÐºÐ¸
                    this.convertHtmlToBlocks(this.articleHtml);

                    // Ð¡Ð¾ÑÑÐ°Ð½ÑÐµÐ¼ Ð² localStorage
                    this.saveToLocalStorage();

                    // ÐÐ°ÐºÑÑÐ²Ð°ÐµÐ¼ ÑÐµÐ´Ð°ÐºÑÐ¾Ñ
                    this.quillInstance = null;
                    this.showArticleEditor = false;

                    // ÐÐµÐ½ÑÐµÐ¼ URL Ð¾Ð±ÑÐ°ÑÐ½Ð¾
                    window.history.pushState({}, '', window.location.pathname);

                    this.showNotification('â Ð¡ÑÐ°ÑÑÑ ÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð°!', 'success');
                } catch (e) {
                    console.error('Saving error:', e);
                    this.showNotification('ÐÑÐ¸Ð±ÐºÐ° ÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð¸Ñ', 'error');
                }
            }
        },

        async initQuillEditor() {
            if (!window.Quill) {
                console.error('Quill not loaded');
                return;
            }

            // Ð ÐµÐ³Ð¸ÑÑÑÐ¸ÑÑÐµÐ¼ Ð¼Ð¾Ð´ÑÐ»Ñ ImageResize ÐµÑÐ»Ð¸ Ð´Ð¾ÑÑÑÐ¿ÐµÐ½
            if (window.ImageResize) {
                Quill.register('modules/imageResize', window.ImageResize.default);
            }

            // ÐÐ°ÑÑÐ¾Ð¼Ð½ÑÐ¹ Ð¾Ð±ÑÐ°Ð±Ð¾ÑÑÐ¸Ðº Ð´Ð»Ñ Ð·Ð°Ð³ÑÑÐ·ÐºÐ¸ Ð¸Ð·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ð¹
            const imageHandler = () => {
                const input = document.createElement('input');
                input.setAttribute('type', 'file');
                input.setAttribute('accept', 'image/*');
                input.click();

                input.onchange = async () => {
                    const file = input.files[0];
                    if (!file) return;

                    const formData = new FormData();
                    formData.append('file', file);

                    try {
                        const response = await fetch('upload.php', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success && data.url) {
                            const range = this.quillInstance.getSelection();
                            this.quillInstance.insertEmbed(range.index, 'image', data.url);
                        } else {
                            this.showNotification('ÐÑÐ¸Ð±ÐºÐ° Ð·Ð°Ð³ÑÑÐ·ÐºÐ¸ Ð¸Ð·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ñ', 'error');
                        }
                    } catch (error) {
                        console.error('Upload error:', error);
                        this.showNotification('ÐÑÐ¸Ð±ÐºÐ° Ð·Ð°Ð³ÑÑÐ·ÐºÐ¸ Ð¸Ð·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ñ', 'error');
                    }
                };
            };

            try {
                this.quillInstance = new Quill('#quill-editor', {
                    theme: 'snow',
                    modules: {
                        toolbar: {
                            container: [
                                [{ 'header': [1, 2, 3, 4, false] }],
                                ['bold', 'italic', 'strike'],
                                ['link'],
                                [{ 'align': [] }],
                                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                ['blockquote'],
                                ['image'],
                                ['clean']
                            ],
                            handlers: {
                                image: imageHandler
                            }
                        },
                        imageResize: {
                            modules: ['Resize', 'DisplaySize']
                        }
                    },
                    placeholder: 'ÐÐ°ÑÐ½Ð¸ÑÐµ Ð¿Ð¸ÑÐ°ÑÑ ÑÑÐ°ÑÑÑ...'
                });

                // ÐÐ°Ð³ÑÑÐ¶Ð°ÐµÐ¼ ÑÑÑÐµÑÑÐ²ÑÑÑÐ¸Ð¹ ÐºÐ¾Ð½ÑÐµÐ½Ñ ÐµÑÐ»Ð¸ ÐµÑÑÑ
                if (this.articleHtml) {
                    this.quillInstance.root.innerHTML = this.articleHtml;
                }

                // ÐÐ¾Ð±Ð°Ð²Ð»ÑÐµÐ¼ ÑÑÐ½ÐºÑÐ¸Ð¾Ð½Ð°Ð» Ð¿ÐµÑÐµÐ¼ÐµÑÐµÐ½Ð¸Ñ Ð¸Ð·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ð¹
                this.$nextTick(() => {
                    this.setupImageDragAndDrop();
                });

                this.showNotification('Ð ÐµÐ´Ð°ÐºÑÐ¾Ñ Ð³Ð¾ÑÐ¾Ð²!', 'success');
            } catch (error) {
                console.error('Quill init error:', error);
                this.showNotification('ÐÑÐ¸Ð±ÐºÐ° Ð¸Ð½Ð¸ÑÐ¸Ð°Ð»Ð¸Ð·Ð°ÑÐ¸Ð¸ ÑÐµÐ´Ð°ÐºÑÐ¾ÑÐ°', 'error');
            }
        },

        setupImageDragAndDrop() {
            const editor = document.querySelector('.ql-editor');
            if (!editor) return;

            let draggedImage = null;
            let startX = 0;
            let currentX = 0;
            let isDragging = false;
            let dragTimer = null;

            // ÐÐµÐ»Ð°ÐµÐ¼ Ð¸Ð·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ñ draggable
            editor.addEventListener('mousedown', (e) => {
                if (e.target.tagName === 'IMG') {
                    // ÐÑÐ¸ÑÐ°ÐµÐ¼ Ð¿ÑÐµÐ´ÑÐ´ÑÑÐ¸Ð¹ ÑÐ°Ð¹Ð¼ÐµÑ ÐµÑÐ»Ð¸ Ð±ÑÐ»
                    if (dragTimer) clearTimeout(dragTimer);

                    draggedImage = e.target;
                    startX = e.clientX;
                    currentX = e.clientX;
                    isDragging = false;

                    // ÐÐ¸Ð·ÑÐ°Ð»ÑÐ½ÑÐ¹ feedback - Ð¼ÐµÐ½ÑÐµÐ¼ ÐºÑÑÑÐ¾Ñ
                    draggedImage.style.cursor = 'grabbing';
                    e.preventDefault();
                }
            });

            document.addEventListener('mousemove', (e) => {
                if (!draggedImage) return;

                currentX = e.clientX;
                const deltaX = Math.abs(e.clientX - startX);

                // ÐÐ°ÑÐ¸Ð½Ð°ÐµÐ¼ drag ÐµÑÐ»Ð¸ ÑÐ´Ð²Ð¸Ð½ÑÐ»Ð¸ Ð±Ð¾Ð»ÑÑÐµ 10px Ð¿Ð¾ Ð³Ð¾ÑÐ¸Ð·Ð¾Ð½ÑÐ°Ð»Ð¸
                if (deltaX > 10 && !isDragging) {
                    isDragging = true;
                    // ÐÐ¸Ð·ÑÐ°Ð»ÑÐ½ÑÐ¹ feedback - Ð´ÐµÐ»Ð°ÐµÐ¼ ÐºÐ°ÑÑÐ¸Ð½ÐºÑ Ð¿Ð¾Ð»ÑÐ¿ÑÐ¾Ð·ÑÐ°ÑÐ½Ð¾Ð¹
                    draggedImage.style.opacity = '0.5';
                    draggedImage.style.transform = 'scale(0.95)';
                }
            });

            document.addEventListener('mouseup', (e) => {
                if (!draggedImage) return;

                if (isDragging) {
                    const editorRect = editor.getBoundingClientRect();
                    const editorWidth = editorRect.width;
                    const mouseX = currentX - editorRect.left;

                    // ÐÐ¿ÑÐµÐ´ÐµÐ»ÑÐµÐ¼ Ð¿Ð¾Ð·Ð¸ÑÐ¸Ñ Ð¿Ð¾ ÑÑÐµÑÐ¸ ÑÐ¸ÑÐ¸Ð½Ñ ÑÐµÐ´Ð°ÐºÑÐ¾ÑÐ°
                    const leftThird = editorWidth / 3;
                    const rightThird = editorWidth * 2 / 3;

                    // Ð¡Ð¾ÑÑÐ°Ð½ÑÐµÐ¼ ÑÐµÐºÑÑÑÑ ÑÐ¸ÑÐ¸Ð½Ñ ÐºÐ°ÑÑÐ¸Ð½ÐºÐ¸
                    const currentWidth = draggedImage.style.width || '';
                    const currentMaxWidth = draggedImage.style.maxWidth || '';

                    // Ð£Ð±Ð¸ÑÐ°ÐµÐ¼ Ð²ÑÐµ ÑÑÐ¸Ð»Ð¸ Ð¿Ð¾Ð·Ð¸ÑÐ¸Ð¾Ð½Ð¸ÑÐ¾Ð²Ð°Ð½Ð¸Ñ
                    draggedImage.style.float = '';
                    draggedImage.style.marginLeft = '';
                    draggedImage.style.marginRight = '';
                    draggedImage.style.marginTop = '';
                    draggedImage.style.marginBottom = '';
                    draggedImage.style.display = '';

                    if (mouseX < leftThird) {
                        // Ð¡Ð»ÐµÐ²Ð°
                        draggedImage.style.float = 'left';
                        draggedImage.style.marginRight = '2rem';
                        draggedImage.style.marginBottom = '1.5rem';
                        draggedImage.style.marginTop = '0.5rem';
                        this.showNotification('ÐÐ·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ðµ ÑÐ»ÐµÐ²Ð°', 'success');
                    } else if (mouseX > rightThird) {
                        // Ð¡Ð¿ÑÐ°Ð²Ð°
                        draggedImage.style.float = 'right';
                        draggedImage.style.marginLeft = '2rem';
                        draggedImage.style.marginBottom = '1.5rem';
                        draggedImage.style.marginTop = '0.5rem';
                        this.showNotification('ÐÐ·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ðµ ÑÐ¿ÑÐ°Ð²Ð°', 'success');
                    } else {
                        // ÐÐ¾ ÑÐµÐ½ÑÑÑ
                        draggedImage.style.display = 'block';
                        draggedImage.style.marginLeft = 'auto';
                        draggedImage.style.marginRight = 'auto';
                        draggedImage.style.marginTop = '2rem';
                        draggedImage.style.marginBottom = '2rem';
                        this.showNotification('ÐÐ·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ðµ Ð¿Ð¾ ÑÐµÐ½ÑÑÑ', 'success');
                    }

                    // ÐÐ¾ÑÑÑÐ°Ð½Ð°Ð²Ð»Ð¸Ð²Ð°ÐµÐ¼ ÑÐ¸ÑÐ¸Ð½Ñ ÐºÐ°ÑÑÐ¸Ð½ÐºÐ¸
                    if (currentWidth) draggedImage.style.width = currentWidth;
                    if (currentMaxWidth) draggedImage.style.maxWidth = currentMaxWidth;
                }

                // ÐÐ¾ÑÑÑÐ°Ð½Ð°Ð²Ð»Ð¸Ð²Ð°ÐµÐ¼ Ð²Ð½ÐµÑÐ½Ð¸Ð¹ Ð²Ð¸Ð´
                if (draggedImage) {
                    draggedImage.style.opacity = '1';
                    draggedImage.style.transform = '';
                    draggedImage.style.cursor = 'move';
                }

                draggedImage = null;
                isDragging = false;
            });
        },
        convertHtmlToBlocks(html) {
            // ÐÑÐµÐ¼ ÑÑÑÐµÑÑÐ²ÑÑÑÐ¸Ð¹ text-block ÑÐ¾ ÑÑÐ¸Ð»ÐµÐ¼ article
            const existingTextBlockIndex = this.blocks.findIndex(b =>
                b.type === 'text-block' && b.data.containerStyle === 'article'
            );

            if (existingTextBlockIndex !== -1) {
                // ÐÐ±Ð½Ð¾Ð²Ð»ÑÐµÐ¼ ÑÑÑÐµÑÑÐ²ÑÑÑÐ¸Ð¹ Ð±Ð»Ð¾Ðº
                this.blocks[existingTextBlockIndex].data.content = html;
                if (!this.blocks[existingTextBlockIndex].id) {
                    this.blocks[existingTextBlockIndex].id = this.generateBlockId();
                }
            } else {
                // ÐÐ¾Ð±Ð°Ð²Ð»ÑÐµÐ¼ Ð½Ð¾Ð²ÑÐ¹ Ð±Ð»Ð¾Ðº Ð² ÐºÐ¾Ð½ÐµÑ (Ð½Ðµ Ð·Ð°Ð¼ÐµÐ½ÑÐµÐ¼ Ð²ÑÐµ Ð±Ð»Ð¾ÐºÐ¸)
                this.blocks.push({
                    id: this.generateBlockId(),
                    type: 'text-block',
                    data: {
                        title: '',
                        content: html,
                        alignment: 'left',
                        containerStyle: 'article'
                    }
                });
            }
        },

        // ===== HELPERS =====

        getBlockIcon(type) {
            const def = this.blockDefinitions.find(b => b.type === type);
            return def ? def.icon : '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#008d8d" viewBox="0 0 256 256"><path d="M223.68,66.15,135.68,18a15.88,15.88,0,0,0-15.36,0l-88,48.17a16,16,0,0,0-8.32,14v95.64a16,16,0,0,0,8.32,14l88,48.17a15.88,15.88,0,0,0,15.36,0l88-48.17a16,16,0,0,0,8.32-14V80.18A16,16,0,0,0,223.68,66.15ZM128,32l80.34,44L128,120,47.66,76ZM40,90l80,43.78v85.79L40,175.82Zm176,85.78h0l-80,43.79V133.82l80-43.78Z"/></svg>';
        },

        getBlockName(type) {
            const def = this.blockDefinitions.find(b => b.type === type);
            return def ? def.name : type;
        },

        escape(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

            escapeAttr(str) {
                if (str === null || str === undefined) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;')
                    .replace(/\n/g, '')
                    .replace(/\r/g, '');
            },

        nl2br(text) {
            if (!text) return '';
            return text.replace(/\n/g, '<br>');
        },

        formatLabel(key) {
            // ÐÑÐµÐ¾Ð±ÑÐ°Ð·ÑÐµÑ camelCase Ð² ÑÐ¸ÑÐ°ÐµÐ¼ÑÐ¹ ÑÐµÐºÑÑ
            const labels = {
                'title': 'ÐÐ°Ð³Ð¾Ð»Ð¾Ð²Ð¾Ðº',
                'subtitle': 'ÐÐ¾Ð´Ð·Ð°Ð³Ð¾Ð»Ð¾Ð²Ð¾Ðº',
                'text': 'Ð¢ÐµÐºÑÑ',
                'content': 'ÐÐ¾Ð½ÑÐµÐ½Ñ',
                'backgroundImage': 'Ð¤Ð¾Ð½Ð¾Ð²Ð¾Ðµ Ð¸Ð·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ðµ',
                'buttonText': 'Ð¢ÐµÐºÑÑ ÐºÐ½Ð¾Ð¿ÐºÐ¸',
                'buttonLink': 'Ð¡ÑÑÐ»ÐºÐ° ÐºÐ½Ð¾Ð¿ÐºÐ¸',
                'image': 'ÐÐ·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ðµ (URL)',
                'url': 'URL Ð¸Ð·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ñ',
                'alt': 'Alt ÑÐµÐºÑÑ',
                'caption': 'ÐÐ¾Ð´Ð¿Ð¸ÑÑ',
                'alignment': 'ÐÑÑÐ°Ð²Ð½Ð¸Ð²Ð°Ð½Ð¸Ðµ',
                'width': 'Ð¨Ð¸ÑÐ¸Ð½Ð°',
                'height': 'ÐÑÑÐ¾ÑÐ°',
                'borderRadius': 'Ð¡ÐºÑÑÐ³Ð»ÐµÐ½Ð¸Ðµ ÑÐ³Ð»Ð¾Ð²',
                'columns': 'ÐÐ¾Ð»Ð¸ÑÐµÑÑÐ²Ð¾ ÐºÐ¾Ð»Ð¾Ð½Ð¾Ðº',
                'cards': 'ÐÐ°ÑÑÐ¾ÑÐºÐ¸',
                'items': 'Ð­Ð»ÐµÐ¼ÐµÐ½ÑÑ',
                'paragraphs': 'ÐÐ°ÑÐ°Ð³ÑÐ°ÑÑ',
                'messages': 'Ð¡Ð¾Ð¾Ð±ÑÐµÐ½Ð¸Ñ',
                'buttons': 'ÐÐ½Ð¾Ð¿ÐºÐ¸',
                'headerTitle': 'ÐÐ°Ð³Ð¾Ð»Ð¾Ð²Ð¾Ðº ÑÐ°ÑÐ°',
                'icon': 'SVG Ð¸ÐºÐ¾Ð½ÐºÐ°',
                'link': 'Ð¡ÑÑÐ»ÐºÐ°',
                'question': 'ÐÐ¾Ð¿ÑÐ¾Ñ',
                'answer': 'ÐÑÐ²ÐµÑ',
                'type': 'Ð¢Ð¸Ð¿',
                'containerStyle': 'Ð¡ÑÐ¸Ð»Ñ ÐºÐ¾Ð½ÑÐµÐ¹Ð½ÐµÑÐ°',
                'style': 'Ð¡ÑÐ¸Ð»Ñ'
            };
            return labels[key] || key;
        },

        hasSubstring(fieldKey, substrings) {
            if (!fieldKey) {
                return false;
            }

            const normalizedKey = String(fieldKey).toLowerCase();
            return substrings.some(substring => normalizedKey.includes(String(substring).toLowerCase()));
        },

        isDimensionKey(fieldKey) {
            return this.hasSubstring(fieldKey, ['height', 'width']);
        },

        isRichTextKey(fieldKey) {
            return this.hasSubstring(fieldKey, ['content', 'text', 'message', 'subtitle', 'description']);
        },

        isImageKey(fieldKey) {
            return this.hasSubstring(fieldKey, ['image']);
        },

        addArrayItem(key) {
            if (!this.selectedBlock) return;

            const array = this.selectedBlock.data[key];
            if (!Array.isArray(array)) return;

            // ÐÐ¿ÑÐµÐ´ÐµÐ»ÑÐµÐ¼ ÑÑÑÑÐºÑÑÑÑ Ð½Ð¾Ð²Ð¾Ð³Ð¾ ÑÐ»ÐµÐ¼ÐµÐ½ÑÐ° Ð½Ð° Ð¾ÑÐ½Ð¾Ð²Ðµ ÑÑÑÐµÑÑÐ²ÑÑÑÐ¸Ñ
            if (array.length > 0) {
                const template = array[0];
                const newItem = {};
                for (const k in template) {
                    if (typeof template[k] === 'string') {
                        newItem[k] = '';
                    } else if (typeof template[k] === 'number') {
                        newItem[k] = 0;
                    } else {
                        newItem[k] = template[k];
                    }
                }
                array.push(newItem);
            } else {
                // ÐÐµÑÐ¾Ð»ÑÐ½ÑÐµ ÑÑÑÑÐºÑÑÑÑ Ð´Ð»Ñ ÑÐ°Ð·Ð½ÑÑ ÑÐ¸Ð¿Ð¾Ð²
                if (key === 'cards' && this.selectedBlock.type === 'service-cards') {
                    array.push({ icon: '', title: 'ÐÐ¾Ð²Ð°Ñ ÐºÐ°ÑÑÐ¾ÑÐºÐ°', text: 'ÐÐ¿Ð¸ÑÐ°Ð½Ð¸Ðµ' });
                } else if (key === 'cards' && this.selectedBlock.type === 'article-cards') {
                    array.push({ image: '', title: 'ÐÐ¾Ð²Ð°Ñ ÑÑÐ°ÑÑÑ', text: 'ÐÐ¿Ð¸ÑÐ°Ð½Ð¸Ðµ', link: '#' });
                } else if (key === 'paragraphs') {
                    array.push('ÐÐ¾Ð²ÑÐ¹ Ð¿Ð°ÑÐ°Ð³ÑÐ°Ñ');
                } else if (key === 'messages') {
                    array.push({ type: 'bot', text: 'ÐÐ¾Ð²Ð¾Ðµ ÑÐ¾Ð¾Ð±ÑÐµÐ½Ð¸Ðµ' });
                } else if (key === 'buttons') {
                    array.push({ text: 'ÐÐ½Ð¾Ð¿ÐºÐ°' });
                } else if (key === 'items') {
                    array.push({ question: 'ÐÐ¾Ð¿ÑÐ¾Ñ?', answer: 'ÐÑÐ²ÐµÑ' });
                } else {
                    array.push({ text: 'ÐÐ¾Ð²ÑÐ¹ ÑÐ»ÐµÐ¼ÐµÐ½Ñ' });
                }
            }

            this.showNotification('Ð­Ð»ÐµÐ¼ÐµÐ½Ñ Ð´Ð¾Ð±Ð°Ð²Ð»ÐµÐ½', 'success');
        },

        removeArrayItem(key, index) {
            if (!this.selectedBlock) return;
            const array = this.selectedBlock.data[key];
            if (!Array.isArray(array)) return;

            if (confirm('Ð£Ð´Ð°Ð»Ð¸ÑÑ ÑÑÐ¾Ñ ÑÐ»ÐµÐ¼ÐµÐ½Ñ?')) {
                array.splice(index, 1);
                this.showNotification('Ð­Ð»ÐµÐ¼ÐµÐ½Ñ ÑÐ´Ð°Ð»ÑÐ½', 'success');
            }
        },

        // ===== RENDER METHODS =====

        renderBlock(block) {
            if (!block.id) {
                // ÐÐ²ÑÐ¾Ð¼Ð°ÑÐ¸ÑÐµÑÐºÐ¸ Ð½Ð°Ð·Ð½Ð°ÑÐ°ÐµÐ¼ Ð²ÑÐµÐ¼ÐµÐ½Ð½ÑÐ¹ ID, ÑÑÐ¾Ð±Ñ Ð½Ðµ Ð»Ð¾Ð¼Ð°ÑÑ Ð¿ÑÐµÐ´Ð¿ÑÐ¾ÑÐ¼Ð¾ÑÑ
                const tmpId = this.generateBlockId ? this.generateBlockId() : `tmp_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
                block.id = tmpId;
                this.debugMsg('Ð£ Ð±Ð»Ð¾ÐºÐ° Ð¾ÑÑÑÑÑÑÐ²Ð¾Ð²Ð°Ð» id â Ð½Ð°Ð·Ð½Ð°ÑÐµÐ½ Ð²ÑÐµÐ¼ÐµÐ½Ð½ÑÐ¹ Ð´Ð»Ñ ÑÐµÐ½Ð´ÐµÑÐ°', 'warning', { type: block.type, id: tmpId });
            }

            const methods = {
                'main-screen': this.renderMainScreen,
                'page-header': this.renderPageHeader,
                'service-cards': this.renderServiceCards,
                'article-cards': this.renderArticleCards,
                'about-section': this.renderAboutSection,
                'text-block': this.renderTextBlock,
                'image-block': this.renderImageBlock,
                'blockquote': this.renderBlockquote,
                'button': this.renderButton,
                'section-title': this.renderSectionTitle,
                'section-divider': this.renderSectionDivider,
                'chat-bot': this.renderChatBot,
                'spacer': this.renderSpacer
            };

            return methods[block.type] ? methods[block.type](block) : '<div>Unknown block type</div>';
        },

        renderMainScreen(block) {
            const data = block.data || block;
            const bgImage = data.backgroundImage || 'https://images.unsplash.com/photo-1506929562872-bb421503ef21?q=80&w=2070&auto=format&fit=crop';
            const title = data.title || '';
            const text = data.text || '';
            const buttonText = data.buttonText || 'Ð£Ð·Ð½Ð°ÑÑ Ð±Ð¾Ð»ÑÑÐµ';
            const buttonLink = data.buttonLink || '#';

            return `
                <section class="hero" style="background-image: linear-gradient(rgba(3, 42, 73, 0.6), rgba(3, 42, 73, 0.6)), url('${this.escape(bgImage)}');">
                    <div class="container">
                        <h1 data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.title" data-block-type="${block.type}">${this.escape(title)}</h1>
                        <p data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.text" data-block-type="${block.type}">${this.escape(text)}</p>
                        <a href="${this.escape(buttonLink)}" class="btn btn-primary">${this.escape(buttonText)}</a>
                    </div>
                </section>
            `;
        },

        renderPageHeader(block) {
            const data = block.data || block;
            const title = data.title || 'ÐÐ°Ð³Ð¾Ð»Ð¾Ð²Ð¾Ðº';
            const subtitle = data.subtitle || '';

            return `
                <section class="page-header unified-background">
                    <div class="container">
                        <h2 data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.title" data-block-type="${block.type}">${this.escape(title)}</h2>
                        ${subtitle ? `<p class="sub-heading" data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.subtitle" data-block-type="${block.type}">${this.escape(subtitle)}</p>` : ''}
                    </div>
                </section>
            `;
        },

        renderServiceCards(block) {
            const data = block.data || block;
            const title = data.title || '';
            const subtitle = data.subtitle || '';
            const cards = data.cards || [];
            const columns = data.columns || 2;

            const cardsHtml = cards.map((card, idx) => `
                <div class="service-card">
                    <div class="icon">${this.escape(card.icon || '')}</div>
                    <h3 data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.cards[${idx}].title" data-block-type="${block.type}">${this.escape(card.title || '')}</h3>
                    <p data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.cards[${idx}].text" data-block-type="${block.type}">${this.escape(card.text || '')}</p>
                </div>
            `).join('');

            return `
                <section>
                    <div class="container">
                        ${title ? `<h2 class="text-center" data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.title" data-block-type="${block.type}">${this.escape(title)}</h2>` : ''}
                        ${subtitle ? `<p class="sub-heading text-center" data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.subtitle" data-block-type="${block.type}">${this.escape(subtitle)}</p>` : ''}
                        <div class="services-grid" style="grid-template-columns: repeat(${columns}, 1fr);">
                            ${cardsHtml}
                        </div>
                    </div>
                </section>
            `;
        },

        renderArticleCards(block) {
            const data = block.data || block;
            const title = data.title || '';
            const cards = data.cards || [];
            const columns = data.columns || 3;

            const cardsHtml = cards.map((card, idx) => {
                const rawImage = card.image || '';
                const imageUrl = this.buildMediaUrl(this.normalizeRelativeUrl(rawImage));
                return `
                <div class="article-card">
                    <img src="${this.escape(imageUrl)}" alt="${this.escape(card.title || '')}">
                    <div class="article-card-content">
                        <h3 data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.cards[${idx}].title" data-block-type="${block.type}">${this.escape(card.title || '')}</h3>
                        <p data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.cards[${idx}].text" data-block-type="${block.type}">${this.escape(card.text || '')}</p>
                        <a href="${this.escape(card.link || '#')}">Ð§Ð¸ÑÐ°ÑÑ Ð´Ð°Ð»ÐµÐµ &rarr;</a>
                    </div>
                </div>
            `;
            }).join('');

            return `
                <section style="padding-top: ${title ? '6rem' : '0'};">
                    <div class="container">
                        ${title ? `<h2 data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.title" data-block-type="${block.type}">${this.escape(title)}</h2>` : ''}
                        <div class="articles-grid" style="grid-template-columns: repeat(${columns}, 1fr);">
                            ${cardsHtml}
                        </div>
                    </div>
                </section>
            `;
        },

        renderAboutSection(block) {
            const data = block.data || {};
            const rawImage = data.image || '';
            const image = rawImage ? this.buildMediaUrl(this.normalizeRelativeUrl(rawImage)) : 'https://placehold.co/600x720/E9EAF2/032A49?text=Photo';
            const title = data.title || 'Ð ÑÐµÐ±Ðµ';
            const paragraphs = data.paragraphs || [];

            const paragraphsHtml = paragraphs.map((p, idx) => {
                const text = this.escape(typeof p === 'string' ? p : p.text || '');
                return `<p data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.paragraphs[${idx}]" data-block-type="${block.type}">${text}</p>`;
            }).join('');

            return `
                <section class="about-section">
                    <div class="container">
                        <div class="about-me">
                            <img src="${this.escape(image)}" alt="${this.escape(title)}" class="about-me-photo">
                            <div>
                                <h2 data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.title" data-block-type="${block.type}">${this.escape(title)}</h2>
                                ${paragraphsHtml}
                            </div>
                        </div>
                    </div>
                </section>
            `;
        },

        renderTextBlock(block) {
            const data = block.data || block;
            const title = data.title || '';
            const content = data.content || '';
            const alignment = data.alignment || 'left';
            const containerStyle = data.containerStyle || 'normal';

            const containerClass = containerStyle === 'article' ? 'article-container' : 'container';
            const alignClass = alignment === 'center' ? 'text-center' : alignment === 'right' ? 'text-right' : 'text-left';

            return `
                <section class="article-block">
                    <div class="${containerClass}">
                        <div class="article-content ${alignClass}">
                            ${title ? `<h2 data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.title" data-block-type="${block.type}">${this.escape(title)}</h2>` : ''}
                                        <p data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.content" data-block-type="${block.type}">${this.escape(content)}</p>
                        </div>
                    </div>
                </section>
            `;
        },

        renderImageBlock(block) {
            const data = block.data || block;
            const rawUrl = data.url || '';
            const url = rawUrl ? this.buildMediaUrl(this.normalizeRelativeUrl(rawUrl)) : 'https://via.placeholder.com/800x400';
            const alt = data.alt || '';
            const caption = data.caption || '';
            const alignment = data.alignment || 'center';
            const width = data.width || '100%';
            const borderRadius = data.borderRadius || '12px';

            let imageClass = '';
            let imageStyle = `border-radius: ${borderRadius};`;

            if (alignment === 'float-left') {
                imageClass = 'article-image-left';
                imageStyle = `width: ${width}; border-radius: ${borderRadius};`;
            } else if (alignment === 'float-right') {
                imageClass = 'article-image-right';
                imageStyle = `width: ${width}; border-radius: ${borderRadius};`;
            } else {
                imageStyle += ` width: 100%; max-width: 900px; display: block; margin: 0 auto;`;
            }

            return `
                <section class="article-block">
                    <div class="container">
                        <figure style="max-width: 900px; margin: 0 auto;">
                            <img src="${this.escape(url)}" alt="${this.escape(alt)}" class="${this.escapeAttr(imageClass)}" style="${this.escapeAttr(imageStyle)}">
                            ${caption ? `<figcaption data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.caption" data-block-type="${block.type}" style="text-align: center; color: var(--text-secondary); margin-top: 1rem; font-size: 0.95rem;">${this.escape(caption)}</figcaption>` : ''}
                        </figure>
                    </div>
                </section>
            `;
        },

        renderBlockquote(block) {
            const data = block.data || {};
            const text = data.text || '';

            return `
                <section class="article-block">
                    <div class="article-container">
                        <div class="article-content">
                            <blockquote data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.text" data-block-type="${block.type}">${this.escape(text)}</blockquote>
                        </div>
                    </div>
                </section>
            `;
        },

        renderButton(block) {
            const data = block.data || {};
            const text = data.text || 'ÐÐ½Ð¾Ð¿ÐºÐ°';
            const link = data.link || '#';
            const alignment = data.alignment || 'center';
            const style = data.style || 'primary';

            const alignClass = alignment === 'left' ? 'text-left' : alignment === 'right' ? 'text-right' : 'text-center';
            const btnClass = style === 'primary' ? 'btn-primary' : 'btn-primary';

            return `
                <section style="padding-top: 0;">
                    <div class="container ${alignClass}" style="margin-top: 3rem; display: flex; justify-content: ${alignment === 'left' ? 'flex-start' : alignment === 'right' ? 'flex-end' : 'center'};">
                        <a href="${this.escape(link)}" class="btn ${btnClass}" style="display: inline-block; width: auto;" data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.text" data-block-type="${block.type}">${this.escape(text)}</a>
                    </div>
                </section>
            `;
        },

        renderSectionTitle(block) {
            const data = block.data || {};
            const text = data.text || 'ÐÐ°Ð³Ð¾Ð»Ð¾Ð²Ð¾Ðº';
            const alignment = data.alignment || 'left';

            const style = alignment === 'center' ? 'text-align: center;' : alignment === 'right' ? 'text-align: right;' : 'text-align: left;';

            return `
                <section class="article-block" style="padding-top: 2rem;">
                    <div class="container">
                        <h3 data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.text" data-block-type="${block.type}" style="font-family: var(--font-heading); font-size: 1.8rem; margin-bottom: 1rem; color: var(--text-dark); ${style}">${this.escape(text)}</h3>
                    </div>
                </section>
            `;
        },

        renderSectionDivider(block) {
            // Divider has no editable text by default
            return `
                <section style="padding: 3rem 0;">
                    <div class="container">
                        <hr class="section-divider">
                    </div>
                </section>
            `;
        },

        renderChatBot(block) {
            const data = block.data || {};
            const placeholder = data.placeholder || 'ÐÐ²ÐµÐ´Ð¸ÑÐµ Ð²Ð°Ñ Ð²Ð¾Ð¿ÑÐ¾Ñ...';
            const buttonText = data.buttonText || 'â';

            return `
                <section style="padding-top: 0;">
                    <div class="container">
                        <div class="chat-container" style="border: 2px solid var(--color-border); border-radius: 12px; padding: 1.5rem;">
                            <div class="chat-input" style="display: flex; gap: 0.75rem;">
                                <input
                                    type="text"
                                    placeholder="${this.escape(placeholder)}"
                                    style="flex: 1; padding: 0.75rem; border: 1px solid var(--color-border); border-radius: 8px; font-family: var(--font-body); font-size: 1rem;"
                                    disabled
                                >
                                <button style="padding: 0.75rem 1.5rem; background: var(--color-action); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 1.2rem;">
                                    <span data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.buttonText" data-block-type="${block.type}">${this.escape(buttonText)}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            `;
        },

        renderSpacer(block) {
            const data = block.data || {};
            const height = data.height || 60;
            return `<div style="height: ${height}px;"></div>`;
        },


        // ===== AUTH & API =====

        async checkAuth() {
            const token = localStorage.getItem('cms_auth_token');
            if (!token) {
                this.showLoginModal = true;
                this.debugMsg('Ð¢Ð¾ÐºÐµÐ½ Ð°Ð²ÑÐ¾ÑÐ¸Ð·Ð°ÑÐ¸Ð¸ Ð½Ðµ Ð½Ð°Ð¹Ð´ÐµÐ½. ÐÐ°Ð¿ÑÐ°ÑÐ¸Ð²Ð°ÐµÐ¼ Ð²ÑÐ¾Ð´.', 'warning');
                return false;
            }

            try {
                this.debugMsg('ÐÑÐ¾Ð²ÐµÑÑÐµÐ¼ Ð°Ð²ÑÐ¾ÑÐ¸Ð·Ð°ÑÐ¸Ñ ÑÐµÐºÑÑÐµÐ³Ð¾ Ð¿Ð¾Ð»ÑÐ·Ð¾Ð²Ð°ÑÐµÐ»Ñ', 'info');
                const user = await this.apiClient.getCurrentUser();
                this.debugMsg('ÐÐ¾Ð»ÑÑÐµÐ½Ñ Ð´Ð°Ð½Ð½ÑÐµ Ð¿Ð¾Ð»ÑÐ·Ð¾Ð²Ð°ÑÐµÐ»Ñ Ð¾Ñ API', 'info', { 
                    userId: user.id, 
                    username: user.username,
                    userObject: user 
                });
                this.currentUser = user;
                this.showLoginModal = false;
                this.debugMsg('ÐÐ²ÑÐ¾ÑÐ¸Ð·Ð°ÑÐ¸Ñ Ð¿Ð¾Ð´ÑÐ²ÐµÑÐ¶Ð´ÐµÐ½Ð°', 'success', { userId: user.id, username: user.username });
                return true;
            } catch (error) {
                console.error('Auth error:', error);
                localStorage.removeItem('cms_auth_token');
                this.showLoginModal = true;
                this.debugMsg('ÐÐ²ÑÐ¾ÑÐ¸Ð·Ð°ÑÐ¸Ñ Ð½Ðµ ÑÐ´Ð°Ð»Ð°ÑÑ, ÑÐ¾ÐºÐµÐ½ ÑÐ±ÑÐ¾ÑÐµÐ½', 'error', { message: error.message, details: error.details || null });
                return false;
            }
        },

        async login() {
            if (!this.loginForm.username || !this.loginForm.password) {
                this.showNotification('ÐÐ°Ð¿Ð¾Ð»Ð½Ð¸ÑÐµ Ð²ÑÐµ Ð¿Ð¾Ð»Ñ', 'error');
                this.debugMsg('ÐÐ¾Ð¿ÑÑÐºÐ° Ð²ÑÐ¾Ð´Ð° Ñ Ð¿ÑÑÑÑÐ¼Ð¸ Ð¿Ð¾Ð»ÑÐ¼Ð¸', 'warning');
                return;
            }

            try {
                this.debugMsg('ÐÐ¾Ð¿ÑÑÐºÐ° Ð²ÑÐ¾Ð´Ð°', 'info', { username: this.loginForm.username });
                const response = await this.apiClient.login(
                    this.loginForm.username,
                    this.loginForm.password
                );

                this.currentUser = response.user;
                this.showLoginModal = false;
                this.showNotification('ÐÑÐ¾Ð´ Ð²ÑÐ¿Ð¾Ð»Ð½ÐµÐ½', 'success');
                this.debugMsg('ÐÑÐ¾Ð´ Ð²ÑÐ¿Ð¾Ð»Ð½ÐµÐ½', 'success', { userId: response.user.id, username: response.user.username });

                // ÐÑÐ¾Ð²ÐµÑÑÐµÐ¼, ÐµÑÑÑ Ð»Ð¸ ID ÑÑÑÐ°Ð½Ð¸ÑÑ Ð² URL
                const urlParams = new URLSearchParams(window.location.search);
                const pageId = urlParams.get('id');
                if (pageId) {
                    this.debugMsg('ÐÐ¾ÑÐ»Ðµ Ð²ÑÐ¾Ð´Ð° Ð¾Ð±Ð½Ð°ÑÑÐ¶ÐµÐ½ Ð¿Ð°ÑÐ°Ð¼ÐµÑÑ id. ÐÐ°Ð³ÑÑÐ¶Ð°ÐµÐ¼ ÑÑÑÐ°Ð½Ð¸ÑÑ', 'info', { pageId });
                    await this.loadPageFromAPI(pageId);
                }
            } catch (error) {
                this.showNotification('ÐÑÐ¸Ð±ÐºÐ° Ð²ÑÐ¾Ð´Ð°: ' + error.message, 'error');
                this.debugMsg('ÐÑÐ¸Ð±ÐºÐ° Ð²ÑÐ¾Ð´Ð°', 'error', { message: error.message });
            }
        },

        logout() {
            this.apiClient.logout();
            this.currentUser = null;
            this.showLoginModal = true;
            this.pageData = {
                title: '',
                slug: '',
                type: 'regular',
                status: 'draft',
                seoTitle: '',
                seoDescription: '',
                seoKeywords: ''
            };
            this.blocks = [];
            this.currentPageId = null;
            this.isEditMode = false;
            this.debugMsg('ÐÐ¾Ð»ÑÐ·Ð¾Ð²Ð°ÑÐµÐ»Ñ Ð²ÑÑÐµÐ» Ð¸Ð· ÑÐ¸ÑÑÐµÐ¼Ñ', 'info');
        },

        async loadPageFromAPI(pageId) {
            if (!this.currentUser) {
                this.showNotification('ÐÐµÐ¾Ð±ÑÐ¾Ð´Ð¸Ð¼Ð° Ð°Ð²ÑÐ¾ÑÐ¸Ð·Ð°ÑÐ¸Ñ', 'error');
                this.debugMsg('ÐÐ¾Ð¿ÑÑÐºÐ° Ð·Ð°Ð³ÑÑÐ·Ð¸ÑÑ ÑÑÑÐ°Ð½Ð¸ÑÑ Ð±ÐµÐ· Ð°Ð²ÑÐ¾ÑÐ¸Ð·Ð°ÑÐ¸Ð¸', 'error', { pageId });
                return;
            }

            try {
                this.debugMsg('ÐÐ°Ð³ÑÑÐ¶Ð°ÐµÐ¼ ÑÑÑÐ°Ð½Ð¸ÑÑ Ð¸Ð· API', 'info', { pageId });
                const response = await this.apiClient.getPage(pageId);
                const pagePayload = toPlainObject(response.page || response);
                const blocksPayload = Array.isArray(response.blocks)
                    ? response.blocks
                    : Array.isArray(pagePayload?.blocks)
                        ? pagePayload.blocks
                        : [];

                this.pageData = {
                    title: pagePayload.title || '',
                    slug: pagePayload.slug || '',
                    type: pagePayload.type || 'regular',
                    status: pagePayload.status || 'draft',
                    seoTitle: pagePayload.seoTitle || '',
                    seoDescription: pagePayload.seoDescription || '',
                    seoKeywords: pagePayload.seoKeywords || ''
                };

                // Load menu settings (backend now returns camelCase after refactoring)
                this.pageSettings.showInMenu = Boolean(pagePayload.showInMenu);
                this.pageSettings.menuPosition = pagePayload.menuOrder !== undefined && pagePayload.menuOrder !== null ? pagePayload.menuOrder : null;
                this.pageSettings.menuTitle = pagePayload.menuTitle || '';

                this.blocks = blocksPayload.map((block, index) => {
                    const mapped = blockFromAPI({ ...block, position: index });
                    if (mapped.customName === undefined || mapped.customName === null) {
                        mapped.customName = '';
                    }
                    mapped.position = index;
                    if (!mapped.id) {
                        mapped.id = this.generateBlockId();
                        this.debugMsg('ÐÐ»Ð¾ÐºÑ Ð¿ÑÐ¸ÑÐ²Ð¾ÐµÐ½ Ð²ÑÐµÐ¼ÐµÐ½Ð½ÑÐ¹ ID (Ð¾ÑÑÑÑÑÑÐ²Ð¾Ð²Ð°Ð» Ð² API)', 'warning', { index, type: mapped.type });
                    }
                    return mapped;
                });

                this.currentPageId = pagePayload.id || pageId;
                this.isEditMode = true;
                this.autoGenerateSlug = false; // ÐÑÐºÐ»ÑÑÐ°ÐµÐ¼ Ð°Ð²ÑÐ¾Ð³ÐµÐ½ÐµÑÐ°ÑÐ¸Ñ Ð¿ÑÐ¸ Ð·Ð°Ð³ÑÑÐ·ÐºÐµ ÑÑÑÐµÑÑÐ²ÑÑÑÐµÐ¹ ÑÑÑÐ°Ð½Ð¸ÑÑ

                this.showNotification('Ð¡ÑÑÐ°Ð½Ð¸ÑÐ° Ð·Ð°Ð³ÑÑÐ¶ÐµÐ½Ð°', 'success');
                this.debugMsg('Ð¡ÑÑÐ°Ð½Ð¸ÑÐ° ÑÑÐ¿ÐµÑÐ½Ð¾ Ð·Ð°Ð³ÑÑÐ¶ÐµÐ½Ð°', 'success', { pageId: this.currentPageId, blocks: this.blocks.length });
            } catch (error) {
                console.error('Error loading page:', error);
                this.showNotification('ÐÑÐ¸Ð±ÐºÐ° Ð·Ð°Ð³ÑÑÐ·ÐºÐ¸: ' + error.message, 'error');
                this.debugMsg('ÐÑÐ¸Ð±ÐºÐ° Ð·Ð°Ð³ÑÑÐ·ÐºÐ¸ ÑÑÑÐ°Ð½Ð¸ÑÑ', 'error', { pageId, message: error.message, details: error.details || null });
            }
        },

        async savePage() {
            if (!this.currentUser) {
                this.showNotification('ÐÐµÐ¾Ð±ÑÐ¾Ð´Ð¸Ð¼Ð° Ð°Ð²ÑÐ¾ÑÐ¸Ð·Ð°ÑÐ¸Ñ', 'error');
                this.debugMsg('ÐÐ¾Ð¿ÑÑÐºÐ° ÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð¸Ñ Ð±ÐµÐ· Ð°Ð²ÑÐ¾ÑÐ¸Ð·Ð°ÑÐ¸Ð¸', 'error');
                return;
            }

            // ÐÐ¾Ð¿Ð¾Ð»Ð½Ð¸ÑÐµÐ»ÑÐ½Ð°Ñ Ð¿ÑÐ¾Ð²ÐµÑÐºÐ° currentUser.id
            if (!this.currentUser.id) {
                this.showNotification('ÐÑÐ¸Ð±ÐºÐ° Ð°Ð²ÑÐ¾ÑÐ¸Ð·Ð°ÑÐ¸Ð¸: Ð¾ÑÑÑÑÑÑÐ²ÑÐµÑ ID Ð¿Ð¾Ð»ÑÐ·Ð¾Ð²Ð°ÑÐµÐ»Ñ', 'error');
                this.debugMsg('ÐÑÐ¸Ð±ÐºÐ°: currentUser.id Ð½Ðµ Ð¾Ð¿ÑÐµÐ´ÐµÐ»ÐµÐ½', 'error', { currentUser: this.currentUser });
                return;
            }

            // ÐÑÐ¾Ð²ÐµÑÑÐµÐ¼ Ð²Ð°Ð»Ð¸Ð´Ð½Ð¾ÑÑÑ ÑÐ¾ÐºÐµÐ½Ð° Ð¿ÐµÑÐµÐ´ ÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð¸ÐµÐ¼
            try {
                this.debugMsg('ÐÑÐ¾Ð²ÐµÑÑÐµÐ¼ Ð²Ð°Ð»Ð¸Ð´Ð½Ð¾ÑÑÑ ÑÐ¾ÐºÐµÐ½Ð° Ð¿ÐµÑÐµÐ´ ÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð¸ÐµÐ¼', 'info');
                await this.apiClient.getCurrentUser();
                this.debugMsg('Ð¢Ð¾ÐºÐµÐ½ Ð²Ð°Ð»Ð¸Ð´ÐµÐ½, Ð¿ÑÐ¾Ð´Ð¾Ð»Ð¶Ð°ÐµÐ¼ ÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð¸Ðµ', 'success');
            } catch (error) {
                this.showNotification('Ð¡ÐµÑÑÐ¸Ñ Ð¸ÑÑÐµÐºÐ»Ð°. ÐÐ¾Ð¶Ð°Ð»ÑÐ¹ÑÑÐ°, Ð²Ð¾Ð¹Ð´Ð¸ÑÐµ ÑÐ½Ð¾Ð²Ð°.', 'error');
                this.debugMsg('Ð¢Ð¾ÐºÐµÐ½ Ð½ÐµÐ´ÐµÐ¹ÑÑÐ²Ð¸ÑÐµÐ»ÐµÐ½, ÑÑÐµÐ±ÑÐµÑÑÑ Ð¿Ð¾Ð²ÑÐ¾ÑÐ½ÑÐ¹ Ð²ÑÐ¾Ð´', 'error', { message: error.message });
                this.logout();
                return;
            }

            this.debugMsg('Ð¡Ð¾ÑÑÐ°Ð½ÐµÐ½Ð¸Ðµ ÑÑÑÐ°Ð½Ð¸ÑÑ', 'info', { 
                userId: this.currentUser.id, 
                username: this.currentUser.username,
                title: this.pageData.title,
                slug: this.pageData.slug 
            });

            if (!this.pageData.title || !this.pageData.slug) {
                this.showNotification('ÐÐ°Ð¿Ð¾Ð»Ð½Ð¸ÑÐµ Ð½Ð°Ð·Ð²Ð°Ð½Ð¸Ðµ Ð¸ slug', 'error');
                this.debugMsg('Ð¡Ð¾ÑÑÐ°Ð½ÐµÐ½Ð¸Ðµ Ð¾ÑÐºÐ»Ð¾Ð½ÐµÐ½Ð¾: Ð¾ÑÑÑÑÑÑÐ²ÑÑÑ Ð½Ð°Ð·Ð²Ð°Ð½Ð¸Ðµ Ð¸Ð»Ð¸ slug', 'warning', { title: this.pageData.title, slug: this.pageData.slug });
                return;
            }

            const slugValidation = validateSlug(this.pageData.slug);
            if (!slugValidation.valid) {
                this.showNotification(slugValidation.message, 'error');
                this.debugMsg('ÐÐµÐºÐ¾ÑÑÐµÐºÑÐ½ÑÐ¹ slug', 'error', { slug: this.pageData.slug, message: slugValidation.message });
                return;
            }

            // PHASE 2: Generate rendered HTML and sanitize before sending
            const renderedHtmlRaw = this.exportRenderedHtml ? this.exportRenderedHtml() : null;

            let renderedHtmlForApi = null;
            if (renderedHtmlRaw) {
                // If DOMPurify is available, sanitize client-side
                try {
                    renderedHtmlForApi = this.sanitizeHTML(renderedHtmlRaw);
                    if (renderedHtmlRaw !== renderedHtmlForApi) {
                        console.warn('[SECURITY] HTML was sanitized by DOMPurify:', {
                            original_length: renderedHtmlRaw.length,
                            sanitized_length: renderedHtmlForApi.length,
                            diff: renderedHtmlRaw.length - renderedHtmlForApi.length
                        });
                    }
                } catch (e) {
                    console.error('[SECURITY] DOMPurify not available, sending raw HTML', e);
                    renderedHtmlForApi = renderedHtmlRaw;
                }
            }

            const basePageData = toPlainObject({
                title: this.pageData.title,
                slug: this.pageData.slug,
                type: this.pageData.type || 'regular',
                status: this.pageData.status || 'draft',
                seoTitle: this.pageData.seoTitle || '',
                seoDescription: this.pageData.seoDescription || '',
                seoKeywords: this.pageData.seoKeywords || '',
                createdBy: this.currentUser.id
            });

            const blocksPayload = this.blocks.map((block, index) =>
                blockToAPI({
                    ...toPlainObject(block),
                    position: index,
                    customName: block.customName === '' ? null : block.customName
                })
            );

            const pageDataForAPI = {
                ...basePageData,
                blocks: blocksPayload,
                // Include sanitized renderedHtml if present (defense in depth)
                ...(renderedHtmlForApi ? { renderedHtml: renderedHtmlForApi } : {}),
                // Menu fields (camelCase expected by backend after refactoring)
                showInMenu: (this.pageSettings.showInMenu && (this.pageData.status === 'published')) ? true : false,
                menuOrder: this.pageSettings.menuPosition === null ? 0 : Number(this.pageSettings.menuPosition || 0),
                menuTitle: this.pageSettings.menuTitle || null
            };

            this.debugMsg('========== Ð¡ÐÐ¥Ð ÐÐÐÐÐÐ Ð¡Ð¢Ð ÐÐÐÐ¦Ð« ==========', 'info');
            this.debugMsg('ÐÐ»Ð¾ÐºÐ¸ Ð½Ð° ÑÑÑÐ°Ð½Ð¸ÑÐµ', 'info', { 
                totalBlocks: this.blocks.length,
                blockTypes: this.blocks.map(b => b.type),
                blocksPayload: blocksPayload
            });
            this.debugMsg('ÐÐ°Ð½Ð½ÑÐµ Ð´Ð»Ñ Ð¾ÑÐ¿ÑÐ°Ð²ÐºÐ¸ Ð² API', 'info', pageDataForAPI);

            try {
                let response;

                this.debugMsg('ÐÑÐ¿ÑÐ°Ð²Ð»ÑÐµÐ¼ ÑÑÑÐ°Ð½Ð¸ÑÑ Ð² API', 'info', { isEditMode: this.isEditMode, pageId: this.currentPageId, blocks: blocksPayload.length });
                if (this.isEditMode && this.currentPageId) {
                    response = await this.apiClient.updatePage(this.currentPageId, pageDataForAPI);
                    this.showNotification('â Ð¡ÑÑÐ°Ð½Ð¸ÑÐ° Ð¾Ð±Ð½Ð¾Ð²Ð»ÐµÐ½Ð°', 'success');
                    this.debugMsg('Ð¡ÑÑÐ°Ð½Ð¸ÑÐ° Ð¾Ð±Ð½Ð¾Ð²Ð»ÐµÐ½Ð°', 'success', { pageId: this.currentPageId });
                } else {
                    response = await this.apiClient.createPage(pageDataForAPI);
                    this.currentPageId = response.page_id || response.pageId || response.id;
                    this.isEditMode = true;
                    this.pageData.status = 'draft'; // â ÐÐ¾Ð±Ð°Ð²Ð¸ÑÑ ÑÑÐ°ÑÑÑ Ð´Ð»Ñ ÐºÐ½Ð¾Ð¿ÐºÐ¸ "ÐÐ¿ÑÐ±Ð»Ð¸ÐºÐ¾Ð²Ð°ÑÑ"
                    window.history.pushState({}, '', `?id=${this.currentPageId}`);
                    this.showNotification('â Ð¡ÑÑÐ°Ð½Ð¸ÑÐ° ÑÐ¾Ð·Ð´Ð°Ð½Ð°', 'success');
                    this.debugMsg('ÐÐ¾Ð²Ð°Ñ ÑÑÑÐ°Ð½Ð¸ÑÐ° ÑÐ¾Ð·Ð´Ð°Ð½Ð°', 'success', { pageId: this.currentPageId });
                }

                // ÐÐ¾ÑÐ»Ðµ ÑÑÐ¿ÐµÑÐ½Ð¾Ð³Ð¾ ÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð¸Ñ Ð¿Ð¾Ð´Ð³ÑÑÐ¶Ð°ÐµÐ¼ ÑÑÑÐ°Ð½Ð¸ÑÑ Ð·Ð°Ð½Ð¾Ð²Ð¾ â
                // ÑÑÐ¾ ÑÐ¸Ð½ÑÑÐ¾Ð½Ð¸Ð·Ð¸ÑÑÐµÑ Ð²ÑÐµÐ¼ÐµÐ½Ð½ÑÐµ client-side ID Ð±Ð»Ð¾ÐºÐ¾Ð² Ñ Ð½Ð°ÑÑÐ¾ÑÑÐ¸Ð¼Ð¸ ID Ñ ÑÐµÑÐ²ÐµÑÐ°
                try {
                    this.debugMsg('Ð ÐµÑÑÐµÑ ÑÑÑÐ°Ð½Ð¸ÑÑ Ð¸Ð· API Ð¿Ð¾ÑÐ»Ðµ ÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð¸Ñ Ð´Ð»Ñ ÑÐ¸Ð½ÑÑÐ¾Ð½Ð¸Ð·Ð°ÑÐ¸Ð¸ ID', 'info', { pageId: this.currentPageId });
                    await this.loadPageFromAPI(this.currentPageId);
                    this.debugMsg('Ð¡Ð¸Ð½ÑÑÐ¾Ð½Ð¸Ð·Ð°ÑÐ¸Ñ Ð±Ð»Ð¾ÐºÐ¾Ð² Ñ ÑÐµÑÐ²ÐµÑÐ½ÑÐ¼Ð¸ ID Ð·Ð°Ð²ÐµÑÑÐµÐ½Ð°', 'success', { pageId: this.currentPageId });
                } catch (e) {
                    this.debugMsg('ÐÐµ ÑÐ´Ð°Ð»Ð¾ÑÑ ÑÐµÑÑÐµÑÐ½ÑÑÑ ÑÑÑÐ°Ð½Ð¸ÑÑ Ð¿Ð¾ÑÐ»Ðµ ÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð¸Ñ', 'warning', { message: e.message });
                }
            } catch (error) {
                console.error('Save error:', error);
                this.showNotification('ÐÑÐ¸Ð±ÐºÐ° ÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð¸Ñ: ' + error.message, 'error');
                this.debugMsg('ÐÑÐ¸Ð±ÐºÐ° ÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð¸Ñ ÑÑÑÐ°Ð½Ð¸ÑÑ', 'error', { message: error.message, details: error.details || null });
            }
        },

        /**
         * Sanitize HTML using DOMPurify
         * Removes dangerous HTML/JS while preserving safe formatting.
         * @param {string} html - Raw HTML
         * @return {string} Sanitized HTML
         */
        sanitizeHTML(html) {
            if (typeof DOMPurify === 'undefined') {
                console.error('[SECURITY] DOMPurify not loaded! HTML will NOT be sanitized!');
                return html; // Fallback (unsafe)
            }

            const config = {
                SAFE_FOR_TEMPLATES: true,
                KEEP_CONTENT: true,
                ALLOWED_TAGS: [
                    'h1','h2','h3','h4','h5','h6',
                    'p','div','span','a','img',
                    'ul','ol','li',
                    'strong','em','br',
                    'section','article','header','footer',
                    'blockquote','code','pre'
                ],
                ALLOWED_ATTR: [
                    'href','src','alt','title',
                    'class','id','style',
                    'data-block-id','data-field-path','data-block-type',
                    'data-inline-editable'
                ],
                ALLOW_DATA_ATTR: true,
                FORBID_TAGS: [ 'script','iframe','object','embed','applet','base','meta','link' ],
                FORBID_ATTR: [ 'onerror','onclick','onload','onmouseover' ]
            };

            return DOMPurify.sanitize(html, config);
        },

        /**
         * Initialize Trusted Types policy (polyfill fallback)
         */
        initTrustedTypesPolicy() {
            try {
                if (typeof trustedTypes === 'undefined') {
                    console.warn('[SECURITY] Trusted Types API not supported in this browser. Using polyfill fallback.');
                    window.trustedTypes = {
                        createPolicy: (name, rules) => rules
                    };
                }

                this.trustedPolicy = trustedTypes.createPolicy('editor-html', {
                    createHTML: (input) => {
                        return this.sanitizeHTML(input);
                    }
                });

                console.log('[SECURITY] Trusted Types policy "editor-html" created');
            } catch (e) {
                console.warn('[SECURITY] Failed to initialize Trusted Types policy', e);
            }
        },

        async publishPage() {
            if (!this.currentPageId) {
                this.showNotification('Ð¡Ð½Ð°ÑÐ°Ð»Ð° ÑÐ¾ÑÑÐ°Ð½Ð¸ÑÐµ ÑÑÑÐ°Ð½Ð¸ÑÑ', 'error');
                this.debugMsg('ÐÐ¾Ð¿ÑÑÐºÐ° Ð¿ÑÐ±Ð»Ð¸ÐºÐ°ÑÐ¸Ð¸ Ð±ÐµÐ· ÑÐ¾ÑÑÐ°Ð½ÐµÐ½Ð¸Ñ', 'warning');
                return;
            }

            try {
                this.debugMsg('ÐÑÐ±Ð»Ð¸ÐºÑÐµÐ¼ ÑÑÑÐ°Ð½Ð¸ÑÑ', 'info', { pageId: this.currentPageId });
                await this.apiClient.publishPage(this.currentPageId);
                this.pageData.status = 'published';
                this.showNotification('â Ð¡ÑÑÐ°Ð½Ð¸ÑÐ° Ð¾Ð¿ÑÐ±Ð»Ð¸ÐºÐ¾Ð²Ð°Ð½Ð°', 'success');
                this.debugMsg('Ð¡ÑÑÐ°Ð½Ð¸ÑÐ° Ð¾Ð¿ÑÐ±Ð»Ð¸ÐºÐ¾Ð²Ð°Ð½Ð°', 'success', { pageId: this.currentPageId });
            } catch (error) {
                console.error('Publish error:', error);
                this.showNotification('ÐÑÐ¸Ð±ÐºÐ° Ð¿ÑÐ±Ð»Ð¸ÐºÐ°ÑÐ¸Ð¸: ' + error.message, 'error');
                this.debugMsg('ÐÑÐ¸Ð±ÐºÐ° Ð¿ÑÐ±Ð»Ð¸ÐºÐ°ÑÐ¸Ð¸ ÑÑÑÐ°Ð½Ð¸ÑÑ', 'error', { pageId: this.currentPageId, message: error.message });
            }
        },

        onTitleChange() {
            // ÐÐ²ÑÐ¾Ð³ÐµÐ½ÐµÑÐ°ÑÐ¸Ñ slug Ð¿ÑÐ¸ Ð¸Ð·Ð¼ÐµÐ½ÐµÐ½Ð¸Ð¸ Ð½Ð°Ð·Ð²Ð°Ð½Ð¸Ñ
            // ÐÐµÐ½ÐµÑÐ¸ÑÑÐµÐ¼ ÑÐ¾Ð»ÑÐºÐ¾ ÐµÑÐ»Ð¸ Ð°Ð²ÑÐ¾Ð³ÐµÐ½ÐµÑÐ°ÑÐ¸Ñ Ð²ÐºÐ»ÑÑÐµÐ½Ð°
            if (this.pageData.title && this.autoGenerateSlug) {
                this.pageData.slug = generateSlug(this.pageData.title);
                this.debugMsg('Slug Ð¾Ð±Ð½Ð¾Ð²Ð»ÑÐ½ Ð°Ð²ÑÐ¾Ð¼Ð°ÑÐ¸ÑÐµÑÐºÐ¸', 'info', { title: this.pageData.title, slug: this.pageData.slug });
            }
        },

        onSlugManualEdit() {
            // ÐÑÐ¸ ÑÑÑÐ½Ð¾Ð¼ ÑÐµÐ´Ð°ÐºÑÐ¸ÑÐ¾Ð²Ð°Ð½Ð¸Ð¸ slug Ð¾ÑÐºÐ»ÑÑÐ°ÐµÐ¼ Ð°Ð²ÑÐ¾Ð³ÐµÐ½ÐµÑÐ°ÑÐ¸Ñ
            this.autoGenerateSlug = false;
            this.debugMsg('Slug Ð¿ÐµÑÐµÐ²ÐµÐ´ÑÐ½ Ð² ÑÑÑÐ½Ð¾Ð¹ ÑÐµÐ¶Ð¸Ð¼', 'warning', { slug: this.pageData.slug });
        },

        regenerateSlug() {
            // ÐÑÐ¸Ð½ÑÐ´Ð¸ÑÐµÐ»ÑÐ½Ð°Ñ ÑÐµÐ³ÐµÐ½ÐµÑÐ°ÑÐ¸Ñ slug Ð¸Ð· ÑÐµÐºÑÑÐµÐ³Ð¾ Ð½Ð°Ð·Ð²Ð°Ð½Ð¸Ñ
            if (this.pageData.title) {
                this.pageData.slug = generateSlug(this.pageData.title);
                this.autoGenerateSlug = true; // ÐÐºÐ»ÑÑÐ°ÐµÐ¼ Ð°Ð²ÑÐ¾Ð³ÐµÐ½ÐµÑÐ°ÑÐ¸Ñ Ð¾Ð±ÑÐ°ÑÐ½Ð¾
                this.showNotification('Slug Ð¾Ð±Ð½Ð¾Ð²Ð»ÐµÐ½ Ð¸Ð· Ð½Ð°Ð·Ð²Ð°Ð½Ð¸Ñ', 'success');
                this.debugMsg('Slug ÑÐµÐ³ÐµÐ½ÐµÑÐ¸ÑÐ¾Ð²Ð°Ð½ Ð²ÑÑÑÐ½ÑÑ', 'success', { slug: this.pageData.slug });
            }
        },

        async exportHTML() {
            this.debugMsg('ÐÐ°ÑÐ¸Ð½Ð°ÐµÐ¼ ÑÐºÑÐ¿Ð¾ÑÑ HTML', 'info');
            
            // ÐÐ°Ð³ÑÑÐ¶Ð°ÐµÐ¼ CSS ÑÐ°Ð¹Ð»
            let cssContent = '';
            try {
                const response = await fetch('styles.css');
                if (response.ok) {
                    cssContent = await response.text();
                    this.debugMsg('CSS ÑÐ°Ð¹Ð» Ð·Ð°Ð³ÑÑÐ¶ÐµÐ½', 'success', { size: cssContent.length });
                } else {
                    this.debugMsg('ÐÐµ ÑÐ´Ð°Ð»Ð¾ÑÑ Ð·Ð°Ð³ÑÑÐ·Ð¸ÑÑ CSS ÑÐ°Ð¹Ð»', 'warning');
                }
            } catch (error) {
                this.debugMsg('ÐÑÐ¸Ð±ÐºÐ° Ð·Ð°Ð³ÑÑÐ·ÐºÐ¸ CSS', 'error', error);
            }
            
            let html = `<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${this.escape(this.pageData.title || 'Healthcare Hacks Brazil')}</title>
    ${cssContent ? `<style>\n${cssContent}\n    </style>` : '<link rel="stylesheet" href="styles.css">'}
</head>
<body>
    <header class="main-header">
        <div class="container">
            <a href="#" class="logo">${this.escape(this.globalSettings.header.logoText)}</a>
            <nav class="main-nav">
                <ul>`;

            for (const item of this.globalSettings.header.navItems) {
                html += `
                    <li><a href="${this.escape(item.link)}">${this.escape(item.text)}</a></li>`;
            }

            html += `
                </ul>
            </nav>
        </div>
    </header>

    <main>`;

            for (const block of this.blocks) {
                html += this.renderBlock(block);
            }

            html += `
    </main>

    <footer class="main-footer">
        <div class="container">
            <a href="#" class="logo">${this.escape(this.globalSettings.footer.logoText)}</a>
            <p>${this.escape(this.globalSettings.footer.copyrightText)}</p>`;

            if (this.globalSettings.footer.privacyLink) {
                html += `
            <p><a href="${this.escape(this.globalSettings.footer.privacyLink)}">${this.escape(this.globalSettings.footer.privacyLinkText)}</a></p>`;
            }

            html += `
        </div>
    </footer>`;

            if (this.globalSettings.cookieBanner.enabled) {
                html += `

    <div class="cookie-banner" id="cookieBanner">
        <div class="cookie-banner-content">
            <div class="cookie-banner-text">
                <p>${this.escape(this.globalSettings.cookieBanner.message)}</p>
            </div>
            <div class="cookie-banner-actions">
                <button class="cookie-btn cookie-btn-accept" onclick="acceptCookies()">${this.escape(this.globalSettings.cookieBanner.acceptText)}</button>
                <button class="cookie-btn cookie-btn-details" onclick="window.location.href='#privacy'">${this.escape(this.globalSettings.cookieBanner.detailsText)}</button>
            </div>
        </div>
    </div>

    <script>
        function acceptCookies() {
            localStorage.setItem('cookiesAccepted', 'true');
            document.getElementById('cookieBanner').style.display = 'none';
        }

        window.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('cookiesAccepted') === 'true') {
                document.getElementById('cookieBanner').style.display = 'none';
            }
        });
    </script>`;
            }

            html += `
</body>
</html>`;

            // Download HTML file
            const blob = new Blob([html], { type: 'text/html' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const filename = this.pageData.slug || 'healthcare-brazil';
            a.download = `${filename}.html`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            this.debugMsg('HTML ÑÐ°Ð¹Ð» ÑÐºÑÐ¿Ð¾ÑÑÐ¸ÑÐ¾Ð²Ð°Ð½', 'success', { filename: `${filename}.html` });
            this.showNotification('ð¥ HTML ÑÐ°Ð¹Ð» ÑÐºÑÐ¿Ð¾ÑÑÐ¸ÑÐ¾Ð²Ð°Ð½ ÑÐ¾ Ð²ÑÑÑÐ¾ÐµÐ½Ð½ÑÐ¼Ð¸ ÑÑÐ¸Ð»ÑÐ¼Ð¸', 'success');
        },

        // ===== GALLERY =====

        async openGallery(fieldKey) {
            this.currentImageField = fieldKey;
            this.currentArrayContext = null;
            this.selectedGalleryImage = null;
            this.showGalleryModal = true;
            await this.loadGalleryImages();
        },

        async openGalleryForArrayItem(arrayKey, itemIndex, fieldKey) {
            this.currentImageField = fieldKey;
            this.currentArrayContext = { arrayKey, itemIndex };
            this.selectedGalleryImage = null;
            this.showGalleryModal = true;
            await this.loadGalleryImages();
        },

        async loadGalleryImages() {
            try {
                const files = await this.apiClient.getMedia('image');
                this.galleryImages = Array.isArray(files)
                    ? files.map((file) => this.normalizeMediaFile(file))
                    : [];

                if (this.galleryImages.length === 0) {
                    this.debugMsg('Ð Ð¼ÐµÐ´Ð¸Ð°Ð±Ð¸Ð±Ð»Ð¸Ð¾ÑÐµÐºÐµ Ð½ÐµÑ Ð¸Ð·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ð¹', 'info');
                } else {
                    this.debugMsg('ÐÐ°Ð³ÑÑÐ¶ÐµÐ½Ñ Ð¸Ð·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ñ Ð¼ÐµÐ´Ð¸Ð°Ð±Ð¸Ð±Ð»Ð¸Ð¾ÑÐµÐºÐ¸', 'info', {
                        count: this.galleryImages.length
                    });
                }
            } catch (e) {
                console.error('Error loading gallery:', e);
                this.showNotification('ÐÑÐ¸Ð±ÐºÐ° Ð·Ð°Ð³ÑÑÐ·ÐºÐ¸ Ð¼ÐµÐ´Ð¸Ð°-ÑÐ°Ð¹Ð»Ð¾Ð²', 'error');
            }
        },

        selectImageFromGallery(image) {
            this.selectedGalleryImage = image;
        },

        confirmImageSelection() {
            if (!this.selectedGalleryImage) return;

            const selectedValue = this.normalizeRelativeUrl(this.selectedGalleryImage.url);

            if (this.currentArrayContext && this.selectedBlock) {
                const { arrayKey, itemIndex } = this.currentArrayContext;
                const array = this.selectedBlock.data[arrayKey];
                if (array && array[itemIndex]) {
                    array[itemIndex][this.currentImageField] = selectedValue;
                }
            } else if (this.selectedBlock) {
                this.selectedBlock.data[this.currentImageField] = selectedValue;
            }

            this.showGalleryModal = false;
            this.selectedGalleryImage = null;
            this.currentImageField = null;
            this.currentArrayContext = null;
            this.showNotification('ÐÐ·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ðµ Ð²ÑÐ±ÑÐ°Ð½Ð¾', 'success');
        },

        async handleFileUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                this.showNotification('ÐÑÐ±ÐµÑÐ¸ÑÐµ Ð¸Ð·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ðµ', 'error');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                this.showNotification('Ð¤Ð°Ð¹Ð» ÑÐ»Ð¸ÑÐºÐ¾Ð¼ Ð±Ð¾Ð»ÑÑÐ¾Ð¹ (Ð¼Ð°ÐºÑ. 5MB)', 'error');
                return;
            }

            this.uploadProgress = 'ÐÐ°Ð³ÑÑÐ·ÐºÐ°...';

            try {
                const result = await this.apiClient.uploadMedia(file, (progress) => {
                    this.uploadProgress = `ÐÐ°Ð³ÑÑÐ·ÐºÐ°: ${Math.round(progress)}%`;
                });

                const normalized = this.normalizeMediaFile({
                    id: result.file_id,
                    filename: result.filename,
                    url: result.file_url,
                    type: result.type,
                    size: result.size,
                    human_size: result.human_size,
                    uploaded_at: new Date().toISOString()
                });

                this.galleryImages.unshift(normalized);
                this.selectedGalleryImage = normalized;
                this.uploadProgress = null;
                this.showNotification('â Ð¤Ð°Ð¹Ð» Ð·Ð°Ð³ÑÑÐ¶ÐµÐ½', 'success');
            } catch (e) {
                console.error('Upload error:', e);
                this.uploadProgress = null;
                this.showNotification(`ÐÑÐ¸Ð±ÐºÐ° Ð·Ð°Ð³ÑÑÐ·ÐºÐ¸ ÑÐ°Ð¹Ð»Ð°: ${e.message || 'ÐÐµÐ¸Ð·Ð²ÐµÑÑÐ½Ð°Ñ Ð¾ÑÐ¸Ð±ÐºÐ°'}`, 'error');
            }

            event.target.value = '';
        },

        async deleteImage(image) {
            if (!image) return;
            if (!confirm(`Ð£Ð´Ð°Ð»Ð¸ÑÑ Ð¸Ð·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ðµ "${image.filename}"?`)) return;

            try {
                await this.apiClient.deleteMedia(image.id);
                this.galleryImages = this.galleryImages.filter((item) => item.id !== image.id);

                if (this.selectedGalleryImage && this.selectedGalleryImage.id === image.id) {
                    this.selectedGalleryImage = null;
                }

                this.showNotification('ÐÐ·Ð¾Ð±ÑÐ°Ð¶ÐµÐ½Ð¸Ðµ ÑÐ´Ð°Ð»ÐµÐ½Ð¾', 'success');
            } catch (e) {
                console.error('Delete error:', e);
                this.showNotification(`ÐÑÐ¸Ð±ÐºÐ° ÑÐ´Ð°Ð»ÐµÐ½Ð¸Ñ ÑÐ°Ð¹Ð»Ð°: ${e.message || 'ÐÐµÐ¸Ð·Ð²ÐµÑÑÐ½Ð°Ñ Ð¾ÑÐ¸Ð±ÐºÐ°'}`, 'error');
            }
        },

        openMediaLibrary() {
            window.open('media-library.html', '_blank', 'noopener');
        },

        normalizeMediaFile(file) {
            const relativeUrl = this.normalizeRelativeUrl(file.url || file.file_url || file.path || '');

            return {
                id: file.id || file.file_id || file.mediaId || file.filename || `temp-${Math.random().toString(36).slice(2)}`,
                filename: file.filename || file.name || 'image',
                url: relativeUrl,
                displayUrl: this.buildMediaUrl(relativeUrl),
                type: file.type || 'image',
                size: file.size || null,
                humanSize: file.human_size || file.humanSize || null,
                uploadedAt: file.uploaded_at || file.uploadedAt || new Date().toISOString()
            };
        },

        buildMediaUrl(path) {
            if (!path) {
                return '';
            }

            if (path.startsWith('http://') || path.startsWith('https://')) {
                return path;
            }

            const baseUrl = window.location.hostname === 'localhost'
                ? 'http://localhost/healthcare-cms-backend/public'
                : '/healthcare-cms-backend/public';

            return `${baseUrl}${path.startsWith('/') ? path : `/${path}`}`;
        },

        normalizeRelativeUrl(path) {
            if (!path) {
                return '';
            }

            if (path.startsWith('http://') || path.startsWith('https://')) {
                return path;
            }

            if (path.startsWith('/uploads/')) {
                return path;
            }

            if (path.startsWith('uploads/')) {
                return `/${path}`;
            }

            return `/uploads/${path.replace(/^\/+/, '')}`;
        },

        // ===== NOTIFICATIONS =====

        showNotification(message, type = 'success') {
            this.notification = { message, type };
            setTimeout(() => {
                this.notification = null;
            }, 3000);
        }
    }
});

// Mount the app and expose it globally for debugging
const mountedApp = app.mount('#app');
window.app = mountedApp;

