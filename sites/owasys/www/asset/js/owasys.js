document.documentElement.dataset.owasysAsset = '1';

/**
 * Adds the OWASYS locale selector and password visibility controls.
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
  const currentLanguageLabel = languageLabels[currentLocale] || currentLocale;

  if (!document.querySelector('[data-context="OWASYS_LOCALE_SWITCHER"]')) {
    const target = document.querySelector('.ow-topbar');
    if (target) {
      const form = document.createElement('form');
      form.className = 'ow-locale-switcher';
      form.dataset.context = 'OWASYS_LOCALE_SWITCHER';
      form.setAttribute('aria-label', currentLanguageLabel);

      const select = document.createElement('select');
      select.name = 'lang';
      select.setAttribute('aria-label', currentLanguageLabel);
      select.title = currentLanguageLabel;

      localeCodes.forEach((code) => {
        const option = document.createElement('option');
        option.value = code;
        option.textContent = languageLabels[code];
        option.lang = code;
        option.selected = code === currentLocale;
        select.appendChild(option);
      });

      select.addEventListener('change', () => {
        const url = new URL(window.location.href);
        url.searchParams.set('lang', select.value);
        window.location.assign(url.pathname + url.search + url.hash);
      });

      form.appendChild(select);
      target.appendChild(form);
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

    const localizedFieldLabel = input.closest('label')?.childNodes[0]?.textContent?.trim() || input.name;
    parent.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'ow-password-eye';
    button.setAttribute('aria-label', localizedFieldLabel);
    button.setAttribute('aria-pressed', 'false');
    button.textContent = '👁';

    button.addEventListener('click', () => {
      const visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
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
    const localizedSectionTitle = container.closest('section')?.querySelector('h2, h3')?.textContent?.trim();
    button.textContent = `⌕ ${localizedSectionTitle || draftIdInput.value}`;
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
