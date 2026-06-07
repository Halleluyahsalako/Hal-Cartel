/* Cartel — admin JS: toggles [data-hal-cartel-advanced] visibility per the user's stored UI mode (no reload). */
(function () {
  if (typeof HalCartelUIData === 'undefined') return;

  function applyMode() {
    var advanced = HalCartelUIData.mode === 'advanced';
    document.querySelectorAll('[data-hal-cartel-advanced]').forEach(function (el) {
      el.classList.toggle('hal-cartel-advanced-field--hidden', !advanced);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyMode);
  } else {
    applyMode();
  }
})();
