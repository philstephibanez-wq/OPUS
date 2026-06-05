/**
 * PUBLIC REFERENCE BOOK SCRIPT
 *
 * Role:
 *   Provide minimal client-side behavior for generated ASAP Reference Book pages.
 *
 * Responsibility:
 *   Enhance documentation pages only. Does not fetch business data, decide
 *   rights, route requests or mutate framework runtime state.
 *
 * Side effects:
 *   Adds a marker class to the document root once loaded.
 *
 * Contract:
 *   Documentation UI only. No business logic. No fallback runtime behavior.
 *
 * Since:
 *   P112C5
 */
(function asapReferenceBookReady() {
  document.documentElement.classList.add('asap-reference-ready');
}());
