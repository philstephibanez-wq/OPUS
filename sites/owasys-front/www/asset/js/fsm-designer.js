(() => {
  'use strict';

  const section = document.querySelector('[data-owasys-fsm-diagram]');
  if (!(section instanceof HTMLElement)) return;
  if (section.dataset.fsmDesignerMode !== 'design') return;

  const encoded = section.dataset.fsmDesignerPayload || '';
  if (encoded === '') return;

  const decodePayload = (value) => {
    const binary = atob(value);
    const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
    return JSON.parse(new TextDecoder('utf-8', {fatal: true}).decode(bytes));
  };

  let model;
  try {
    model = decodePayload(encoded);
  } catch (error) {
    section.dataset.fsmDesignerError = 'payload';
    return;
  }

  if (!model
      || model.contract !== 'OWASYS_EFSM_DESIGNER_SNAPSHOT_V1'
      || typeof model.states !== 'object'
      || typeof model.transitions !== 'object'
      || typeof model.signals !== 'object') {
    section.dataset.fsmDesignerError = 'contract';
    return;
  }

  const inspector = section.querySelector('[data-fsm-designer-inspector]');
  const empty = section.querySelector('[data-fsm-designer-empty]');
  const selection = section.querySelector('[data-fsm-designer-selection]');
  const kindNode = section.querySelector('[data-fsm-designer-kind]');
  const idNode = section.querySelector('[data-fsm-designer-id]');
  const fieldsNode = section.querySelector('[data-fsm-designer-fields]');
  const svg = section.querySelector('svg.fsm-diagram');

  if (!(inspector instanceof HTMLElement)
      || !(empty instanceof HTMLElement)
      || !(selection instanceof HTMLElement)
      || !(kindNode instanceof HTMLElement)
      || !(idNode instanceof HTMLElement)
      || !(fieldsNode instanceof HTMLElement)
      || !(svg instanceof SVGSVGElement)) {
    section.dataset.fsmDesignerError = 'surface';
    return;
  }

  const clearSelection = () => {
    svg.querySelectorAll('.is-fsm-designer-selected').forEach((node) => {
      node.classList.remove('is-fsm-designer-selected');
    });
    svg.querySelectorAll('.fsm-designer-bezier-preview').forEach((node) => {
      node.remove();
    });
  };

  const scalar = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'true' : 'false';
    if (Array.isArray(value)) {
      if (value.length === 0) return '[]';
      return value.every((item) => (
        item === null
        || ['string', 'number', 'boolean'].includes(typeof item)
      ))
        ? value.map((item) => scalar(item)).join(', ')
        : JSON.stringify(value);
    }
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
  };

  const appendField = (name, value) => {
    const dt = document.createElement('dt');
    dt.textContent = name;
    const dd = document.createElement('dd');
    dd.textContent = scalar(value);
    fieldsNode.append(dt, dd);
  };

  const menuEligibility = (signal) => {
    const origin = String(signal.origin || '');
    const type = String(signal.type || '');
    if (origin !== 'user') {
      return ['false', 'origin != user'];
    }
    if (type !== 'navigation' && type !== 'command') {
      return ['false', 'type not menu-projectable'];
    }
    return ['true', 'eligible'];
  };

  const inspectState = (id) => {
    const state = model.states[id];
    if (!state || typeof state !== 'object') return false;

    kindNode.textContent = 'STATE';
    idNode.textContent = id;
    fieldsNode.replaceChildren();

    const navigation = state.navigation && typeof state.navigation === 'object'
      ? state.navigation
      : {};
    const diagram = state.diagram && typeof state.diagram === 'object'
      ? state.diagram
      : {};

    appendField('id', state.id);
    appendField('type', state.type);
    appendField('module', state.module);
    appendField('route', state.route);
    appendField('template', state.template);
    appendField('requires_auth', state.requires_auth);
    appendField('requires_current_app', state.requires_current_app);
    appendField('navigation.visible', navigation.visible);
    appendField('navigation.order', navigation.order);
    appendField('navigation.label', navigation.label);
    appendField('diagram.rank', diagram.rank);
    appendField('diagram.order', diagram.order);
    appendField('initial_state', model.initial_state === id);

    return true;
  };

  const simpleCubic = (path) => {
    if (!(path instanceof SVGPathElement)) return null;
    const d = path.getAttribute('d') || '';
    const numberPattern = /[-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[-+]?\d+)?/gi;
    const numbers = Array.from(d.matchAll(numberPattern), (match) => Number(match[0]));
    const commands = d.replace(numberPattern, '').replace(/[\s,]/g, '');
    if (commands !== 'MC'
        || numbers.length !== 8
        || numbers.some((value) => !Number.isFinite(value))) {
      return null;
    }
    return {
      p0:{x:numbers[0], y:numbers[1]},
      c1:{x:numbers[2], y:numbers[3]},
      c2:{x:numbers[4], y:numbers[5]},
      p3:{x:numbers[6], y:numbers[7]},
    };
  };

  const showBezierPreview = (group) => {
    svg.querySelectorAll('.fsm-designer-bezier-preview').forEach((node) => {
      node.remove();
    });
    const path = group.querySelector('path.fsm-edge');
    const curve = simpleCubic(path);
    if (!curve) return 'compound_or_none';

    const ns = 'http://www.w3.org/2000/svg';
    const overlay = document.createElementNS(ns, 'g');
    overlay.setAttribute('class', 'fsm-designer-bezier-preview');

    const line1 = document.createElementNS(ns, 'line');
    line1.setAttribute('x1', String(curve.p0.x));
    line1.setAttribute('y1', String(curve.p0.y));
    line1.setAttribute('x2', String(curve.c1.x));
    line1.setAttribute('y2', String(curve.c1.y));

    const line2 = document.createElementNS(ns, 'line');
    line2.setAttribute('x1', String(curve.c2.x));
    line2.setAttribute('y1', String(curve.c2.y));
    line2.setAttribute('x2', String(curve.p3.x));
    line2.setAttribute('y2', String(curve.p3.y));

    overlay.append(line1, line2);

    [
      ['P0', curve.p0],
      ['C1', curve.c1],
      ['C2', curve.c2],
      ['P3', curve.p3],
    ].forEach(([role, point]) => {
      const circle = document.createElementNS(ns, 'circle');
      circle.setAttribute('cx', String(point.x));
      circle.setAttribute('cy', String(point.y));
      circle.setAttribute('r', role.startsWith('C') ? '6' : '4');
      circle.setAttribute('data-bezier-role', role);
      overlay.append(circle);
    });

    svg.append(overlay);
    return 'cubic_bezier';
  };

  const inspectTransition = (id, group) => {
    const transition = model.transitions[id];
    if (!transition || typeof transition !== 'object') return false;

    kindNode.textContent = 'TRANSITION';
    idNode.textContent = id;
    fieldsNode.replaceChildren();

    appendField('id', transition.id);
    appendField('from', transition.from);
    appendField('next_state', transition.next_state);
    appendField('scope', transition.scope || 'local');
    appendField('from_states', transition.from_states || []);
    appendField('signal', transition.signal);
    appendField('guards', transition.guards || transition.guard || []);
    appendField('actions', transition.actions || transition.action || []);
    appendField('runtime_operations', transition.runtime_operations || []);

    const signal = model.signals[transition.signal] || {};
    appendField('signal.type', signal.type);
    appendField('signal.origin', signal.origin);
    appendField('signal.menu', signal.menu);
    appendField('signal.menu_order', signal.menu_order);
    appendField('signal.label_key', signal.label_key);
    appendField('signal.menu_state', signal.menu_state);
    appendField('signal.resource', signal.resource);
    appendField('signal.operation', signal.operation);

    const [eligible, reason] = menuEligibility(signal);
    appendField('signal.menu_eligible', eligible);
    appendField('signal.menu_reason', reason);
    appendField('layout.path_kind', showBezierPreview(group));

    return true;
  };

  const showSelection = (kind, id, node) => {
    clearSelection();
    node.classList.add('is-fsm-designer-selected');

    const ok = kind === 'state'
      ? inspectState(id)
      : inspectTransition(id, node);

    if (!ok) return;
    empty.hidden = true;
    selection.hidden = false;
    inspector.dataset.selectionKind = kind;
    inspector.dataset.selectionId = id;
  };

  section.addEventListener('submit', (event) => {
    const target = event.target;
    if (target instanceof HTMLFormElement
        && target.closest('[data-owasys-fsm-diagram]') === section) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  section.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const transition = target.closest('.fsm-transition[data-transition-id]');
    const state = target.closest('.fsm-node[data-state]');

    if (transition instanceof SVGGElement) {
      event.preventDefault();
      event.stopImmediatePropagation();
      const id = transition.dataset.transitionId || '';
      if (id !== '') showSelection('transition', id, transition);
      return;
    }

    if (state instanceof SVGGElement) {
      event.preventDefault();
      event.stopImmediatePropagation();
      const id = state.dataset.state || '';
      if (id !== '') showSelection('state', id, state);
    }
  }, true);

  section.addEventListener('keydown', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    if ((event.key === 'Enter' || event.key === ' ')
        && target.closest('.fsm-signal-link, .fsm-signal-post-submit')) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  section.dataset.fsmDesignerReady = '1';
})();