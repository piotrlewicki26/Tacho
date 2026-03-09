/* TachoSystem – Application JS */
'use strict';

// ── Sidebar: mobile open/close with overlay ─────────────────────────────
(function () {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const toggle   = document.getElementById('sidebarToggle');
  if (!sidebar) return;

  function openSidebar()  { sidebar.classList.add('open'); overlay && overlay.classList.add('active'); }
  function closeSidebar() { sidebar.classList.remove('open'); overlay && overlay.classList.remove('active'); }

  toggle  && toggle.addEventListener('click',  () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
  overlay && overlay.addEventListener('click', closeSidebar);
})();

// ── Sidebar: desktop collapse (mini icon-only mode) ─────────────────────
(function () {
  const STORAGE_KEY = 'tacho_sidebar_collapsed';
  const body        = document.body;
  const btnPin      = document.getElementById('sidebarPin');
  const btnCollapse = document.getElementById('sidebarCollapseBtn');

  function applyState(collapsed) {
    body.classList.toggle('sidebar-collapsed', collapsed);
    if (btnPin)      btnPin.title      = collapsed ? 'Rozwiń menu' : 'Zwiń menu';
    if (btnCollapse) btnCollapse.title = collapsed ? 'Rozwiń menu' : 'Zwiń menu';
  }

  // Restore persisted state on load
  const stored = localStorage.getItem(STORAGE_KEY) === 'true';
  applyState(stored);

  function toggle() {
    const next = !body.classList.contains('sidebar-collapsed');
    applyState(next);
    localStorage.setItem(STORAGE_KEY, String(next));
  }

  btnPin      && btnPin.addEventListener('click',      toggle);
  btnCollapse && btnCollapse.addEventListener('click', toggle);
})();

// ── Sidebar: collapsible nav groups ─────────────────────────────────────
(function () {
  const STORAGE_KEY = 'tacho_open_groups';

  function loadOpen() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch { return []; }
  }
  function saveOpen(list) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
  }

  document.querySelectorAll('.nav-group').forEach((group) => {
    const toggle = group.querySelector('.nav-group-toggle');
    const items  = group.querySelector('.nav-group-items');
    if (!toggle || !items) return;

    const groupId = group.dataset.group;

    // If PHP already rendered .open (active child), do nothing; else apply saved state
    if (!group.classList.contains('open')) {
      const openGroups = loadOpen();
      if (openGroups.includes(groupId)) {
        group.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
      }
    }

    toggle.addEventListener('click', () => {
      const isOpen = group.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(isOpen));

      // Persist state
      const openGroups = loadOpen();
      if (isOpen) {
        if (!openGroups.includes(groupId)) openGroups.push(groupId);
      } else {
        const idx = openGroups.indexOf(groupId);
        if (idx >= 0) openGroups.splice(idx, 1);
      }
      saveOpen(openGroups);
    });
  });
})();

// ── Auto-dismiss alerts (flash messages) ────────────────────────────────
(function () {
  const AUTO_DISMISS_DELAY_MS = 6000;
  document.querySelectorAll('.alert-dismissible').forEach((el) => {
    const delay = parseInt(el.dataset.autoDismiss || '0', 10) || AUTO_DISMISS_DELAY_MS;
    setTimeout(() => {
      const btn = el.querySelector('.btn-close');
      if (btn) btn.click();
    }, delay);
  });
})();

// ── Confirm on dangerous actions ────────────────────────────────────────
(function () {
  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (e) => {
      if (!confirm(el.dataset.confirm || 'Czy na pewno?')) {
        e.preventDefault();
      }
    });
  });
})();

// ── File input: show selected filename ──────────────────────────────────
(function () {
  document.querySelectorAll('input[type="file"]').forEach((input) => {
    input.addEventListener('change', () => {
      const label = input.id && document.querySelector(`label[for="${input.id}"]`);
      if (label && input.files.length) {
        label.textContent = input.files[0].name;
      }
    });
  });
})();
