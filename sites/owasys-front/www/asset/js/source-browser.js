document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  const tree = document.querySelector('[data-context="OWASYS_SOURCE_FILE_TREE"]');
  if (!(tree instanceof HTMLElement)) {
    return;
  }

  const forms = [...tree.querySelectorAll('form[data-source-path]')];
  const root = { directories: new Map(), files: [] };

  forms.forEach((form) => {
    const path = String(form.dataset.sourcePath || '');
    const parts = path.split('/').filter(Boolean);
    const name = parts.pop();
    if (!name) {
      return;
    }
    let node = root;
    parts.forEach((part) => {
      if (!node.directories.has(part)) {
        node.directories.set(part, { directories: new Map(), files: [] });
      }
      node = node.directories.get(part);
    });
    node.files.push({ name, form });
  });

  const renderNode = (node, parent, depth = 0) => {
    [...node.directories.entries()]
      .sort(([left], [right]) => left.localeCompare(right))
      .forEach(([name, child]) => {
        const details = document.createElement('details');
        details.className = 'ow-source-directory';
        details.open = depth < 2;
        const summary = document.createElement('summary');
        summary.textContent = name;
        const list = document.createElement('ul');
        renderNode(child, list, depth + 1);
        details.append(summary, list);
        const item = document.createElement('li');
        item.className = 'ow-source-tree-node';
        item.append(details);
        parent.append(item);
      });

    [...node.files]
      .sort((left, right) => left.name.localeCompare(right.name))
      .forEach(({ form }) => {
        const item = document.createElement('li');
        item.className = 'ow-source-tree-node';
        item.append(form);
        parent.append(item);
      });
  };

  const list = document.createElement('ul');
  renderNode(root, list);
  tree.replaceChildren(list);

  const selectedButton = tree.querySelector(
    '.ow-source-file[aria-current="true"]'
  );
  if (selectedButton instanceof HTMLButtonElement) {
    let ancestor = selectedButton.closest('details');
    while (ancestor instanceof HTMLDetailsElement) {
      ancestor.open = true;
      ancestor = ancestor.parentElement?.closest('details') || null;
    }
    selectedButton.scrollIntoView({ block: 'nearest' });
  }

  let editor = null;
  const selection = document.querySelector(
    '[data-context="OWASYS_SOURCE_SELECTION"]'
  );
  const host = document.querySelector(
    '[data-context="OWASYS_SOURCE_CONTENT_EDITOR"]'
  );
  const initialTextarea = host?.querySelector('textarea[data-source-path]');

  const ensureEditor = (content, path) => {
    if (!(host instanceof HTMLElement)
        || window.OWASYSCodeMirror?.contract !== 'OWASYS_CODEMIRROR_6_V1') {
      return;
    }
    host.hidden = false;
    if (editor === null) {
      host.replaceChildren();
      editor = window.OWASYSCodeMirror.create({
        parent: host,
        value: content,
        path,
        onChange: () => {}
      });
      editor.setReadOnly(true);
      return;
    }
    editor.setPath(path);
    editor.setValue(content);
  };

  if (initialTextarea instanceof HTMLTextAreaElement) {
    ensureEditor(
      initialTextarea.value,
      String(initialTextarea.dataset.sourcePath || '')
    );
  }

  tree.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    const button = form.querySelector('.ow-source-file');
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }
    tree.querySelectorAll('.ow-source-file').forEach((candidate) => {
      candidate.classList.remove('is-loading');
      candidate.disabled = true;
    });
    button.disabled = false;
    button.classList.add('is-loading');
    button.setAttribute('aria-busy', 'true');
    if (!window.fetch || !(selection instanceof HTMLElement)) {
      return;
    }
    event.preventDefault();
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });
      const result = await response.json();
      if (!response.ok
          || result.contract !== 'OWASYS_SOURCE_SELECTION_V1'
          || typeof result.selected?.content !== 'string') {
        throw new Error('OWASYS_SOURCE_SELECTION_RESPONSE_INVALID');
      }
      const selected = result.selected;
      ensureEditor(selected.content, selected.path);
      selection.querySelector('[data-source-selection-path]').textContent =
        selected.path;
      selection.querySelector('[data-source-selection-bytes]').textContent =
        String(selected.bytes);
      selection.querySelector('[data-source-selection-sha256]').textContent =
        selected.sha256;
      selection.querySelector('[data-source-selection-metadata]').hidden =
        false;
      selection.querySelector('[data-source-selection-empty]').hidden = true;
      tree.querySelectorAll('.ow-source-file').forEach((candidate) => {
        candidate.removeAttribute('aria-current');
      });
      button.setAttribute('aria-current', 'true');
    } catch (error) {
      form.submit();
    }
    tree.querySelectorAll('.ow-source-file').forEach((candidate) => {
      candidate.disabled = false;
      candidate.classList.remove('is-loading');
      candidate.removeAttribute('aria-busy');
    });
  });
});
