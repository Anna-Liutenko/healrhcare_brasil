/**
 * InlineEditorManager — single consolidated implementation
 * - Debounced autosave (2s)
 * - Ctrl+S manual save
 * - Tooltip on hover
 * - saveChanges uses Turndown if available, otherwise falls back to HTML
 */
class InlineEditorManager {
  constructor(previewElement, pageId) {
    this.preview = previewElement;
    this.pageId = pageId;
    this.activeElement = null;
    this.isInlineMode = false;
    this.undoStack = [];
    this.redoStack = [];

    // timers / helpers
    this._debounceTimer = null;
    this._tooltip = null;
    this._toolbar = null;
    this._selectedRange = null; // Store selection for formatting operations
    this._savedSelectionRange = null; // Store selection when toolbar clicked
    this._isFormattingAction = false; // Flag to prevent blur during formatting
    this._toolbarButtons = null; // Cache toolbar buttons for state updates

    // bound handlers
    this._onMouseEnter = this._onMouseEnter.bind(this);
    this._onMouseLeave = this._onMouseLeave.bind(this);
    this._onClickElement = this._onClickElement.bind(this);
    this._onInput = this._onInput.bind(this);
    this._onBlur = this._onBlur.bind(this);
    this._onGlobalKeydown = this._onGlobalKeydown.bind(this);
    this._onDocumentClick = this._onDocumentClick.bind(this);
    this._onSelectionChange = this._onSelectionChange.bind(this);
  }

  enableInlineMode() {
    if (this.isInlineMode) return;
    this.isInlineMode = true;

    const editables = this.preview.querySelectorAll('[data-inline-editable="true"]');
    editables.forEach(el => {
      el.addEventListener('mouseenter', this._onMouseEnter);
      el.addEventListener('mouseleave', this._onMouseLeave);
      el.addEventListener('click', this._onClickElement);
      el.classList.add('inline-editable-ready');
    });

    // global key handler for Ctrl+S
    document.addEventListener('keydown', this._onGlobalKeydown);
    // close active editing when clicking outside editable elements
    document.addEventListener('click', this._onDocumentClick, true);
    // track selection changes for formatting toolbar
    document.addEventListener('selectionchange', this._onSelectionChange);
    // Alternative: track selection via mouseup and keyup
    document.addEventListener('mouseup', this._onSelectionChange);
    document.addEventListener('keyup', this._onSelectionChange);

    console.log(`InlineEditor: enabled for ${editables.length} elements`);
  }

  disableInlineMode() {
    if (!this.isInlineMode) return;
    this.isInlineMode = false;

    const editables = this.preview.querySelectorAll('[data-inline-editable="true"]');
    editables.forEach(el => {
      el.removeEventListener('mouseenter', this._onMouseEnter);
      el.removeEventListener('mouseleave', this._onMouseLeave);
      el.removeEventListener('click', this._onClickElement);
      el.classList.remove('inline-editable-hover', 'inline-editable-ready');
      el.removeAttribute('contenteditable');
    });

    document.removeEventListener('keydown', this._onGlobalKeydown);
    document.removeEventListener('click', this._onDocumentClick, true);
    document.removeEventListener('selectionchange', this._onSelectionChange);
    document.removeEventListener('mouseup', this._onSelectionChange);
    document.removeEventListener('keyup', this._onSelectionChange);

    this._hideTooltip();
    this._hideFormattingToolbar();

    // keep last content but clear editing state
    if (this.activeElement) {
      this.activeElement.removeEventListener('input', this._onInput);
      this.activeElement.removeEventListener('blur', this._onBlur);
      this.activeElement.removeAttribute('contenteditable');
      this.activeElement = null;
    }

    this.undoStack = [];
    this.redoStack = [];

    if (this._debounceTimer) {
      clearTimeout(this._debounceTimer);
      this._debounceTimer = null;
    }

    console.log('InlineEditor: disabled');
  }

  // --- events
  _onMouseEnter(e) {
    if (!this.isInlineMode) return;
    const target = e.currentTarget;
    target.classList.add('inline-editable-hover');
    this._showTooltip(target);
  }

  _onMouseLeave(e) {
    if (!this.isInlineMode) return;
    const target = e.currentTarget;
    target.classList.remove('inline-editable-hover');
    this._hideTooltip();
  }

  _onClickElement(e) {
    if (!this.isInlineMode) return;
    e.preventDefault();
    e.stopPropagation();
    const el = e.currentTarget;
    this._hideTooltip();
    this.startEdit(el);
  }

