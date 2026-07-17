document.documentElement.dataset.owasysTheme = 'owasys';

/**
 * Creates the classic OWASYS global header, horizontal navigation and locale selector.
 * The selected application remains visible in the persistent header.
 * The locale registry is limited to the 24 official EU languages plus Ukrainian.
 */
document.addEventListener('DOMContentLoaded', () => {
  const languageLabels = {
    bg: 'Български', hr: 'Hrvatski', cs: 'Čeština', da: 'Dansk', nl: 'Nederlands',
    en: 'English', et: 'Eesti', fi: 'Suomi', fr: 'Français', de: 'Deutsch',
    el: 'Ελληνικά', hu: 'Magyar', ga: 'Gaeilge', it: 'Italiano', lv: 'Latviešu',
    lt: 'Lietuvių', mt: 'Malti', pl: 'Polski', pt: 'Português', ro: 'Română',
    sk: 'Slovenčina', sl: 'Slovenščina', es: 'Español', sv: 'Svenska', uk: 'Українська'
  };

  const expectedCodes = Object.keys(languageLabels);
  if (expectedCodes.length !== 25) throw new Error('OWASYS_LOCALE_REGISTRY_INVALID');

  const shell = document.querySelector('.ow-shell');
  if (!(shell instanceof HTMLElement) || !shell.parentNode) return;

  const sidebar = document.querySelector('.ow-sidebar');
  const renderedBrand = sidebar?.querySelector('.ow-brand');
  let header = document.querySelector('.ow-global-header');
  if (!(header instanceof HTMLElement)) {
    header = document.createElement('header');
    header.className = 'ow-global-header';
    header.dataset.context = 'OWASYS_GLOBAL_HEADER';

    const identity = document.createElement('a');
    identity.className = 'ow-global-header-identity';
    identity.href = window.location.pathname.startsWith('/owasys') ? '/owasys/' : '/';
    const renderedName = renderedBrand?.querySelector('strong')?.textContent?.trim() || 'OWASYS';
    const renderedSubtitle = renderedBrand?.querySelector('span')?.textContent?.trim() || renderedName;
    const name = document.createElement('strong');
    name.textContent = renderedName;
    const subtitle = document.createElement('span');
    subtitle.textContent = renderedSubtitle;
    identity.append(name, subtitle);

    const actions = document.createElement('div');
    actions.className = 'ow-global-header-actions';
    actions.dataset.context = 'OWASYS_GLOBAL_HEADER_ACTIONS';
    header.append(identity, actions);
    shell.parentNode.insertBefore(header, shell);
  }

  renderedBrand?.remove();

  let globalNav = document.querySelector('.ow-global-nav');
  if (!(globalNav instanceof HTMLElement)) {
    globalNav = document.createElement('nav');
    globalNav.className = 'ow-global-nav';
    globalNav.dataset.context = 'OWASYS_GLOBAL_NAVIGATION';
    globalNav.setAttribute('aria-label', 'OWASYS');
    header.insertAdjacentElement('afterend', globalNav);
  }

  const currentApplication = sidebar?.querySelector('.ow-current-app');
  const authStatus = sidebar?.querySelector('.ow-auth-status');
  const navigationLinks = sidebar
    ? Array.from(sidebar.querySelectorAll('.ow-nav a'))
    : [];

  const canonicalMissingKey = (value) => {
    const key = String(value).trim();
    if (/^[a-z0-9]+(?:[._-][a-z0-9]+)+$/.test(key)) {
      return `[[${key}]]`;
    }
    return key;
  };

  navigationLinks.forEach((link) => {
    link.classList.add('ow-global-nav-link');
    link.textContent = canonicalMissingKey(link.textContent);
    globalNav.appendChild(link);
  });

  const actions = header.querySelector('.ow-global-header-actions');
  if (!(actions instanceof HTMLElement)) return;

  if (currentApplication instanceof HTMLElement) {
    currentApplication.classList.add('ow-global-current-app');
    currentApplication.dataset.context = 'OWASYS_GLOBAL_CURRENT_APPLICATION';
    actions.appendChild(currentApplication);
  }

  if (authStatus instanceof HTMLElement) {
    authStatus.classList.add('ow-global-auth-status');
    actions.appendChild(authStatus);
  }

  document.querySelectorAll('[data-context="OWASYS_LOCALE_SWITCHER"]').forEach((node) => node.remove());
  const form = document.createElement('form');
  form.method = 'get';
  form.className = 'ow-locale-switcher';
  form.dataset.context = 'OWASYS_LOCALE_SWITCHER';

  for (const [key, value] of new URLSearchParams(window.location.search).entries()) {
    if (key === 'lang') continue;
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = key;
    hidden.value = value;
    form.appendChild(hidden);
  }

  const currentLocale = (document.documentElement.getAttribute('lang') || 'fr').toLowerCase();
  const currentLanguageLabel = languageLabels[currentLocale] || currentLocale;
  const select = document.createElement('select');
  select.name = 'lang';
  select.setAttribute('aria-label', currentLanguageLabel);
  select.title = currentLanguageLabel;
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

  const isApplicationRegistry = document.body.dataset.opusState === 'registry';
  if (!isApplicationRegistry) {
    document.querySelectorAll('.ow-context-panel').forEach((panel) => panel.remove());
  }

  sidebar?.remove();
  shell.classList.add('ow-shell-horizontal-navigation');
});