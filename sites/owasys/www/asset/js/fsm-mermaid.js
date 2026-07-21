(() => {
  'use strict';

  const nodeElement = (host, stateId) => {
    const prefix = `flowchart-${stateId}-`;

    return Array.from(host.querySelectorAll('[id]')).find(
      (element) => element.id.startsWith(prefix)
    ) || null;
  };

  const bindRoutes = (host, routes) => {
    Object.entries(routes).forEach(([stateId, url]) => {
      if (typeof url !== 'string' || url === '') {
        return;
      }

      const node = nodeElement(host, stateId);
      if (!(node instanceof Element)) {
        return;
      }

      node.classList.add('ow-mermaid-node-link');
      node.setAttribute('role', 'link');
      node.setAttribute('tabindex', '0');

      const navigate = () => window.location.assign(url);

      node.addEventListener('click', navigate);
      node.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          navigate();
        }
      });
    });
  };

  const renderPanel = async (panel) => {
    const host = panel.querySelector('[data-opus-mermaid="true"]');
    const sourceNode = host?.querySelector('script[type="text/plain"]');

    if (
      !(host instanceof Element)
      || !(sourceNode instanceof HTMLScriptElement)
      || !window.OPUS?.Mermaid
    ) {
      panel.dataset.fsmMermaidStatus = 'error';
      return;
    }

    let routes = {};
    try {
      routes = JSON.parse(panel.dataset.fsmRoutes || '{}');
    } catch {
      panel.dataset.fsmMermaidStatus = 'error';
      return;
    }

    try {
      await window.OPUS.Mermaid.render({
        parent: host,
        source: sourceNode.textContent || '',
        id: host.id || 'owasys-fsm-diagram'
      });

      bindRoutes(host, routes);
      panel.dataset.fsmMermaidStatus = 'ready';
    } catch (error) {
      console.error('OWASYS_FSM_MERMAID_RENDER_FAILED', error);
      panel.dataset.fsmMermaidStatus = 'error';
    }
  };

  const initialize = () => {
    document
      .querySelectorAll('[data-owasys-fsm-diagram]')
      .forEach((panel) => {
        void renderPanel(panel);
      });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
