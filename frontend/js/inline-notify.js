// файл: frontend/js/inline-notify.js
(function(){
  function ensureContainer(){
    let c = document.getElementById('inline-notify-container');
    if(!c){ c = document.createElement('div'); c.id='inline-notify-container'; c.style.position='fixed'; c.style.right='1rem'; c.style.bottom='1rem'; c.style.zIndex='99999'; c.style.display='flex'; c.style.flexDirection='column'; c.style.alignItems='flex-end'; c.style.gap = '6px'; document.body.appendChild(c);} 
    return c;
  }
  window.inlineNotify = function(message, type){
    try{
      // prefer header notify area if present
      const header = document.getElementById('header-notify');
      if (header) {
        const el = document.createElement('div');
        el.className = 'header-notify-item header-notify-' + (type||'info');
        el.textContent = message;
        header.appendChild(el);
        setTimeout(()=> { try { el.remove(); } catch(e){} }, 3000);
        return;
      }

      const c = ensureContainer();
      const el = document.createElement('div');
      el.className = 'inline-notify inline-notify-'+(type||'info');
      el.textContent = message;
      el.style.marginTop='0.5rem'; el.style.padding='0.6rem 0.8rem'; el.style.borderRadius='6px'; el.style.color='#fff'; el.style.boxShadow='0 6px 18px rgba(0,0,0,0.12)'; el.style.maxWidth='320px'; el.style.wordBreak='break-word';
      if(type==='success') el.style.background='#4CAF50'; else if(type==='error') el.style.background='#F44336'; else el.style.background='#333';
      c.appendChild(el);
      setTimeout(()=> { try { el.remove(); } catch(e){} }, 3000);
    }catch(e){console.warn('inlineNotify failed', e)}
  }
})();
