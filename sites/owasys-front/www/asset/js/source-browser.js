document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  const tree = document.querySelector('[data-context="OWASYS_SOURCE_FILE_TREE"]');
  if (!(tree instanceof HTMLElement)) {
    return;
  }

  const links = [...tree.querySelectorAll('a[data-source-path]')];
  const root = { directories: new Map(), files: [] };

  links.forEach((link) => {
    const path = String(link.dataset.sourcePath || '');
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
    node.files.push({ name, link });
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
      .forEach(({ link }) => {
        const item = document.createElement('li');
        item.className = 'ow-source-tree-node';
        item.append(link);
        parent.append(item);
      });
  };

  const list = document.createElement('ul');
  renderNode(root, list);
  tree.replaceChildren(list);

  const selectedLink = tree.querySelector(
    '.ow-source-file[aria-current="page"]'
  );
  if (selectedLink instanceof HTMLAnchorElement) {
    let ancestor = selectedLink.closest('details');
    while (ancestor instanceof HTMLDetailsElement) {
      ancestor.open = true;
      ancestor = ancestor.parentElement?.closest('details') || null;
    }
    selectedLink.scrollIntoView({ block: 'nearest' });
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

  window.addEventListener('popstate', () => {
    window.location.reload();
  });

  tree.addEventListener('click', async (event) => {
    const link = event.target instanceof Element
      ? event.target.closest('a[data-source-path]')
      : null;
    if (!(link instanceof HTMLAnchorElement)) {
      return;
    }
    if (event.button !== 0 || event.ctrlKey || event.metaKey
        || event.shiftKey || event.altKey) {
      return;
    }
    if (!window.fetch || !(selection instanceof HTMLElement)) {
      return;
    }
    event.preventDefault();
    tree.querySelectorAll('.ow-source-file').forEach((candidate) => {
      candidate.classList.remove('is-loading');
      candidate.setAttribute('aria-disabled', 'true');
    });
    link.removeAttribute('aria-disabled');
    link.classList.add('is-loading');
    link.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(link.href, {
        method: 'GET',
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
      link.setAttribute('aria-current', 'page');
      window.history.pushState(
        { contract: 'OWASYS_SOURCE_URL_STATE_V1', path: selected.path },
        '',
        link.href
      );
    } catch (error) {
      window.location.assign(link.href);
    }
    tree.querySelectorAll('.ow-source-file').forEach((candidate) => {
      candidate.removeAttribute('aria-disabled');
      candidate.classList.remove('is-loading');
      candidate.removeAttribute('aria-busy');
    });
  });
});
