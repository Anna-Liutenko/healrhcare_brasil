// frontend/js/inline-modal.js
(function(){
  function ensure(){
    let c = document.getElementById('inline-modal-root');
    if(!c){ c = document.createElement('div'); c.id='inline-modal-root'; document.body.appendChild(c);} return c;
  }
  window.inlineConfirm = function(message){
    return new Promise(resolve => {
      const root = ensure();
      root.innerHTML = `
        <div class="inline-modal-backdrop">
          <div class="inline-modal">
            <div class="inline-modal-body">${message}</div>
            <div class="inline-modal-actions">
              <button id="inline-confirm-yes">Да</button>
              <button id="inline-confirm-no">Нет</button>
            </div>
          </div>
        </div>
      `;
      document.getElementById('inline-confirm-yes').addEventListener('click', ()=>{ root.innerHTML=''; resolve(true); });
      document.getElementById('inline-confirm-no').addEventListener('click', ()=>{ root.innerHTML=''; resolve(false); });
    });
  }

  window.inlinePrompt = function(message, defaultValue = ''){
    return new Promise(resolve => {
      const root = ensure();
      root.innerHTML = `
        <div class="inline-modal-backdrop">
          <div class="inline-modal">
            <div class="inline-modal-body">${message}</div>
            <div class="inline-modal-input">
              <input type="text" id="inline-prompt-input" value="${defaultValue}" />
            </div>
            <div class="inline-modal-actions">
              <button id="inline-prompt-ok">OK</button>
              <button id="inline-prompt-cancel">Отмена</button>
            </div>
          </div>
        </div>
      `;
      const input = document.getElementById('inline-prompt-input');
      input.focus();
      input.select();
      
      const handleOk = () => {
        root.innerHTML = '';
        resolve(input.value.trim() || null);
      };
      
      const handleCancel = () => {
        root.innerHTML = '';
        resolve(null);
      };
      
      document.getElementById('inline-prompt-ok').addEventListener('click', handleOk);
      document.getElementById('inline-prompt-cancel').addEventListener('click', handleCancel);
      
      // Allow Enter to submit
      input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          handleOk();
        }
      });
      
      // Allow Escape to cancel
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          handleCancel();
        }
      });
    });
  }
})();
