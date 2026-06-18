(function () {
  'use strict';

  var root = document.querySelector('[data-refbook-sidebar-state]');
  if (!root || !window.localStorage) {
    return;
  }

  var storageKey = 'OPUS_REFBOOK_NAV_GROUP_OPEN_V1';
  var saved = {};

  try {
    saved = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {};
  } catch (error) {
    saved = {};
  }

  function persist() {
    var state = {};
    root.querySelectorAll('details[data-refbook-nav-group]').forEach(function (details) {
      state[details.getAttribute('data-refbook-nav-group')] = details.open === true;
    });

    window.localStorage.setItem(storageKey, JSON.stringify(state));
  }

  root.querySelectorAll('details[data-refbook-nav-group]').forEach(function (details) {
    var name = details.getAttribute('data-refbook-nav-group');
    var hasActiveLink = details.querySelector('a.active') !== null;

    if (Object.prototype.hasOwnProperty.call(saved, name)) {
      details.open = saved[name] === true;
    }

    if (hasActiveLink) {
      details.open = true;
    }

    details.addEventListener('toggle', persist);
  });

  persist();
}());
