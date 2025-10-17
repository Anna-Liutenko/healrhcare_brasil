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

    // bound handlers
    this._onMouseEnter = this._onMouseEnter.bind(this);
    this._onMouseLeave = this._onMouseLeave.bind(this);
    this._onClickElement = this._onClickElement.bind(this);
    this._onInput = this._onInput.bind(this);
    this._onBlur = this._onBlur.bind(this);
    this._onGlobalKeydown = this._onGlobalKeydown.bind(this);
    this._onDocumentClick = this._onDocumentClick.bind(this);
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

    this._hideTooltip();

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
    const target = e.target;
    if (!this.preview.contains(target)) {
      // clicked outside preview entirely
      this._blurActiveElement();
      return;
    }

    // if clicked an element that is not an inline-editable nor inside one, blur
    const editable = target.closest && target.closest('[data-inline-editable="true"]');
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

  // --- editing lifecycle
  startEdit(element) {
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

    // Non-invasive debug: log blockId/fieldPath and preview of html for diagnosis
    try {
      console.debug('[InlineEditor] saveChanges called', {
        pageId: this.pageId,
        blockId: blockId || null,
        fieldPath: fieldPath || null,
        htmlPreviewSample: (this.activeElement && this.activeElement.innerHTML) ? this.activeElement.innerHTML.slice(0, 800) : null
      });
    } catch (e) {
      // swallow logging errors
    }

    if (!blockId || !fieldPath) {
      console.warn('InlineEditor: missing data-block-id or data-field-path; skipping save', this.activeElement);
      return;
    }

    const html = this.activeElement.innerHTML;
    let markdown = html;

    try {
      if (typeof TurndownService === 'function' || typeof window.TurndownService === 'function') {
        const T = typeof TurndownService === 'function' ? TurndownService : window.TurndownService;
        const td = new T();
        markdown = td.turndown(html);
      } else {
        console.warn('TurndownService not available; sending HTML as markdown fallback');
      }
    } catch (err) {
      console.warn('Turndown conversion failed, using HTML as markdown fallback', err);
      markdown = html;
    }

  // Include both 'markdown' and 'newMarkdown' for backward/forward compatibility
  const payload = { blockId, fieldPath, markdown, newMarkdown: markdown, html };
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
      console.log('InlineEditor: save OK', json);
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
}

window.InlineEditorManager = InlineEditorManager;
