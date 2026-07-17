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
    .ow-source-workspace{display:grid;grid-template-columns:minmax(16rem,26%) minmax(0,1fr);gap:1rem;min-height:38rem;height:calc(100vh - 15rem)}
    .ow-source-tree-panel,.ow-source-editor-panel{min-height:0;display:flex;flex-direction:column}
    .ow-source-tree{overflow:auto;min-height:0;padding-right:.35rem}
    .ow-source-editor-host{min-height:20rem;flex:1;border:1px solid rgba(125,160,200,.35);border-radius:.55rem;overflow:hidden;background:#081426}
    .ow-source-actions{position:sticky;bottom:0;z-index:2;padding:.65rem 0;background:var(--ow-surface,#0d1b2e)}
    .ow-source-result{max-height:18rem;overflow:auto}
    @media(max-width:980px){.ow-source-meta,.ow-source-workspace{grid-template-columns:1fr;height:auto}.ow-source-tree{max-height:18rem}.ow-source-editor-host{height:34rem;flex:none}}
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

  const openFile = async (path) => {
    setBusy(true); result.hidden = true;
    try {
      const file = await request({action:'read', path});
      selectedPath = file.path; expectedSha256 = file.sha256; previewSha256 = '';
      fileTitle.textContent = file.path; editorAdapter.setPath(file.path); editorAdapter.setValue(file.content); editorAdapter.focus();
    } catch (error) { showError(error); } finally { setBusy(false); }
  };

  const refresh = async () => {
    setBusy(true);
    try {
      const payload = await request({action:'list'});
      repository.textContent = JSON.stringify(payload.repository, null, 2); tree.replaceChildren();
      payload.files.forEach((file) => {
        const button = document.createElement('button'); button.type = 'button'; button.className = 'ow-button ow-button-secondary';
        button.style.cssText = 'display:block;width:100%;margin-bottom:.35rem;text-align:left';
        button.textContent = `${file.path} (${file.bytes} B)`; button.addEventListener('click', () => openFile(file.path)); tree.appendChild(button);
      });
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
