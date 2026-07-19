document.addEventListener('DOMContentLoaded', async () => {
  const sidebar = document.querySelector('.ow-sidebar');
  if (!sidebar || document.querySelector('[data-context="OWASYS_LOCALE_SWITCHER"]')) return;

  const locales = [
    ['bg','🇧🇬','Български'],['hr','🇭🇷','Hrvatski'],['cs','🇨🇿','Čeština'],['da','🇩🇰','Dansk'],
    ['nl','🇳🇱','Nederlands'],['en','🇮🇪','English'],['et','🇪🇪','Eesti'],['fi','🇫🇮','Suomi'],
    ['fr','🇫🇷','Français'],['de','🇩🇪','Deutsch'],['el','🇬🇷','Ελληνικά'],['hu','🇭🇺','Magyar'],
    ['ga','🇮🇪','Gaeilge'],['it','🇮🇹','Italiano'],['lv','🇱🇻','Latviešu'],['lt','🇱🇹','Lietuvių'],
    ['mt','🇲🇹','Malti'],['pl','🇵🇱','Polski'],['pt','🇵🇹','Português'],['ro','🇷🇴','Română'],
    ['sk','🇸🇰','Slovenčina'],['sl','🇸🇮','Slovenščina'],['es','🇪🇸','Español'],['sv','🇸🇪','Svenska'],
    ['uk','🇺🇦','Українська']
  ];

  const url = new URL(window.location.href);
  const saved = window.localStorage.getItem('owasys_locale');
  const requested = (url.searchParams.get('lang') || saved || document.documentElement.lang || 'fr').toLowerCase();
  const current = locales.some(([code]) => code === requested) ? requested : 'fr';
  window.localStorage.setItem('owasys_locale', current);

  const form = document.createElement('form');
  form.className = 'ow-locale-switcher';
  form.dataset.context = 'OWASYS_LOCALE_SWITCHER';
  form.setAttribute('aria-label', 'Language');
  const select = document.createElement('select');
  select.name = 'lang';
  select.setAttribute('aria-label', 'Language');
  locales.forEach(([code, flag, name]) => {
    const option = document.createElement('option');
    option.value = code;
    option.textContent = `${flag} ${name}`;
    option.selected = code === current;
    select.append(option);
  });
  select.addEventListener('change', () => {
    const next = new URL(window.location.href);
    next.searchParams.set('lang', select.value);
    window.localStorage.setItem('owasys_locale', select.value);
    window.location.assign(next.toString());
  });
  form.append(select);

  const auth = sidebar.querySelector('.ow-auth-status');
  if (auth) auth.insertAdjacentElement('afterend', form);
  else sidebar.prepend(form);

  let nav = sidebar.querySelector('.ow-nav');
  if (!nav) {
    nav = document.createElement('nav');
    nav.className = 'ow-nav';
    sidebar.append(nav);
  }

  const routeDefinitions = [
    ['menu.home','/'],['menu.applications','/applications'],['menu.structure','/structure'],
    ['menu.data','/data'],['menu.workflows','/workflows'],['menu.security','/security'],
    ['menu.source','/source'],['menu.build','/build']
  ];

  try {
    const base = window.location.pathname.startsWith('/owasys/') || window.location.pathname === '/owasys' ? '/owasys' : '';
    const response = await fetch(`${base}/i18n.php?lang=${encodeURIComponent(current)}`, {credentials:'same-origin'});
    const payload = await response.json();
    const messages = payload && payload.messages && typeof payload.messages === 'object' ? payload.messages : {};
    nav.replaceChildren();
    routeDefinitions.forEach(([key, path]) => {
      const anchor = document.createElement('a');
      anchor.href = `${base}${path === '/' ? '/' : path}?lang=${encodeURIComponent(current)}`;
      anchor.textContent = messages[key] || key.replace('menu.','');
      if ((window.location.pathname.replace(base, '') || '/') === path) anchor.setAttribute('aria-current','page');
      nav.append(anchor);
    });
  } catch (_) {
    // Keep the server-rendered navigation when the catalogue endpoint is unavailable.
  }
});
