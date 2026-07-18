document.documentElement.dataset.owasysTheme = 'owasys';

/**
 * OWASYS theme progressive enhancements only.
 *
 * Navigation, header, current application context, authentication controls and
 * locale selection are rendered by the PHP backend. This file must never
 * create, move, remove or translate structural UI elements.
 */

if (window.mermaid && typeof window.mermaid.initialize === 'function') {
  const initializeMermaid = window.mermaid.initialize.bind(window.mermaid);

  window.mermaid.initialize = (configuration = {}) => initializeMermaid({
    ...configuration,
    flowchart: {
      nodeSpacing: 56,
      rankSpacing: 96,
      diagramPadding: 32,
      useMaxWidth: false,
      curve: 'basis',
      ...(configuration.flowchart || {})
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  document.documentElement.classList.add('owasys-js-available');
});
