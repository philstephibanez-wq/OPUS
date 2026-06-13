(function () {
  'use strict';

  if (window.__OPUS_RUNTIME_ROBOT_INSTALLED__ === true) {
    return;
  }

  window.__OPUS_RUNTIME_ROBOT_INSTALLED__ = true;

  function textOf(node) {
    return node ? String(node.innerText || node.textContent || '') : '';
  }

  function inspectPage() {
    var bodyText = document.body ? document.body.innerText || '' : '';
    var runtimeError = /OPUS_[A-Z0-9_]*ERROR|Fatal error|Parse error|Warning:/i.test(bodyText);
    var header = document.querySelector('header,[role="banner"],.refbook-header');
    var sidebar = document.querySelector('aside,[role="navigation"],.refbook-sidebar,.sidebar');
    var main = document.querySelector('main,[role="main"],.refbook-main');
    var footer = document.querySelector('footer,[role="contentinfo"],.refbook-footer');
    var diagrams = document.querySelectorAll('svg,.diagram,[data-diagram],.mermaid').length;
    var forms = document.querySelectorAll('form').length;
    var links = document.querySelectorAll('a[href]').length;
    var html = document.documentElement;

    if (html) {
      html.setAttribute('data-opus-runtime-robot-extension', 'installed');
      html.setAttribute('data-opus-runtime-robot-last-check', new Date().toISOString());
    }

    return {
      ok: runtimeError === false && header !== null && sidebar !== null && main !== null && footer !== null,
      url: window.location.href,
      title: document.title || '',
      lang: html ? html.getAttribute('lang') || '' : '',
      bodyClass: document.body ? String(document.body.className || '') : '',
      hasHeader: header !== null,
      hasSidebar: sidebar !== null,
      hasMain: main !== null,
      hasFooter: footer !== null,
      diagramCount: diagrams,
      formCount: forms,
      linkCount: links,
      markers: {
        runtimeError: runtimeError,
        opusText: /Opus/i.test(bodyText),
        refbookText: /REFBOOK|RefBook/i.test(bodyText),
        headerText: textOf(header).slice(0, 180),
        sidebarText: textOf(sidebar).slice(0, 180),
        footerText: textOf(footer).slice(0, 180)
      },
      checkedAt: new Date().toISOString()
    };
  }

  chrome.runtime.onMessage.addListener(function (message, sender, sendResponse) {
    if (!message || message.type !== 'OPUS_RUNTIME_ROBOT_INSPECT') {
      return false;
    }

    sendResponse(inspectPage());
    return true;
  });

  inspectPage();
}());
