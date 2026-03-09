/* TachoSystem – Application JS */
'use strict';

// ── Horizontal navbar: dropdown open/close ───────────────────────────────
(function () {
  /** Close all open dropdowns except `except`. */
  function closeAll(except) {
    document.querySelectorAll('.nav-dropdown.open').forEach(function (dd) {
      if (dd !== except) {
        dd.classList.remove('open');
        const toggle = dd.querySelector('.nav-dropdown-toggle');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  document.querySelectorAll('.nav-dropdown').forEach(function (dropdown) {
    const toggle = dropdown.querySelector('.nav-dropdown-toggle');
    if (!toggle) return;

    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      const isOpen = dropdown.classList.contains('open');
      closeAll(isOpen ? null : dropdown);
      dropdown.classList.toggle('open', !isOpen);
      toggle.setAttribute('aria-expanded', String(!isOpen));
    });
  });

  // Close when clicking outside (only if a dropdown is currently open)
  document.addEventListener('click', function () {
    if (document.querySelector('.nav-dropdown.open')) closeAll(null);
  });

  // Close on Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAll(null);
  });
})();

// ── Mobile menu toggle ───────────────────────────────────────────────────
(function () {
  const btn  = document.getElementById('navbarMobileToggle');
  const menu = document.getElementById('navbarMenu');
  if (!btn || !menu) return;

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    const isOpen = menu.classList.toggle('open');
    btn.setAttribute('aria-expanded', String(isOpen));
    btn.querySelector('i').className = isOpen ? 'bi bi-x-lg' : 'bi bi-list';
  });

  // Close mobile menu when clicking outside
  document.addEventListener('click', function (e) {
    if (!menu.contains(e.target) && e.target !== btn) {
      menu.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
      btn.querySelector('i').className = 'bi bi-list';
    }
  });
})();

// ── Auto-dismiss alerts (flash messages) ────────────────────────────────
(function () {
  const AUTO_DISMISS_DELAY_MS = 6000;
  document.querySelectorAll('.alert-dismissible').forEach(function (el) {
    const delay = parseInt(el.dataset.autoDismiss || '0', 10) || AUTO_DISMISS_DELAY_MS;
    setTimeout(function () {
      const btn = el.querySelector('.btn-close');
      if (btn) btn.click();
    }, delay);
  });
})();

// ── Confirm on dangerous actions ─────────────────────────────────────────
(function () {
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm || 'Czy na pewno?')) {
        e.preventDefault();
      }
    });
  });
})();

// ── File input: show selected filename ───────────────────────────────────
(function () {
  document.querySelectorAll('input[type="file"]').forEach(function (input) {
    input.addEventListener('change', function () {
      const label = input.id && document.querySelector('label[for="' + input.id + '"]');
      if (label && input.files.length) {
        label.textContent = input.files[0].name;
      }
    });
  });
})();
