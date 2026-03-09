<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(APP_TITLE) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .sidebar { min-height: 100vh; background: #1e2d40; }
        .sidebar .nav-link { color: #a8b4c4; border-radius: .375rem; }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active { background: #2d4560; color: #fff; }
        .sidebar .brand { color: #fff; font-weight: 700; letter-spacing: .02em; }
        .main-content { min-height: 100vh; }
        .stat-card { border: none; border-left: 4px solid transparent; }
        .stat-card.total   { border-color: #6c757d; }
        .stat-card.active  { border-color: #198754; }
        .stat-card.expired { border-color: #dc3545; }
        .stat-card.inactive{ border-color: #fd7e14; }
        .license-key { font-family: 'Courier New', monospace; font-size: .9rem; letter-spacing: .05em; }
        .badge-module { font-size: .75rem; }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0">
        <!-- Sidebar -->
        <nav class="col-auto sidebar py-3 px-2 d-flex flex-column" style="width:220px">
            <a href="/" class="brand d-flex align-items-center gap-2 px-2 pb-3 mb-2 border-bottom border-secondary text-decoration-none">
                <i class="bi bi-shield-lock-fill fs-4 text-info"></i>
                <span class="fs-6">Generator<br>Licencji</span>
            </a>
            <?php
                $currentPath = rtrim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
                $scriptBase  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                $currentPath = '/' . ltrim(substr($currentPath, strlen($scriptBase)), '/');
            ?>
            <ul class="nav flex-column gap-1">
                <li class="nav-item">
                    <a class="nav-link<?= $currentPath === '/' ? ' active' : '' ?>"
                       href="/">
                        <i class="bi bi-grid-1x2 me-2"></i>Pulpit
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= str_starts_with($currentPath, '/generate') ? ' active' : '' ?>"
                       href="/generate">
                        <i class="bi bi-plus-circle me-2"></i>Generuj licencję
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= str_starts_with($currentPath, '/verify') ? ' active' : '' ?>"
                       href="/verify">
                        <i class="bi bi-patch-check me-2"></i>Weryfikuj klucz
                    </a>
                </li>
            </ul>
            <div class="mt-auto px-2 pt-3 border-top border-secondary">
                <small class="text-secondary d-block mb-1">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($auth->username() ?? '') ?>
                </small>
                <form method="POST" action="/logout">
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-box-arrow-right me-1"></i>Wyloguj
                    </button>
                </form>
            </div>
        </nav>

        <!-- Main content -->
        <div class="col main-content p-4">
            <?php if (!empty($flashMsg)): ?>
                <div class="alert alert-<?= htmlspecialchars($flashMsg['type']) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($flashMsg['text']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
<script>
/**
 * Copy `text` to the clipboard.
 * Works in both secure (HTTPS/localhost) and non-secure contexts.
 * Calls the optional `onSuccess` callback after a successful copy.
 */
function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity  = '0';
    document.body.appendChild(ta);
    ta.focus(); ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
}

function copyToClipboard(text, onSuccess) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
            .then(() => { if (onSuccess) onSuccess(); })
            .catch(() => { fallbackCopy(text); if (onSuccess) onSuccess(); });
    } else {
        fallbackCopy(text);
        if (onSuccess) onSuccess();
    }
}
</script>
</body>
</html>
