(() => {
  'use strict';

  const treeHost = document.querySelector('[data-context="OWASYS_SOURCE_FILE_TREE"]');
  const form = document.querySelector('[data-context="OWASYS_SOURCE_EDITOR_FORM"]');
  const textarea = form?.querySelector('textarea[name="new_content"]') ?? null;
  const editorHost = document.querySelector('[data-context="OWASYS_SOURCE_CONTENT_EDITOR"]');
  const selection = document.querySelector('[data-context="OWASYS_SOURCE_SELECTION"]');
  const emptySelection = document.querySelector('[data-source-selection-empty]');
  const tabs = document.querySelector('[data-context="OWASYS_SOURCE_TABS"]');
  const tabsList = tabs?.querySelector('[data-source-tabs-list]') ?? null;
  const previewPanel = document.querySelector('[data-context="OWASYS_SOURCE_PREVIEW"]');
  const pathLabel = document.querySelector('[data-source-selection-path]');
  const bytesLabel = document.querySelector('[data-source-selection-bytes]');
  const hashLabel = document.querySelector('[data-source-selection-sha256]');
  const expectedHash = form?.querySelector('[data-source-expected-hash]') ?? null;
  const serverContent = form?.querySelector('[data-source-server-content]') ?? null;
  const cleanLabel = document.querySelector('[data-source-clean]');
  const dirtyLabel = document.querySelector('[data-source-dirty]');
  const actionButtons = form
    ? Array.from(form.querySelectorAll('button[name="source_action"]'))
    : [];
  const canEditRole = form?.dataset.sourceEditable === '1';
  const unsavedMessage = form?.dataset.unsavedMessage || 'Unsaved changes will be lost.';

  let editor = null;
  let originalValue = serverContent?.value ?? textarea?.value ?? '';
  let currentPath = textarea?.dataset.sourcePath ?? '';
  let dirty = false;
  let submitting = false;

  const leafName = (path) => {
    const parts = String(path || '').split('/').filter(Boolean);
    return parts.at(-1) || path;
  };

  const setDirty = (value) => {
    dirty = Boolean(value);
    if (cleanLabel) cleanLabel.hidden = dirty;
    if (dirtyLabel) dirtyLabel.hidden = !dirty;
    document.body.dataset.sourceDirty = dirty ? '1' : '0';
  };

  const setActionsEnabled = (enabled) => {
    for (const button of actionButtons) {
      button.disabled = !enabled;
    }
  };

  const hideTransientPanels = () => {
    if (previewPanel) previewPanel.hidden = true;
    for (const context of [
      'OWASYS_SOURCE_SAVED',
      'OWASYS_SOURCE_CONFLICT',
      'OWASYS_SOURCE_FAILURE',
    ]) {
      const node = document.querySelector(`[data-context="${context}"]`);
      if (node) node.hidden = true;
    }
  };

  const markCurrentLinks = (path) => {
    for (const link of document.querySelectorAll('a[data-source-path]')) {
      if (link.dataset.sourcePath === path) {
        link.setAttribute('aria-current', 'page');
      } else {
        link.removeAttribute('aria-current');
      }
    }
  };

  const addTab = (path, url) => {
    if (!tabs || !tabsList || !path) return;
    tabs.classList.remove('is-hidden');
    let link = Array.from(tabsList.querySelectorAll('a[data-source-tab-path]'))
      .find((candidate) => candidate.dataset.sourceTabPath === path);
    if (!link) {
      const item = document.createElement('li');
      link = document.createElement('a');
      link.href = url;
      link.dataset.sourcePath = path;
      link.dataset.sourceTabPath = path;
      link.textContent = leafName(path);
      item.append(link);
      tabsList.append(item);
    } else {
      link.href = url;
    }
    for (const candidate of tabsList.querySelectorAll('a[data-source-tab-path]')) {
      if (candidate === link) {
        candidate.setAttribute('aria-current', 'page');
      } else {
        candidate.removeAttribute('aria-current');
      }
    }
  };

  const currentValue = () => editor ? editor.getValue() : (textarea?.value ?? '');

  const replaceEditorValue = (value, path) => {
    if (!textarea) return;
    textarea.value = value;
    textarea.dataset.sourcePath = path;
    textarea.readOnly = !canEditRole;
    if (editor) {
      editor.setValue(value);
      editor.setPath(path);
      editor.setReadOnly(!canEditRole);
      editor.focus();
    }
    originalValue = value;
    if (serverContent) serverContent.value = value;
    currentPath = path;
    setDirty(false);
  };

  const applySelection = (selected, url) => {
    const path = String(selected.path || '');
    const content = String(selected.content ?? '');
    const sha256 = String(selected.sha256 || '').toLowerCase();
    if (!path || !/^[a-f0-9]{64}$/.test(sha256)) {
      throw new Error('OWASYS_SOURCE_SELECTION_INVALID');
    }

    hideTransientPanels();
    selection?.classList.remove('is-hidden');
    emptySelection?.classList.add('is-hidden');
    if (pathLabel) pathLabel.textContent = path;
    if (bytesLabel) bytesLabel.textContent = String(selected.bytes ?? content.length);
    if (hashLabel) hashLabel.textContent = sha256;
    if (expectedHash) expectedHash.value = sha256;
    if (form) form.action = url;
    replaceEditorValue(content, path);
    setActionsEnabled(canEditRole);
    markCurrentLinks(path);
    addTab(path, url);
  };

  const loadSelection = async (link) => {
    if (dirty && !window.confirm(unsavedMessage)) return;
    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin) return;
    link.classList.add('is-loading');
    try {
      const response = await fetch(url.href, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const payload = await response.json();
      if (!response.ok || !payload || typeof payload.selected !== 'object') {
        throw new Error(payload?.error_code || 'OWASYS_SOURCE_SELECTION_FAILED');
      }
      applySelection(payload.selected, url.pathname);
      window.history.pushState({ sourcePath: payload.selected.path }, '', url.pathname);
    } catch (_) {
      window.location.assign(url.href);
    } finally {
      link.classList.remove('is-loading');
    }
  };

  const bindSourceNavigation = () => {
    document.addEventListener('click', (event) => {
      if (event.defaultPrevented || event.button !== 0
        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }
      const link = event.target.closest('a[data-source-path]');
      if (!link) return;
      event.preventDefault();
      void loadSelection(link);
    });
    window.addEventListener('popstate', () => window.location.reload());
  };

  const buildTree = () => {
    if (!treeHost) return;
    const links = Array.from(treeHost.querySelectorAll(':scope > a[data-source-path]'));
    if (links.length === 0) return;
    const root = { directories: new Map(), files: [] };
    for (const link of links) {
      const parts = String(link.dataset.sourcePath || '').split('/').filter(Boolean);
      if (parts.length === 0) continue;
      let node = root;
      for (const directory of parts.slice(0, -1)) {
        if (!node.directories.has(directory)) {
          node.directories.set(directory, { directories: new Map(), files: [] });
        }
        node = node.directories.get(directory);
      }
      node.files.push(link);
    }

    const renderNode = (node) => {
      const list = document.createElement('ul');
      for (const [name, child] of Array.from(node.directories.entries())
        .sort(([left], [right]) => left.localeCompare(right))) {
        const item = document.createElement('li');
        item.className = 'ow-source-tree-node';
        const details = document.createElement('details');
        details.className = 'ow-source-directory';
        details.open = true;
        const summary = document.createElement('summary');
        summary.textContent = name;
        details.append(summary, renderNode(child));
        item.append(details);
        list.append(item);
      }
      for (const link of node.files.sort((left, right) =>
        String(left.dataset.sourcePath).localeCompare(String(right.dataset.sourcePath)))) {
        const item = document.createElement('li');
        item.className = 'ow-source-tree-node';
        item.append(link);
        list.append(item);
      }
      return list;
    };

    treeHost.replaceChildren(renderNode(root));
  };

  const initializeEditor = () => {
    if (!form || !textarea || !editorHost || !window.OWASYSCodeMirror) return;
    const parent = document.createElement('div');
    parent.className = 'ow-source-codemirror';
    editorHost.prepend(parent);
    textarea.hidden = true;
    editor = window.OWASYSCodeMirror.create({
      parent,
      value: textarea.value,
      path: textarea.dataset.sourcePath || '',
      onChange: (value) => {
        textarea.value = value;
        setDirty(value !== originalValue);
        hideTransientPanels();
      },
    });
    editor.setReadOnly(textarea.readOnly || !canEditRole);
  };

  if (form && textarea) {
    form.addEventListener('submit', (event) => {
      textarea.value = currentValue();
      if (!currentPath || !expectedHash?.value) {
        event.preventDefault();
        return;
      }
      submitting = true;
    });
  }

  window.addEventListener('beforeunload', (event) => {
    if (!dirty || submitting) return;
    event.preventDefault();
    event.returnValue = '';
  });

  buildTree();
  initializeEditor();
  bindSourceNavigation();
  setDirty(currentValue() !== originalValue);
})();