  _onDocumentClick(e) {
    // if click is outside preview or outside any inline editable, stop editing
    if (!this.isInlineMode) return;
    if (this._isFormattingAction) return;
    const target = e.target;
    if (!target) return;

    // Normalize target so we can call closest even when originating from text nodes
    const baseNode = target.nodeType === Node.TEXT_NODE ? target.parentElement : target;
    if (!baseNode) return;

    if (baseNode.closest('.inline-formatting-toolbar')) {
      return;
    }
    if (!this.preview.contains(baseNode)) {
      // clicked outside preview entirely
      this._blurActiveElement();
      return;
    }

    // if clicked an element that is not an inline-editable nor inside one, blur
    const editable = baseNode.closest('[data-inline-editable="true"]');
    if (!editable) {
      this._blurActiveElement();
    }
  }

  _onInput(e) {
    // keep simple undo snapshots (only when changed)
    const html = e.currentTarget.innerHTML;
    const last = this.undoStack.length ? this.undoStack[this.undoStack.length - 1] : null;
    if (last !== html) {
      this.pushUndoState(html);
    }

    // schedule debounced save
    if (this._debounceTimer) clearTimeout(this._debounceTimer);
    this._debounceTimer = setTimeout(() => {
      this.saveChanges().catch(err => {
        console.warn('Auto-save failed:', err);
      });
      this._debounceTimer = null;
    }, 2000);
  }

  _onBlur(e) {
    // Don't blur if we're in the middle of a formatting action
    if (this._isFormattingAction) {
      if (this.activeElement) {
        setTimeout(() => this.activeElement.focus(), 0);
      }
      return;
    }

    // Don't blur if focus is moving to toolbar
    const relatedTarget = e.relatedTarget;
    if (relatedTarget && relatedTarget.closest('.inline-formatting-toolbar')) {
      // Keep focus on activeElement, focus moved to toolbar temporarily
      if (this.activeElement) {
        setTimeout(() => this.activeElement.focus(), 0);
      }
      return;
    }

    // immediate save on blur (if there are unsaved changes)
    if (this._debounceTimer) {
      clearTimeout(this._debounceTimer);
      this._debounceTimer = null;
    }
    this.saveChanges().catch(err => console.warn('Save on blur failed:', err));
  }

