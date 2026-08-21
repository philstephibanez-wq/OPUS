(() => {
  'use strict';

  const DESIGNER_REVISION = 'P117W_R45B2A4BZ2R6';
  const section = document.querySelector('[data-owasys-fsm-diagram]');
  if (!(section instanceof HTMLElement)
      || section.dataset.fsmDesignerMode !== 'design') return;

  const decodePayload = (value) => {
    const binary = atob(value);
    const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
    return JSON.parse(new TextDecoder('utf-8', {fatal:true}).decode(bytes));
  };

  let model;
  try {
    model = decodePayload(section.dataset.fsmDesignerPayload || '');
  } catch (_) {
    section.dataset.fsmDesignerError = 'payload';
    return;
  }
  if (!model
      || model.contract !== 'OWASYS_EFSM_DESIGNER_SNAPSHOT_V2'
      || !model.definition
      || typeof model.definition !== 'object'
      || !/^[a-f0-9]{64}$/.test(String(model.base_sha256 || ''))) {
    section.dataset.fsmDesignerError = 'contract';
    return;
  }

  const inspector = section.querySelector('[data-fsm-designer-inspector]');
  const empty = section.querySelector('[data-fsm-designer-empty]');
  const selection = section.querySelector('[data-fsm-designer-selection]');
  const kindNode = section.querySelector('[data-fsm-designer-kind]');
  const idNode = section.querySelector('[data-fsm-designer-id]');
  const fieldsNode = section.querySelector('[data-fsm-designer-fields]');
  const editor = section.querySelector('[data-fsm-state-editor]');
  const editorStatus = section.querySelector('[data-fsm-designer-status]');
  const svg = section.querySelector('svg.fsm-diagram');
  if (!(inspector instanceof HTMLElement)
      || !(empty instanceof HTMLElement)
      || !(selection instanceof HTMLElement)
      || !(kindNode instanceof HTMLElement)
      || !(idNode instanceof HTMLElement)
      || !(fieldsNode instanceof HTMLElement)
      || !(editor instanceof HTMLFormElement)
      || !(editorStatus instanceof HTMLElement)
      || !(svg instanceof SVGSVGElement)) {
    section.dataset.fsmDesignerError = 'surface';
    return;
  }

  const stateButtons = Array.from(
    section.querySelectorAll('[data-fsm-state-action]')
  ).filter((node) => node instanceof HTMLButtonElement);
  const createButton = section.querySelector('[data-fsm-state-action="create"]');
  const renameButton = section.querySelector('[data-fsm-state-action="rename"]');
  const deleteButton = section.querySelector('[data-fsm-state-action="delete"]');
  const cancelButton = editor.querySelector('[data-fsm-state-cancel]');
  const submitButton = editor.querySelector('[data-fsm-state-submit]');
  const confirmationRow = editor.querySelector('[data-fsm-delete-confirmation-row]');
  const confirmationInput = editor.querySelector('[name="delete_confirmation"]');

  let states = {};
  let transitions = {};
  let signals = {};
  let selectedKind = '';
  let selectedId = '';
  let activeTool = 'select';
  let pendingCreatePoint = null;

  const rebuildIndexes = () => {
    states = {};
    transitions = {};
    signals = {};
    (Array.isArray(model.definition.states) ? model.definition.states : [])
      .forEach((item) => {
        if (item && typeof item === 'object' && typeof item.id === 'string') {
          states[item.id] = item;
        }
      });
    (Array.isArray(model.definition.transitions) ? model.definition.transitions : [])
      .forEach((item) => {
        if (item && typeof item === 'object' && typeof item.id === 'string') {
          transitions[item.id] = item;
        }
      });
    (Array.isArray(model.definition.signals) ? model.definition.signals : [])
      .forEach((item) => {
        if (item && typeof item === 'object' && typeof item.id === 'string') {
          signals[item.id] = item;
        }
      });
  };
  rebuildIndexes();

  const scalar = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'true' : 'false';
    if (Array.isArray(value)) return value.length === 0
      ? '[]'
      : value.every((item) => item === null || ['string','number','boolean'].includes(typeof item))
        ? value.map((item) => scalar(item)).join(', ')
        : JSON.stringify(value);
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

  const clearBezier = () => {
    svg.querySelectorAll('.fsm-designer-bezier-preview').forEach((node) => node.remove());
  };

  const simpleCubic = (path) => {
    if (!(path instanceof SVGPathElement)) return null;
    const d = path.getAttribute('d') || '';
    const numberPattern = /[-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[-+]?\d+)?/gi;
    const numbers = Array.from(d.matchAll(numberPattern), (match) => Number(match[0]));
    const commands = d.replace(numberPattern, '').replace(/[\s,]/g, '');
    if (commands !== 'MC' || numbers.length !== 8 || numbers.some((v) => !Number.isFinite(v))) {
      return null;
    }
    return {
      p0:{x:numbers[0],y:numbers[1]}, c1:{x:numbers[2],y:numbers[3]},
      c2:{x:numbers[4],y:numbers[5]}, p3:{x:numbers[6],y:numbers[7]},
    };
  };

  const showBezierPreview = (group) => {
    clearBezier();
    const curve = simpleCubic(group.querySelector('path.fsm-edge'));
    if (!curve) return 'compound_or_none';
    const ns = 'http://www.w3.org/2000/svg';
    const overlay = document.createElementNS(ns, 'g');
    overlay.setAttribute('class', 'fsm-designer-bezier-preview');
    [[curve.p0,curve.c1],[curve.c2,curve.p3]].forEach(([a,b]) => {
      const line = document.createElementNS(ns, 'line');
      line.setAttribute('x1', String(a.x)); line.setAttribute('y1', String(a.y));
      line.setAttribute('x2', String(b.x)); line.setAttribute('y2', String(b.y));
      overlay.append(line);
    });
    [['P0',curve.p0],['C1',curve.c1],['C2',curve.c2],['P3',curve.p3]].forEach(([role,p]) => {
      const circle = document.createElementNS(ns, 'circle');
      circle.setAttribute('cx', String(p.x)); circle.setAttribute('cy', String(p.y));
      circle.setAttribute('r', role.startsWith('C') ? '6' : '4');
      circle.setAttribute('data-bezier-role', role);
      overlay.append(circle);
    });
    svg.append(overlay);
    return 'cubic_bezier';
  };

  const transitionSources = (transition) => {
    if (!transition || typeof transition !== 'object') return [];
    if (transition.scope === 'global' && Array.isArray(transition.from_states)) {
      return transition.from_states.filter((source) => typeof source === 'string');
    }
    return typeof transition.from === 'string' && transition.from !== '*'
      ? [transition.from]
      : [];
  };

  const stateConnectivity = (id) => {
    let incoming = 0;
    let outgoing = 0;
    let self = 0;
    const outgoingSignals = new Set();

    Object.values(transitions).forEach((transition) => {
      if (!transition || typeof transition !== 'object') return;
      const target = String(transition.next_state || transition.nextState || '');
      const sources = transitionSources(transition);
      if (target === id) incoming += 1;
      if (sources.includes(id)) {
        outgoing += 1;
        if (target === id) self += 1;
        const signal = String(transition.signal || '');
        if (signal !== '') outgoingSignals.add(signal);
      }
    });

    return {
      incoming,
      outgoing,
      self,
      outgoingSignals:Array.from(outgoingSignals).sort(),
    };
  };

  const inspectState = (id) => {
    const state = states[id];
    if (!state) return false;
    const connectivity = stateConnectivity(id);
    kindNode.textContent = 'STATE';
    idNode.textContent = id;
    fieldsNode.replaceChildren();
    appendField('id', state.id);
    appendField('initial', model.definition.initial_state === id);
    if (typeof model.definition.final_state === 'string' && model.definition.final_state !== '') {
      appendField('final', model.definition.final_state === id);
    }
    appendField('incoming', connectivity.incoming);
    appendField('outgoing', connectivity.outgoing);
    appendField('self', connectivity.self);
    appendField('outgoing_signals', connectivity.outgoingSignals);
    return true;
  };

  const inspectTransition = (id, group) => {
    const transition = transitions[id];
    if (!transition) return false;
    kindNode.textContent = 'TRANSITION';
    idNode.textContent = id;
    fieldsNode.replaceChildren();
    appendField('id', transition.id);
    appendField('scope', transition.scope || (transition.interrupt === 'nmi' ? 'nmi' : 'local'));
    appendField('from', transition.from);
    appendField('from_states', transition.from_states || []);
    appendField('signal', transition.signal);
    appendField('signal.origin', signals[transition.signal]?.origin);
    appendField('guards', transition.guards || transition.guard || []);
    appendField('actions', transition.actions || transition.action || []);
    appendField('runtime_operations', transition.runtime_operations || []);
    appendField('next_state', transition.next_state || transition.nextState);
    appendField('layout.path_kind', showBezierPreview(group));
    return true;
  };

  const clearSelection = () => {
    svg.querySelectorAll('.is-fsm-designer-selected').forEach((node) => node.classList.remove('is-fsm-designer-selected'));
    clearBezier();
  };

  const updateButtons = () => {
    const stateSelected = selectedKind === 'state' && !!states[selectedId];
    if (createButton instanceof HTMLButtonElement) createButton.disabled = false;
    [renameButton, deleteButton].forEach((button) => {
      if (button instanceof HTMLButtonElement) button.disabled = !stateSelected;
    });
  };

  const showSelection = (kind, id, node) => {
    clearSelection();
    node.classList.add('is-fsm-designer-selected');
    const ok = kind === 'state' ? inspectState(id) : inspectTransition(id, node);
    if (!ok) return;
    selectedKind = kind;
    selectedId = id;
    empty.hidden = true;
    selection.hidden = false;
    editor.hidden = true;
    inspector.dataset.selectionKind = kind;
    inspector.dataset.selectionId = id;
    updateButtons();
  };

  const svgPoint = (event) => {
    const point = svg.createSVGPoint();
    point.x = event.clientX;
    point.y = event.clientY;
    const matrix = svg.getScreenCTM();
    return matrix ? point.matrixTransform(matrix.inverse()) : point;
  };

  const field = (name) => editor.elements.namedItem(name);
  const setValue = (name, value) => {
    const node = field(name);
    if (node instanceof HTMLInputElement || node instanceof HTMLSelectElement) {
      node.value = value === null || value === undefined ? '' : String(value);
    }
  };
  const getValue = (name) => {
    const node = field(name);
    if (node instanceof HTMLInputElement || node instanceof HTMLSelectElement) {
      return node.value;
    }
    return '';
  };

  const setEditorMode = (mode, state = null) => {
    if (!['create','rename','delete'].includes(mode)) {
      throw new Error('OWASYS_FSM_DESIGNER_STATE_MODE_INVALID');
    }
    editor.reset();
    editor.dataset.mode = mode;
    editor.hidden = false;
    selection.hidden = true;
    empty.hidden = true;
    const source = state || {};
    setValue('state_id', source.id || '');
    setValue('delete_confirmation', '');

    const deleting = mode === 'delete';
    if (confirmationRow instanceof HTMLElement) confirmationRow.hidden = !deleting;
    if (confirmationInput instanceof HTMLInputElement) confirmationInput.required = deleting;

    const idInput = field('state_id');
    if (idInput instanceof HTMLInputElement) {
      idInput.readOnly = deleting;
      idInput.focus();
      if (!deleting) idInput.select();
    }
    editor.dataset.originalId = source.id || '';
    editorStatus.textContent = '';
  };

  const closeEditor = () => {
    editor.hidden = true;
    if (selectedId !== '' && selectedKind === 'state' && states[selectedId]) {
      selection.hidden = false;
      inspectState(selectedId);
    } else {
      empty.hidden = false;
    }
    activeTool = 'select';
    stateButtons.forEach((button) => button.classList.remove('is-active'));
    updateButtons();
  };

  const commandForEditor = () => {
    const mode = editor.dataset.mode || '';
    const originalId = editor.dataset.originalId || '';
    const id = String(getValue('state_id')).trim();
    if (mode === 'create') return {operation:'state.create', state:{id}};
    if (mode === 'rename') return {operation:'state.rename', state_id:originalId, new_id:id};
    if (mode === 'delete') {
      return {
        operation:'state.delete',
        state_id:originalId,
        confirmation:String(getValue('delete_confirmation')).trim(),
      };
    }
    throw new Error('OWASYS_FSM_DESIGNER_EDITOR_MODE_INVALID');
  };

  const drawDraftState = (id, point) => {
    if (!point || svg.querySelector(`.fsm-node[data-state="${CSS.escape(id)}"]`)) return;
    const ns = 'http://www.w3.org/2000/svg';
    const width = 204;
    const height = 76;
    const x = Math.max(8, point.x - width / 2);
    const y = Math.max(70, point.y - height / 2);
    const group = document.createElementNS(ns, 'g');
    group.setAttribute('class', 'fsm-node fsm-designer-draft-node');
    group.setAttribute('data-state', id);
    group.setAttribute('data-x', String(x));
    group.setAttribute('data-y', String(y));
    group.setAttribute('data-w', String(width));
    group.setAttribute('data-h', String(height));
    const rect = document.createElementNS(ns, 'rect');
    rect.setAttribute('x', String(x)); rect.setAttribute('y', String(y));
    rect.setAttribute('width', String(width)); rect.setAttribute('height', String(height));
    rect.setAttribute('rx', '10');
    const text = document.createElementNS(ns, 'text');
    text.setAttribute('x', String(x + width / 2));
    text.setAttribute('y', String(y + height / 2 + 5));
    text.setAttribute('text-anchor', 'middle');
    text.textContent = id;
    group.append(rect, text);
    svg.append(group);
  };

  const applyDomResult = (operation, refactor, originalId) => {
    if (operation === 'state.create') {
      const id = String((model.definition.states || []).slice(-1)[0]?.id || '');
      drawDraftState(id, pendingCreatePoint);
      pendingCreatePoint = null;
      const node = svg.querySelector(`.fsm-node[data-state="${CSS.escape(id)}"]`);
      if (node instanceof SVGGElement) showSelection('state', id, node);
      return;
    }
    if (operation === 'state.rename') {
      const oldId = String(refactor.state_old || originalId || '');
      const newId = String(refactor.state_new || '');
      const node = svg.querySelector(`.fsm-node[data-state="${CSS.escape(oldId)}"]`);
      if (node instanceof SVGGElement && newId !== '') {
        node.dataset.state = newId;
        node.querySelectorAll('text').forEach((text) => {
          if ((text.textContent || '').trim() === oldId) text.textContent = newId;
        });
        selectedKind = 'state';
        selectedId = newId;
        showSelection('state', newId, node);
      }
      return;
    }
    if (operation === 'state.delete') {
      const node = svg.querySelector(`.fsm-node[data-state="${CSS.escape(originalId)}"]`);
      if (node) node.remove();
      selectedKind = '';
      selectedId = '';
      clearSelection();
      selection.hidden = true;
      editor.hidden = true;
      empty.hidden = false;
      updateButtons();
    }
  };

  const sendCommand = async (command, originalId) => {
    const actionUrl = section.dataset.fsmDesignerActionUrl || window.location.pathname;
    const csrf = section.dataset.fsmDesignerCsrf || '';
    if (csrf === '') throw new Error('OWASYS_FSM_DESIGNER_CSRF_MISSING');
    const body = new URLSearchParams();
    body.set('owasys_fsm_designer_command', '1');
    body.set('csrf_token', csrf);
    body.set('base_sha256', String(model.base_sha256));
    body.set('draft_json', JSON.stringify(model.definition));
    body.set('command_json', JSON.stringify(command));
    const response = await fetch(actionUrl, {
      method:'POST',
      credentials:'same-origin',
      headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body:body.toString(),
    });
    const payload = await response.json();
    if (!response.ok || !payload || payload.ok !== true) {
      throw new Error(String(payload?.error_code || 'OWASYS_FSM_DESIGNER_DRAFT_COMMAND_FAILED'));
    }
    const data = payload.data;
    if (!data || data.contract !== 'OWASYS_EFSM_DRAFT_COMMAND_RESULT_V1' || !data.definition) {
      throw new Error('OWASYS_FSM_DESIGNER_DRAFT_RESPONSE_INVALID');
    }
    model.definition = data.definition;
    rebuildIndexes();
    applyDomResult(String(data.operation || command.operation || ''), data.refactor || {}, originalId);
    editorStatus.textContent = `draft ${String(data.draft_sha256 || '').slice(0, 12)}`;
    section.dataset.fsmDesignerDirty = '1';
  };

  stateButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const action = button.dataset.fsmStateAction || '';
      stateButtons.forEach((item) => item.classList.remove('is-active'));
      button.classList.add('is-active');
      if (action === 'create') {
        activeTool = 'state-create';
        pendingCreatePoint = null;
        editor.hidden = true;
        selection.hidden = true;
        empty.hidden = false;
        editorStatus.textContent = 'state.create: click canvas';
        return;
      }
      if (selectedKind !== 'state' || !states[selectedId]) return;
      activeTool = 'select';
      setEditorMode(action, states[selectedId]);
    });
  });

  if (cancelButton instanceof HTMLButtonElement) {
    cancelButton.addEventListener('click', closeEditor);
  }

  editor.addEventListener('submit', async (event) => {
    event.preventDefault();
    const originalId = editor.dataset.originalId || '';
    try {
      if (submitButton instanceof HTMLButtonElement) submitButton.disabled = true;
      editorStatus.textContent = 'validation…';
      await sendCommand(commandForEditor(), originalId);
      closeEditor();
    } catch (error) {
      editorStatus.textContent = error instanceof Error
        ? error.message
        : 'OWASYS_FSM_DESIGNER_DRAFT_COMMAND_FAILED';
    } finally {
      if (submitButton instanceof HTMLButtonElement) submitButton.disabled = false;
    }
  });

  section.addEventListener('submit', (event) => {
    const target = event.target;
    if (target instanceof HTMLFormElement && target !== editor
        && target.closest('[data-owasys-fsm-diagram]') === section) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  section.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    if (target.closest('.ow-fsm-designer-toolbar, [data-fsm-state-editor]')) return;

    const transition = target.closest('.fsm-transition[data-transition-id]');
    const state = target.closest('.fsm-node[data-state]');
    if (transition instanceof SVGGElement) {
      event.preventDefault();
      event.stopImmediatePropagation();
      if (activeTool === 'state-create') return;
      const id = transition.dataset.transitionId || '';
      if (id !== '') showSelection('transition', id, transition);
      return;
    }
    if (state instanceof SVGGElement) {
      event.preventDefault();
      event.stopImmediatePropagation();
      if (activeTool === 'state-create') return;
      const id = state.dataset.state || '';
      if (id !== '') showSelection('state', id, state);
      return;
    }
    if (activeTool === 'state-create' && target.closest('svg.fsm-diagram')) {
      event.preventDefault();
      event.stopImmediatePropagation();
      pendingCreatePoint = svgPoint(event);
      setEditorMode('create', null);
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

  updateButtons();
  section.dataset.fsmDesignerReady = '1';
  section.dataset.fsmDesignerRevision = DESIGNER_REVISION;
})();
