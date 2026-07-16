document.addEventListener('DOMContentLoaded', () => {
  if (document.body.dataset.opusState !== 'source' || document.querySelector('[data-context="OWASYS_SOURCE_EDITOR_UI"]')) {
    return;
  }

  const main = document.querySelector('.ow-main');
  if (!main) {
    return;
  }

  const ownScript = document.querySelector('script[src$="/asset/js/source-editor.js"]');
  const ownPath = ownScript instanceof HTMLScriptElement ? new URL(ownScript.src, window.location.href).pathname : '/asset/js/source-editor.js';
  const basePath = ownPath.replace(/\/asset\/js\/source-editor\.js$/, '') || '';
  const endpoint = `${basePath}/source-action.php`;

  const request = async (payload) => {
    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-OWASYS-Source': 'OWASYS_SOURCE_SCREEN_V1'
      },
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (!response.ok || result.error) {
      throw new Error(result.error || `OWASYS_SOURCE_HTTP_${response.status}`);
    }
    return result;
  };

  const panel = document.createElement('section');
  panel.className = 'ow-card';
  panel.dataset.context = 'OWASYS_SOURCE_EDITOR_UI';
  panel.innerHTML = '<h2>Source & Git</h2><p class="ow-muted">Authorized application files only. Preview and validation are required before an atomic write.</p>';

  const repository = document.createElement('pre');
  repository.className = 'ow-write-plan';
  repository.dataset.context = 'OWASYS_SOURCE_REPOSITORY_STATUS';
  repository.textContent = 'OWASYS_SOURCE_LOADING';

  const gitPanel = document.createElement('section');
  gitPanel.className = 'ow-card';
  gitPanel.dataset.context = 'OWASYS_SOURCE_GIT_WRITE_UI';
  const gitTitle = document.createElement('h3');
  gitTitle.textContent = 'Application Git commit';
  const gitHelp = document.createElement('p');
  gitHelp.className = 'ow-muted';
  gitHelp.textContent = 'Only the selected application subtree can be staged and committed. No push, pull, reset or arbitrary command is available.';
  const commitMessage = document.createElement('input');
  commitMessage.type = 'text';
  commitMessage.maxLength = 200;
  commitMessage.placeholder = 'Commit message';
  commitMessage.dataset.context = 'OWASYS_SOURCE_GIT_COMMIT_MESSAGE';
  const gitActions = document.createElement('div');
  gitActions.className = 'ow-inline-form';
  const stageButton = document.createElement('button');
  stageButton.type = 'button';
  stageButton.className = 'ow-button ow-button-secondary';
  stageButton.textContent = 'Prepare application changes';
  stageButton.dataset.context = 'OWASYS_SOURCE_GIT_STAGE';
  const commitButton = document.createElement('button');
  commitButton.type = 'button';
  commitButton.className = 'ow-button';
  commitButton.textContent = 'Commit application';
  commitButton.dataset.context = 'OWASYS_SOURCE_GIT_COMMIT';
  commitButton.disabled = true;
  gitActions.append(stageButton, commitButton);
  const gitResult = document.createElement('pre');
  gitResult.className = 'ow-write-plan';
  gitResult.dataset.context = 'OWASYS_SOURCE_GIT_WRITE_RESULT';
  gitResult.hidden = true;
  gitPanel.append(gitTitle, gitHelp, commitMessage, gitActions, gitResult);

  const workspace = document.createElement('div');
  workspace.style.display = 'grid';
  workspace.style.gridTemplateColumns = 'minmax(16rem, 30%) minmax(0, 1fr)';
  workspace.style.gap = '1rem';

  const treePanel = document.createElement('div');
  const treeTitle = document.createElement('h3');
  treeTitle.textContent = 'Application files';
  const tree = document.createElement('div');
  tree.dataset.context = 'OWASYS_SOURCE_FILE_TREE';
  treePanel.append(treeTitle, tree);

  const editorPanel = document.createElement('div');
  const fileTitle = document.createElement('h3');
  fileTitle.textContent = 'Select a file';
  const editor = document.createElement('textarea');
  editor.rows = 28;
  editor.spellcheck = false;
  editor.disabled = true;
  editor.style.width = '100%';
  editor.dataset.context = 'OWASYS_SOURCE_CONTENT_EDITOR';

  const actions = document.createElement('div');
  actions.className = 'ow-inline-form';
  const previewButton = document.createElement('button');
  previewButton.type = 'button';
  previewButton.className = 'ow-button ow-button-secondary';
  previewButton.textContent = 'Preview diff';
  previewButton.disabled = true;
  const saveButton = document.createElement('button');
  saveButton.type = 'button';
  saveButton.className = 'ow-button';
  saveButton.textContent = 'Validate & save';
  saveButton.disabled = true;
  const gitButton = document.createElement('button');
  gitButton.type = 'button';
  gitButton.className = 'ow-button ow-button-secondary';
  gitButton.textContent = 'Git diff';
  gitButton.disabled = true;
  actions.append(previewButton, saveButton, gitButton);

  const result = document.createElement('pre');
  result.className = 'ow-write-plan';
  result.dataset.context = 'OWASYS_SOURCE_EDITOR_RESULT';
  result.hidden = true;

  editorPanel.append(fileTitle, editor, actions, result);
  workspace.append(treePanel, editorPanel);
  panel.append(repository, gitPanel, workspace);
  main.appendChild(panel);

  let selectedPath = '';
  let expectedSha256 = '';
  let previewSha256 = '';
  let stagedFiles = [];

  const setBusy = (busy) => {
    tree.querySelectorAll('button').forEach((button) => { button.disabled = busy; });
    previewButton.disabled = busy || selectedPath === '';
    gitButton.disabled = busy || selectedPath === '';
    saveButton.disabled = busy || selectedPath === '' || previewSha256 !== expectedSha256;
    editor.disabled = busy || selectedPath === '';
    stageButton.disabled = busy;
    commitMessage.disabled = busy;
    commitButton.disabled = busy || stagedFiles.length === 0 || commitMessage.value.trim() === '';
  };

  const showError = (error, target = result) => {
    target.hidden = false;
    target.textContent = JSON.stringify({
      contract: 'OWASYS_SOURCE_ERROR_V1',
      error: error instanceof Error ? error.message : 'OWASYS_SOURCE_UI_ERROR'
    }, null, 2);
  };

  const openFile = async (path) => {
    setBusy(true);
    result.hidden = true;
    try {
      const file = await request({ action: 'read', path });
      selectedPath = file.path;
      expectedSha256 = file.sha256;
      previewSha256 = '';
      fileTitle.textContent = file.path;
      editor.value = file.content;
    } catch (error) {
      showError(error);
    } finally {
      setBusy(false);
    }
  };

  const refresh = async () => {
    setBusy(true);
    try {
      const payload = await request({ action: 'list' });
      repository.textContent = JSON.stringify(payload.repository, null, 2);
      tree.replaceChildren();
      payload.files.forEach((file) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ow-button ow-button-secondary';
        button.style.display = 'block';
        button.style.width = '100%';
        button.style.marginBottom = '.35rem';
        button.style.textAlign = 'left';
        button.textContent = `${file.path} (${file.bytes} B)`;
        button.addEventListener('click', () => openFile(file.path));
        tree.appendChild(button);
      });
    } catch (error) {
      repository.textContent = error instanceof Error ? error.message : 'OWASYS_SOURCE_LIST_ERROR';
    } finally {
      setBusy(false);
    }
  };

  editor.addEventListener('input', () => {
    previewSha256 = '';
    saveButton.disabled = true;
  });

  commitMessage.addEventListener('input', () => {
    commitButton.disabled = stagedFiles.length === 0 || commitMessage.value.trim() === '';
  });

  previewButton.addEventListener('click', async () => {
    setBusy(true);
    result.hidden = false;
    result.textContent = 'OWASYS_SOURCE_PREVIEW_RUNNING';
    try {
      const payload = await request({ action: 'preview', path: selectedPath, content: editor.value });
      result.textContent = JSON.stringify(payload, null, 2);
      previewSha256 = expectedSha256;
    } catch (error) {
      previewSha256 = '';
      showError(error);
    } finally {
      setBusy(false);
    }
  });

  gitButton.addEventListener('click', async () => {
    setBusy(true);
    result.hidden = false;
    result.textContent = 'OWASYS_SOURCE_GIT_DIFF_RUNNING';
    try {
      const payload = await request({ action: 'git-diff', path: selectedPath });
      result.textContent = payload.diff || 'OWASYS_SOURCE_GIT_DIFF_EMPTY';
    } catch (error) {
      showError(error);
    } finally {
      setBusy(false);
    }
  });

  saveButton.addEventListener('click', async () => {
    if (previewSha256 !== expectedSha256) {
      showError(new Error('OWASYS_SOURCE_PREVIEW_REQUIRED'));
      return;
    }
    if (!window.confirm(`Write validated changes to ${selectedPath}?`)) {
      return;
    }
    setBusy(true);
    result.hidden = false;
    result.textContent = 'OWASYS_SOURCE_WRITE_RUNNING';
    try {
      const payload = await request({
        action: 'write',
        path: selectedPath,
        content: editor.value,
        expected_sha256: expectedSha256
      });
      expectedSha256 = payload.sha256;
      previewSha256 = '';
      result.textContent = JSON.stringify(payload, null, 2);
      await refresh();
    } catch (error) {
      showError(error);
    } finally {
      setBusy(false);
    }
  });

  stageButton.addEventListener('click', async () => {
    if (!window.confirm('Prepare only the selected application changes for commit?')) {
      return;
    }
    setBusy(true);
    gitResult.hidden = false;
    gitResult.textContent = 'OWASYS_SOURCE_GIT_STAGE_RUNNING';
    try {
      const payload = await request({ action: 'git-stage-application' });
      stagedFiles = Array.isArray(payload.staged_files) ? payload.staged_files : [];
      gitResult.textContent = JSON.stringify(payload, null, 2);
      await refresh();
    } catch (error) {
      stagedFiles = [];
      showError(error, gitResult);
    } finally {
      setBusy(false);
    }
  });

  commitButton.addEventListener('click', async () => {
    const message = commitMessage.value.trim();
    if (stagedFiles.length === 0) {
      showError(new Error('OWASYS_GIT_NOTHING_STAGED_FOR_APPLICATION'), gitResult);
      return;
    }
    if (message === '') {
      showError(new Error('OWASYS_GIT_COMMIT_MESSAGE_INVALID'), gitResult);
      return;
    }
    if (!window.confirm(`Commit ${stagedFiles.length} staged application file(s) with message: ${message}?`)) {
      return;
    }
    setBusy(true);
    gitResult.hidden = false;
    gitResult.textContent = 'OWASYS_SOURCE_GIT_COMMIT_RUNNING';
    try {
      const payload = await request({ action: 'git-commit-application', message });
      stagedFiles = [];
      commitMessage.value = '';
      gitResult.textContent = JSON.stringify(payload, null, 2);
      await refresh();
    } catch (error) {
      showError(error, gitResult);
    } finally {
      setBusy(false);
    }
  });

  refresh();
});