(() => {
  'use strict';

  const nodeElement = (host, stateId) => {
    const prefix = `flowchart-${stateId}-`;

    return Array.from(
      host.querySelectorAll('g.node[id]')
    ).find(
      (element) => element.id.startsWith(prefix)
    ) || null;
  };

  const accessibleLabel = (node, stateId) => {
    const text = node.textContent?.replace(/\s+/g, ' ').trim();

    return text || stateId;
  };

  const bindRoutes = (host, routes) => {
    const entries = Object.entries(routes);
    let bound = 0;

    entries.forEach(([stateId, url]) => {
      if (typeof url !== 'string' || url === '') {
        throw new TypeError(
          `OWASYS_FSM_MERMAID_ROUTE_INVALID:${stateId}`
        );
      }

      const node = nodeElement(host, stateId);

      if (!(node instanceof SVGGElement)) {
        throw new TypeError(
          `OWASYS_FSM_MERMAID_NODE_MISSING:${stateId}`
        );
      }

      const navigate = () => window.location.assign(url);

      node.classList.add('ow-mermaid-node-link');
      node.dataset.owasysFsmState = stateId;
      node.dataset.owasysFsmRoute = url;
      node.setAttribute('role', 'link');
      node.setAttribute('tabindex', '0');
      node.setAttribute(
        'aria-label',
        accessibleLabel(node, stateId)
      );

      node.addEventListener('click', navigate);
      node.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          navigate();
        }
      });

      bound += 1;
    });

    if (bound !== entries.length) {
      throw new Error(
        `OWASYS_FSM_MERMAID_BINDING_INCOMPLETE:${bound}/${entries.length}`
      );
    }

    host.dataset.owasysFsmBoundRoutes = String(bound);

    return bound;
  };

  const sourceFrom = (host) => {
    const sourceNode = host.querySelector(
      'script[type="application/json"][data-opus-mermaid-source]'
    );

    if (!(sourceNode instanceof HTMLScriptElement)) {
      throw new TypeError(
        'OPUS_MERMAID_SOURCE_NODE_MISSING'
      );
    }

    const payload = JSON.parse(sourceNode.textContent || '{}');

    if (
      typeof payload.source !== 'string'
      || payload.source.trim() === ''
    ) {
      throw new TypeError('OPUS_MERMAID_SOURCE_REQUIRED');
    }

    return payload.source;
  };

  const routesFrom = (panel) => {
    const routes = JSON.parse(
      panel.dataset.fsmRoutes || '{}'
    );

    if (
      routes === null
      || typeof routes !== 'object'
      || Array.isArray(routes)
    ) {
      throw new TypeError(
        'OWASYS_FSM_MERMAID_ROUTES_INVALID'
      );
    }

    return routes;
  };

  const renderPanel = async (panel) => {
    const host = panel.querySelector(
      '[data-opus-mermaid="true"]'
    );

    if (
      !(host instanceof Element)
      || !window.OPUS?.Mermaid
    ) {
      panel.dataset.fsmMermaidStatus = 'error';
      return;
    }

    try {
      const source = sourceFrom(host);
      const routes = routesFrom(panel);

      await window.OPUS.Mermaid.render({
        parent: host,
        source,
        id: host.id || 'owasys-fsm-diagram'
      });

      const bound = bindRoutes(host, routes);

      panel.dataset.fsmMermaidBoundRoutes = String(bound);
      panel.dataset.fsmMermaidStatus = 'ready';
    } catch (error) {
      console.error(
        'OWASYS_FSM_MERMAID_RENDER_FAILED',
        error
      );
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
    document.addEventListener(
      'DOMContentLoaded',
      initialize,
      { once: true }
    );
  } else {
    initialize();
  }
})();
