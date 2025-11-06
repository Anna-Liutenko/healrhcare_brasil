// Paste this into browser console to diagnose toolbar issues
(function() {
  console.log('========== TOOLBAR DIAGNOSTIC ==========');
  
  const toolbar = document.querySelector('.inline-formatting-toolbar');
  console.log('1. Toolbar element found:', !!toolbar);
  
  if (toolbar) {
    const computed = window.getComputedStyle(toolbar);
    console.log('2. Toolbar classes:', toolbar.className);
    console.log('3. Toolbar inline styles:', toolbar.style.cssText);
    console.log('4. Computed visibility:', computed.visibility);
    console.log('5. Computed opacity:', computed.opacity);
    console.log('6. Computed display:', computed.display);
    console.log('7. Computed z-index:', computed.zIndex);
    console.log('8. Computed position:', computed.position);
    console.log('9. Computed top:', computed.top);
    console.log('10. Computed left:', computed.left);
    console.log('11. Computed transform:', computed.transform);
    console.log('12. Computed pointer-events:', computed.pointerEvents);
    console.log('13. In DOM:', document.body.contains(toolbar));
    console.log('14. Parent element:', toolbar.parentElement?.tagName);
    console.log('15. BoundingClientRect:', toolbar.getBoundingClientRect());
    console.log('16. Buttons count:', toolbar.querySelectorAll('button').length);
  }
  
  const editables = document.querySelectorAll('[data-inline-editable="true"]');
  console.log('17. Editable elements found:', editables.length);
  
  const activeEditable = document.querySelector('[contenteditable="true"]');
  console.log('18. Active editable:', !!activeEditable);
  
  if (activeEditable) {
    console.log('19. Active element HTML preview:', activeEditable.innerHTML.slice(0, 100));
    console.log('20. Active element has formatting:', {
      strong: activeEditable.querySelector('strong'),
      em: activeEditable.querySelector('em'),
      u: activeEditable.querySelector('u'),
      s: activeEditable.querySelector('s')
    });
  }
  
  console.log('========== END DIAGNOSTIC ==========');
})();
