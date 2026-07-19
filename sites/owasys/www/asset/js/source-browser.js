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
    .ow-source-tree{overflow:auto;min-height:0;padding-right:.35rem}
    .ow-source-tree button{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.45rem;width:100%;margin:.18rem 0;padding:.48rem .58rem;text-align:left;border:1px solid rgba(125,160,200,.28);border-radius:.45rem;background:rgba(32,52,78,.72);color:inherit;cursor:pointer}
    .ow-source-tree button:hover,.ow-source-tree button[aria-current="true"]{border-color:#6bdcff;background:rgba(38,93,126,.9)}
    .ow-source-path{min-width:0;overflow-wrap:anywhere;word-break:break-word;font-weight:700}
    .ow-source-size{white-space:nowrap;opacity:.7;font-size:.82rem}
    .ow-source-editor-host{min-height:20rem;flex:1;border:1px solid rgba(125,160,200,.35);border-radius:.55rem;overflow:hidden;background:#081426}
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
  fallback.readOnly = true;
  fallback.spellcheck = false;
  fallback.style.cssText = 'width:100%;height:100%;resize:none;background:#081426;color:#e8f0ff;font-family:Consolas,monospace;padding:1rem;border:0';

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
    tree.querySelectorAll('button').forEach((button) => {
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
    }
  };

  const refresh = async () => {
    tree.textContent = 'Chargement…';
    try {
      const payload = await request({action: 'list'});
      tree.replaceChildren();
      payload.files.forEach((file) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.path = file.path;
        const path = document.createElement('span');
        path.className = 'ow-source-path';
        path.textContent = file.path;
        const size = document.createElement('span');
        size.className = 'ow-source-size';
        size.textContent = `${file.bytes} B`;
        button.append(path, size);
        button.addEventListener('click', () => openFile(file.path));
        tree.append(button);
      });
    } catch (error) {
      tree.textContent = error instanceof Error ? error.message : 'OWASYS_SOURCE_LIST_ERROR';
    }
  };

  refresh();
});
