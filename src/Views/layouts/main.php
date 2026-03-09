<!DOCTYPE html>
<html lang="pl" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<?php
$user        = \Core\Auth::user();
$companyId   = \Core\Auth::companyId();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isActive    = fn(string $p) => str_starts_with($currentPath, $p) ? 'active' : '';

// Pre-fetch company name once
$companyName = '';
if ($companyId) {
    $co = \Core\Database::fetchOne('SELECT name FROM companies WHERE id=:id', ['id' => $companyId]);
    if ($co) $companyName = $co['name'];
}

// Helper: is any path in array active? (for auto-expanding groups)
$anyActive = function(array $paths) use ($currentPath): bool {
    foreach ($paths as $p) {
        if ($p === '/' ? $currentPath === '/' : str_starts_with($currentPath, $p)) return true;
    }
    return false;
};
$roleLabels = ['superadmin' => 'Super Admin', 'admin' => 'Administrator', 'operator' => 'Operator'];
$roleLabel  = $roleLabels[$user['role'] ?? ''] ?? ($user['role'] ?? '');
$userInitial = strtoupper(substr($user['name'] ?? 'U', 0, 1));
?>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ═══════════════════ SIDEBAR ═══════════════════ -->
<nav id="sidebar" class="sidebar">

  <!-- Brand header -->
  <div class="sidebar-header">
    <a href="/" class="sidebar-brand">
      <div class="sidebar-brand-icon">
        <i class="bi bi-speedometer2"></i>
      </div>
      <span class="sidebar-brand-name">Tacho<strong>System</strong></span>
    </a>
    <button class="sidebar-pin-btn d-none d-lg-flex" id="sidebarPin" title="Zwiń/Rozwiń">
      <i class="bi bi-layout-sidebar-inset"></i>
    </button>
  </div>

  <!-- Navigation -->
  <div class="sidebar-nav">

    <!-- ── Dashboard ────────────────────────────────────── -->
    <a class="nav-item <?= $currentPath === '/' ? 'active' : '' ?>" href="/">
      <span class="nav-icon"><i class="bi bi-grid-1x2-fill"></i></span>
      <span class="nav-label">Dashboard</span>
    </a>

    <!-- ── Flota ────────────────────────────────────────── -->
    <div class="nav-group <?= $anyActive(['/drivers', '/vehicles']) ? 'open' : '' ?>" data-group="fleet">
      <button class="nav-group-toggle" type="button" aria-expanded="<?= $anyActive(['/drivers', '/vehicles']) ? 'true' : 'false' ?>">
        <span class="nav-icon"><i class="bi bi-diagram-3-fill"></i></span>
        <span class="nav-label">Flota</span>
        <i class="bi bi-chevron-down nav-chevron"></i>
      </button>
      <div class="nav-group-items">
        <a class="nav-item nav-sub <?= $isActive('/drivers') ?>" href="/drivers">
          <span class="nav-icon"><i class="bi bi-person-badge-fill"></i></span>
          <span class="nav-label">Kierowcy</span>
        </a>
        <a class="nav-item nav-sub <?= $isActive('/vehicles') ?>" href="/vehicles">
          <span class="nav-icon"><i class="bi bi-truck-front-fill"></i></span>
          <span class="nav-label">Pojazdy</span>
        </a>
      </div>
    </div>

    <!-- ── Tachograf ─────────────────────────────────────── -->
    <div class="nav-group <?= $anyActive(['/analysis']) ? 'open' : '' ?>" data-group="tacho">
      <button class="nav-group-toggle" type="button" aria-expanded="<?= $anyActive(['/analysis']) ? 'true' : 'false' ?>">
        <span class="nav-icon"><i class="bi bi-hdd-fill"></i></span>
        <span class="nav-label">Tachograf</span>
        <i class="bi bi-chevron-down nav-chevron"></i>
      </button>
      <div class="nav-group-items">
        <a class="nav-item nav-sub <?= $isActive('/analysis') ?>" href="/analysis">
          <span class="nav-icon"><i class="bi bi-file-earmark-binary-fill"></i></span>
          <span class="nav-label">Analiza DDD</span>
        </a>
      </div>
    </div>

    <!-- ── Raporty ───────────────────────────────────────── -->
    <div class="nav-group <?= $anyActive(['/reports']) ? 'open' : '' ?>" data-group="reports">
      <button class="nav-group-toggle" type="button" aria-expanded="<?= $anyActive(['/reports']) ? 'true' : 'false' ?>">
        <span class="nav-icon"><i class="bi bi-bar-chart-fill"></i></span>
        <span class="nav-label">Raporty</span>
        <i class="bi bi-chevron-down nav-chevron"></i>
      </button>
      <div class="nav-group-items">
        <a class="nav-item nav-sub <?= $isActive('/reports/vacation') ?>" href="/reports/vacation">
          <span class="nav-icon"><i class="bi bi-calendar-check-fill"></i></span>
          <span class="nav-label">Urlopówka</span>
        </a>
        <a class="nav-item nav-sub <?= $isActive('/reports/delegation') ?>" href="/reports/delegation">
          <span class="nav-icon"><i class="bi bi-globe-europe-africa"></i></span>
          <span class="nav-label">Delegacja</span>
        </a>
      </div>
    </div>

    <!-- ── Administracja (admin + superadmin) ────────────── -->
    <?php if ($user && in_array($user['role'], ['superadmin', 'admin'], true)): ?>
    <?php $adminPaths = $user['role'] === 'superadmin' ? ['/companies', '/admin/licenses', '/admin/users'] : ['/admin/users']; ?>
    <div class="nav-group <?= $anyActive($adminPaths) ? 'open' : '' ?>" data-group="admin">
      <button class="nav-group-toggle" type="button" aria-expanded="<?= $anyActive($adminPaths) ? 'true' : 'false' ?>">
        <span class="nav-icon"><i class="bi bi-shield-lock-fill"></i></span>
        <span class="nav-label">Administracja</span>
        <i class="bi bi-chevron-down nav-chevron"></i>
      </button>
      <div class="nav-group-items">
        <?php if ($user['role'] === 'superadmin'): ?>
        <a class="nav-item nav-sub <?= $isActive('/companies') ?>" href="/companies">
          <span class="nav-icon"><i class="bi bi-building-fill"></i></span>
          <span class="nav-label">Firmy</span>
        </a>
        <a class="nav-item nav-sub <?= $isActive('/admin/licenses') ?>" href="/admin/licenses">
          <span class="nav-icon"><i class="bi bi-key-fill"></i></span>
          <span class="nav-label">Licencje</span>
        </a>
        <?php endif; ?>
        <a class="nav-item nav-sub <?= $isActive('/admin/users') ?>" href="/admin/users">
          <span class="nav-icon"><i class="bi bi-people-fill"></i></span>
          <span class="nav-label">Użytkownicy</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /.sidebar-nav -->

  <!-- User footer -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-user-avatar"><?= $userInitial ?></div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= htmlspecialchars($user['name'] ?? '') ?></div>
        <div class="sidebar-user-role"><?= htmlspecialchars($roleLabel) ?></div>
      </div>
      <a href="/logout" class="sidebar-logout-btn" title="Wyloguj się">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>

