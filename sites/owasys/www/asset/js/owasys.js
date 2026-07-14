document.documentElement.dataset.owasysAsset = '1';

/**
 * Adds an explicit password visibility toggle to every OWASYS password field.
 * The button is generated client-side so the server-side view-models stay data-only.
 */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[type="password"]').forEach((input) => {
    if (!(input instanceof HTMLInputElement) || input.dataset.owasysPasswordToggle === '1') {
      return;
    }

    input.dataset.owasysPasswordToggle = '1';
    const wrapper = document.createElement('span');
    wrapper.className = 'ow-password-field';
    const parent = input.parentNode;
    if (!parent) {
      return;
    }

    parent.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'ow-password-eye';
    button.setAttribute('aria-label', 'Afficher le mot de passe');
    button.setAttribute('aria-pressed', 'false');
    button.textContent = '👁';

    button.addEventListener('click', () => {
      const visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      button.setAttribute('aria-label', visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
      button.setAttribute('aria-pressed', visible ? 'false' : 'true');
    });

    wrapper.appendChild(button);
  });

  document.querySelectorAll('[data-context="OWASYS_STRUCTURE_APPLY_DRAFT_FORM"]').forEach((form) => {
    if (!(form instanceof HTMLFormElement) || form.dataset.owasysWritePlanAttached === '1') {
      return;
    }
    const container = form.closest('span');
    if (!container) {
      return;
    }
    const text = container.textContent || '';
    const stateMatch = text.match(/(?:état|state)\s*:\s*([a-z0-9_-]+)/i);
    const stateId = stateMatch && stateMatch[1] ? stateMatch[1] : 'unknown';
    if (stateId === 'unknown') {
      return;
    }
    const locale = (document.documentElement.getAttribute('lang') || 'fr').toLowerCase();
    const localeFile = locale === 'en' ? 'application/default/local/en.php' : 'application/default/local/fr.php';
    const files = [
      'config/routes.json',
      'config/application.fsm.json',
      'config/fsm.json',
      `application/states/${stateId}/views/index.php`,
      `application/states/${stateId}/templates/index.score`,
      localeFile
    ];
    const plan = document.createElement('div');
    plan.className = 'ow-write-plan';
    plan.dataset.context = 'OWASYS_STRUCTURE_WRITE_PLAN';
    const title = document.createElement('strong');
    title.textContent = 'OWASYS_STRUCTURE_WRITE_PLAN';
    const list = document.createElement('ul');
    files.forEach((file) => {
      const item = document.createElement('li');
      item.textContent = file;
      list.appendChild(item);
    });
    plan.appendChild(title);
    plan.appendChild(list);
    container.insertBefore(plan, form);
    form.dataset.owasysWritePlanAttached = '1';
  });

  if (window.mermaid) {
    window.mermaid.initialize({
      startOnLoad: true,
      securityLevel: 'loose',
      theme: 'dark'
    });
  }
});
