import { EditorState, Compartment } from '@codemirror/state';
import {
  EditorView,
  keymap,
  lineNumbers,
  highlightActiveLine,
  highlightActiveLineGutter,
  drawSelection,
  dropCursor,
  rectangularSelection,
  crosshairCursor
} from '@codemirror/view';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { searchKeymap, highlightSelectionMatches } from '@codemirror/search';
import {
  autocompletion,
  completionKeymap,
  closeBrackets,
  closeBracketsKeymap
} from '@codemirror/autocomplete';
import {
  indentOnInput,
  syntaxHighlighting,
  defaultHighlightStyle,
  bracketMatching,
  foldGutter,
  foldKeymap
} from '@codemirror/language';
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
  if (lower.endsWith('.html') || lower.endsWith('.htm')) return html();
  if (lower.endsWith('.sql')) return sql();
  if (lower.endsWith('.md') || lower.endsWith('.markdown')) return markdown();
  if (lower.endsWith('.score')) return html();
  return [];
};

const darkTheme = EditorView.theme({
  '&': {
    height: '100%',
    backgroundColor: '#081426',
    color: '#e8f0ff',
    fontSize: '14px'
  },
  '.cm-content': {
    fontFamily: 'Consolas, "Cascadia Code", monospace',
    caretColor: '#6bdcff',
    minHeight: '100%'
  },
  '.cm-cursor, .cm-dropCursor': { borderLeftColor: '#6bdcff' },
  '&.cm-focused .cm-selectionBackground, .cm-selectionBackground, .cm-content ::selection': {
    backgroundColor: '#234b70'
  },
  '.cm-panels': { backgroundColor: '#101f35', color: '#e8f0ff' },
  '.cm-panels.cm-panels-top': { borderBottom: '1px solid #2f4968' },
  '.cm-panels.cm-panels-bottom': { borderTop: '1px solid #2f4968' },
  '.cm-searchMatch': { backgroundColor: '#755d00' },
  '.cm-searchMatch.cm-searchMatch-selected': { backgroundColor: '#b27b00' },
  '.cm-activeLine': { backgroundColor: '#112642' },
  '.cm-selectionMatch': { backgroundColor: '#29496b' },
  '.cm-matchingBracket, .cm-nonmatchingBracket': { outline: '1px solid #6bdcff' },
  '.cm-gutters': { backgroundColor: '#0c1a2d', color: '#8095b3', border: 'none' },
  '.cm-activeLineGutter': { backgroundColor: '#162c49', color: '#dce8f8' },
  '.cm-foldPlaceholder': { backgroundColor: '#152943', border: 'none', color: '#a8bdd7' },
  '.cm-tooltip': { border: '1px solid #2f4968', backgroundColor: '#101f35' },
  '.cm-tooltip-autocomplete > ul > li[aria-selected]': {
    backgroundColor: '#244d72',
    color: '#fff'
  },
  '.cm-scroller': { overflow: 'auto' }
}, { dark: true });

const create = ({ parent, value = '', path = '', onChange = () => {} }) => {
  const language = new Compartment();
  const editable = new Compartment();
  const state = EditorState.create({
    doc: value,
    extensions: [
      lineNumbers(),
      highlightActiveLineGutter(),
      history(),
      foldGutter(),
      drawSelection(),
      dropCursor(),
      EditorState.allowMultipleSelections.of(true),
      indentOnInput(),
      bracketMatching(),
      closeBrackets(),
      autocompletion(),
      rectangularSelection(),
      crosshairCursor(),
      highlightActiveLine(),
      highlightSelectionMatches(),
      syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
      darkTheme,
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
      editable.of(EditorView.editable.of(false)),
      EditorView.updateListener.of((update) => {
        if (update.docChanged) onChange(update.state.doc.toString());
      })
    ]
  });

  const view = new EditorView({ state, parent });

  return {
    getValue: () => view.state.doc.toString(),
    setValue: (nextValue) => view.dispatch({
      changes: { from: 0, to: view.state.doc.length, insert: String(nextValue) }
    }),
    setPath: (nextPath) => view.dispatch({
      effects: language.reconfigure(languageForPath(nextPath))
    }),
    setReadOnly: (readOnly) => view.dispatch({
      effects: editable.reconfigure(EditorView.editable.of(!readOnly))
    }),
    focus: () => view.focus(),
    destroy: () => view.destroy()
  };
};

window.OWASYSCodeMirror = Object.freeze({
  contract: 'OWASYS_CODEMIRROR_6_V1',
  create
});
