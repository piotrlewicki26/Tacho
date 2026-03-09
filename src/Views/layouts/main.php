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

$matchesPath = function(string $p) use ($currentPath): bool {
    return $p === '/' ? $currentPath === '/' : str_starts_with($currentPath, $p);
};
$isActive    = function(string $p) use ($matchesPath): string {
    return $matchesPath($p) ? 'active' : '';
};
$anyActive   = function(array $paths) use ($matchesPath): bool {
    foreach ($paths as $p) {
        if ($matchesPath($p)) return true;
    }
    return false;
};

$companyName = '';
if ($companyId) {
    $co = \Core\Database::fetchOne('SELECT name FROM companies WHERE id=:id', ['id' => $companyId]);
    if ($co) $companyName = $co['name'];
}

$roleLabels  = ['superadmin' => 'Super Admin', 'admin' => 'Administrator', 'operator' => 'Operator'];
$roleLabel   = $roleLabels[$user['role'] ?? ''] ?? ($user['role'] ?? '');
$userInitial = strtoupper(substr($user['name'] ?? 'U', 0, 1));
?>

<!-- ═══════════════════ TOP NAVBAR ═══════════════════ -->
<header class="top-navbar" id="topNavbar">
  <div class="navbar-inner">

    <!-- Brand -->
    <a href="/" class="navbar-brand-link">
      <div class="navbar-brand-icon"><i class="bi bi-speedometer2"></i></div>
      <span class="navbar-brand-text">Tacho<strong>System</strong></span>
    </a>

    <!-- Divider -->
    <div class="navbar-vr d-none d-lg-block"></div>

    <!-- ── Navigation items ── -->
    <nav class="navbar-menu" id="navbarMenu" aria-label="Główna nawigacja">

      <!-- Dashboard -->
      <a class="nav-link <?= $isActive('/') ?>" href="/">
        <i class="bi bi-grid-1x2-fill nav-icon"></i>
        <span>Dashboard</span>
      </a>

      <!-- Fleet dropdown -->
      <div class="nav-dropdown <?= $anyActive(['/drivers','/vehicles']) ? 'active' : '' ?>">
        <button class="nav-link nav-dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">
          <i class="bi bi-diagram-3-fill nav-icon"></i>
          <span>Flota</span>
          <i class="bi bi-chevron-down nav-caret"></i>
        </button>
        <div class="nav-dropdown-menu">
          <div class="nav-dropdown-section-label">Zarządzanie</div>
          <a class="nav-dropdown-item <?= $isActive('/drivers') ?>" href="/drivers">
            <i class="bi bi-person-badge-fill text-primary"></i>
            <div><div class="nav-dropdown-item-title">Kierowcy</div><div class="nav-dropdown-item-sub">Lista i profile</div></div>
          </a>
          <a class="nav-dropdown-item <?= $isActive('/vehicles') ?>" href="/vehicles">
            <i class="bi bi-truck-front-fill text-warning"></i>
            <div><div class="nav-dropdown-item-title">Pojazdy</div><div class="nav-dropdown-item-sub">Flota i tachografy</div></div>
          </a>
        </div>
      </div>

      <!-- Tachograph dropdown -->
      <div class="nav-dropdown <?= $anyActive(['/analysis']) ? 'active' : '' ?>">
        <button class="nav-link nav-dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">
          <i class="bi bi-hdd-fill nav-icon"></i>
          <span>Tachograf</span>
          <i class="bi bi-chevron-down nav-caret"></i>
        </button>
        <div class="nav-dropdown-menu">
          <div class="nav-dropdown-section-label">Analiza danych</div>
          <a class="nav-dropdown-item <?= $isActive('/analysis') ?>" href="/analysis">
            <i class="bi bi-file-earmark-binary-fill text-info"></i>
            <div><div class="nav-dropdown-item-title">Analiza DDD</div><div class="nav-dropdown-item-sub">Wczytaj i analizuj pliki</div></div>
          </a>
        </div>
      </div>

      <!-- Reports dropdown -->
      <div class="nav-dropdown <?= $anyActive(['/reports']) ? 'active' : '' ?>">
        <button class="nav-link nav-dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">
          <i class="bi bi-bar-chart-fill nav-icon"></i>
          <span>Raporty</span>
          <i class="bi bi-chevron-down nav-caret"></i>
        </button>
        <div class="nav-dropdown-menu">
          <div class="nav-dropdown-section-label">Dokumenty</div>
          <a class="nav-dropdown-item <?= $isActive('/reports/vacation') ?>" href="/reports/vacation">
            <i class="bi bi-calendar-check-fill text-success"></i>
            <div><div class="nav-dropdown-item-title">Urlopówka</div><div class="nav-dropdown-item-sub">Ewidencja urlopów</div></div>
          </a>
          <a class="nav-dropdown-item <?= $isActive('/reports/delegation') ?>" href="/reports/delegation">
            <i class="bi bi-globe-europe-africa text-primary"></i>
            <div><div class="nav-dropdown-item-title">Delegacja</div><div class="nav-dropdown-item-sub">Rozliczenia wyjazdów</div></div>
          </a>
        </div>
      </div>

      <!-- Admin dropdown (admin + superadmin) -->
      <?php if ($user && in_array($user['role'], ['superadmin', 'admin'], true)): ?>
      <?php $adminPaths = $user['role'] === 'superadmin' ? ['/companies','/admin/licenses','/admin/users'] : ['/admin/users']; ?>
      <div class="nav-dropdown <?= $anyActive($adminPaths) ? 'active' : '' ?>">
        <button class="nav-link nav-dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">
          <i class="bi bi-shield-lock-fill nav-icon"></i>
          <span>Administracja</span>
          <i class="bi bi-chevron-down nav-caret"></i>
        </button>
        <div class="nav-dropdown-menu">
          <div class="nav-dropdown-section-label">Panel admin</div>
          <?php if ($user['role'] === 'superadmin'): ?>
          <a class="nav-dropdown-item <?= $isActive('/companies') ?>" href="/companies">
            <i class="bi bi-building-fill text-secondary"></i>
            <div><div class="nav-dropdown-item-title">Firmy</div><div class="nav-dropdown-item-sub">Zarządzaj firmami</div></div>
          </a>
          <a class="nav-dropdown-item <?= $isActive('/admin/licenses') ?>" href="/admin/licenses">
            <i class="bi bi-patch-check-fill text-success"></i>
            <div><div class="nav-dropdown-item-title">Aktywacja</div><div class="nav-dropdown-item-sub">Licencje firm</div></div>
          </a>
          <?php endif; ?>
          <a class="nav-dropdown-item <?= $isActive('/admin/users') ?>" href="/admin/users">
            <i class="bi bi-people-fill text-primary"></i>
            <div><div class="nav-dropdown-item-title">Użytkownicy</div><div class="nav-dropdown-item-sub">Konta i uprawnienia</div></div>
          </a>
        </div>
      </div>
      <?php endif; ?>

    </nav><!-- /.navbar-menu -->

    <!-- ── Right section ── -->
    <div class="navbar-right">

      <?php if ($companyName): ?>
      <div class="navbar-company-badge d-none d-xl-flex">
        <i class="bi bi-building-fill"></i>
        <span><?= htmlspecialchars($companyName) ?></span>
      </div>
      <?php endif; ?>

      <div class="navbar-date d-none d-lg-flex">
        <i class="bi bi-calendar3"></i>
        <span><?= date('d.m.Y') ?></span>
      </div>

      <!-- User dropdown -->
      <div class="nav-dropdown nav-dropdown-right">
        <button class="navbar-user-btn nav-dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">
          <div class="navbar-avatar"><?= $userInitial ?></div>
          <span class="navbar-user-name d-none d-lg-block"><?= htmlspecialchars($user['name'] ?? '') ?></span>
          <i class="bi bi-chevron-down nav-caret d-none d-lg-block"></i>
        </button>
        <div class="nav-dropdown-menu nav-dropdown-menu-end">
          <div class="nav-dropdown-header">
            <div class="navbar-avatar navbar-avatar-lg"><?= $userInitial ?></div>
            <div>
              <div class="fw-semibold small"><?= htmlspecialchars($user['name'] ?? '') ?></div>
              <div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($user['email'] ?? '') ?></div>
              <span class="badge-role mt-1 d-inline-block"><?= htmlspecialchars($roleLabel) ?></span>
            </div>
          </div>
          <div class="nav-dropdown-divider"></div>
          <a class="nav-dropdown-item text-danger-hover" href="/logout">
            <i class="bi bi-box-arrow-right text-danger"></i>
            <div><div class="nav-dropdown-item-title">Wyloguj się</div></div>
          </a>
        </div>
      </div>

      <!-- Mobile toggle -->
      <button class="navbar-mobile-btn" id="navbarMobileToggle" aria-label="Menu" aria-expanded="false" aria-controls="navbarMenu">
        <i class="bi bi-list"></i>
      </button>

    </div><!-- /.navbar-right -->
  </div><!-- /.navbar-inner -->
</header><!-- /#topNavbar -->

<!-- ═══════════════════ PAGE WRAPPER ═══════════════════ -->
<div class="page-wrapper">

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

</div><!-- /.page-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
