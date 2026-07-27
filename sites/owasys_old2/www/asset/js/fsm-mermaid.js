(() => {
  'use strict';

  const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
  const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';

  const routesFrom = (panel) => {
    const decoded = JSON.parse(panel.dataset.fsmRoutes || '{}');

    if (
      decoded === null
      || typeof decoded !== 'object'
      || Array.isArray(decoded)
    ) {
      throw new TypeError(
        'OWASYS_FSM_MERMAID_ROUTES_INVALID'
      );
    }

    return Object.entries(decoded).map(([stateId, definition]) => {
      const normalized = typeof definition === 'string'
        ? { url: definition, node_class: '' }
        : definition;

      if (
        normalized === null
        || typeof normalized !== 'object'
        || typeof normalized.url !== 'string'
        || normalized.url === ''
      ) {
        throw new TypeError(
          `OWASYS_FSM_MERMAID_ROUTE_INVALID:${stateId}`
        );
      }

      return {
        stateId,
        url: normalized.url,
        nodeClass: typeof normalized.node_class === 'string'
          ? normalized.node_class
          : ''
      };
    });
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
      throw new TypeError(
        'OPUS_MERMAID_SOURCE_REQUIRED'
      );
    }

    return payload.source;
  };

  const nodeCandidates = (host) => Array.from(
    host.querySelectorAll(
      'svg g.node, svg [data-node="true"]'
    )
  ).filter((node) => node instanceof Element);

  const idMatches = (node, stateId) => {
    const id = node.id || '';
    const dataId = node.getAttribute('data-id') || '';
    const dataNodeId = node.getAttribute('data-node-id') || '';

    return dataId === stateId
      || dataNodeId === stateId
      || id === stateId
      || id.startsWith(`flowchart-${stateId}-`)
      || id.startsWith(`flowchart-${stateId}`);
  };

  const nodeFor = (
    host,
    candidates,
    route,
    index,
    routeCount
  ) => {
    if (route.nodeClass !== '') {
      const byClass = host.querySelector(
        `svg g.node.${route.nodeClass}, svg .${route.nodeClass}[data-node="true"]`
      );

      if (byClass instanceof Element) {
        return byClass;
      }
    }

    const byIdentifier = candidates.find(
      (node) => idMatches(node, route.stateId)
    );

    if (byIdentifier instanceof Element) {
      return byIdentifier;
    }

    if (candidates.length === routeCount) {
      return candidates[index] || null;
    }

    return null;
  };

  const accessibleLabel = (node, stateId) => {
    const text = node.textContent
      ?.replace(/\s+/g, ' ')
      .trim();

    return text || stateId;
  };

  const wrapNode = (node, route) => {
    const existing = node.closest(
      'a[data-owasys-fsm-route]'
    );

    if (existing instanceof Element) {
      return existing;
    }

    const parent = node.parentNode;

    if (!(parent instanceof Element)) {
      throw new TypeError(
        `OWASYS_FSM_MERMAID_NODE_PARENT_INVALID:${route.stateId}`
      );
    }

    const link = document.createElementNS(
      SVG_NAMESPACE,
      'a'
    );

    link.setAttribute('href', route.url);
    link.setAttributeNS(
      XLINK_NAMESPACE,
      'xlink:href',
      route.url
    );
    link.setAttribute('target', '_self');
    link.setAttribute('role', 'link');
    link.setAttribute('tabindex', '0');
    link.setAttribute(
      'aria-label',
      accessibleLabel(node, route.stateId)
    );
    link.classList.add('ow-mermaid-node-link');
    link.dataset.owasysFsmState = route.stateId;
    link.dataset.owasysFsmRoute = route.url;

    parent.insertBefore(link, node);
    link.appendChild(node);

    link.addEventListener('click', (event) => {
      if (
        event.defaultPrevented
        || event.button !== 0
        || event.ctrlKey
        || event.metaKey
        || event.shiftKey
        || event.altKey
      ) {
        return;
      }

      event.preventDefault();
      window.location.assign(route.url);
    });

    link.addEventListener('keydown', (event) => {
      if (event.key === ' ') {
        event.preventDefault();
        window.location.assign(route.url);
      }
    });

    return link;
  };

  const bindRoutes = (host, routes) => {
    const candidates = nodeCandidates(host);
    const used = new Set();

    routes.forEach((route, index) => {
      const node = nodeFor(
        host,
        candidates,
        route,
        index,
        routes.length
      );

      if (
        !(node instanceof Element)
        || used.has(node)
      ) {
        throw new TypeError(
          `OWASYS_FSM_MERMAID_NODE_MISSING:${route.stateId}`
        );
      }

      used.add(node);
      wrapNode(node, route);
    });

    host.dataset.owasysFsmBoundRoutes = String(
      routes.length
    );

    return routes.length;
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

      panel.dataset.fsmMermaidBoundRoutes = String(
        bound
      );
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
