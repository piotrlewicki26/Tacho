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

<!-- Sidebar -->
<nav id="sidebar" class="sidebar d-flex flex-column">
  <div class="sidebar-brand px-3 py-4">
    <a href="/" class="text-decoration-none">
      <span class="brand-text">Tacho<span class="text-primary">System</span></span>
    </a>
  </div>

  <?php
  $user = \Core\Auth::user();
  $companyId = \Core\Auth::companyId();
  $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
  $isActive = fn(string $p) => str_starts_with($currentPath, $p) ? 'active' : '';
  ?>

  <ul class="nav flex-column px-2 flex-grow-1">
    <li class="nav-item">
      <a class="nav-link <?= $currentPath === '/' ? 'active' : '' ?>" href="/">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
      </a>
    </li>

    <li class="nav-section-label mt-3">FLOTA</li>
    <li class="nav-item">
      <a class="nav-link <?= $isActive('/drivers') ?>" href="/drivers">
        <i class="bi bi-person-badge me-2"></i>Kierowcy
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $isActive('/vehicles') ?>" href="/vehicles">
        <i class="bi bi-truck me-2"></i>Pojazdy
      </a>
    </li>

    <li class="nav-section-label mt-3">TACHOGRAF</li>
    <li class="nav-item">
      <a class="nav-link <?= $isActive('/analysis') ?>" href="/analysis">
        <i class="bi bi-file-earmark-binary me-2"></i>Analiza DDD
      </a>
    </li>

    <li class="nav-section-label mt-3">RAPORTY</li>
    <li class="nav-item">
      <a class="nav-link <?= $isActive('/reports/vacation') ?>" href="/reports/vacation">
        <i class="bi bi-calendar-check me-2"></i>Urlopówka
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $isActive('/reports/delegation') ?>" href="/reports/delegation">
        <i class="bi bi-globe-europe-africa me-2"></i>Delegacja
      </a>
    </li>

    <?php if ($user && in_array($user['role'], ['superadmin', 'admin'], true)): ?>
    <li class="nav-section-label mt-3">ADMINISTRACJA</li>
    <?php if ($user['role'] === 'superadmin'): ?>
    <li class="nav-item">
      <a class="nav-link <?= $isActive('/companies') ?>" href="/companies">
        <i class="bi bi-building me-2"></i>Firmy
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $isActive('/admin/licenses') ?>" href="/admin/licenses">
        <i class="bi bi-key me-2"></i>Licencje
      </a>
    </li>
    <?php endif; ?>
    <li class="nav-item">
      <a class="nav-link <?= $isActive('/admin/users') ?>" href="/admin/users">
        <i class="bi bi-people me-2"></i>Użytkownicy
      </a>
    </li>
    <?php endif; ?>
  </ul>

  <div class="sidebar-footer px-3 py-3 border-top border-secondary">
    <div class="d-flex align-items-center gap-2">
      <div class="avatar-circle bg-primary text-white fw-bold">
        <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
      </div>
      <div class="flex-grow-1 overflow-hidden">
        <div class="fw-semibold text-truncate small"><?= htmlspecialchars($user['name'] ?? '') ?></div>
        <div class="text-muted" style="font-size:.7rem"><?= htmlspecialchars($user['role'] ?? '') ?></div>
      </div>
      <a href="/logout" class="btn btn-sm btn-outline-secondary" title="Wyloguj">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</nav>

<!-- Main -->
<div class="main-wrapper">
  <!-- Topbar -->
  <header class="topbar d-flex align-items-center px-4">
    <button class="btn btn-sm btn-link text-secondary sidebar-toggle me-3 d-lg-none" id="sidebarToggle">
      <i class="bi bi-list fs-5"></i>
    </button>
    <div class="fw-semibold text-white"><?= htmlspecialchars($pageTitle ?? '') ?></div>
    <div class="ms-auto d-flex align-items-center gap-3">
      <?php
      if ($companyId) {
        $co = \Core\Database::fetchOne('SELECT name FROM companies WHERE id=:id', ['id' => $companyId]);
        if ($co) echo '<span class="badge bg-secondary">' . htmlspecialchars($co['name']) . '</span>';
      }
      ?>
      <span class="text-muted small"><?= date('d.m.Y') ?></span>
    </div>
  </header>

  <!-- Content -->
  <main class="main-content p-4">
    <?php $flash = \Core\Auth::getFlash(); if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible d-flex align-items-center mb-4" role="alert">
      <i class="bi bi-<?= $flash['type'] === 'error' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
      <?= htmlspecialchars($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?= $content ?? '' ?>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
