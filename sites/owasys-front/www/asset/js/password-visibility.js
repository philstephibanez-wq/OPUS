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

  /*
   * R8B6S full-site visible-I18n guard.
   * SCORE translations are handled server-side. This guard only neutralizes
   * legacy visible JS fallbacks that cannot be interpreted as technical IDs.
   */
  const marker = '⚠';
  const locale = String(document.documentElement.lang || '').trim();
  const language = locale.split('-', 1)[0].toLowerCase();

  const originalConfirm = window.confirm.bind(window);
  window.confirm = (message) => originalConfirm(
    String(message) === 'Unsaved changes will be lost.' ? marker : message
  );

  const exactFrench = new Set([
    'Modifier le libellé',
    'Libellé',
    'libellé',
    'traduction à renseigner',
    'validation…',
    'écriture source PHP…',
    'NMI: guards interdits; actions éditables',
    'source PHP développeur · écriture persistante',
  ]);

  const replaceFrenchFragment = (value) => {
    if (language === 'fr') return value;
    let next = value;
    next = next.replace(/Suppression bloquée par:\s*/g, marker + ' ');
    next = next.replace(/signal orphelin:\s*/g, marker + ' ');
    return exactFrench.has(next.trim()) ? marker : next;
  };

  const normalizeNode = (node) => {
    if (!(node instanceof Element)) return;
    if (node.matches('code, pre, textarea, input, option')) return;
    if (node.closest('[data-context="OWASYS_SOURCE_CONTENT_EDITOR"]')) return;

    if (node.childElementCount === 0 && node.textContent) {
      const next = replaceFrenchFragment(node.textContent);
      if (next !== node.textContent) node.textContent = next;
    }
    for (const attribute of ['title', 'aria-label']) {
      if (!node.hasAttribute(attribute)) continue;
      const current = node.getAttribute(attribute) || '';
      const next = replaceFrenchFragment(current);
      if (next !== current) node.setAttribute(attribute, next);
    }

    if (node.matches('[data-fsm-designer-status]') && language !== 'en') {
      const current = node.textContent || '';
      if (/^(persisted|draft)\s+/i.test(current)) {
        node.textContent = current.replace(/^(persisted|draft)\s+/i, marker + ' ');
      }
    }
  };

  const scan = (root) => {
    if (!(root instanceof Element)) return;
    normalizeNode(root);
    root.querySelectorAll('*').forEach(normalizeNode);
  };

  scan(document.body);
  const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      if (mutation.type === 'attributes') {
        normalizeNode(mutation.target);
        continue;
      }
      if (mutation.target instanceof Element) normalizeNode(mutation.target);
      mutation.addedNodes.forEach((node) => {
        if (node instanceof Element) scan(node);
      });
    }
  });
  observer.observe(document.body, {
    subtree: true,
    childList: true,
    characterData: true,
    attributes: true,
    attributeFilter: ['title', 'aria-label'],
  });
})();
