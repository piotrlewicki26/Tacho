/* TachoSystem – Application JS */
'use strict';

// ── Navbar: dropdown keyboard & click management ─────────────────────────
(function () {

  /** Close all open dropdowns, optionally keeping `except` open. */
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

    // ── Click to open / close ────────────────────────────────────────────
    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      const isOpen = dropdown.classList.contains('open');
      closeAll(isOpen ? null : dropdown);
      dropdown.classList.toggle('open', !isOpen);
      toggle.setAttribute('aria-expanded', String(!isOpen));
    });

    // ── Keyboard navigation inside open dropdown ─────────────────────────
    dropdown.addEventListener('keydown', function (e) {
      const items = Array.from(
        dropdown.querySelectorAll('.nav-dropdown-menu .nav-dropdown-item:not([disabled])')
      );
      const isOpen = dropdown.classList.contains('open');

      if (e.key === 'Enter' || e.key === ' ') {
        if (e.target === toggle) {
          e.preventDefault();
          toggle.click();
        }
        return;
      }

      if (!isOpen) return;

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        const idx = items.indexOf(document.activeElement);
        const next = items[idx + 1] || items[0];
        if (next) next.focus();
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        const idx = items.indexOf(document.activeElement);
        const prev = items[idx - 1] || items[items.length - 1];
        if (prev) prev.focus();
      } else if (e.key === 'Tab' || e.key === 'Escape') {
        closeAll(null);
        toggle.focus();
      }
    });
  });

  // ── Close when clicking outside ──────────────────────────────────────
  document.addEventListener('click', function () {
    if (document.querySelector('.nav-dropdown.open')) closeAll(null);
  });

  // ── Close on Escape ──────────────────────────────────────────────────
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAll(null);
  });

})();


// ── Mobile menu: toggle + backdrop ──────────────────────────────────────
(function () {
  const btn  = document.getElementById('navbarMobileToggle');
  const menu = document.getElementById('navbarMenu');
  if (!btn || !menu) return;

  // Inject backdrop element once
  let backdrop = document.querySelector('.navbar-backdrop');
  if (!backdrop) {
    backdrop = document.createElement('div');
    backdrop.className = 'navbar-backdrop';
    document.body.appendChild(backdrop);
  }

  function openMenu() {
    menu.classList.add('open');
    backdrop.classList.add('visible');
    btn.setAttribute('aria-expanded', 'true');
    btn.querySelector('i').className = 'bi bi-x-lg';
    document.body.style.overflow = 'hidden';
  }

  function closeMenu() {
    menu.classList.remove('open');
    backdrop.classList.remove('visible');
    btn.setAttribute('aria-expanded', 'false');
    btn.querySelector('i').className = 'bi bi-list';
    document.body.style.overflow = '';
  }

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    menu.classList.contains('open') ? closeMenu() : openMenu();
  });

  // Close on backdrop click
  backdrop.addEventListener('click', closeMenu);

  // Close on Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMenu();
  });
})();


// ── Navbar scroll-shadow ─────────────────────────────────────────────────
(function () {
  const navbar = document.getElementById('topNavbar');
  if (!navbar) return;

  function onScroll() {
    navbar.classList.toggle('scrolled', window.scrollY > 4);
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll(); // run once on load
})();


// ── Navbar date: live clock (updates every minute) ───────────────────────
(function () {
  const el = document.querySelector('.navbar-date time');
  if (!el) return;

  function pad(n) { return String(n).padStart(2, '0'); }

  function update() {
    const d = new Date();
    el.textContent = pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear();
  }

  // Tick at the next full minute, then every 60 s
  const ms = (60 - new Date().getSeconds()) * 1000;
  setTimeout(function () {
    update();
    setInterval(update, 60000);
  }, ms);
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
