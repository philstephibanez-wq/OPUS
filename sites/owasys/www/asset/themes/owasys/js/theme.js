document.documentElement.dataset.owasysTheme = 'owasys';

/**
 * OWASYS theme progressive enhancements only.
 *
 * Navigation, header, current application context, authentication controls and
 * locale selection are rendered by the PHP backend. This file must never
 * create, move, remove or translate structural UI elements.
 */
document.addEventListener('DOMContentLoaded', () => {
  document.documentElement.classList.add('owasys-js-available');
});
