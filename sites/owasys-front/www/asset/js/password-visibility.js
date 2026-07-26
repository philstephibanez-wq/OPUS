(() => {
  'use strict';
  const selector = '[data-ow-password-toggle]';
  const toggle = (button) => {
    const input = document.getElementById(button.getAttribute('aria-controls') || '');
    if (!(input instanceof HTMLInputElement)) return;
    const showing = input.type === 'text';
    let start = null, end = null;
    try { start = input.selectionStart; end = input.selectionEnd; } catch (_) {}
    input.type = showing ? 'password' : 'text';
    const pressed = !showing;
    button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
    const label = pressed ? button.dataset.labelHide : button.dataset.labelShow;
    if (label) { button.setAttribute('aria-label', label); button.title = label; }
    input.focus({preventScroll: true});
    if (start !== null && end !== null) { try { input.setSelectionRange(start, end); } catch (_) {} }
  };
  document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest(selector) : null;
    if (button instanceof HTMLButtonElement) toggle(button);
  });
})();
