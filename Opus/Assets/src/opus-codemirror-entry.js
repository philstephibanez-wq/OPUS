import { EditorState, Compartment } from '@codemirror/state';
import { EditorView, keymap, lineNumbers, highlightActiveLine, highlightActiveLineGutter } from '@codemirror/view';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { searchKeymap, highlightSelectionMatches } from '@codemirror/search';
import { autocompletion, completionKeymap, closeBrackets, closeBracketsKeymap } from '@codemirror/autocomplete';
import { indentOnInput, bracketMatching, foldGutter, foldKeymap } from '@codemirror/language';
import { javascript } from '@codemirror/lang-javascript';
import { json } from '@codemirror/lang-json';
import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { php } from '@codemirror/lang-php';
import { sql } from '@codemirror/lang-sql';
import { markdown } from '@codemirror/lang-markdown';

const languageForPath = (path) => {
  const lower = String(path || '').toLowerCase();
  if (lower.endsWith('.php')) return php();
  if (lower.endsWith('.json')) return json();
  if (lower.endsWith('.js') || lower.endsWith('.mjs') || lower.endsWith('.cjs')) return javascript();
  if (lower.endsWith('.css')) return css();
  if (lower.endsWith('.html') || lower.endsWith('.htm') || lower.endsWith('.score')) return html();
  if (lower.endsWith('.sql')) return sql();
  if (lower.endsWith('.md') || lower.endsWith('.markdown')) return markdown();
  return [];
};

const create = ({ parent, value = '', path = '', readOnly = true, onChange = () => {} }) => {
  if (!(parent instanceof Element)) throw new TypeError('OPUS_CODEMIRROR_PARENT_REQUIRED');

  const language = new Compartment();
  const editable = new Compartment();
  const state = EditorState.create({
    doc: String(value),
    extensions: [
      lineNumbers(),
      highlightActiveLineGutter(),
      history(),
      foldGutter(),
      indentOnInput(),
      bracketMatching(),
      closeBrackets(),
      autocompletion(),
      highlightActiveLine(),
      highlightSelectionMatches(),
      keymap.of([
        ...closeBracketsKeymap,
        ...defaultKeymap,
        ...searchKeymap,
        ...historyKeymap,
        ...foldKeymap,
        ...completionKeymap,
        indentWithTab
      ]),
      language.of(languageForPath(path)),
      editable.of(EditorView.editable.of(!readOnly)),
      EditorView.updateListener.of((update) => {
        if (update.docChanged) onChange(update.state.doc.toString());
      })
    ]
  });

  const view = new EditorView({ state, parent });
  parent.dataset.opusComponent = 'codemirror';

  return Object.freeze({
    getValue: () => view.state.doc.toString(),
    setValue: (nextValue) => view.dispatch({
      changes: { from: 0, to: view.state.doc.length, insert: String(nextValue) }
    }),
    setPath: (nextPath) => view.dispatch({
      effects: language.reconfigure(languageForPath(nextPath))
    }),
    setReadOnly: (nextReadOnly) => view.dispatch({
      effects: editable.reconfigure(EditorView.editable.of(!nextReadOnly))
    }),
    focus: () => view.focus(),
    destroy: () => view.destroy()
  });
};

window.OPUS = window.OPUS || {};
window.OPUS.CodeMirror = Object.freeze({
  contract: 'OPUS_CODEMIRROR_6_V1',
  create
});
