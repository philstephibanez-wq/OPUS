(() => {
  'use strict';

  const DESIGNER_REVISION = 'P117W_R45B2A4BZ2R8B4A';
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
      || model.contract !== 'OWASYS_EFSM_DESIGNER_SNAPSHOT_V4'
      || !/^[a-z][a-z0-9_-]{0,63}$/.test(String(model.efsm_id || ''))
      || !model.definition
      || typeof model.definition !== 'object'
      || !/^[a-z][a-z0-9-]{0,63}$/.test(String(model.application_id || ''))
      || !/^[a-f0-9]{64}$/.test(String(model.base_sha256 || ''))) {
    section.dataset.fsmDesignerError = 'contract';
    return;
  }
  model.command_history = [];
  const handlerAuthoringSupported =
    model.handler_authoring_supported === true;

  const inspector = section.querySelector('[data-fsm-designer-inspector]');
  const empty = section.querySelector('[data-fsm-designer-empty]');
  const selection = section.querySelector('[data-fsm-designer-selection]');
  const kindNode = section.querySelector('[data-fsm-designer-kind]');
  const idNode = section.querySelector('[data-fsm-designer-id]');
  const fieldsNode = section.querySelector('[data-fsm-designer-fields]');
  const stateEditor = section.querySelector('[data-fsm-state-editor]');
  const transitionEditor = section.querySelector('[data-fsm-transition-handler-editor]');
  const handlerSourceEditor = section.querySelector('[data-fsm-handler-source-editor]');
  const editorStatus = section.querySelector('[data-fsm-designer-status]');
  const svg = section.querySelector('svg.fsm-diagram');
  if (!(inspector instanceof HTMLElement)
      || !(empty instanceof HTMLElement)
      || !(selection instanceof HTMLElement)
      || !(kindNode instanceof HTMLElement)
      || !(idNode instanceof HTMLElement)
      || !(fieldsNode instanceof HTMLElement)
      || !(stateEditor instanceof HTMLFormElement)
      || !(transitionEditor instanceof HTMLFormElement)
      || !(handlerSourceEditor instanceof HTMLFormElement)
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
  const transitionEditButton = section.querySelector('[data-fsm-transition-action="handlers"]');
  const stateCancelButton = stateEditor.querySelector('[data-fsm-state-cancel]');
  const stateSubmitButton = stateEditor.querySelector('[data-fsm-state-submit]');
  const confirmationRow = stateEditor.querySelector('[data-fsm-delete-confirmation-row]');
  const confirmationInput = stateEditor.querySelector('[name="delete_confirmation"]');
  const transitionCancelButton = transitionEditor.querySelector('[data-fsm-transition-handler-cancel]');
  const transitionSubmitButton = transitionEditor.querySelector('[data-fsm-transition-handler-submit]');
  const guardTextarea = transitionEditor.querySelector('[name="transition_guards"]');
  const actionTextarea = transitionEditor.querySelector('[name="transition_actions"]');
  const guardCatalogSelect = transitionEditor.querySelector('[data-fsm-guard-catalog]');
  const actionCatalogSelect = transitionEditor.querySelector('[data-fsm-action-catalog]');
  const guardAddButton = transitionEditor.querySelector('[data-fsm-handler-add="guard"]');
  const actionAddButton = transitionEditor.querySelector('[data-fsm-handler-add="action"]');
  const handlerAuthorButtons = Array.from(
    section.querySelectorAll('[data-fsm-handler-author]')
  ).filter((node) => node instanceof HTMLButtonElement);
  const handlerExistingRow = handlerSourceEditor instanceof HTMLFormElement
    ? handlerSourceEditor.querySelector('[data-fsm-handler-existing-row]')
    : null;
  const handlerExistingSelect = handlerSourceEditor instanceof HTMLFormElement
    ? handlerSourceEditor.querySelector('[data-fsm-handler-existing]')
    : null;
  const handlerSourceSubmitButton = handlerSourceEditor instanceof HTMLFormElement
    ? handlerSourceEditor.querySelector('[data-fsm-handler-source-submit]')
    : null;
  const handlerSourceCancelButton = handlerSourceEditor instanceof HTMLFormElement
    ? handlerSourceEditor.querySelector('[data-fsm-handler-source-cancel]')
    : null;
  const handlerSourceMeta = handlerSourceEditor instanceof HTMLFormElement
    ? handlerSourceEditor.querySelector('[data-fsm-handler-source-meta]')
    : null;

  const handlerEntries = (entries, kind) => {
    const result = [];
    const seen = new Set();
    entries.forEach((entry) => {
      if (!entry || typeof entry !== 'object') {
        throw new Error(`OWASYS_FSM_DESIGNER_${kind}_CATALOG_INVALID`);
      }
      const id = String(entry.id || '').trim();
      if (!/^[a-z][a-z0-9_:-]{0,127}$/.test(id) || seen.has(id)) {
        throw new Error(`OWASYS_FSM_DESIGNER_${kind}_CATALOG_INVALID`);
      }
      seen.add(id);
      result.push({
        id,
        description:String(entry.description || ''),
        source:String(entry.source || ''),
        dynamic:entry.dynamic === true,
        managed:entry.managed === true,
        code:String(entry.code || ''),
        handler_sha256:String(entry.handler_sha256 || ''),
        source_sha256:String(entry.source_sha256 || ''),
      });
    });
    return result;
  };

  let guardEntries = [];
  let actionEntries = [];
  const guardIds = new Set();
  const actionIds = new Set();
  let handlerCatalogReady = false;

  const fillCatalog = (select, entries) => {
    if (!(select instanceof HTMLSelectElement)) return;
    select.replaceChildren();
    entries.forEach((entry) => {
      const option = document.createElement('option');
      option.value = entry.id;
      option.textContent = entry.description === ''
        ? entry.id
        : `${entry.id} — ${entry.description}`;
      option.title = entry.source;
      select.append(option);
    });
  };
  const adoptCsrf = (payload) => {
    const token = String(payload?.csrf_token || '').trim();
    if (/^[a-f0-9]{64}$/.test(token)) {
      section.dataset.fsmDesignerCsrf = token;
    }
  };
  const loadHandlerCatalog = async () => {
    const actionUrl = section.dataset.fsmDesignerActionUrl || window.location.pathname;
    const csrf = section.dataset.fsmDesignerCsrf || '';
    if (csrf === '') throw new Error('OWASYS_FSM_DESIGNER_CSRF_MISSING');
    const body = new URLSearchParams();
    body.set('owasys_fsm_designer_catalog', '1');
    body.set('efsm_id', String(model.efsm_id));
    body.set('csrf_token', csrf);
    const response = await fetch(actionUrl, {
      method:'POST',
      credentials:'same-origin',
      headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body:body.toString(),
    });
    const payload = await response.json();
    adoptCsrf(payload);
    const catalog = payload?.data;
    if (!response.ok
        || !payload
        || payload.ok !== true
        || !catalog
        || catalog.contract !== 'OWASYS_EFSM_HANDLER_CATALOG_V1'
        || !Array.isArray(catalog.guards)
        || !Array.isArray(catalog.actions)) {
      throw new Error(String(payload?.error_code || 'OWASYS_FSM_DESIGNER_HANDLER_CATALOG_FAILED'));
    }
    guardEntries = handlerEntries(catalog.guards, 'GUARD');
    actionEntries = handlerEntries(catalog.actions, 'ACTION');
    guardIds.clear();
    actionIds.clear();
    guardEntries.forEach((entry) => guardIds.add(entry.id));
    actionEntries.forEach((entry) => actionIds.add(entry.id));
    fillCatalog(guardCatalogSelect, guardEntries);
    fillCatalog(actionCatalogSelect, actionEntries);
    handlerCatalogReady = true;
    section.dataset.fsmHandlerCatalogReady = '1';
    updateButtons();
    if (selectedKind === 'transition' && selectedId !== '' && transitions[selectedId]) {
      const node = svg.querySelector(`.fsm-transition[data-transition-id="${CSS.escape(selectedId)}"]`);
      if (node instanceof SVGGElement) inspectTransition(selectedId, node);
    }
  };

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

  const asStringList = (value) => {
    if (typeof value === 'string') {
      const id = value.trim();
      return id === '' ? [] : [id];
    }
    if (!Array.isArray(value)) return [];
    return value
      .filter((item) => typeof item === 'string')
      .map((item) => item.trim())
      .filter((item) => item !== '');
  };

  const registeredSummary = (items, catalog) => items.map(
    (id) => `${id} ${catalog.has(id) ? '✓' : '✕'}`
  );

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
      line.setAttribute('x1', String(a.x));
      line.setAttribute('y1', String(a.y));
      line.setAttribute('x2', String(b.x));
      line.setAttribute('y2', String(b.y));
      overlay.append(line);
    });
    [['P0',curve.p0],['C1',curve.c1],['C2',curve.c2],['P3',curve.p3]].forEach(([role,p]) => {
      const circle = document.createElementNS(ns, 'circle');
      circle.setAttribute('cx', String(p.x));
      circle.setAttribute('cy', String(p.y));
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
    if (typeof model.definition.final_state === 'string'
        && model.definition.final_state !== '') {
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
    const guards = asStringList(transition.guards ?? transition.guard ?? []);
    const actions = asStringList(transition.actions ?? transition.action ?? []);
    kindNode.textContent = 'TRANSITION';
    idNode.textContent = id;
    fieldsNode.replaceChildren();
    appendField('id', transition.id);
    appendField('scope', transition.scope || (transition.interrupt === 'nmi' ? 'nmi' : 'local'));
    appendField('from', transition.from);
    appendField('from_states', transition.from_states || []);
    appendField('signal', transition.signal);
    appendField('signal.origin', signals[transition.signal]?.origin);
    appendField('guards', guards);
    appendField('guards.registered', registeredSummary(guards, guardIds));
    appendField('actions', actions);
    appendField('actions.registered', registeredSummary(actions, actionIds));
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
    const transitionSelected = selectedKind === 'transition' && !!transitions[selectedId];
    if (createButton instanceof HTMLButtonElement) createButton.disabled = false;
    [renameButton, deleteButton].forEach((button) => {
      if (button instanceof HTMLButtonElement) button.disabled = !stateSelected;
    });
    if (transitionEditButton instanceof HTMLButtonElement) {
      transitionEditButton.disabled = !handlerAuthoringSupported
        || !transitionSelected
        || !handlerCatalogReady;
    }
    handlerAuthorButtons.forEach((button) => {
      const [kind, mode] = String(button.dataset.fsmHandlerAuthor || '').split(':', 2);
      const entries = kind === 'guard'
        ? guardEntries
        : kind === 'action'
          ? actionEntries
          : [];
      const editable = entries.some(
        (entry) => entry.managed === true
          && !(kind === 'guard' && entry.dynamic === true)
      );
      button.disabled = !handlerAuthoringSupported
        || !handlerCatalogReady
        || !['guard','action'].includes(kind)
        || !['create','update'].includes(mode)
        || (mode === 'update' && !editable);
    });
  };

  const hideEditors = () => {
    stateEditor.hidden = true;
    transitionEditor.hidden = true;
    handlerSourceEditor.hidden = true;
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
    hideEditors();
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

  const stateField = (name) => stateEditor.elements.namedItem(name);
  const setStateValue = (name, value) => {
    const node = stateField(name);
    if (node instanceof HTMLInputElement || node instanceof HTMLSelectElement) {
      node.value = value === null || value === undefined ? '' : String(value);
    }
  };
  const getStateValue = (name) => {
    const node = stateField(name);
    if (node instanceof HTMLInputElement || node instanceof HTMLSelectElement) {
      return node.value;
    }
    return '';
  };

  const setStateEditorMode = (mode, state = null) => {
    if (!['create','rename','delete'].includes(mode)) {
      throw new Error('OWASYS_FSM_DESIGNER_STATE_MODE_INVALID');
    }
    stateEditor.reset();
    stateEditor.dataset.mode = mode;
    stateEditor.hidden = false;
    transitionEditor.hidden = true;
    handlerSourceEditor.hidden = true;
    selection.hidden = true;
    empty.hidden = true;
    const source = state || {};
    setStateValue('state_id', source.id || '');
    setStateValue('delete_confirmation', '');
    const deleting = mode === 'delete';
    if (confirmationRow instanceof HTMLElement) confirmationRow.hidden = !deleting;
    if (confirmationInput instanceof HTMLInputElement) confirmationInput.required = deleting;
    const idInput = stateField('state_id');
    if (idInput instanceof HTMLInputElement) {
      idInput.readOnly = deleting;
      idInput.focus();
      if (!deleting) idInput.select();
    }
    stateEditor.dataset.originalId = source.id || '';
    editorStatus.textContent = '';
  };

  const closeEditors = () => {
    hideEditors();
    if (selectedKind === 'state' && selectedId !== '' && states[selectedId]) {
      selection.hidden = false;
      inspectState(selectedId);
    } else if (selectedKind === 'transition'
        && selectedId !== '' && transitions[selectedId]) {
      selection.hidden = false;
      const node = svg.querySelector(`.fsm-transition[data-transition-id="${CSS.escape(selectedId)}"]`);
      inspectTransition(selectedId, node instanceof SVGGElement ? node : svg);
    } else {
      empty.hidden = false;
    }
    activeTool = 'select';
    stateButtons.forEach((button) => button.classList.remove('is-active'));
    updateButtons();
  };

  const commandForStateEditor = () => {
    const mode = stateEditor.dataset.mode || '';
    const originalId = stateEditor.dataset.originalId || '';
    const id = String(getStateValue('state_id')).trim();
    if (mode === 'create') return {operation:'state.create', state:{id}};
    if (mode === 'rename') {
      return {operation:'state.rename', state_id:originalId, new_id:id};
    }
    if (mode === 'delete') {
      return {
        operation:'state.delete',
        state_id:originalId,
        confirmation:String(getStateValue('delete_confirmation')).trim(),
      };
    }
    throw new Error('OWASYS_FSM_DESIGNER_EDITOR_MODE_INVALID');
  };

  const parseHandlerText = (textarea, catalog, kind) => {
    if (!(textarea instanceof HTMLTextAreaElement)) {
      throw new Error(`OWASYS_FSM_DESIGNER_${kind}_EDITOR_MISSING`);
    }
    const items = textarea.value
      .split(/\r?\n/)
      .map((item) => item.trim())
      .filter((item) => item !== '');
    const seen = new Set();
    items.forEach((id) => {
      if (!/^[a-z][a-z0-9_:-]{0,127}$/.test(id)) {
        throw new Error(`OWASYS_FSM_DESIGNER_${kind}_NAME_INVALID:${id}`);
      }
      if (seen.has(id)) {
        throw new Error(`OWASYS_FSM_DESIGNER_${kind}_DUPLICATE:${id}`);
      }
      if (!catalog.has(id)) {
        throw new Error(`OWASYS_FSM_${kind}_HANDLER_MISSING:${id}`);
      }
      seen.add(id);
    });
    return items;
  };

  const openTransitionEditor = (id) => {
    if (!handlerCatalogReady) {
      throw new Error('OWASYS_FSM_DESIGNER_HANDLER_CATALOG_NOT_READY');
    }
    const transition = transitions[id];
    if (!transition) return;
    transitionEditor.reset();
    transitionEditor.hidden = false;
    stateEditor.hidden = true;
    handlerSourceEditor.hidden = true;
    selection.hidden = true;
    empty.hidden = true;
    transitionEditor.dataset.transitionId = id;
    const transitionIdInput = transitionEditor.elements.namedItem('transition_id');
    const signalInput = transitionEditor.elements.namedItem('transition_signal');
    if (transitionIdInput instanceof HTMLInputElement) transitionIdInput.value = id;
    if (signalInput instanceof HTMLInputElement) {
      signalInput.value = String(transition.signal || '');
    }
    if (guardTextarea instanceof HTMLTextAreaElement) {
      guardTextarea.value = asStringList(
        transition.guards ?? transition.guard ?? []
      ).join('\n');
    }
    if (actionTextarea instanceof HTMLTextAreaElement) {
      actionTextarea.value = asStringList(
        transition.actions ?? transition.action ?? []
      ).join('\n');
    }
    const nmi = transition.interrupt === 'nmi';
    if (guardTextarea instanceof HTMLTextAreaElement) guardTextarea.disabled = nmi;
    if (guardCatalogSelect instanceof HTMLSelectElement) guardCatalogSelect.disabled = nmi;
    if (guardAddButton instanceof HTMLButtonElement) guardAddButton.disabled = nmi;
    editorStatus.textContent = nmi
      ? 'NMI: guards interdits; actions éditables'
      : '';
  };

  const appendHandlerFromCatalog = (kind) => {
    const select = kind === 'guard' ? guardCatalogSelect : actionCatalogSelect;
    const textarea = kind === 'guard' ? guardTextarea : actionTextarea;
    if (!(select instanceof HTMLSelectElement)
        || !(textarea instanceof HTMLTextAreaElement)
        || select.disabled) return;
    const id = String(select.value || '').trim();
    if (id === '') return;
    const existing = textarea.value
      .split(/\r?\n/)
      .map((item) => item.trim())
      .filter((item) => item !== '');
    if (!existing.includes(id)) existing.push(id);
    textarea.value = existing.join('\n');
    textarea.focus();
  };

  const commandForTransitionEditor = () => {
    const transitionId = String(transitionEditor.dataset.transitionId || '');
    const transition = transitions[transitionId];
    if (!transition) {
      throw new Error('OWASYS_FSM_DESIGNER_TRANSITION_UNKNOWN');
    }
    const guards = transition.interrupt === 'nmi'
      ? []
      : parseHandlerText(guardTextarea, guardIds, 'GUARD');
    const actions = parseHandlerText(actionTextarea, actionIds, 'ACTION');
    return {
      operation:'transition.handlers.update',
      transition_id:transitionId,
      guards,
      actions,
    };
  };

  const entriesForKind = (kind) => kind === 'guard'
    ? guardEntries
    : kind === 'action'
      ? actionEntries
      : [];

  const editableEntriesForKind = (kind) => entriesForKind(kind).filter(
    (entry) => entry.managed === true
      && !(kind === 'guard' && entry.dynamic === true)
  );

  const defaultHandlerCode = (kind) => kind === 'guard'
    ? `function (
    string $currentState,
    string $signal,
    array $transition,
    array $context,
    FsmProcessor $processor
): bool {
    unset($currentState, $signal, $transition, $context, $processor);
    return true;
}`
    : `function (
    string $action,
    array $transition,
    array $context,
    FsmActionDispatcher $dispatcher
): bool {
    unset($action, $transition, $context, $dispatcher);
    return true;
}`;

  const handlerField = (name) => handlerSourceEditor.elements.namedItem(name);

  const loadHandlerEditorEntry = (kind, id) => {
    const entry = editableEntriesForKind(kind).find((item) => item.id === id);
    if (!entry) {
      throw new Error(`OWASYS_FSM_DESIGNER_${kind.toUpperCase()}_HANDLER_NOT_MANAGED:${id}`);
    }
    const idInput = handlerField('handler_id');
    const codeInput = handlerField('handler_code');
    if (idInput instanceof HTMLInputElement) {
      idInput.value = entry.id;
      idInput.readOnly = true;
    }
    if (codeInput instanceof HTMLTextAreaElement) {
      codeInput.value = entry.code;
    }
    if (handlerSourceMeta instanceof HTMLElement) {
      handlerSourceMeta.textContent = `${entry.source} · ${entry.handler_sha256.slice(0, 12)}`;
    }
  };

  const openHandlerSourceEditor = (kind, mode) => {
    if (!handlerCatalogReady) {
      throw new Error('OWASYS_FSM_DESIGNER_HANDLER_CATALOG_NOT_READY');
    }
    if (!['guard','action'].includes(kind)
        || !['create','update'].includes(mode)) {
      throw new Error('OWASYS_FSM_DESIGNER_HANDLER_EDITOR_MODE_INVALID');
    }

    handlerSourceEditor.reset();
    handlerSourceEditor.dataset.handlerKind = kind;
    handlerSourceEditor.dataset.handlerMode = mode;
    handlerSourceEditor.hidden = false;
    stateEditor.hidden = true;
    transitionEditor.hidden = true;
    selection.hidden = true;
    empty.hidden = true;

    const kindInput = handlerField('handler_kind');
    const idInput = handlerField('handler_id');
    const codeInput = handlerField('handler_code');
    if (kindInput instanceof HTMLInputElement) {
      kindInput.value = kind.toUpperCase();
    }

    if (mode === 'create') {
      if (handlerExistingRow instanceof HTMLElement) {
        handlerExistingRow.hidden = true;
      }
      if (idInput instanceof HTMLInputElement) {
        idInput.value = '';
        idInput.readOnly = false;
        idInput.focus();
      }
      if (codeInput instanceof HTMLTextAreaElement) {
        codeInput.value = defaultHandlerCode(kind);
      }
      if (handlerSourceMeta instanceof HTMLElement) {
        handlerSourceMeta.textContent = 'source PHP développeur · écriture persistante';
      }
      return;
    }

    const entries = editableEntriesForKind(kind);
    if (entries.length === 0) {
      throw new Error(`OWASYS_FSM_DESIGNER_${kind.toUpperCase()}_HANDLER_NOT_MANAGED`);
    }
    if (!(handlerExistingSelect instanceof HTMLSelectElement)) {
      throw new Error('OWASYS_FSM_DESIGNER_HANDLER_EXISTING_SELECT_MISSING');
    }
    handlerExistingSelect.replaceChildren();
    entries.forEach((entry) => {
      const option = document.createElement('option');
      option.value = entry.id;
      option.textContent = entry.id;
      handlerExistingSelect.append(option);
    });
    if (handlerExistingRow instanceof HTMLElement) {
      handlerExistingRow.hidden = false;
    }
    loadHandlerEditorEntry(kind, entries[0].id);
  };

  const sendHandlerSource = async () => {
    const kind = String(handlerSourceEditor.dataset.handlerKind || '');
    const mode = String(handlerSourceEditor.dataset.handlerMode || '');
    const idInput = handlerField('handler_id');
    const codeInput = handlerField('handler_code');
    const handlerId = idInput instanceof HTMLInputElement
      ? idInput.value.trim()
      : '';
    const handlerCode = codeInput instanceof HTMLTextAreaElement
      ? codeInput.value.trim()
      : '';

    if (!['guard','action'].includes(kind)
        || !['create','update'].includes(mode)
        || !/^[a-z][a-z0-9_:-]{0,127}$/.test(handlerId)
        || handlerCode === ''
        || (kind === 'guard' && handlerId.startsWith('acl:'))) {
      throw new Error('OWASYS_FSM_DESIGNER_HANDLER_REQUEST_INVALID');
    }

    const actionUrl = section.dataset.fsmDesignerActionUrl || window.location.pathname;
    const csrf = section.dataset.fsmDesignerCsrf || '';
    if (csrf === '') {
      throw new Error('OWASYS_FSM_DESIGNER_CSRF_MISSING');
    }

    const body = new URLSearchParams();
    body.set('owasys_fsm_designer_handler', '1');
    body.set('efsm_id', String(model.efsm_id));
    body.set('csrf_token', csrf);
    body.set('handler_kind', kind);
    body.set('handler_id', handlerId);
    body.set('handler_mode', mode);
    body.set('handler_code', handlerCode);

    const response = await fetch(actionUrl, {
      method:'POST',
      credentials:'same-origin',
      headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body:body.toString(),
    });
    const payload = await response.json();
    adoptCsrf(payload);
    const data = payload?.data;
    if (!response.ok
        || !payload
        || payload.ok !== true
        || !data
        || data.contract !== 'OWASYS_EFSM_HANDLER_WRITE_RESULT_V1'
        || data.kind !== kind
        || data.handler_id !== handlerId
        || !/^[a-f0-9]{64}$/.test(String(data.source_sha256 || ''))) {
      throw new Error(String(payload?.error_code || 'OWASYS_FSM_DESIGNER_HANDLER_WRITE_FAILED'));
    }

    await loadHandlerCatalog();
    section.dataset.fsmHandlerSourceDirty = '1';
    closeEditors();
    editorStatus.textContent =
      `${kind}.${mode} ${handlerId} · source ${String(data.source_sha256).slice(0, 12)}`;
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
    rect.setAttribute('x', String(x));
    rect.setAttribute('y', String(y));
    rect.setAttribute('width', String(width));
    rect.setAttribute('height', String(height));
    rect.setAttribute('rx', '10');
    const text = document.createElementNS(ns, 'text');
    text.setAttribute('x', String(x + width / 2));
    text.setAttribute('y', String(y + height / 2 + 5));
    text.setAttribute('text-anchor', 'middle');
    text.textContent = id;
    group.append(rect, text);
    svg.append(group);
  };

  const applyDomResult = (operation, refactor, originalId, command) => {
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
      hideEditors();
      empty.hidden = false;
      updateButtons();
      return;
    }
    if (operation === 'transition.handlers.update') {
      const id = String(command.transition_id || selectedId || '');
      const node = svg.querySelector(`.fsm-transition[data-transition-id="${CSS.escape(id)}"]`);
      if (node instanceof SVGGElement) {
        selectedKind = 'transition';
        selectedId = id;
        showSelection('transition', id, node);
      }
    }
  };

  const sendCommand = async (command, originalId = '') => {
    const actionUrl = section.dataset.fsmDesignerActionUrl || window.location.pathname;
    const csrf = section.dataset.fsmDesignerCsrf || '';
    if (csrf === '') throw new Error('OWASYS_FSM_DESIGNER_CSRF_MISSING');
    const body = new URLSearchParams();
    body.set('owasys_fsm_designer_command', '1');
    body.set('efsm_id', String(model.efsm_id));
    body.set('csrf_token', csrf);
    body.set('base_sha256', String(model.base_sha256));
    body.set('history_json', JSON.stringify(model.command_history));
    body.set('command_json', JSON.stringify(command));
    const response = await fetch(actionUrl, {
      method:'POST',
      credentials:'same-origin',
      headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body:body.toString(),
    });
    const payload = await response.json();
    adoptCsrf(payload);
    if (!response.ok || !payload || payload.ok !== true) {
      throw new Error(String(payload?.error_code || 'OWASYS_FSM_DESIGNER_DRAFT_COMMAND_FAILED'));
    }
    const data = payload.data;
    const persisted = data?.persisted === true;
    if (!data
        || data.contract !== 'OWASYS_EFSM_DRAFT_COMMAND_RESULT_V2'
        || !data.definition
        || (persisted && Number(data.history_count) !== 0)
        || (!persisted
          && Number(data.history_count) !== model.command_history.length + 1)) {
      throw new Error('OWASYS_FSM_DESIGNER_DRAFT_RESPONSE_INVALID');
    }
    model.definition = data.definition;

    if (persisted) {
      const nextBase = String(data.base_sha256 || '');
      if (!/^[a-f0-9]{64}$/.test(nextBase)
          || String(data.source_path || '') !== String(model.source_path || '')) {
        throw new Error('OWASYS_FSM_DESIGNER_PERSISTED_RESPONSE_INVALID');
      }
      model.base_sha256 = nextBase;
      model.command_history = [];
      rebuildIndexes();
      section.dataset.fsmDesignerDirty = '0';
      editorStatus.textContent =
        `persisted ${String(data.operation || command.operation || '')} · ${nextBase.slice(0, 12)}`;
      window.location.reload();
      return;
    }

    model.command_history.push(JSON.parse(JSON.stringify(command)));
    rebuildIndexes();
    applyDomResult(
      String(data.operation || command.operation || ''),
      data.refactor || {},
      originalId,
      command
    );
    editorStatus.textContent = `draft ${String(data.draft_sha256 || '').slice(0, 12)} · ${model.command_history.length} cmd`;
    section.dataset.fsmDesignerDirty = '1';
  };

  stateButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const action = button.dataset.fsmStateAction || '';
      stateButtons.forEach((item) => item.classList.remove('is-active'));
      button.classList.add('is-active');
      if (action === 'create') {
        activeTool = 'select';
        pendingCreatePoint = null;
        setStateEditorMode('create', null);
        editorStatus.textContent = '';
        return;
      }
      if (selectedKind !== 'state' || !states[selectedId]) return;
      activeTool = 'select';
      setStateEditorMode(action, states[selectedId]);
    });
  });

  if (transitionEditButton instanceof HTMLButtonElement) {
    transitionEditButton.addEventListener('click', () => {
      if (selectedKind !== 'transition' || !transitions[selectedId]) return;
      activeTool = 'select';
      openTransitionEditor(selectedId);
    });
  }
  if (guardAddButton instanceof HTMLButtonElement) {
    guardAddButton.addEventListener('click', () => appendHandlerFromCatalog('guard'));
  }
  if (actionAddButton instanceof HTMLButtonElement) {
    actionAddButton.addEventListener('click', () => appendHandlerFromCatalog('action'));
  }
  handlerAuthorButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const [kind, mode] = String(button.dataset.fsmHandlerAuthor || '').split(':', 2);
      try {
        openHandlerSourceEditor(kind, mode);
      } catch (error) {
        editorStatus.textContent = error instanceof Error
          ? error.message
          : 'OWASYS_FSM_DESIGNER_HANDLER_EDITOR_FAILED';
      }
    });
  });
  if (handlerExistingSelect instanceof HTMLSelectElement) {
    handlerExistingSelect.addEventListener('change', () => {
      try {
        loadHandlerEditorEntry(
          String(handlerSourceEditor.dataset.handlerKind || ''),
          String(handlerExistingSelect.value || '')
        );
      } catch (error) {
        editorStatus.textContent = error instanceof Error
          ? error.message
          : 'OWASYS_FSM_DESIGNER_HANDLER_LOAD_FAILED';
      }
    });
  }
  if (handlerSourceCancelButton instanceof HTMLButtonElement) {
    handlerSourceCancelButton.addEventListener('click', closeEditors);
  }
  if (stateCancelButton instanceof HTMLButtonElement) {
    stateCancelButton.addEventListener('click', closeEditors);
  }
  if (transitionCancelButton instanceof HTMLButtonElement) {
    transitionCancelButton.addEventListener('click', closeEditors);
  }

  stateEditor.addEventListener('submit', async (event) => {
    event.preventDefault();
    const originalId = stateEditor.dataset.originalId || '';
    try {
      if (stateSubmitButton instanceof HTMLButtonElement) stateSubmitButton.disabled = true;
      editorStatus.textContent = 'validation…';
      await sendCommand(commandForStateEditor(), originalId);
      closeEditors();
    } catch (error) {
      editorStatus.textContent = error instanceof Error
        ? error.message
        : 'OWASYS_FSM_DESIGNER_DRAFT_COMMAND_FAILED';
    } finally {
      if (stateSubmitButton instanceof HTMLButtonElement) stateSubmitButton.disabled = false;
    }
  });

  transitionEditor.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      if (transitionSubmitButton instanceof HTMLButtonElement) transitionSubmitButton.disabled = true;
      editorStatus.textContent = 'validation…';
      await sendCommand(commandForTransitionEditor());
      closeEditors();
    } catch (error) {
      editorStatus.textContent = error instanceof Error
        ? error.message
        : 'OWASYS_FSM_DESIGNER_DRAFT_COMMAND_FAILED';
    } finally {
      if (transitionSubmitButton instanceof HTMLButtonElement) transitionSubmitButton.disabled = false;
    }
  });

  handlerSourceEditor.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      if (handlerSourceSubmitButton instanceof HTMLButtonElement) {
        handlerSourceSubmitButton.disabled = true;
      }
      editorStatus.textContent = 'écriture source PHP…';
      await sendHandlerSource();
    } catch (error) {
      editorStatus.textContent = error instanceof Error
        ? error.message
        : 'OWASYS_FSM_DESIGNER_HANDLER_WRITE_FAILED';
    } finally {
      if (handlerSourceSubmitButton instanceof HTMLButtonElement) {
        handlerSourceSubmitButton.disabled = false;
      }
    }
  });
  section.addEventListener('submit', (event) => {
    const target = event.target;
    if (target instanceof HTMLFormElement
        && target !== stateEditor
        && target !== transitionEditor
        && target !== handlerSourceEditor
        && target.closest('[data-owasys-fsm-diagram]') === section) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  section.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    if (target.closest('.ow-fsm-designer-toolbar, [data-fsm-state-editor], [data-fsm-transition-handler-editor], [data-fsm-handler-source-editor]')) return;

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
      setStateEditorMode('create', null);
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
  if (handlerAuthoringSupported) {
    loadHandlerCatalog().catch((error) => {
      handlerCatalogReady = false;
      updateButtons();
      editorStatus.textContent = error instanceof Error
        ? error.message
        : 'OWASYS_FSM_DESIGNER_HANDLER_CATALOG_FAILED';
      section.dataset.fsmDesignerHandlerError = 'handler_catalog';
    });
  } else {
    handlerCatalogReady = false;
    section.dataset.fsmHandlerCatalogReady = '0';
    updateButtons();
  }
  section.dataset.fsmDesignerReady = '1';
  section.dataset.fsmDesignerRevision = DESIGNER_REVISION;
})();
