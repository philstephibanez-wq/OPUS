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
    .ow-source-editor-host{min-height:20rem;flex:1;border:1px solid rgba(125,160,200,.35);border-radius:.55rem;overflow:auto;background:#081426}
    .ow-source-code{margin:0;min-height:100%;padding:1rem;color:#e8f0ff;font:14px/1.55 Consolas,"Cascadia Code",monospace;tab-size:4;white-space:pre;overflow:auto}
    .ow-syn-comment{color:#718096}.ow-syn-string{color:#a7f3d0}.ow-syn-number{color:#fbbf24}.ow-syn-keyword{color:#7dd3fc;font-weight:700}.ow-syn-variable{color:#f0abfc}.ow-syn-tag{color:#fb7185}.ow-syn-attr{color:#c4b5fd}
    @media(max-width:980px){.ow-source-browser{grid-template-columns:1fr;height:auto}.ow-source-tree{max-height:22rem}.ow-source-editor-host{height:34rem;flex:none}}
  `;
  document.head.appendChild(style);

  const escapeHtml = (value) => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

  const highlight = (content, path) => {
    let html = escapeHtml(content);
    const lower = String(path || '').toLowerCase();

    if (lower.endsWith('.html') || lower.endsWith('.htm') || lower.endsWith('.score') || lower.endsWith('.xml')) {
      return html
        .replace(/(&lt;\/?)([A-Za-z][\w:-]*)/g, '$1<span class="ow-syn-tag">$2</span>')
        .replace(/\s([A-Za-z_:][\w:.-]*)(=)/g, ' <span class="ow-syn-attr">$1</span>$2')
        .replace(/(&quot;.*?&quot;|'.*?')/g, '<span class="ow-syn-string">$1</span>');
    }

    return html
      .replace(/(\/\*[\s\S]*?\*\/|\/\/[^\n]*|#[^\n]*)/g, '<span class="ow-syn-comment">$1</span>')
      .replace(/(&quot;(?:\\.|[^&])*?&quot;|'(?:\\.|[^'])*?')/g, '<span class="ow-syn-string">$1</span>')
      .replace(/\b(\d+(?:\.\d+)?)\b/g, '<span class="ow-syn-number">$1</span>')
      .replace(/\b(class|function|return|if|else|elseif|foreach|for|while|do|switch|case|break|continue|try|catch|finally|throw|new|use|namespace|public|private|protected|static|const|let|var|async|await|import|export|from|true|false|null|extends|implements|interface|trait|match|yield|echo|require|require_once|include|include_once)\b/g, '<span class="ow-syn-keyword">$1</span>')
      .replace(/(\$[A-Za-z_][A-Za-z0-9_]*)/g, '<span class="ow-syn-variable">$1</span>');
  };

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
  const code = document.createElement('code');
  const pre = document.createElement('pre');
  pre.className = 'ow-source-code';
  pre.append(code);
  editorHost.append(pre);
  codePanel.append(fileTitle, editorHost);

  workspace.append(treePanel, codePanel);
  panel.append(workspace);
  main.appendChild(panel);

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
      code.innerHTML = highlight(file.content, file.path);
      markSelected();
    } catch (error) {
      fileTitle.textContent = error instanceof Error ? error.message : 'OWASYS_SOURCE_READ_ERROR';
      code.textContent = '';
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

  const renderTreeNode = (node, parent, depth = 0) => {
    [...node.directories.entries()].sort(([a], [b]) => a.localeCompare(b)).forEach(([name, child]) => {
      const details = document.createElement('details');
      details.className = 'ow-source-directory';
      details.open = depth < 2;
      const summary = document.createElement('summary');
      summary.textContent = name;
      summary.title = name;
      const list = document.createElement('ul');
      renderTreeNode(child, list, depth + 1);
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