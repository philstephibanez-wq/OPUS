document.addEventListener('DOMContentLoaded', async () => {
  const sidebar = document.querySelector('.ow-sidebar');
  if (!sidebar || document.querySelector('[data-context="OWASYS_LOCALE_SWITCHER"]')) return;

  const locales = [
    ['bg','bg','Български'],['hr','hr','Hrvatski'],['cs','cz','Čeština'],['da','dk','Dansk'],
    ['nl','nl','Nederlands'],['en','eu','English'],['et','ee','Eesti'],['fi','fi','Suomi'],
    ['fr','fr','Français'],['de','de','Deutsch'],['el','gr','Ελληνικά'],['hu','hu','Magyar'],
    ['ga','ie','Gaeilge'],['it','it','Italiano'],['lv','lv','Latviešu'],['lt','lt','Lietuvių'],
    ['mt','mt','Malti'],['pl','pl','Polski'],['pt','pt','Português'],['ro','ro','Română'],
    ['sk','sk','Slovenčina'],['sl','si','Slovenščina'],['es','es','Español'],['sv','se','Svenska'],
    ['uk','ua','Українська']
  ];

  const url = new URL(window.location.href);
  const saved = window.localStorage.getItem('owasys_locale');
  const requested = (url.searchParams.get('lang') || saved || document.documentElement.lang || 'fr').toLowerCase();
  const current = locales.some(([code]) => code === requested) ? requested : 'fr';
  window.localStorage.setItem('owasys_locale', current);

  const flagUrl = (country) => `https://flagcdn.com/24x18/${country}.png`;
  const selected = locales.find(([code]) => code === current) || locales[0];

  const switcher = document.createElement('details');
  switcher.className = 'ow-locale-switcher';
  switcher.dataset.context = 'OWASYS_LOCALE_SWITCHER';

  const summary = document.createElement('summary');
  summary.setAttribute('aria-label', 'Language');
  summary.innerHTML = `<img src="${flagUrl(selected[1])}" width="24" height="18" alt=""><span>${selected[2]}</span>`;
  switcher.append(summary);

  const list = document.createElement('div');
  list.className = 'ow-locale-options';
  locales.forEach(([code, country, name]) => {
    const link = document.createElement('a');
    const next = new URL(window.location.href);
    next.searchParams.set('lang', code);
    link.href = next.toString();
    link.dataset.locale = code;
    if (code === current) link.setAttribute('aria-current', 'true');
    link.innerHTML = `<img src="${flagUrl(country)}" width="24" height="18" alt=""><span>${name}</span>`;
    link.addEventListener('click', () => window.localStorage.setItem('owasys_locale', code));
    list.append(link);
  });
  switcher.append(list);

  const auth = sidebar.querySelector('.ow-auth-status');
  if (auth) auth.insertAdjacentElement('afterend', switcher);
  else sidebar.prepend(switcher);

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