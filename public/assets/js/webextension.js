/* TachoSystem – browser/extension environment helpers */
'use strict';

(function () {

  // ── Companion-extension detection ────────────────────────────────────────
  // A companion browser extension may inject
  //   <meta name="x-tacho-ext" content="version=X.Y.Z">
  // into the page head to announce its presence.
  var extMeta    = document.querySelector('meta[name="x-tacho-ext"]');
  var extVersion = null;
  if (extMeta) {
    var vm = extMeta.content.match(/version=([0-9.]+)/);
    extVersion = vm ? vm[1] : null;   // null-safe: match() returns null when no match
  }

  // ── Action-element registration ───────────────────────────────────────────
  // Elements annotated with [data-ext-action="type:payload"] can be triggered
  // by an installed extension.  Format: "type:payload" where type is [a-z_]+.
  document.querySelectorAll('[data-ext-action]').forEach(function (el) {
    var raw   = el.getAttribute('data-ext-action') || '';
    var parts = raw.match(/^([a-z_]+):/);
    var type  = parts ? parts[1] : null;   // null-safe: parts is null when pattern unmatched
    if (!type) return;
    el.setAttribute('data-ext-ready', '1');
    el.addEventListener('click', function () {
      window.dispatchEvent(new CustomEvent('tacho:ext-action', {
        detail: { type: type, raw: raw },
        bubbles: true
      }));
    });
  });

  // ── Expose detection result for downstream scripts ────────────────────────
  window.__tachoWebExt = {
    version : extVersion,
    detected: extVersion !== null,
    ready   : true
  };

})();
