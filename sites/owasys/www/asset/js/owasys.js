document.documentElement.dataset.owasysAsset = '1';

/**
 * Adds the OWASYS locale selector, password visibility controls and Build pipeline UI.
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
  const buildEndpoint = `${basePath}/build-action.php`;

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

  if (document.body.dataset.opusState === 'build' && !document.querySelector('[data-context="OWASYS_BUILD_PIPELINE_UI"]')) {
    const main = document.querySelector('.ow-main');
    if (main) {
      const defaultRequest = {
        id: 'demo-generated',
        slug: 'demo-generated',
        name: 'Demo generated by OWASYS',
        kind: 'fullstack',
        root_path: 'sites/demo-generated',
        blueprint: 'opus-site-standard',
        default_locale: 'fr',
        theme: 'starter',
        controllers: ['home'],
        routes: [{ id: 'home.index', path: '/', state: 'home', controller: 'home' }],
        datasources: [],
        security_profiles: [{ id: 'admin', permissions: ['*'] }],
        workflows: []
      };

      const panel = document.createElement('section');
      panel.className = 'ow-card';
      panel.dataset.context = 'OWASYS_BUILD_PIPELINE_UI';
      panel.innerHTML = '<h2>OWASYS Build Pipeline</h2><p class="ow-muted">Preview, generate, validate and export one portable OPUS application.</p>';

      const form = document.createElement('form');
      form.className = 'ow-password-form';

      const requestLabel = document.createElement('label');
      requestLabel.textContent = 'Application request JSON';
      const requestInput = document.createElement('textarea');
      requestInput.name = 'request_json';
      requestInput.rows = 22;
      requestInput.spellcheck = false;
      requestInput.value = JSON.stringify(defaultRequest, null, 2);
      requestLabel.appendChild(requestInput);
      form.appendChild(requestLabel);

      const outputLabel = document.createElement('label');
      outputLabel.textContent = 'Export ZIP path';
      const outputInput = document.createElement('input');
      outputInput.name = 'output_zip';
      outputInput.value = 'var/owasys-export/demo-generated.zip';
      outputLabel.appendChild(outputInput);
      form.appendChild(outputLabel);

      const overwriteLabel = document.createElement('label');
      const overwriteInput = document.createElement('input');
      overwriteInput.type = 'checkbox';
      overwriteInput.name = 'overwrite';
      overwriteLabel.appendChild(overwriteInput);
      overwriteLabel.append(' Overwrite existing ZIP');
      form.appendChild(overwriteLabel);

      const actions = document.createElement('div');
      actions.className = 'ow-inline-form';
      [
        ['preview', 'Preview'],
        ['build', 'Generate & validate'],
        ['build-and-export', 'Generate, validate & export']
      ].forEach(([mode, label]) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = mode === 'preview' ? 'ow-button ow-button-secondary' : 'ow-button';
        button.dataset.buildMode = mode;
        button.textContent = label;
        actions.appendChild(button);
      });
      form.appendChild(actions);

      const result = document.createElement('pre');
      result.className = 'ow-write-plan';
      result.dataset.context = 'OWASYS_BUILD_PIPELINE_RESULT';
      result.hidden = true;
      panel.appendChild(form);
      panel.appendChild(result);
      main.appendChild(panel);

      actions.querySelectorAll('button[data-build-mode]').forEach((button) => {
        button.addEventListener('click', async () => {
          result.hidden = false;
          result.textContent = 'OWASYS_BUILD_PIPELINE_RUNNING';
          actions.querySelectorAll('button').forEach((candidate) => { candidate.disabled = true; });
          try {
            const request = JSON.parse(requestInput.value);
            const response = await fetch(buildEndpoint, {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json',
                'X-OWASYS-Build': 'OWASYS_BUILD_PIPELINE'
              },
              body: JSON.stringify({
                mode: button.dataset.buildMode,
                request,
                output_zip: outputInput.value,
                overwrite: overwriteInput.checked
              })
            });
            const payload = await response.json();
            result.textContent = JSON.stringify(payload, null, 2);
          } catch (error) {
            result.textContent = JSON.stringify({
              contract: 'OWASYS_BUILD_HTTP_RESULT_V1',
              status: 'error',
              error: error instanceof Error ? error.message : 'OWASYS_BUILD_UI_ERROR'
            }, null, 2);
          } finally {
            actions.querySelectorAll('button').forEach((candidate) => { candidate.disabled = false; });
          }
        });
      });
    }
  }

  if (window.mermaid) {
    window.mermaid.initialize({
      startOnLoad: true,
      securityLevel: 'loose',
      theme: 'dark'
    });
  }
});
