(() => {
  "use strict";
  // P114C3AB_COMPACT_ALERT

  const markers = ["⚠[", "I18N:", "I18N_MISSING"];


  // P114C3AA_SHORT_BRACKET_ALERT
  function normalizeMissingText(text) {
    return text
      .replace(/⚠\s*I18N_MISSING:\s*([A-Za-z0-9_.-]+)/g, "⚠[$1]")
      .replace(/⚠\s*I18N:\s*([A-Za-z0-9_.-]+)/g, "⚠[$1]");
  }
  function markElement(element) {
    if (!(element instanceof HTMLElement)) {
      return;
    }

    element.textContent = normalizeMissingText(element.textContent);
    element.classList.add("i18n-missing-visible");

    if (element.tagName === "OPTION") {
      element.textContent = element.textContent.replace("⚠", "⚠");
      return;
    }

    element.setAttribute("data-i18n-missing", "true");
    element.setAttribute("title", element.textContent.trim());
  }

  function scan(root) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const targets = new Set();

    while (walker.nextNode()) {
      const node = walker.currentNode;
      if (!node.nodeValue || !markers.some((marker) => node.nodeValue.indexOf(marker) !== -1)) {
        continue;
      }

      const parent = node.parentElement;
      if (parent) {
        targets.add(parent);
      }
    }

    targets.forEach(markElement);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => scan(document.body));
  } else {
    scan(document.body);
  }
})();
