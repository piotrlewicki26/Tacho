/* TachoSystem – Application JS */
'use strict';

// ── Sidebar toggle (mobile) ─────────────────────────────────────────────
(function () {
  const toggle  = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (!toggle || !sidebar) return;

  toggle.addEventListener('click', () => {
    sidebar.classList.toggle('show');
  });

  document.addEventListener('click', (e) => {
    if (!sidebar.contains(e.target) && e.target !== toggle) {
      sidebar.classList.remove('show');
    }
  });
})();

// ── Auto-dismiss alerts ──────────────────────────────────────────────────
(function () {
  document.querySelectorAll('.alert-dismissible[data-auto-dismiss]').forEach((el) => {
    setTimeout(() => {
      const btn = el.querySelector('.btn-close');
      if (btn) btn.click();
    }, 5000);
  });
})();

// ── Confirm delete on forms ──────────────────────────────────────────────
(function () {
  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (e) => {
      if (!confirm(el.dataset.confirm || 'Czy na pewno?')) {
        e.preventDefault();
      }
    });
  });
})();

// ── File input label update ──────────────────────────────────────────────
(function () {
  document.querySelectorAll('input[type="file"]').forEach((input) => {
    input.addEventListener('change', () => {
      const label = document.querySelector(`label[for="${input.id}"]`);
      if (label && input.files.length) {
        label.textContent = input.files[0].name;
      }
    });
  });
})();
