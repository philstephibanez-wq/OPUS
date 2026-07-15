document.documentElement.dataset.owasysAsset = '1';

/**
 * Adds an explicit password visibility toggle to every OWASYS password field.
 * The button is generated client-side so the server-side view-models stay data-only.
 */
document.addEventListener('DOMContentLoaded', () => {
  const languageLabels = {
    bg: 'Български',
    hr: 'Hrvatski',
    cs: 'Čeština',
    da: 'Dansk',
    nl: 'Nederlands',
    en: 'English',
    et: 'Eesti',
    fi: 'Suomi',
    fr: 'Français',
    de: 'Deutsch',
    el: 'Ελληνικά',
    hu: 'Magyar',
    ga: 'Gaeilge',
    it: 'Italiano',
    lv: 'Latviešu',
    lt: 'Lietuvių',
    mt: 'Malti',
    pl: 'Polski',
    pt: 'Português',
    ro: 'Română',
    sk: 'Slovenčina',
    sl: 'Slovenščina',
    es: 'Español',
    sv: 'Svenska',
    uk: 'Українська'
  };
  const localeCodes = Object.keys(languageLabels);
  const currentLocale = (document.documentElement.getAttribute('lang') || new URLSearchParams(window.location.search).get('lang') || 'fr').toLowerCase();

  if (!document.querySelector('[data-context="OWASYS_LOCALE_SWITCHER"]')) {
    const target = document.querySelector('.ow-sidebar .ow-auth-status') || document.querySelector('.ow-sidebar') || document.body;
    if (target) {
      const wrapper = document.createElement('nav');
      wrapper.className = 'ow-locale-switcher';
      wrapper.dataset.context = 'OWASYS_LOCALE_SWITCHER';
      wrapper.setAttribute('aria-label', 'Langues UE + ukrainien');
      wrapper.style.display = 'flex';
      wrapper.style.flexWrap = 'wrap';
      wrapper.style.gap = '0.35rem';
      wrapper.style.margin = '0.75rem 0';
      wrapper.style.padding = '0.75rem';
      wrapper.style.border = '1px solid rgba(148, 163, 184, 0.25)';
      wrapper.style.borderRadius = '0.75rem';

      const title = document.createElement('small');
      title.textContent = 'Langues UE + Українська';
      title.style.flexBasis = '100%';
      title.style.opacity = '0.75';
      wrapper.appendChild(title);

      localeCodes.forEach((code) => {
        const url = new URL(window.location.href);
        url.searchParams.set('lang', code);
        const link = document.createElement('a');
        link.href = url.pathname + url.search + url.hash;
        link.textContent = languageLabels[code];
        link.dataset.locale = code;
        link.style.fontSize = '0.78rem';
        link.style.lineHeight = '1';
        link.style.padding = '0.35rem 0.45rem';
        link.style.borderRadius = '999px';
        link.style.textDecoration = 'none';
        link.style.border = '1px solid rgba(148, 163, 184, 0.25)';
        if (code === currentLocale) {
          link.setAttribute('aria-current', 'true');
          link.style.fontWeight = '700';
          link.style.borderColor = 'rgba(74, 222, 128, 0.85)';
        }
        wrapper.appendChild(link);
      });

      if (target.parentNode && target !== document.body) {
        target.parentNode.insertBefore(wrapper, target.nextSibling);
      } else {
        document.body.insertBefore(wrapper, document.body.firstChild);
      }
    }
  }

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

  const script = document.querySelector('script[src$="/asset/js/owasys.js"]');
  const scriptPath = script instanceof HTMLScriptElement ? new URL(script.src, window.location.href).pathname : '/asset/js/owasys.js';
  const basePath = scriptPath.replace(/\/asset\/js\/owasys\.js$/, '') || '';
  const previewEndpoint = `${basePath}/structure-preview.php`;

  document.querySelectorAll('[data-context="OWASYS_STRUCTURE_APPLY_DRAFT_FORM"]').forEach((form) => {
    if (!(form instanceof HTMLFormElement) || form.dataset.owasysWritePlanAttached === '1') {
      return;
    }
    const container = form.closest('span');
    const draftIdInput = form.querySelector('input[name="owasys_draft_id"]');
    if (!container || !(draftIdInput instanceof HTMLInputElement) || draftIdInput.value === '') {
      return;
    }

    const previewForm = document.createElement('form');
    previewForm.method = 'post';
    previewForm.action = previewEndpoint;
    previewForm.className = 'ow-inline-form';
    previewForm.dataset.context = 'OWASYS_STRUCTURE_WRITE_PLAN_FORM';

    const action = document.createElement('input');
    action.type = 'hidden';
    action.name = 'owasys_action';
    action.value = 'preview-structure-draft';
    previewForm.appendChild(action);

    const draftId = document.createElement('input');
    draftId.type = 'hidden';
    draftId.name = 'owasys_draft_id';
    draftId.value = draftIdInput.value;
    previewForm.appendChild(draftId);

    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'ow-button ow-button-secondary';
    button.textContent = currentLocale === 'en' ? 'Preview server plan' : 'Prévisualiser le plan serveur';
    previewForm.appendChild(button);

    const result = document.createElement('div');
    result.className = 'ow-write-plan';
    result.dataset.context = 'OWASYS_STRUCTURE_WRITE_PLAN';
    result.hidden = true;
    result.textContent = 'OWASYS_STRUCTURE_WRITE_PLAN';

    previewForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      result.hidden = false;
      result.textContent = 'OWASYS_STRUCTURE_WRITE_PLAN';
      try {
        const response = await fetch(previewForm.action, {
          method: 'POST',
          body: new FormData(previewForm),
          credentials: 'same-origin',
          headers: {
            'X-OWASYS-Preview': 'OWASYS_STRUCTURE_WRITE_PLAN'
          }
        });
        const body = await response.text();
        result.innerHTML = body;
      } catch (error) {
        result.textContent = 'OWASYS_STRUCTURE_WRITE_PLAN_ERROR';
      }
    });

    container.insertBefore(previewForm, form);
    container.insertBefore(result, form);
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
