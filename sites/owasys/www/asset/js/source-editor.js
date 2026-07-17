document.addEventListener('DOMContentLoaded', () => {
  if (document.body.dataset.opusState !== 'source' || document.querySelector('[data-context="OWASYS_SOURCE_EDITOR_UI"]')) return;

  const main = document.querySelector('.ow-main');
  if (!main) return;

  const ownScript = document.querySelector('script[src$="/asset/js/source-editor.js"]');
  const ownPath = ownScript instanceof HTMLScriptElement ? new URL(ownScript.src, window.location.href).pathname : '/asset/js/source-editor.js';
  const basePath = ownPath.replace(/\/asset\/js\/source-editor\.js$/, '') || '';
  const endpoint = `${basePath}/source-action.php`;

  const style = document.createElement('style');
  style.textContent = `
    [data-context="OWASYS_SOURCE_EDITOR_UI"]{display:grid;gap:1rem}
    .ow-source-meta{display:grid;grid-template-columns:minmax(0,1fr) minmax(18rem,30%);gap:1rem}
    .ow-source-workspace{display:grid;grid-template-columns:minmax(20rem,32%) minmax(0,1fr);gap:1rem;min-height:38rem;height:calc(100vh - 15rem)}
    .ow-source-tree-panel,.ow-source-editor-panel{min-height:0;display:flex;flex-direction:column}
    .ow-source-tree{overflow:auto;min-height:0;padding-right:.35rem;overflow-x:hidden}
    .ow-source-tree ul{list-style:none;margin:0;padding:0}
    .ow-source-tree-node{margin:.12rem 0}
    .ow-source-directory{margin:.15rem 0}
    .ow-source-directory>summary{cursor:pointer;padding:.45rem .55rem;border-radius:.45rem;font-weight:700;overflow-wrap:anywhere;word-break:break-word}
    .ow-source-directory>summary:hover{background:rgba(90,160,210,.12)}
    .ow-source-directory>ul{margin-left:.65rem;padding-left:.6rem;border-left:1px solid rgba(125,160,200,.25)}
    .ow-source-file{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.45rem;align-items:start;width:100%;margin:.18rem 0;padding:.48rem .58rem;text-align:left;border:1px solid rgba(125,160,200,.28);border-radius:.45rem;background:rgba(32,52,78,.72);color:inherit;cursor:pointer}
    .ow-source-file:hover{border-color:#6bdcff;background:rgba(43,78,112,.85)}
    .ow-source-file[aria-current="true"]{border-color:#6bdcff;background:rgba(38,93,126,.9);box-shadow:0 0 0 1px rgba(107,220,255,.25)}
    .ow-source-file-name{min-width:0;overflow-wrap:anywhere;word-break:break-word;font-weight:700}
    .ow-source-file-size{white-space:nowrap;opacity:.7;font-size:.82rem}
    .ow-source-editor-host{min-height:20rem;flex:1;border:1px solid rgba(125,160,200,.35);border-radius:.55rem;overflow:hidden;background:#081426}
    .ow-source-actions{position:sticky;bottom:0;z-index:2;padding:.65rem 0;background:var(--ow-surface,#0d1b2e)}
    .ow-source-result{max-height:18rem;overflow:auto}
    @media(max-width:980px){.ow-source-meta,.ow-source-workspace{grid-template-columns:1fr;height:auto}.ow-source-tree{max-height:22rem}.ow-source-editor-host{height:34rem;flex:none}}
  `;
  document.head.appendChild(style);

  const request = async (payload) => {
    const response = await fetch(endpoint, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'X-OWASYS-Source': 'OWASYS_SOURCE_SCREEN_V1'},
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (!response.ok || result.error) throw new Error(result.error || `OWASYS_SOURCE_HTTP_${response.status}`);
    return result;
  };

  const panel = document.createElement('section');
  panel.className = 'ow-card';
  panel.dataset.context = 'OWASYS_SOURCE_EDITOR_UI';
  panel.innerHTML = '<div><h2>Source & Git</h2><p class="ow-muted">Authorized application files only. Preview and validation are required before an atomic write.</p></div>';

  const repository = document.createElement('pre');
  repository.className = 'ow-write-plan';
  repository.dataset.context = 'OWASYS_SOURCE_REPOSITORY_STATUS';
  repository.textContent = 'OWASYS_SOURCE_LOADING';

  const gitPanel = document.createElement('section');
  gitPanel.className = 'ow-card';
  gitPanel.dataset.context = 'OWASYS_SOURCE_GIT_WRITE_UI';
  const gitTitle = document.createElement('h3'); gitTitle.textContent = 'Application Git commit';
  const gitHelp = document.createElement('p'); gitHelp.className = 'ow-muted';
  gitHelp.textContent = 'Only the selected application subtree can be staged and committed. No push, pull, reset or arbitrary command is available.';
  const commitMessage = document.createElement('input');
  commitMessage.type = 'text'; commitMessage.maxLength = 200; commitMessage.placeholder = 'Commit message';
  commitMessage.dataset.context = 'OWASYS_SOURCE_GIT_COMMIT_MESSAGE';
  const gitActions = document.createElement('div'); gitActions.className = 'ow-inline-form';
  const stageButton = document.createElement('button'); stageButton.type = 'button'; stageButton.className = 'ow-button ow-button-secondary'; stageButton.textContent = 'Prepare application changes'; stageButton.dataset.context = 'OWASYS_SOURCE_GIT_STAGE';
  const commitButton = document.createElement('button'); commitButton.type = 'button'; commitButton.className = 'ow-button'; commitButton.textContent = 'Commit application'; commitButton.dataset.context = 'OWASYS_SOURCE_GIT_COMMIT'; commitButton.disabled = true;
  gitActions.append(stageButton, commitButton);
  const gitResult = document.createElement('pre'); gitResult.className = 'ow-write-plan ow-source-result'; gitResult.dataset.context = 'OWASYS_SOURCE_GIT_WRITE_RESULT'; gitResult.hidden = true;
  gitPanel.append(gitTitle, gitHelp, commitMessage, gitActions, gitResult);

  const meta = document.createElement('div'); meta.className = 'ow-source-meta'; meta.append(repository, gitPanel);
  const workspace = document.createElement('div'); workspace.className = 'ow-source-workspace';
  const treePanel = document.createElement('section'); treePanel.className = 'ow-card ow-source-tree-panel';
  const treeTitle = document.createElement('h3'); treeTitle.textContent = 'Application files';
  const tree = document.createElement('div'); tree.className = 'ow-source-tree'; tree.dataset.context = 'OWASYS_SOURCE_FILE_TREE';
  treePanel.append(treeTitle, tree);

  const editorPanel = document.createElement('section'); editorPanel.className = 'ow-card ow-source-editor-panel';
  const fileTitle = document.createElement('h3'); fileTitle.textContent = 'Select a file';
  const editorHost = document.createElement('div'); editorHost.className = 'ow-source-editor-host'; editorHost.dataset.context = 'OWASYS_SOURCE_CONTENT_EDITOR';
  const fallback = document.createElement('textarea'); fallback.spellcheck = false; fallback.disabled = true; fallback.style.cssText = 'width:100%;height:100%;resize:none;background:#081426;color:#e8f0ff;font-family:Consolas,monospace;padding:1rem;border:0';
  let editorAdapter;
  if (window.OWASYSCodeMirror?.contract === 'OWASYS_CODEMIRROR_6_V1') {
    editorAdapter = window.OWASYSCodeMirror.create({parent: editorHost, value: '', path: '', onChange: () => { previewSha256 = ''; saveButton.disabled = true; }});
  } else {
    editorHost.append(fallback);
    editorAdapter = {
      getValue: () => fallback.value,
      setValue: (value) => { fallback.value = String(value); },
      setPath: () => {},
      setReadOnly: (readOnly) => { fallback.disabled = readOnly; },
      focus: () => fallback.focus()
    };
    fallback.addEventListener('input', () => { previewSha256 = ''; saveButton.disabled = true; });
  }

  const actions = document.createElement('div'); actions.className = 'ow-inline-form ow-source-actions';
  const previewButton = document.createElement('button'); previewButton.type = 'button'; previewButton.className = 'ow-button ow-button-secondary'; previewButton.textContent = 'Preview diff'; previewButton.disabled = true;
  const saveButton = document.createElement('button'); saveButton.type = 'button'; saveButton.className = 'ow-button'; saveButton.textContent = 'Validate & save'; saveButton.disabled = true;
  const gitButton = document.createElement('button'); gitButton.type = 'button'; gitButton.className = 'ow-button ow-button-secondary'; gitButton.textContent = 'Git diff'; gitButton.disabled = true;
  actions.append(previewButton, saveButton, gitButton);
  const result = document.createElement('pre'); result.className = 'ow-write-plan ow-source-result'; result.dataset.context = 'OWASYS_SOURCE_EDITOR_RESULT'; result.hidden = true;
  editorPanel.append(fileTitle, editorHost, actions, result);
  workspace.append(treePanel, editorPanel);
  panel.append(meta, workspace);
  main.appendChild(panel);

  let selectedPath = '', expectedSha256 = '', previewSha256 = '';
  let stagedFiles = [];

  const setBusy = (busy) => {
    tree.querySelectorAll('button').forEach((button) => { button.disabled = busy; });
    previewButton.disabled = busy || selectedPath === '';
    gitButton.disabled = busy || selectedPath === '';
    saveButton.disabled = busy || selectedPath === '' || previewSha256 !== expectedSha256;
    editorAdapter.setReadOnly(busy || selectedPath === '');
    stageButton.disabled = busy; commitMessage.disabled = busy;
    commitButton.disabled = busy || stagedFiles.length === 0 || commitMessage.value.trim() === '';
  };

  const showError = (error, target = result) => { target.hidden = false; target.textContent = JSON.stringify({contract:'OWASYS_SOURCE_ERROR_V1', error:error instanceof Error ? error.message : 'OWASYS_SOURCE_UI_ERROR'}, null, 2); };

  const markSelectedFile = () => {
    tree.querySelectorAll('.ow-source-file').forEach((button) => {
      button.setAttribute('aria-current', button.dataset.path === selectedPath ? 'true' : 'false');
    });
  };

  const openFile = async (path) => {
    setBusy(true); result.hidden = true;
    try {
      const file = await request({action:'read', path});
      selectedPath = file.path; expectedSha256 = file.sha256; previewSha256 = '';
      fileTitle.textContent = file.path; fileTitle.title = file.path;
      editorAdapter.setPath(file.path); editorAdapter.setValue(file.content); editorAdapter.focus();
      markSelectedFile();
    } catch (error) { showError(error); } finally { setBusy(false); }
  };

  const buildTreeModel = (files) => {
    const root = {directories: new Map(), files: []};
    files.forEach((file) => {
      const parts = String(file.path).split('/').filter(Boolean);
      const fileName = parts.pop() || file.path;
      let node = root;
      parts.forEach((part) => {
        if (!node.directories.has(part)) node.directories.set(part, {directories: new Map(), files: []});
        node = node.directories.get(part);
      });
      node.files.push({...file, name: fileName});
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
    setBusy(true);
    try {
      const payload = await request({action:'list'});
      repository.textContent = JSON.stringify(payload.repository, null, 2);
      tree.replaceChildren();
      const rootList = document.createElement('div');
      renderTreeNode(buildTreeModel(payload.files), rootList);
      tree.append(rootList);
      markSelectedFile();
    } catch (error) { repository.textContent = error instanceof Error ? error.message : 'OWASYS_SOURCE_LIST_ERROR'; } finally { setBusy(false); }
  };

  commitMessage.addEventListener('input', () => { commitButton.disabled = stagedFiles.length === 0 || commitMessage.value.trim() === ''; });
  previewButton.addEventListener('click', async () => {
    setBusy(true); result.hidden = false; result.textContent = 'OWASYS_SOURCE_PREVIEW_RUNNING';
    try { const payload = await request({action:'preview', path:selectedPath, content:editorAdapter.getValue()}); result.textContent = JSON.stringify(payload, null, 2); previewSha256 = expectedSha256; }
    catch (error) { previewSha256 = ''; showError(error); } finally { setBusy(false); }
  });
  gitButton.addEventListener('click', async () => {
    setBusy(true); result.hidden = false; result.textContent = 'OWASYS_SOURCE_GIT_DIFF_RUNNING';
    try { const payload = await request({action:'git-diff', path:selectedPath}); result.textContent = payload.diff || 'OWASYS_SOURCE_GIT_DIFF_EMPTY'; }
    catch (error) { showError(error); } finally { setBusy(false); }
  });
  saveButton.addEventListener('click', async () => {
    if (previewSha256 !== expectedSha256) return showError(new Error('OWASYS_SOURCE_PREVIEW_REQUIRED'));
    if (!window.confirm(`Write validated changes to ${selectedPath}?`)) return;
    setBusy(true); result.hidden = false; result.textContent = 'OWASYS_SOURCE_WRITE_RUNNING';
    try {
      const payload = await request({action:'write', path:selectedPath, content:editorAdapter.getValue(), expected_sha256:expectedSha256});
      expectedSha256 = payload.sha256; previewSha256 = ''; result.textContent = JSON.stringify(payload, null, 2); await refresh();
    } catch (error) { showError(error); } finally { setBusy(false); }
  });
  stageButton.addEventListener('click', async () => {
    if (!window.confirm('Prepare only the selected application changes for commit?')) return;
    setBusy(true); gitResult.hidden = false; gitResult.textContent = 'OWASYS_SOURCE_GIT_STAGE_RUNNING';
    try { const payload = await request({action:'git-stage-application'}); stagedFiles = Array.isArray(payload.staged_files) ? payload.staged_files : []; gitResult.textContent = JSON.stringify(payload, null, 2); await refresh(); }
    catch (error) { stagedFiles = []; showError(error, gitResult); } finally { setBusy(false); }
  });
  commitButton.addEventListener('click', async () => {
    const message = commitMessage.value.trim();
    if (stagedFiles.length === 0) return showError(new Error('OWASYS_GIT_NOTHING_STAGED_FOR_APPLICATION'), gitResult);
    if (message === '') return showError(new Error('OWASYS_GIT_COMMIT_MESSAGE_INVALID'), gitResult);
    if (!window.confirm(`Commit ${stagedFiles.length} staged application file(s) with message: ${message}?`)) return;
    setBusy(true); gitResult.hidden = false; gitResult.textContent = 'OWASYS_SOURCE_GIT_COMMIT_RUNNING';
    try { const payload = await request({action:'git-commit-application', message}); stagedFiles = []; commitMessage.value = ''; gitResult.textContent = JSON.stringify(payload, null, 2); await refresh(); }
    catch (error) { showError(error, gitResult); } finally { setBusy(false); }
  });

  refresh();
});