</nav><!-- /#sidebar -->

<!-- ═══════════════════ MAIN WRAPPER ═══════════════════ -->
<div class="main-wrapper">

  <!-- Topbar -->
  <header class="topbar">
    <div class="topbar-start">
      <!-- Mobile hamburger -->
      <button class="topbar-icon-btn d-lg-none" id="sidebarToggle" aria-label="Menu">
        <i class="bi bi-list"></i>
      </button>
      <!-- Desktop collapse (mirrors sidebar pin) -->
      <button class="topbar-icon-btn d-none d-lg-flex" id="sidebarCollapseBtn" aria-label="Toggle sidebar">
        <i class="bi bi-layout-sidebar-inset"></i>
      </button>
      <div class="topbar-divider d-none d-lg-block"></div>
      <h1 class="topbar-title"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
    </div>

    <div class="topbar-end">
      <?php if ($companyName): ?>
      <div class="topbar-company">
        <i class="bi bi-building me-1 opacity-50"></i><?= htmlspecialchars($companyName) ?>
      </div>
      <?php endif; ?>

      <div class="topbar-date">
        <i class="bi bi-calendar3 me-1 opacity-50"></i><?= date('d.m.Y') ?>
      </div>

      <!-- User dropdown -->
      <div class="dropdown">
        <button class="topbar-user-btn dropdown-toggle" type="button"
                data-bs-toggle="dropdown" aria-expanded="false">
          <div class="topbar-avatar"><?= $userInitial ?></div>
          <span class="topbar-user-name d-none d-md-block"><?= htmlspecialchars($user['name'] ?? '') ?></span>
          <i class="bi bi-chevron-down topbar-user-chevron d-none d-md-block"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end topbar-dropdown">
          <li class="dropdown-header-item">
            <div class="d-flex align-items-center gap-2 px-1">
              <div class="topbar-avatar topbar-avatar-lg"><?= $userInitial ?></div>
              <div>
                <div class="fw-semibold small"><?= htmlspecialchars($user['name'] ?? '') ?></div>
                <div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                <span class="badge badge-role mt-1"><?= htmlspecialchars($roleLabel) ?></span>
              </div>
            </div>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="/logout">
              <i class="bi bi-box-arrow-right me-2 text-danger"></i>Wyloguj się
            </a>
          </li>
        </ul>
      </div>
    </div>
  </header>

  <!-- Flash messages -->
  <?php $flash = \Core\Auth::getFlash(); if ($flash): ?>
  <div class="flash-wrapper px-4 pt-4">
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible d-flex align-items-center gap-2 mb-0" role="alert">
      <i class="bi bi-<?= $flash['type'] === 'error' ? 'exclamation-triangle-fill' : 'check-circle-fill' ?>"></i>
      <span><?= htmlspecialchars($flash['message']) ?></span>
      <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Page content -->
  <main class="main-content p-4">
    <?= $content ?? '' ?>
  </main>

</div><!-- /.main-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
