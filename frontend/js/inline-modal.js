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
})();
