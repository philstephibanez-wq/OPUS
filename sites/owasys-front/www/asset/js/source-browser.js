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

  tree.addEventListener('submit', (event) => {
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
  });

  const textarea = document.querySelector(
    '[data-context="OWASYS_SOURCE_CONTENT_EDITOR"] textarea[data-source-path]'
  );
  const host = document.querySelector(
    '[data-context="OWASYS_SOURCE_CONTENT_EDITOR"]'
  );
  if (!(textarea instanceof HTMLTextAreaElement)
      || !(host instanceof HTMLElement)
      || window.OWASYSCodeMirror?.contract !== 'OWASYS_CODEMIRROR_6_V1') {
    return;
  }

  const content = textarea.value;
  const path = String(textarea.dataset.sourcePath || '');
  textarea.remove();
  const editor = window.OWASYSCodeMirror.create({
    parent: host,
    value: content,
    path,
    onChange: () => {}
  });
  editor.setReadOnly(true);
});