  _onGlobalKeydown(e) {
    if (!this.isInlineMode) return;
    const isSave = (e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S');
    if (isSave) {
      e.preventDefault();
      if (!this.activeElement) return;
      // cancel pending debounce and save immediately
      if (this._debounceTimer) {
        clearTimeout(this._debounceTimer);
        this._debounceTimer = null;
      }
      this.saveChanges().catch(err => console.warn('Manual save failed:', err));
    }
  }

  _onSelectionChange(e) {
    if (!this.isInlineMode || !this.activeElement) {
      this._hideFormattingToolbar();
      return;
    }

    const selection = window.getSelection();
    if (!selection) {
      this._selectedRange = null;
      this._setToolbarSelectionState(false);
      this._showFormattingToolbar(true);
      return;
    }

    const hasRange = selection.rangeCount > 0 && !selection.isCollapsed && selection.toString().length > 0;

    if (hasRange) {
      const range = selection.getRangeAt(0);
      const editable = this.activeElement;
      if (editable && editable.contains(range.commonAncestorContainer)) {
        this._selectedRange = range.cloneRange();
        this._setToolbarSelectionState(true);
        this._showFormattingToolbar();
        console.debug('[InlineEditor] 📝 Selection detected - showing toolbar:', {
          text: selection.toString().slice(0, 30),
          elementType: editable.tagName
        });
        return;
      }
    }

    this._selectedRange = null;
    this._setToolbarSelectionState(false);

    const anchorNode = selection.anchorNode;
    if (anchorNode && this.activeElement.contains(anchorNode)) {
      this._showFormattingToolbar(true);
    } else {
      this._hideFormattingToolbar();
    }
  }

  // --- editing lifecycle
  startEdit(element) {
    // Don't restart editing if already editing this element
    if (this.activeElement === element) {
      console.debug('[InlineEditor] Already editing this element, skipping');
      return;
    }

    // If another element was active, properly stop listening and clear state
    if (this.activeElement && this.activeElement !== element) {
      try {
        this.activeElement.removeEventListener('input', this._onInput);
        this.activeElement.removeEventListener('blur', this._onBlur);
        this.activeElement.classList.remove('inline-editable-hover');
        this.activeElement.removeAttribute('contenteditable');
      } catch (e) {}
    }

    this.activeElement = element;
    element.setAttribute('contenteditable', 'true');
    element.focus();

    // initial snapshot
    this.pushUndoState(element.innerHTML);

    element.addEventListener('input', this._onInput);
    element.addEventListener('blur', this._onBlur);

    this._selectedRange = null;
    this._savedSelectionRange = null;
    
    console.log('[InlineEditor] 🚀 About to call _setToolbarSelectionState and _showFormattingToolbar');
    this._setToolbarSelectionState(false);
    console.log('[InlineEditor] 🚀 _setToolbarSelectionState completed, calling _showFormattingToolbar');
    this._showFormattingToolbar(true);
    console.log('[InlineEditor] 🚀 _showFormattingToolbar completed');

    console.log('InlineEditor: start editing', element);
  }

  _blurActiveElement() {
    if (!this.activeElement) return;
    try {
      this.activeElement.removeEventListener('input', this._onInput);
      this.activeElement.removeEventListener('blur', this._onBlur);
      this.activeElement.classList.remove('inline-editable-hover');
      this.activeElement.removeAttribute('contenteditable');
      // blur DOM focus
      if (document.activeElement === this.activeElement) {
        this.activeElement.blur();
      }
    } catch (e) {}
    this.activeElement = null;
    this._hideFormattingToolbar();
  }

  // note: we keep the element editable until disableInlineMode is called

  // --- save
  async saveChanges() {
    if (!this.activeElement) {
      console.warn('InlineEditor: no active element to save');
      return;
    }

    const blockId = this.activeElement.dataset.blockId;
    const fieldPath = this.activeElement.dataset.fieldPath;

    if (!blockId || !fieldPath) {
      console.warn('InlineEditor: missing data-block-id or data-field-path; skipping save', this.activeElement);
      return;
    }

    const rawHTML = this.activeElement.innerHTML;
    let markdown = rawHTML;

    console.log('[InlineEditor] 💾 SAVE START', {
      blockId,
      fieldPath,
      htmlPreview: rawHTML.slice(0, 150),
      hasStrongTag: rawHTML.includes('<strong>'),
      hasEmTag: rawHTML.includes('<em>'),
      hasUTag: rawHTML.includes('<u>'),
      hasSTag: rawHTML.includes('<s>')
    });
    
    console.log('[InlineEditor] 💾💾 FULL RAW HTML:', rawHTML);
    console.log('[InlineEditor] 💾💾 HTML TAGS PRESENT:', {
      '<b>': rawHTML.includes('<b>'),
      '<strong>': rawHTML.includes('<strong>'),
      '<i>': rawHTML.includes('<i>'),
      '<em>': rawHTML.includes('<em>'),
      '<u>': rawHTML.includes('<u>'),
      '<strike>': rawHTML.includes('<strike>'),
      '<s>': rawHTML.includes('<s>')
    });

    try {
      if (typeof TurndownService === 'function' || typeof window.TurndownService === 'function') {
        const T = typeof TurndownService === 'function' ? TurndownService : window.TurndownService;
        const td = new T();
        
        // Add rule to convert <strike> and <s> to ~~strikethrough~~
        td.addRule('strikethrough', {
          filter: ['strike', 's', 'del'],
          replacement: function(content) {
            return '~~' + content + '~~';
          }
        });
        
        // Add rule to preserve <u> tags (underline not in standard markdown)
        td.addRule('underline', {
          filter: ['u'],
          replacement: function(content) {
            return '<u>' + content + '</u>';
          }
        });
        
        markdown = td.turndown(rawHTML);
        console.log('[InlineEditor] 📝 Markdown conversion', {
          markdownPreview: markdown.slice(0, 150),
          hasBoldMarker: markdown.includes('**'),
          hasItalicMarker: markdown.includes('_') || markdown.includes('*'),
          hasUnderlineTag: markdown.includes('<u>'),
          hasStrikeTag: markdown.includes('~~') || markdown.includes('<s>')
        });
        
        console.log('[InlineEditor] 📝📝 FULL MARKDOWN:', markdown);
        console.log('[InlineEditor] 📝📝 MARKDOWN MARKERS:', {
          '**bold**': markdown.includes('**'),
          '_italic_': markdown.includes('_'),
          '*italic*': markdown.includes('*'),
          '<u>underline</u>': markdown.includes('<u>'),
          '~~strike~~': markdown.includes('~~')
        });
      } else {
        console.warn('TurndownService not available; sending HTML as markdown fallback');
      }
    } catch (err) {
      console.warn('Turndown conversion failed, using HTML as markdown fallback', err);
      markdown = rawHTML;
    }

  // Include both 'markdown' and 'newMarkdown' for backward/forward compatibility
  const payload = { blockId, fieldPath, markdown, newMarkdown: markdown, html: rawHTML };
    const url = `/healthcare-cms-backend/api/pages/${this.pageId}/inline`;

    // Non-invasive debug: show small preview of payload (no sensitive full dumps)
    try {
      const payloadPreview = Object.assign({}, payload);
      if (payloadPreview.html && payloadPreview.html.length > 1000) payloadPreview.html = payloadPreview.html.slice(0, 1000) + '...';
      if (payloadPreview.markdown && payloadPreview.markdown.length > 1000) payloadPreview.markdown = payloadPreview.markdown.slice(0, 1000) + '...';
      console.debug('[InlineEditor] PATCH payload preview', payloadPreview, 'url:', url);
    } catch (e) {}

    try {
      const res = await fetch(url, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!res.ok) {
        const text = await res.text();
        console.error('InlineEditor: save failed HTTP', res.status, text);
        this._showSaveError();
        return;
      }

      const json = await res.json().catch(() => ({ success: true }));
      // success: brief visual feedback
      this._flashSaved(this.activeElement);
      console.log('[InlineEditor] ✅ SAVE SUCCESS', {
        response: json,
        savedHTML: rawHTML.slice(0, 150),
        savedMarkdown: markdown.slice(0, 150)
      });
      return json;
    } catch (err) {
      console.error('InlineEditor: network/save error', err);
      this._showSaveError();
      throw err;
    }
  }

  // --- helpers
  pushUndoState(html) {
    if (!html) html = '';
    const last = this.undoStack.length ? this.undoStack[this.undoStack.length - 1] : null;
    if (last === html) return;
    this.undoStack.push(html);
    if (this.undoStack.length > 100) this.undoStack.shift();
    this.redoStack = [];
  }

  undo() {
    if (!this.activeElement || this.undoStack.length === 0) return;
    const prev = this.undoStack.pop();
    this.redoStack.push(this.activeElement.innerHTML);
    this.activeElement.innerHTML = prev;
  }

  redo() {
    if (!this.activeElement || this.redoStack.length === 0) return;
    const next = this.redoStack.pop();
    this.undoStack.push(this.activeElement.innerHTML);
    this.activeElement.innerHTML = next;
  }

  _showTooltip(target) {
    // create tooltip lazily
    if (!this._tooltip) {
      this._tooltip = document.createElement('div');
      this._tooltip.className = 'inline-tooltip';
      this._tooltip.textContent = 'Редактировать — Ctrl+S сохранить';
      document.body.appendChild(this._tooltip);
    }

    // position near target
    const rect = target.getBoundingClientRect();
    const top = Math.max(8, rect.top - 30);
    const left = rect.left + Math.min(200, rect.width / 2);
    this._tooltip.style.top = top + window.scrollY + 'px';
    this._tooltip.style.left = left + window.scrollX + 'px';
    this._tooltip.style.display = 'block';
  }

  _hideTooltip() {
    if (!this._tooltip) return;
    this._tooltip.style.display = 'none';
  }

  _flashSaved(el) {
    try {
      el.classList.add('inline-saved');
      setTimeout(() => el.classList.remove('inline-saved'), 900);
    } catch (e) {}
  }

  _showSaveError() {
    // gentle visual hint on save error
    if (!this.activeElement) return;
    const el = this.activeElement;
    el.classList.add('inline-save-error');
    setTimeout(() => el.classList.remove('inline-save-error'), 1600);
  }

  // --- Formatting Methods ---

  _showFormattingToolbar(force = false) {
    if (!this._toolbar) {
      this._toolbar = this._createFormattingToolbar();
      document.body.appendChild(this._toolbar);
      console.log('[InlineEditor] 🎨 TOOLBAR CREATED', {
        element: this._toolbar,
        isInDOM: document.body.contains(this._toolbar),
        className: this._toolbar.className,
        computedDisplay: window.getComputedStyle(this._toolbar).display,
        computedVisibility: window.getComputedStyle(this._toolbar).visibility,
        computedOpacity: window.getComputedStyle(this._toolbar).opacity
      });
    }

    if (!force && !this._selectedRange) {
      console.debug('[InlineEditor] ⚠️ No selected range, cannot show toolbar');
      return;
    }

    // Position toolbar fixed, NOT above selection (to avoid layout issues)
    // Place it at the top of viewport, centered
    // Position at top with small margin (CSS handles centering)
    this._toolbar.style.top = '10px';
    this._toolbar.style.left = '50%';
    this._toolbar.style.zIndex = '99999';
    this._toolbar.style.visibility = 'visible';
    this._toolbar.style.opacity = '1';
    this._toolbar.style.pointerEvents = 'auto';
    this._toolbar.style.transform = 'translate(-50%, 0)';
    this._toolbar.classList.add('is-visible');
    
    console.log('[InlineEditor] 👁️ TOOLBAR SHOWN', {
      hasClass: this._toolbar.classList.contains('is-visible'),
      visibility: this._toolbar.style.visibility,
      opacity: this._toolbar.style.opacity,
      computedVisibility: window.getComputedStyle(this._toolbar).visibility,
      computedOpacity: window.getComputedStyle(this._toolbar).opacity,
      top: this._toolbar.style.top,
      left: this._toolbar.style.left
    });
  }  _hideFormattingToolbar() {
    if (!this._toolbar) return;
  this._toolbar.classList.remove('is-visible');
  this._toolbar.style.visibility = 'hidden';
  this._toolbar.style.opacity = '0';
  this._toolbar.style.pointerEvents = 'none';
  this._toolbar.style.transform = 'translate(-50%, -8px)';
    console.debug('[InlineEditor] ✅ Toolbar hidden');
    this._setToolbarSelectionState(false);
    this._selectedRange = null;
    this._savedSelectionRange = null;
  }

  _createFormattingToolbar() {
    const toolbar = document.createElement('div');
    toolbar.className = 'inline-formatting-toolbar';
    toolbar.style.position = 'fixed';
    toolbar.style.zIndex = '99999';
    
    console.debug('[InlineEditor] 🔧 Creating toolbar');

    const buttonRefs = Object.create(null);
    const buttons = [
      { icon: '<strong>B</strong>', title: 'Жирный (Ctrl+B)', action: () => this.formatBold(), key: 'bold' },
      { icon: '<em>I</em>', title: 'Курсив (Ctrl+I)', action: () => this.formatItalic(), key: 'italic' },
      { icon: '<u>U</u>', title: 'Подчёркивание (Ctrl+U)', action: () => this.formatUnderline(), key: 'underline' },
      { icon: '<s>S</s>', title: 'Зачёркивание', action: () => this.formatStrikethrough(), key: 'strikethrough' },
      { icon: '🔗', title: 'Ссылка', action: () => this.insertLink(), key: 'link' },
      { icon: '✕', title: 'Сбросить форматирование', action: () => this.clearFormatting(), key: 'clear' }
    ];

    buttons.forEach(btn => {
      const button = document.createElement('button');
      button.className = 'inline-toolbar-btn';
      button.type = 'button';
      button.innerHTML = btn.icon;
      button.title = btn.title;
      button.setAttribute('data-format', btn.key);
      
      // CRITICAL: Use pointerdown instead of click to preserve selection
      // pointerdown fires BEFORE focus is lost, unlike click
      button.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        this._isFormattingAction = true;

        console.debug('[InlineEditor] 👆 Button pointerdown:', { format: btn.key });
        
        // Save selection right now, before anything else happens
        const sel = window.getSelection();
        if (sel && sel.rangeCount > 0) {
          this._savedSelectionRange = sel.getRangeAt(0).cloneRange();
          console.debug('[InlineEditor] 💾 Selection saved:', {
            text: sel.toString().slice(0, 30),
            rangeCount: sel.rangeCount
          });
        }
      });

      button.addEventListener('pointerup', () => {
        // pointerup fires before click; keep flag true until click handler runs
        // click handler will reset flag in finally block
      });

      button.addEventListener('pointercancel', () => {
        this._isFormattingAction = false;
      });
      
      button.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (button.disabled) {
          return;
        }

        console.debug('[InlineEditor] 🖱️ Button click:', {
          format: btn.key,
          hasSavedSelection: !!this._savedSelectionRange,
          hasActiveElement: !!this.activeElement
        });
        
        const executeAction = async () => {
          try {
            // Restore saved selection before formatting
            if (this._savedSelectionRange && this.activeElement) {
              const sel = window.getSelection();
              sel.removeAllRanges();
              sel.addRange(this._savedSelectionRange);
              
              // Verify selection was restored
              const restored = window.getSelection();
              console.debug('[InlineEditor] 🔄 Selection restored:', {
                text: restored.toString().slice(0, 30),
                rangeCount: restored.rangeCount
              });
            }
            
            // Apply formatting (can be sync or async, so await it)
            await btn.action();
            
            // Save to undo stack
            if (this.activeElement) {
              this.pushUndoState(this.activeElement.innerHTML);
              console.debug('[InlineEditor] 💾 Undo state saved after formatting');
            }
          } catch (err) {
            console.error('[InlineEditor] ❌ Error during formatting:', err);
          } finally {
            this._isFormattingAction = false;
            // Clear saved selection
            this._savedSelectionRange = null;
            
            // Restore focus to editable element
            if (this.activeElement) {
              this.activeElement.focus();
              console.debug('[InlineEditor] ✅ Focus restored to active element');
            }
          }
        };
        
        executeAction();
      });

