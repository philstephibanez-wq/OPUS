document.documentElement.dataset.owasysTheme = 'owasys';

/**
 * Replaces any legacy locale-link cloud with one accessible selector.
 * The registry is deliberately limited to the 24 official EU languages plus Ukrainian.
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

  const currentLocale = (document.documentElement.getAttribute('lang') || 'fr').toLowerCase();
  const legacy = document.querySelector('[data-context="OWASYS_LOCALE_SWITCHER"]');
  const host = legacy?.parentElement || document.querySelector('.ow-sidebar');
  if (!host) {
    return;
  }

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

  if (legacy) {
    legacy.replaceWith(form);
  } else {
    const anchor = document.querySelector('.ow-sidebar .ow-auth-status');
    anchor?.insertAdjacentElement('afterend', form);
  }
});
