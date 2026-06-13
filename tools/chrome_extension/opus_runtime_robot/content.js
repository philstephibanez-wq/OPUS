(function () {
  'use strict';

  if (window.__OPUS_RUNTIME_ROBOT_INSTALLED__ === true) {
    return;
  }

  window.__OPUS_RUNTIME_ROBOT_INSTALLED__ = true;

  function inspectPage() {
    var bodyText = document.body ? document.body.innerText || '' : '';
    var runtimeError = /OPUS_[A-Z0-9_]*ERROR|Fatal error|Parse error|Warning:/i.test(bodyText);
    var header = document.querySelector('header,[role="banner"]');
    var main = document.querySelector('main,[role="main"]');
    var forms = document.querySelectorAll('form').length;
    var links = document.querySelectorAll('a[href]').length;

    return {
      ok: runtimeError === false,
      url: window.location.href,
      title: document.title || '',
      lang: document.documentElement ? document.documentElement.getAttribute('lang') || '' : '',
      bodyClass: document.body ? String(document.body.className || '') : '',
      hasHeader: header !== null,
      hasMain: main !== null,
      formCount: forms,
      linkCount: links,
      markers: {
        runtimeError: runtimeError,
        asapText: /ASAP/i.test(bodyText),
        refbookText: /REFBOOK|RefBook/i.test(bodyText)
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
}());
