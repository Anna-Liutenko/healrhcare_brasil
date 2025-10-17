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
            autoGenerateSlug: true, // Флаг автогенерации slug

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
                        { text: 'Главная', link: '#' },
                        { text: 'Гайды', link: '#' },
                        { text: 'Блог', link: '#' },
                        { text: 'Бот', link: '#' }
                    ]
                },
                footer: {
                    logoText: 'Healthcare Hacks Brazil',
                    copyrightText: '© 2025 Анна Лютенко (Anna Liutenko). Все права защищены.',
                    privacyLink: '#privacy',
                    privacyLinkText: 'Политика конфиденциальности'
                },
                cookieBanner: {
                    enabled: true,
                    message: 'Мы используем cookie для улучшения работы сайта. Продолжая использовать сайт, вы соглашаетесь с нашей Политикой конфиденциальности.',
                    acceptText: 'Принять',
                    detailsText: 'Подробнее'
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
        this.debugMsg('Инициализация редактора', 'info');
        // Store auth promise to wait in mounted()
        this._authPromise = this.checkAuth();
        await this._authPromise;
    },

    async mounted() {
        // CRITICAL: Wait for auth to complete before checking currentUser
        await this._authPromise;
        
        const urlParams = new URLSearchParams(window.location.search);
        const pageId = urlParams.get('id');

        if (pageId) {
            this.debugMsg('Обнаружен параметр id в URL при монтировании', 'info', { pageId });
            
            // После await this._authPromise currentUser гарантированно установлен
            if (this.currentUser && !this.showLoginModal) {
                this.debugMsg('Пользователь авторизован, загружаем страницу', 'info', { pageId });
                await this.loadPageFromAPI(pageId);
            } else {
                // Если пользователь НЕ авторизован, ждём логина
                // loadPageFromAPI будет вызван ВНУТРИ login() после успешного входа
                this.debugMsg('Пользователь не авторизован, ожидание входа. Страница будет загружена после логина.', 'info', { pageId });
            }
        }

        // Initialize inline editor toggle (Stage 1)
        this.$nextTick(() => {
            const toggleBtn = document.getElementById('toggleInlineMode');
            if (toggleBtn) {
                const enableLabel = toggleBtn.dataset.inlineEnableLabel || '📝 Enable Inline Editing';
                const disableLabel = toggleBtn.dataset.inlineDisableLabel || '🚫 Disable Inline Editing';

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
        // Инициализация Quill после открытия редактора статей
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
                    this.debugMsg('Статус страницы изменён на не опубликованный — снимаем флажок показа в меню', 'info', { status: newVal });
                }
                this.pageSettings.showInMenu = false;
            }
        }
    },

    methods: {
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
                return payload.length > 2000 ? `${payload.slice(0, 2000)}…` : payload;
            }

            try {
                const plain = toPlainObject(payload);
                const json = JSON.stringify(plain, null, 2);
                return json.length > 2000 ? `${json.slice(0, 2000)}…` : json;
            } catch (error) {
                console.warn('Не удалось сериализовать payload для debug panel', error);
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
            this.debugMsg('Логи очищены', 'info');
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
                type: blockDef.type,
                customName: '', // Пустое = использовать название по умолчанию
                data: JSON.parse(JSON.stringify(blockDef.defaultData))
            };

            this.blocks.push(newBlock);
            this.selectedBlockIndex = this.blocks.length - 1;
            this.selectedBlock = this.blocks[this.selectedBlockIndex];
            this.activeTab = 'block';

            this.showNotification('Блок добавлен', 'success');
            this.debugMsg('Добавлен блок из библиотеки', 'info', { type: blockDef.type });
        },

        selectBlock(index) {
            this.selectedBlockIndex = index;
            this.selectedBlock = this.blocks[index];
            this.activeTab = 'block';
            this.debugMsg('Выбран блок', 'info', { index, type: this.selectedBlock?.type });
        },

        moveBlockUp(index) {
            if (index === 0) return;
            const blocks = this.blocks;
            [blocks[index - 1], blocks[index]] = [blocks[index], blocks[index - 1]];
            this.selectedBlockIndex = index - 1;
            this.debugMsg('Блок перемещён вверх', 'info', { from: index, to: index - 1 });
        },

        moveBlockDown(index) {
            if (index === this.blocks.length - 1) return;
            const blocks = this.blocks;
            [blocks[index], blocks[index + 1]] = [blocks[index + 1], blocks[index]];
            this.selectedBlockIndex = index + 1;
            this.debugMsg('Блок перемещён вниз', 'info', { from: index, to: index + 1 });
        },

        duplicateBlock(index) {
            const blockCopy = JSON.parse(JSON.stringify(this.blocks[index]));
            this.blocks.splice(index + 1, 0, blockCopy);
            this.showNotification('Блок продублирован', 'success');
            this.debugMsg('Блок продублирован', 'success', { index });
        },

        removeBlock(index) {
            if (confirm('Удалить этот блок?')) {
                this.blocks.splice(index, 1);
                if (this.selectedBlockIndex === index) {
                    this.selectedBlock = null;
                    this.selectedBlockIndex = null;
                }
                this.showNotification('Блок удалён', 'success');
                this.debugMsg('Блок удалён', 'warning', { index });
            }
        },

        // ===== DRAG & DROP =====

        onLibraryBlockDragStart(event, blockDef) {
            this.draggedBlockType = blockDef;
            this.isDraggingFromLibrary = true;

            // Добавляем класс для визуального feedback
            event.target.classList.add('dragging');

            // Устанавливаем данные для переноса
            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData('text/plain', blockDef.type);
        },

        onLibraryBlockDragEnd(event) {
            // Убираем класс dragging
            event.target.classList.remove('dragging');
            this.isDraggingFromLibrary = false;
            this.draggedBlockType = null;
        },

        onPreviewDragOver(event) {
            if (this.isDraggingFromLibrary) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';

                // Добавляем класс для подсветки области
                const previewArea = event.currentTarget;
                previewArea.classList.add('drag-over');
            }
        },

        onPreviewDragLeave(event) {
            // Убираем подсветку области
            const previewArea = event.currentTarget;
            previewArea.classList.remove('drag-over');
        },

        onPreviewDrop(event) {
            event.preventDefault();

            // Убираем подсветку
            const previewArea = event.currentTarget;
            previewArea.classList.remove('drag-over');

            if (this.isDraggingFromLibrary && this.draggedBlockType) {
                // Добавляем блок
                this.addBlock(this.draggedBlockType);
                this.showNotification(`Блок "${this.draggedBlockType.name}" добавлен`, 'success');
            }

            this.isDraggingFromLibrary = false;
            this.draggedBlockType = null;
        },

        // Drag & Drop для сортировки блоков
        onBlockDragStart(event, index) {
            this.draggedBlockIndex = index;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', index.toString());
        },

        onBlockDragEnd(event) {
            this.draggedBlockIndex = null;
            this.dragOverBlockIndex = null;

            // Убираем все классы подсветки
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

            // Убираем все классы подсветки
            document.querySelectorAll('.block-item').forEach(el => {
                el.classList.remove('drag-over-top', 'drag-over-bottom');
            });

            // Определяем куда вставлять: сверху или снизу
            const targetElement = event.currentTarget;
            const rect = targetElement.getBoundingClientRect();
            const midpoint = rect.top + rect.height / 2;

            if (event.clientY < midpoint) {
                // Вставка сверху
                targetElement.classList.add('drag-over-top');
                this.dragOverBlockIndex = targetIndex;
            } else {
                // Вставка снизу
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

            // Определяем позицию вставки
            const targetElement = event.currentTarget;
            const rect = targetElement.getBoundingClientRect();
            const midpoint = rect.top + rect.height / 2;

            let insertIndex;
            if (event.clientY < midpoint) {
                insertIndex = targetIndex;
            } else {
                insertIndex = targetIndex + 1;
            }

            // Перемещаем блок
            const draggedBlock = this.blocks[this.draggedBlockIndex];
            this.blocks.splice(this.draggedBlockIndex, 1);

            // Корректируем индекс если удалили элемент выше
            if (this.draggedBlockIndex < insertIndex) {
                insertIndex--;
            }

            this.blocks.splice(insertIndex, 0, draggedBlock);

            // Обновляем выбранный блок
            this.selectedBlockIndex = insertIndex;
            this.selectedBlock = this.blocks[insertIndex];

            // Убираем классы подсветки
            document.querySelectorAll('.block-item').forEach(el => {
                el.classList.remove('drag-over-top', 'drag-over-bottom');
            });

            this.draggedBlockIndex = null;
            this.dragOverBlockIndex = null;

            this.showNotification('Блок перемещён', 'success');
        },

        applyTemplate(template) {
            if (confirm(`Применить шаблон "${template.name}"?\n\nТекущие блоки будут заменены.`)) {
                this.blocks = JSON.parse(JSON.stringify(template.blocks));

                // Добавляем customName если его нет
                this.blocks.forEach(block => {
                    if (!block.hasOwnProperty('customName')) {
                        block.customName = '';
                    }
                });

                this.showTemplatesModal = false;
                this.selectedBlock = null;
                this.selectedBlockIndex = null;
                this.showNotification(`✨ Шаблон "${template.name}" применён!`, 'success');
            }
        },

        // ===== BLOCK PREVIEW =====

        showBlockPreview(blockDef) {
            this.previewBlock = blockDef;
        },

        renderPreviewBlock() {
            if (!this.previewBlock) return '';

            const tempBlock = {
                type: this.previewBlock.type,
                data: JSON.parse(JSON.stringify(this.previewBlock.defaultData))
            };

            return this.renderBlock(tempBlock);
        },

        // ===== ARTICLE EDITOR =====

        async openArticleEditor() {
            this.showArticleEditor = true;

            // Проверяем, есть ли уже сохраненная статья в text-block
            const textBlock = this.blocks.find(b => b.type === 'text-block' && b.data.containerStyle === 'article');
            if (textBlock && textBlock.data.content) {
                this.articleHtml = textBlock.data.content;
            }

            // Меняем URL
            window.history.pushState({}, '', window.location.pathname + '#article-editor');

            this.showNotification('Открываю редактор статей...', 'success');
        },


        closeArticleEditor() {
            if (confirm('Закрыть редактор? Несохраненные изменения будут потеряны.')) {
                this.quillInstance = null;
                this.showArticleEditor = false;

                // Меняем URL обратно
                window.history.pushState({}, '', window.location.pathname);
            }
        },

        async saveArticleAndClose() {
            if (this.quillInstance) {
                try {
                    // Получаем HTML из Quill
                    this.articleHtml = this.quillInstance.root.innerHTML;

                    // Конвертируем HTML в наши блоки
                    this.convertHtmlToBlocks(this.articleHtml);

                    // Сохраняем в localStorage
                    this.saveToLocalStorage();

                    // Закрываем редактор
                    this.quillInstance = null;
                    this.showArticleEditor = false;

                    // Меняем URL обратно
                    window.history.pushState({}, '', window.location.pathname);

                    this.showNotification('✅ Статья сохранена!', 'success');
                } catch (e) {
                    console.error('Saving error:', e);
                    this.showNotification('Ошибка сохранения', 'error');
                }
            }
        },

        async initQuillEditor() {
            if (!window.Quill) {
                console.error('Quill not loaded');
                return;
            }

            // Регистрируем модуль ImageResize если доступен
            if (window.ImageResize) {
                Quill.register('modules/imageResize', window.ImageResize.default);
            }

            // Кастомный обработчик для загрузки изображений
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
                            this.showNotification('Ошибка загрузки изображения', 'error');
                        }
                    } catch (error) {
                        console.error('Upload error:', error);
                        this.showNotification('Ошибка загрузки изображения', 'error');
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
                    placeholder: 'Начните писать статью...'
                });

                // Загружаем существующий контент если есть
                if (this.articleHtml) {
                    this.quillInstance.root.innerHTML = this.articleHtml;
                }

                // Добавляем функционал перемещения изображений
                this.$nextTick(() => {
                    this.setupImageDragAndDrop();
                });

                this.showNotification('Редактор готов!', 'success');
            } catch (error) {
                console.error('Quill init error:', error);
                this.showNotification('Ошибка инициализации редактора', 'error');
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

            // Делаем изображения draggable
            editor.addEventListener('mousedown', (e) => {
                if (e.target.tagName === 'IMG') {
                    // Очищаем предыдущий таймер если был
                    if (dragTimer) clearTimeout(dragTimer);

                    draggedImage = e.target;
                    startX = e.clientX;
                    currentX = e.clientX;
                    isDragging = false;

                    // Визуальный feedback - меняем курсор
                    draggedImage.style.cursor = 'grabbing';
                    e.preventDefault();
                }
            });

            document.addEventListener('mousemove', (e) => {
                if (!draggedImage) return;

                currentX = e.clientX;
                const deltaX = Math.abs(e.clientX - startX);

                // Начинаем drag если сдвинули больше 10px по горизонтали
                if (deltaX > 10 && !isDragging) {
                    isDragging = true;
                    // Визуальный feedback - делаем картинку полупрозрачной
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

                    // Определяем позицию по трети ширины редактора
                    const leftThird = editorWidth / 3;
                    const rightThird = editorWidth * 2 / 3;

                    // Сохраняем текущую ширину картинки
                    const currentWidth = draggedImage.style.width || '';
                    const currentMaxWidth = draggedImage.style.maxWidth || '';

                    // Убираем все стили позиционирования
                    draggedImage.style.float = '';
                    draggedImage.style.marginLeft = '';
                    draggedImage.style.marginRight = '';
                    draggedImage.style.marginTop = '';
                    draggedImage.style.marginBottom = '';
                    draggedImage.style.display = '';

                    if (mouseX < leftThird) {
                        // Слева
                        draggedImage.style.float = 'left';
                        draggedImage.style.marginRight = '2rem';
                        draggedImage.style.marginBottom = '1.5rem';
                        draggedImage.style.marginTop = '0.5rem';
                        this.showNotification('Изображение слева', 'success');
                    } else if (mouseX > rightThird) {
                        // Справа
                        draggedImage.style.float = 'right';
                        draggedImage.style.marginLeft = '2rem';
                        draggedImage.style.marginBottom = '1.5rem';
                        draggedImage.style.marginTop = '0.5rem';
                        this.showNotification('Изображение справа', 'success');
                    } else {
                        // По центру
                        draggedImage.style.display = 'block';
                        draggedImage.style.marginLeft = 'auto';
                        draggedImage.style.marginRight = 'auto';
                        draggedImage.style.marginTop = '2rem';
                        draggedImage.style.marginBottom = '2rem';
                        this.showNotification('Изображение по центру', 'success');
                    }

                    // Восстанавливаем ширину картинки
                    if (currentWidth) draggedImage.style.width = currentWidth;
                    if (currentMaxWidth) draggedImage.style.maxWidth = currentMaxWidth;
                }

                // Восстанавливаем внешний вид
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
            // Ищем существующий text-block со стилем article
            const existingTextBlockIndex = this.blocks.findIndex(b =>
                b.type === 'text-block' && b.data.containerStyle === 'article'
            );

            if (existingTextBlockIndex !== -1) {
                // Обновляем существующий блок
                this.blocks[existingTextBlockIndex].data.content = html;
            } else {
                // Добавляем новый блок в конец (не заменяем все блоки)
                this.blocks.push({
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

        nl2br(text) {
            if (!text) return '';
            return text.replace(/\n/g, '<br>');
        },

        formatLabel(key) {
            // Преобразует camelCase в читаемый текст
            const labels = {
                'title': 'Заголовок',
                'subtitle': 'Подзаголовок',
                'text': 'Текст',
                'content': 'Контент',
                'backgroundImage': 'Фоновое изображение',
                'buttonText': 'Текст кнопки',
                'buttonLink': 'Ссылка кнопки',
                'image': 'Изображение (URL)',
                'url': 'URL изображения',
                'alt': 'Alt текст',
                'caption': 'Подпись',
                'alignment': 'Выравнивание',
                'width': 'Ширина',
                'height': 'Высота',
                'borderRadius': 'Скругление углов',
                'columns': 'Количество колонок',
                'cards': 'Карточки',
                'items': 'Элементы',
                'paragraphs': 'Параграфы',
                'messages': 'Сообщения',
                'buttons': 'Кнопки',
                'headerTitle': 'Заголовок чата',
                'icon': 'SVG иконка',
                'link': 'Ссылка',
                'question': 'Вопрос',
                'answer': 'Ответ',
                'type': 'Тип',
                'containerStyle': 'Стиль контейнера',
                'style': 'Стиль'
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

            // Определяем структуру нового элемента на основе существующих
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
                // Дефолтные структуры для разных типов
                if (key === 'cards' && this.selectedBlock.type === 'service-cards') {
                    array.push({ icon: '', title: 'Новая карточка', text: 'Описание' });
                } else if (key === 'cards' && this.selectedBlock.type === 'article-cards') {
                    array.push({ image: '', title: 'Новая статья', text: 'Описание', link: '#' });
                } else if (key === 'paragraphs') {
                    array.push('Новый параграф');
                } else if (key === 'messages') {
                    array.push({ type: 'bot', text: 'Новое сообщение' });
                } else if (key === 'buttons') {
                    array.push({ text: 'Кнопка' });
                } else if (key === 'items') {
                    array.push({ question: 'Вопрос?', answer: 'Ответ' });
                } else {
                    array.push({ text: 'Новый элемент' });
                }
            }

            this.showNotification('Элемент добавлен', 'success');
        },

        removeArrayItem(key, index) {
            if (!this.selectedBlock) return;
            const array = this.selectedBlock.data[key];
            if (!Array.isArray(array)) return;

            if (confirm('Удалить этот элемент?')) {
                array.splice(index, 1);
                this.showNotification('Элемент удалён', 'success');
            }
        },

        // ===== RENDER METHODS =====

        renderBlock(block) {
            if (!block.id) {
                console.warn('Block ID is missing. Skipping rendering for block:', block);
                return '<div>Invalid block</div>';
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
            const buttonText = data.buttonText || 'Узнать больше';
            const buttonLink = data.buttonLink || '#';

            return `
                <section class="hero" style="background-image: linear-gradient(rgba(3, 42, 73, 0.6), rgba(3, 42, 73, 0.6)), url('${this.escape(bgImage)}');">
                    <div class="container">
                        <h1 data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.title" data-block-type="${block.type}">${title}</h1>
                        <p data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.text" data-block-type="${block.type}">${this.escape(text)}</p>
                        <a href="${this.escape(buttonLink)}" class="btn btn-primary">${this.escape(buttonText)}</a>
                    </div>
                </section>
            `;
        },

        renderPageHeader(block) {
            const data = block.data || block;
            const title = data.title || 'Заголовок';
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
                    <div class="icon">${card.icon || ''}</div>
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
                        <a href="${this.escape(card.link || '#')}">Читать далее &rarr;</a>
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
            const title = data.title || 'О себе';
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
                            <p data-inline-editable="true" data-block-id="${block.id || ''}" data-field-path="data.content" data-block-type="${block.type}">${content}</p>
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
                            <img src="${this.escape(url)}" alt="${this.escape(alt)}" class="${imageClass}" style="${imageStyle}">
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
            const text = data.text || 'Кнопка';
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
            const text = data.text || 'Заголовок';
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
            const placeholder = data.placeholder || 'Введите ваш вопрос...';
            const buttonText = data.buttonText || '→';

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
                this.debugMsg('Токен авторизации не найден. Запрашиваем вход.', 'warning');
                return false;
            }

            try {
                this.debugMsg('Проверяем авторизацию текущего пользователя', 'info');
                const user = await this.apiClient.getCurrentUser();
                this.debugMsg('Получены данные пользователя от API', 'info', { 
                    userId: user.id, 
                    username: user.username,
                    userObject: user 
                });
                this.currentUser = user;
                this.showLoginModal = false;
                this.debugMsg('Авторизация подтверждена', 'success', { userId: user.id, username: user.username });
                return true;
            } catch (error) {
                console.error('Auth error:', error);
                localStorage.removeItem('cms_auth_token');
                this.showLoginModal = true;
                this.debugMsg('Авторизация не удалась, токен сброшен', 'error', { message: error.message, details: error.details || null });
                return false;
            }
        },

        async login() {
            if (!this.loginForm.username || !this.loginForm.password) {
                this.showNotification('Заполните все поля', 'error');
                this.debugMsg('Попытка входа с пустыми полями', 'warning');
                return;
            }

            try {
                this.debugMsg('Попытка входа', 'info', { username: this.loginForm.username });
                const response = await this.apiClient.login(
                    this.loginForm.username,
                    this.loginForm.password
                );

                this.currentUser = response.user;
                this.showLoginModal = false;
                this.showNotification('Вход выполнен', 'success');
                this.debugMsg('Вход выполнен', 'success', { userId: response.user.id, username: response.user.username });

                // Проверяем, есть ли ID страницы в URL
                const urlParams = new URLSearchParams(window.location.search);
                const pageId = urlParams.get('id');
                if (pageId) {
                    this.debugMsg('После входа обнаружен параметр id. Загружаем страницу', 'info', { pageId });
                    await this.loadPageFromAPI(pageId);
                }
            } catch (error) {
                this.showNotification('Ошибка входа: ' + error.message, 'error');
                this.debugMsg('Ошибка входа', 'error', { message: error.message });
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
            this.debugMsg('Пользователь вышел из системы', 'info');
        },

        async loadPageFromAPI(pageId) {
            if (!this.currentUser) {
                this.showNotification('Необходима авторизация', 'error');
                this.debugMsg('Попытка загрузить страницу без авторизации', 'error', { pageId });
                return;
            }

            try {
                this.debugMsg('Загружаем страницу из API', 'info', { pageId });
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

                // Load menu settings (backend uses snake_case)
                this.pageSettings.showInMenu = Boolean(pagePayload.show_in_menu);
                this.pageSettings.menuPosition = pagePayload.menu_position !== undefined && pagePayload.menu_position !== null ? pagePayload.menu_position : null;
                this.pageSettings.menuTitle = pagePayload.menu_title || '';

                this.blocks = blocksPayload.map((block, index) => {
                    const mapped = blockFromAPI({ ...block, position: index });
                    if (mapped.customName === undefined || mapped.customName === null) {
                        mapped.customName = '';
                    }
                    mapped.position = index;
                    return mapped;
                });

                this.currentPageId = pagePayload.id || pageId;
                this.isEditMode = true;
                this.autoGenerateSlug = false; // Отключаем автогенерацию при загрузке существующей страницы

                this.showNotification('Страница загружена', 'success');
                this.debugMsg('Страница успешно загружена', 'success', { pageId: this.currentPageId, blocks: this.blocks.length });
            } catch (error) {
                console.error('Error loading page:', error);
                this.showNotification('Ошибка загрузки: ' + error.message, 'error');
                this.debugMsg('Ошибка загрузки страницы', 'error', { pageId, message: error.message, details: error.details || null });
            }
        },

        async savePage() {
            if (!this.currentUser) {
                this.showNotification('Необходима авторизация', 'error');
                this.debugMsg('Попытка сохранения без авторизации', 'error');
                return;
            }

            // Дополнительная проверка currentUser.id
            if (!this.currentUser.id) {
                this.showNotification('Ошибка авторизации: отсутствует ID пользователя', 'error');
                this.debugMsg('Ошибка: currentUser.id не определен', 'error', { currentUser: this.currentUser });
                return;
            }

            // Проверяем валидность токена перед сохранением
            try {
                this.debugMsg('Проверяем валидность токена перед сохранением', 'info');
                await this.apiClient.getCurrentUser();
                this.debugMsg('Токен валиден, продолжаем сохранение', 'success');
            } catch (error) {
                this.showNotification('Сессия истекла. Пожалуйста, войдите снова.', 'error');
                this.debugMsg('Токен недействителен, требуется повторный вход', 'error', { message: error.message });
                this.logout();
                return;
            }

            this.debugMsg('Сохранение страницы', 'info', { 
                userId: this.currentUser.id, 
                username: this.currentUser.username,
                title: this.pageData.title,
                slug: this.pageData.slug 
            });

            if (!this.pageData.title || !this.pageData.slug) {
                this.showNotification('Заполните название и slug', 'error');
                this.debugMsg('Сохранение отклонено: отсутствуют название или slug', 'warning', { title: this.pageData.title, slug: this.pageData.slug });
                return;
            }

            const slugValidation = validateSlug(this.pageData.slug);
            if (!slugValidation.valid) {
                this.showNotification(slugValidation.message, 'error');
                this.debugMsg('Некорректный slug', 'error', { slug: this.pageData.slug, message: slugValidation.message });
                return;
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
                // Menu fields (snake_case expected by backend)
                show_in_menu: (this.pageSettings.showInMenu && (this.pageData.status === 'published')) ? 1 : 0,
                menu_position: this.pageSettings.menuPosition === null ? 0 : Number(this.pageSettings.menuPosition || 0),
                menu_title: this.pageSettings.menuTitle || null
            };

            this.debugMsg('========== СОХРАНЕНИЕ СТРАНИЦЫ ==========', 'info');
            this.debugMsg('Блоки на странице', 'info', { 
                totalBlocks: this.blocks.length,
                blockTypes: this.blocks.map(b => b.type),
                blocksPayload: blocksPayload
            });
            this.debugMsg('Данные для отправки в API', 'info', pageDataForAPI);

            try {
                let response;

                this.debugMsg('Отправляем страницу в API', 'info', { isEditMode: this.isEditMode, pageId: this.currentPageId, blocks: blocksPayload.length });
                if (this.isEditMode && this.currentPageId) {
                    response = await this.apiClient.updatePage(this.currentPageId, pageDataForAPI);
                    this.showNotification('✅ Страница обновлена', 'success');
                    this.debugMsg('Страница обновлена', 'success', { pageId: this.currentPageId });
                } else {
                    response = await this.apiClient.createPage(pageDataForAPI);
                    this.currentPageId = response.page_id || response.pageId || response.id;
                    this.isEditMode = true;
                    this.pageData.status = 'draft'; // ← Добавить статус для кнопки "Опубликовать"
                    window.history.pushState({}, '', `?id=${this.currentPageId}`);
                    this.showNotification('✅ Страница создана', 'success');
                    this.debugMsg('Новая страница создана', 'success', { pageId: this.currentPageId });
                }
            } catch (error) {
                console.error('Save error:', error);
                this.showNotification('Ошибка сохранения: ' + error.message, 'error');
                this.debugMsg('Ошибка сохранения страницы', 'error', { message: error.message, details: error.details || null });
            }
        },

        async publishPage() {
            if (!this.currentPageId) {
                this.showNotification('Сначала сохраните страницу', 'error');
                this.debugMsg('Попытка публикации без сохранения', 'warning');
                return;
            }

            try {
                this.debugMsg('Публикуем страницу', 'info', { pageId: this.currentPageId });
                await this.apiClient.publishPage(this.currentPageId);
                this.pageData.status = 'published';
                this.showNotification('✅ Страница опубликована', 'success');
                this.debugMsg('Страница опубликована', 'success', { pageId: this.currentPageId });
            } catch (error) {
                console.error('Publish error:', error);
                this.showNotification('Ошибка публикации: ' + error.message, 'error');
                this.debugMsg('Ошибка публикации страницы', 'error', { pageId: this.currentPageId, message: error.message });
            }
        },

        onTitleChange() {
            // Автогенерация slug при изменении названия
            // Генерируем только если автогенерация включена
            if (this.pageData.title && this.autoGenerateSlug) {
                this.pageData.slug = generateSlug(this.pageData.title);
                this.debugMsg('Slug обновлён автоматически', 'info', { title: this.pageData.title, slug: this.pageData.slug });
            }
        },

        onSlugManualEdit() {
            // При ручном редактировании slug отключаем автогенерацию
            this.autoGenerateSlug = false;
            this.debugMsg('Slug переведён в ручной режим', 'warning', { slug: this.pageData.slug });
        },

        regenerateSlug() {
            // Принудительная регенерация slug из текущего названия
            if (this.pageData.title) {
                this.pageData.slug = generateSlug(this.pageData.title);
                this.autoGenerateSlug = true; // Включаем автогенерацию обратно
                this.showNotification('Slug обновлен из названия', 'success');
                this.debugMsg('Slug регенерирован вручную', 'success', { slug: this.pageData.slug });
            }
        },

        async exportHTML() {
            this.debugMsg('Начинаем экспорт HTML', 'info');
            
            // Загружаем CSS файл
            let cssContent = '';
            try {
                const response = await fetch('styles.css');
                if (response.ok) {
                    cssContent = await response.text();
                    this.debugMsg('CSS файл загружен', 'success', { size: cssContent.length });
                } else {
                    this.debugMsg('Не удалось загрузить CSS файл', 'warning');
                }
            } catch (error) {
                this.debugMsg('Ошибка загрузки CSS', 'error', error);
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

            this.debugMsg('HTML файл экспортирован', 'success', { filename: `${filename}.html` });
            this.showNotification('📥 HTML файл экспортирован со встроенными стилями', 'success');
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
                    this.debugMsg('В медиабиблиотеке нет изображений', 'info');
                } else {
                    this.debugMsg('Загружены изображения медиабиблиотеки', 'info', {
                        count: this.galleryImages.length
                    });
                }
            } catch (e) {
                console.error('Error loading gallery:', e);
                this.showNotification('Ошибка загрузки медиа-файлов', 'error');
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
            this.showNotification('Изображение выбрано', 'success');
        },

        async handleFileUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                this.showNotification('Выберите изображение', 'error');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                this.showNotification('Файл слишком большой (макс. 5MB)', 'error');
                return;
            }

            this.uploadProgress = 'Загрузка...';

            try {
                const result = await this.apiClient.uploadMedia(file, (progress) => {
                    this.uploadProgress = `Загрузка: ${Math.round(progress)}%`;
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
                this.showNotification('✅ Файл загружен', 'success');
            } catch (e) {
                console.error('Upload error:', e);
                this.uploadProgress = null;
                this.showNotification(`Ошибка загрузки файла: ${e.message || 'Неизвестная ошибка'}`, 'error');
            }

            event.target.value = '';
        },

        async deleteImage(image) {
            if (!image) return;
            if (!confirm(`Удалить изображение "${image.filename}"?`)) return;

            try {
                await this.apiClient.deleteMedia(image.id);
                this.galleryImages = this.galleryImages.filter((item) => item.id !== image.id);

                if (this.selectedGalleryImage && this.selectedGalleryImage.id === image.id) {
                    this.selectedGalleryImage = null;
                }

                this.showNotification('Изображение удалено', 'success');
            } catch (e) {
                console.error('Delete error:', e);
                this.showNotification(`Ошибка удаления файла: ${e.message || 'Неизвестная ошибка'}`, 'error');
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
