document.documentElement.dataset.owasysTheme = 'owasys';

/**
 * Creates the classic OWASYS global header and places the single locale selector on its right.
 * The locale registry is limited to the 24 official EU languages plus Ukrainian.
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

  const expectedCodes = Object.keys(languageLabels);
  if (expectedCodes.length !== 25) {
    throw new Error('OWASYS_LOCALE_REGISTRY_INVALID');
  }

  const shell = document.querySelector('.ow-shell');
  if (!(shell instanceof HTMLElement) || !shell.parentNode) {
    return;
  }

  let header = document.querySelector('.ow-global-header');
  if (!(header instanceof HTMLElement)) {
    header = document.createElement('header');
    header.className = 'ow-global-header';
    header.dataset.context = 'OWASYS_GLOBAL_HEADER';

    const identity = document.createElement('a');
    identity.className = 'ow-global-header-identity';
    identity.href = window.location.pathname.startsWith('/owasys') ? '/owasys/' : '/';
    identity.innerHTML = '<strong>OWASYS</strong><span>OPUS Web Application System</span>';

    const actions = document.createElement('div');
    actions.className = 'ow-global-header-actions';
    actions.dataset.context = 'OWASYS_GLOBAL_HEADER_ACTIONS';

    header.appendChild(identity);
    header.appendChild(actions);
    shell.parentNode.insertBefore(header, shell);
  }

  const actions = header.querySelector('.ow-global-header-actions');
  if (!(actions instanceof HTMLElement)) {
    return;
  }

  document.querySelectorAll('[data-context="OWASYS_LOCALE_SWITCHER"]').forEach((node) => node.remove());

  const form = document.createElement('form');
  form.method = 'get';
  form.className = 'ow-locale-switcher';
  form.dataset.context = 'OWASYS_LOCALE_SWITCHER';

  for (const [key, value] of new URLSearchParams(window.location.search).entries()) {
    if (key === 'lang') {
      continue;
    }
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = key;
    hidden.value = value;
    form.appendChild(hidden);
  }

  const currentLocale = (document.documentElement.getAttribute('lang') || 'fr').toLowerCase();
  const select = document.createElement('select');
  select.name = 'lang';
  select.setAttribute('aria-label', 'Language');
  select.dataset.localeCount = String(expectedCodes.length);

  for (const code of expectedCodes) {
    const option = document.createElement('option');
    option.value = code;
    option.textContent = languageLabels[code];
    option.lang = code;
    option.selected = code === currentLocale;
    select.appendChild(option);
  }

  select.addEventListener('change', () => form.submit());
  form.appendChild(select);
  actions.appendChild(form);
});
