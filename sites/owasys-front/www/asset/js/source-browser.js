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

});
