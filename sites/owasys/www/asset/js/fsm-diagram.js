document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-context="OWASYS_FSM_DIAGRAM"]')
    .forEach((container) => {
      if (!(container instanceof HTMLElement) || container.dataset.rendered === '1') {
        return;
      }

      const source = container.querySelector('[data-fsm-source]');
      const canvas = container.querySelector('[data-fsm-canvas]');
      if (!(source instanceof HTMLElement) || !(canvas instanceof HTMLElement)) {
        return;
      }

      const lines = source.textContent.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
      const nodes = new Map();
      const edges = [];

      lines.forEach((line) => {
        const node = line.match(/^([A-Za-z0-9_]+)\["(.*)"\]:::(active|primary)$/);
        if (node) {
          nodes.set(node[1], { id: node[1], label: node[2].replace(/\\"/g, '"'), active: node[3] === 'active' });
          return;
        }
        const edge = line.match(/^([A-Za-z0-9_]+)\s+-->\|(.+)\|\s+([A-Za-z0-9_]+)$/);
        if (edge) {
          edges.push({ from: edge[1], label: edge[2], to: edge[3] });
        }
      });

      if (nodes.size === 0) {
        return;
      }

      const ordered = Array.from(nodes.values());
      const columns = Math.min(4, Math.max(1, Math.ceil(Math.sqrt(ordered.length))));
      const nodeWidth = 190;
      const nodeHeight = 64;
      const gapX = 90;
      const gapY = 90;
      const margin = 40;

      ordered.forEach((node, index) => {
        node.x = margin + (index % columns) * (nodeWidth + gapX);
        node.y = margin + Math.floor(index / columns) * (nodeHeight + gapY);
      });

      const rows = Math.ceil(ordered.length / columns);
      const width = Math.max(420, margin * 2 + columns * nodeWidth + (columns - 1) * gapX);
      const height = Math.max(220, margin * 2 + rows * nodeHeight + (rows - 1) * gapY);
      const ns = 'http://www.w3.org/2000/svg';
      const svg = document.createElementNS(ns, 'svg');
      svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
      svg.setAttribute('role', 'img');
      svg.setAttribute('aria-label', 'Schéma FSM');
      svg.classList.add('ow-fsm-svg');

      const defs = document.createElementNS(ns, 'defs');
      const marker = document.createElementNS(ns, 'marker');
      marker.setAttribute('id', 'ow-fsm-arrow');
      marker.setAttribute('markerWidth', '10');
      marker.setAttribute('markerHeight', '10');
      marker.setAttribute('refX', '9');
      marker.setAttribute('refY', '3');
      marker.setAttribute('orient', 'auto');
      marker.setAttribute('markerUnits', 'strokeWidth');
      const markerPath = document.createElementNS(ns, 'path');
      markerPath.setAttribute('d', 'M0,0 L0,6 L9,3 z');
      markerPath.setAttribute('class', 'ow-fsm-arrow');
      marker.appendChild(markerPath);
      defs.appendChild(marker);
      svg.appendChild(defs);

      edges.forEach((edge) => {
        const from = nodes.get(edge.from);
        const to = nodes.get(edge.to);
        if (!from || !to) {
          return;
        }
        const x1 = from.x + nodeWidth / 2;
        const y1 = from.y + nodeHeight / 2;
        const x2 = to.x + nodeWidth / 2;
        const y2 = to.y + nodeHeight / 2;
        const dx = x2 - x1;
        const dy = y2 - y1;
        const length = Math.max(1, Math.hypot(dx, dy));
        const ux = dx / length;
        const uy = dy / length;
        const startX = x1 + ux * (nodeWidth / 2 - 8);
        const startY = y1 + uy * (nodeHeight / 2 - 8);
        const endX = x2 - ux * (nodeWidth / 2 - 8);
        const endY = y2 - uy * (nodeHeight / 2 - 8);

        const line = document.createElementNS(ns, 'line');
        line.setAttribute('x1', String(startX));
        line.setAttribute('y1', String(startY));
        line.setAttribute('x2', String(endX));
        line.setAttribute('y2', String(endY));
        line.setAttribute('marker-end', 'url(#ow-fsm-arrow)');
        line.setAttribute('class', 'ow-fsm-edge');
        svg.appendChild(line);

        const label = document.createElementNS(ns, 'text');
        label.setAttribute('x', String((startX + endX) / 2));
        label.setAttribute('y', String((startY + endY) / 2 - 8));
        label.setAttribute('text-anchor', 'middle');
        label.setAttribute('class', 'ow-fsm-edge-label');
        label.textContent = edge.label;
        svg.appendChild(label);
      });

      ordered.forEach((node) => {
        const group = document.createElementNS(ns, 'g');
        group.setAttribute('class', node.active ? 'ow-fsm-node is-active' : 'ow-fsm-node');

        const rect = document.createElementNS(ns, 'rect');
        rect.setAttribute('x', String(node.x));
        rect.setAttribute('y', String(node.y));
        rect.setAttribute('width', String(nodeWidth));
        rect.setAttribute('height', String(nodeHeight));
        rect.setAttribute('rx', '14');
        group.appendChild(rect);

        const text = document.createElementNS(ns, 'text');
        text.setAttribute('x', String(node.x + nodeWidth / 2));
        text.setAttribute('y', String(node.y + nodeHeight / 2 + 5));
        text.setAttribute('text-anchor', 'middle');
        text.textContent = node.label;
        group.appendChild(text);
        svg.appendChild(group);
      });

      canvas.replaceChildren(svg);
      source.hidden = true;
      canvas.hidden = false;
      container.dataset.rendered = '1';
    });
});
