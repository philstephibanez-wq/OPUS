document.addEventListener('DOMContentLoaded', () => {
  if (document.body.dataset.opusState !== 'source' || document.querySelector('[data-context="OWASYS_SOURCE_BROWSER_UI"]')) return;

  const main = document.querySelector('.ow-main');
  if (!main) return;

  const ownScript = document.querySelector('script[src$="/asset/js/source-browser.js"]');
  const ownPath = ownScript instanceof HTMLScriptElement ? new URL(ownScript.src, window.location.href).pathname : '/asset/js/source-browser.js';
  const basePath = ownPath.replace(/\/asset\/js\/source-browser\.js$/, '') || '';
  const endpoint = `${basePath}/source-action.php`;

  const style = document.createElement('style');
  style.textContent = `
    [data-context="OWASYS_SOURCE_BROWSER_UI"]{display:grid;gap:1rem}
    .ow-source-browser{display:grid;grid-template-columns:minmax(18rem,30%) minmax(0,1fr);gap:1rem;min-height:38rem;height:calc(100vh - 15rem)}
    .ow-source-tree-panel,.ow-source-code-panel{min-height:0;display:flex;flex-direction:column}
    .ow-source-tree{overflow:auto;min-height:0;padding-right:.35rem;overflow-x:hidden}
    .ow-source-tree ul{list-style:none;margin:0;padding:0}
    .ow-source-tree-node{margin:.12rem 0}
    .ow-source-directory{margin:.15rem 0}
    .ow-source-directory>summary{cursor:pointer;padding:.45rem .55rem;border-radius:.45rem;font-weight:700;overflow-wrap:anywhere;word-break:break-word}
    .ow-source-directory>summary:hover{background:rgba(90,160,210,.12)}
    .ow-source-directory>ul{margin-left:.65rem;padding-left:.6rem;border-left:1px solid rgba(125,160,200,.25)}
    .ow-source-file{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.45rem;align-items:start;width:100%;margin:.18rem 0;padding:.48rem .58rem;text-align:left;border:1px solid rgba(125,160,200,.28);border-radius:.45rem;background:rgba(32,52,78,.72);color:inherit;cursor:pointer}
    .ow-source-file:hover,.ow-source-file[aria-current="true"]{border-color:#6bdcff;background:rgba(38,93,126,.9)}
    .ow-source-file-name{min-width:0;overflow-wrap:anywhere;word-break:break-word;font-weight:700}
    .ow-source-file-size{white-space:nowrap;opacity:.7;font-size:.82rem}
    .ow-source-editor-host{min-height:20rem;flex:1;border:1px solid rgba(125,160,200,.35);border-radius:.55rem;overflow:hidden;background:#081426}
    .ow-source-editor-host>.cm-editor{height:100%}
    .ow-source-editor-host .cm-scroller{overflow:auto}
    .ow-source-fallback{width:100%;height:100%;resize:none;background:#081426;color:#e8f0ff;font:14px/1.55 Consolas,"Cascadia Code",monospace;padding:1rem;border:0}
    @media(max-width:980px){.ow-source-browser{grid-template-columns:1fr;height:auto}.ow-source-tree{max-height:22rem}.ow-source-editor-host{height:34rem;flex:none}}
  `;
  document.head.appendChild(style);

  const request = async (payload) => {
    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'X-OWASYS-Source': 'OWASYS_SOURCE_BROWSER_V1'},
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (!response.ok || result.error) throw new Error(result.error || `OWASYS_SOURCE_HTTP_${response.status}`);
    return result;
  };

  const panel = document.createElement('section');
  panel.className = 'ow-card';
  panel.dataset.context = 'OWASYS_SOURCE_BROWSER_UI';
  panel.innerHTML = '<div><h2>Code source</h2><p class="ow-muted">Lecture seule des fichiers autorisés de l’application sélectionnée, avec coloration syntaxique.</p></div>';

  const workspace = document.createElement('div');
  workspace.className = 'ow-source-browser';

  const treePanel = document.createElement('section');
  treePanel.className = 'ow-card ow-source-tree-panel';
  const treeTitle = document.createElement('h3');
  treeTitle.textContent = 'Fichiers de l’application';
  const tree = document.createElement('div');
  tree.className = 'ow-source-tree';
  tree.dataset.context = 'OWASYS_SOURCE_FILE_TREE';
  treePanel.append(treeTitle, tree);

  const codePanel = document.createElement('section');
  codePanel.className = 'ow-card ow-source-code-panel';
  const fileTitle = document.createElement('h3');
  fileTitle.textContent = 'Sélectionner un fichier';
  const editorHost = document.createElement('div');
  editorHost.className = 'ow-source-editor-host';
  editorHost.dataset.context = 'OWASYS_SOURCE_CONTENT_EDITOR';
  codePanel.append(fileTitle, editorHost);

  workspace.append(treePanel, codePanel);
  panel.append(workspace);
  main.appendChild(panel);

  const fallback = document.createElement('textarea');
  fallback.className = 'ow-source-fallback';
  fallback.readOnly = true;
  fallback.spellcheck = false;

  let editor;
  if (window.OWASYSCodeMirror?.contract === 'OWASYS_CODEMIRROR_6_V1') {
    editor = window.OWASYSCodeMirror.create({parent: editorHost, value: '', path: '', onChange: () => {}});
    editor.setReadOnly(true);
  } else {
    editorHost.append(fallback);
    editor = {
      setValue: (value) => { fallback.value = String(value); },
      setPath: () => {},
      setReadOnly: () => {},
      focus: () => fallback.focus()
    };
  }

  let selectedPath = '';

  const markSelected = () => {
    tree.querySelectorAll('.ow-source-file').forEach((button) => {
      button.setAttribute('aria-current', button.dataset.path === selectedPath ? 'true' : 'false');
    });
  };

  const openFile = async (path) => {
    fileTitle.textContent = 'Chargement…';
    try {
      const file = await request({action: 'read', path});
      selectedPath = file.path;
      fileTitle.textContent = file.path;
      editor.setPath(file.path);
      editor.setValue(file.content);
      editor.setReadOnly(true);
      editor.focus();
      markSelected();
    } catch (error) {
      fileTitle.textContent = error instanceof Error ? error.message : 'OWASYS_SOURCE_READ_ERROR';
      editor.setValue('');
    }
  };

  const buildTreeModel = (files) => {
    const root = {directories: new Map(), files: []};
    files.forEach((file) => {
      const parts = String(file.path).split('/').filter(Boolean);
      const name = parts.pop() || file.path;
      let node = root;
      parts.forEach((part) => {
        if (!node.directories.has(part)) node.directories.set(part, {directories: new Map(), files: []});
        node = node.directories.get(part);
      });
      node.files.push({...file, name});
    });
    return root;
  };

  const renderTreeNode = (node, parent) => {
    [...node.directories.entries()].sort(([a], [b]) => a.localeCompare(b)).forEach(([name, child]) => {
      const details = document.createElement('details');
      details.className = 'ow-source-directory';
      details.open = false;
      const summary = document.createElement('summary');
      summary.textContent = name;
      summary.title = name;
      const list = document.createElement('ul');
      renderTreeNode(child, list);
      details.append(summary, list);
      const item = document.createElement('div');
      item.className = 'ow-source-tree-node';
      item.append(details);
      parent.append(item);
    });

    [...node.files].sort((a, b) => a.name.localeCompare(b.name)).forEach((file) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'ow-source-file';
      button.dataset.path = file.path;
      button.title = file.path;
      button.setAttribute('aria-current', file.path === selectedPath ? 'true' : 'false');
      const name = document.createElement('span');
      name.className = 'ow-source-file-name';
      name.textContent = file.name;
      const size = document.createElement('span');
      size.className = 'ow-source-file-size';
      size.textContent = `${file.bytes} B`;
      button.append(name, size);
      button.addEventListener('click', () => openFile(file.path));
      const item = document.createElement('div');
      item.className = 'ow-source-tree-node';
      item.append(button);
      parent.append(item);
    });
  };

  const refresh = async () => {
    tree.textContent = 'Chargement…';
    try {
      const payload = await request({action: 'list'});
      tree.replaceChildren();
      const root = document.createElement('div');
      renderTreeNode(buildTreeModel(payload.files), root);
      tree.append(root);
      markSelected();
    } catch (error) {
      tree.textContent = error instanceof Error ? error.message : 'OWASYS_SOURCE_LIST_ERROR';
    }
  };

  refresh();
});