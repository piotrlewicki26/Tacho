<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? 'Login') ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background: #0f1117; min-height: 100vh; }
    .auth-card { max-width: 420px; background: #1a1d27; border: 1px solid #2d3250; border-radius: 12px; }
    .brand-logo { font-size: 2rem; font-weight: 800; letter-spacing: -1px; }
    .brand-logo span { color: #4f8ef7; }
  </style>
</head>
<body class="d-flex align-items-center justify-content-center">
  <div class="auth-card shadow-lg p-4 p-md-5 w-100 mx-3">
    <div class="text-center mb-4">
      <div class="brand-logo text-white">Tacho<span>System</span></div>
      <p class="text-muted mt-1 mb-0 small">System kontroli czasu pracy kierowcy</p>
    </div>
    <?php if (isset($flash) && $flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?> alert-dismissible" role="alert">
      <?= htmlspecialchars($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?= $content ?? '' ?>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
