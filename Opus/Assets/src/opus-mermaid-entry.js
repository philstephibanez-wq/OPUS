import mermaid from 'mermaid';

let initialized = false;

const initialize = (options = {}) => {
  if (initialized) return;
  mermaid.initialize({
    startOnLoad: false,
    securityLevel: 'strict',
    deterministicIds: true,
    suppressErrorRendering: true,
    ...options
  });
  initialized = true;
};

const render = async ({ parent, source, id = 'opus-mermaid' }) => {
  if (!(parent instanceof Element)) throw new TypeError('OPUS_MERMAID_PARENT_REQUIRED');

  const definition = String(source || '').trim();
  if (definition === '') throw new TypeError('OPUS_MERMAID_SOURCE_REQUIRED');

  initialize();
  const suffix = typeof crypto.randomUUID === 'function'
    ? crypto.randomUUID().replaceAll('-', '')
    : String(Date.now());

  const result = await mermaid.render(`${id}-${suffix}`, definition);
  parent.innerHTML = result.svg;
  result.bindFunctions?.(parent);
  parent.dataset.opusComponent = 'mermaid';

  return Object.freeze({
    svg: result.svg,
    destroy: () => {
      parent.replaceChildren();
      delete parent.dataset.opusComponent;
    }
  });
};

window.OPUS = window.OPUS || {};
window.OPUS.Mermaid = Object.freeze({
  contract: 'OPUS_MERMAID_11_V1',
  initialize,
  render
});