      toolbar.appendChild(button);
      buttonRefs[btn.key] = button;
    });

    this._toolbarButtons = buttonRefs;
    this._setToolbarSelectionState(false);
    return toolbar;
  }

  _setToolbarSelectionState(hasSelection) {
    if (!this._toolbarButtons) return;
    const disable = !hasSelection;
    ['link', 'clear'].forEach(key => {
      const btn = this._toolbarButtons[key];
      if (btn) {
        btn.disabled = disable;
      }
    });
    if (this._toolbar) {
      this._toolbar.dataset.hasSelection = hasSelection ? 'true' : 'false';
    }
  }

  formatBold() {
    if (!this.activeElement) {
      console.warn('[InlineEditor] No active element for formatBold');
      return;
    }
    const beforeHTML = this.activeElement.innerHTML;
    console.debug('[InlineEditor] 🔤 BEFORE BOLD:', beforeHTML);
    
    document.execCommand('bold', false, null);
    
    const afterHTML = this.activeElement.innerHTML;
    console.debug('[InlineEditor] 🔤 AFTER BOLD:', afterHTML);
    console.debug('[InlineEditor] 🔤 HAS <B>:', afterHTML.includes('<b>') || afterHTML.includes('<strong>'));
  }

  formatItalic() {
    if (!this.activeElement) {
      console.warn('[InlineEditor] No active element for formatItalic');
      return;
    }
    console.debug('[InlineEditor] Applying italic formatting');
    document.execCommand('italic', false, null);
  }

  formatUnderline() {
    if (!this.activeElement) {
      console.warn('[InlineEditor] No active element for formatUnderline');
      return;
    }
    console.debug('[InlineEditor] Applying underline formatting');
    document.execCommand('underline', false, null);
  }

  formatStrikethrough() {
    if (!this.activeElement) {
      console.warn('[InlineEditor] No active element for formatStrikethrough');
      return;
    }
    console.debug('[InlineEditor] Applying strikethrough formatting');
    document.execCommand('strikethrough', false, null);
  }

  async insertLink() {
    if (!this.activeElement) {
      console.warn('[InlineEditor] No active element for insertLink');
      return;
    }

    console.debug('[InlineEditor] Inserting link, saved selection:', !!this._savedSelectionRange);
    
    // Restore saved selection for checking if text is selected
    if (this._savedSelectionRange) {
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(this._savedSelectionRange);
    }
    
    const selection = window.getSelection();
    const selectedText = selection ? selection.toString() : '';
    
    if (!selectedText.length) {
      console.warn('[InlineEditor] No text selected for link');
      this.showNotification?.('Выделите текст для ссылки', 'warning');
      return;
    }

    try {
      // Use window.prompt for URL (simple fallback)
      const url = window.prompt('Введите URL ссылки:', 'https://');
      
      if (url && this.activeElement) {
        // Restore selection again after modal closes
        if (this._savedSelectionRange) {
          const sel = window.getSelection();
          sel.removeAllRanges();
          sel.addRange(this._savedSelectionRange);
        }
        
        document.execCommand('createLink', false, url);
        console.debug('[InlineEditor] ✅ Link inserted:', url);
      }
    } catch (err) {
      console.error('[InlineEditor] Error inserting link:', err);
    }
  }

  clearFormatting() {
    if (!this.activeElement) {
      console.warn('[InlineEditor] No active element for clearFormatting');
      return;
    }
    console.debug('[InlineEditor] Clearing formatting');
    document.execCommand('removeFormat', false, null);
  }
}

window.InlineEditorManager = InlineEditorManager;
