<?php
declare(strict_types=1);

/**
 * One-time installation wizard.
 *
 * Steps:
 *   1. Check PHP requirements
 *   2. Configure database & secret
 *   3. Create admin account
 *   4. Write .env and .installed marker
 */

// Prevent running after install
if (file_exists(__DIR__ . '/.installed')) {
    die('<p>System jest już zainstalowany. Usuń plik <code>.installed</code>, aby ponownie uruchomić konfigurację.</p>');
}

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// Autoloader
// ---------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'LicenseGenerator\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $file = SRC_PATH . '/' . substr($class, strlen($prefix)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use LicenseGenerator\Database;
use LicenseGenerator\Auth;

// ---------------------------------------------------------------------------
// Requirements check
// ---------------------------------------------------------------------------
$requirements = [
    'PHP ≥ 8.0'         => version_compare(PHP_VERSION, '8.0.0', '>='),
    'Rozszerzenie PDO'   => extension_loaded('pdo'),
    'PDO SQLite'         => extension_loaded('pdo_sqlite'),
    'JSON'               => extension_loaded('json'),
    'mbstring'           => extension_loaded('mbstring'),
    'Zapis do katalogu'  => is_writable(__DIR__),
];

$allOk = !in_array(false, $requirements, true);

// ---------------------------------------------------------------------------
// Handle POST
// ---------------------------------------------------------------------------
$step    = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2 && $allOk) {
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $secret    = trim($_POST['secret'] ?? '');
    $dbPath    = trim($_POST['db_path'] ?? (__DIR__ . '/database/licenses.db'));

    if ($username === '') {
        $errors[] = 'Nazwa użytkownika jest wymagana.';
    } elseif (!preg_match('/^[A-Za-z0-9_\-]{3,64}$/', $username)) {
        $errors[] = 'Nazwa użytkownika może mieć 3–64 znaki: litery, cyfry, _ i -.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Hasło musi mieć co najmniej 8 znaków.';
    }

    if ($password !== $password2) {
        $errors[] = 'Podane hasła nie są identyczne.';
    }

    if (strlen($secret) < 32) {
        $errors[] = 'Sekret licencji musi mieć co najmniej 32 znaki.';
    }

    if (empty($errors)) {
        // Write .env
        $envContent = <<<ENV
# TachoSystem – Generator Licencji – konfiguracja
DATABASE_PATH={$dbPath}
LICENSE_SECRET={$secret}
APP_DEBUG=false
ENV;
        file_put_contents(__DIR__ . '/.env', $envContent);

        // Reload config with new .env values
        putenv("DATABASE_PATH={$dbPath}");
        putenv("LICENSE_SECRET={$secret}");
        $_ENV['DATABASE_PATH'] = $dbPath;
        $_ENV['LICENSE_SECRET'] = $secret;

        // Re-define with new values
        define('DATABASE_PATH_SETUP', $dbPath);
        define('LICENSE_SECRET_SETUP', $secret);

        // Create database and admin user
        try {
            $db   = new Database($dbPath);
            $auth = new Auth($db);
            $auth->createUser($username, $password);

            // Write marker file
            file_put_contents(__DIR__ . '/.installed', date('c'));

            $success = true;
        } catch (\Throwable $e) {
            $errors[] = 'Błąd podczas tworzenia bazy danych: ' . $e->getMessage();
        }
    }
}

// Generate a suggested secret
$suggestedSecret = bin2hex(random_bytes(32));

?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalacja – <?= htmlspecialchars(APP_TITLE) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: linear-gradient(135deg, #1e2d40, #2d4560); min-height: 100vh; }
        .setup-card { max-width: 640px; border-radius: 1rem; box-shadow: 0 1rem 3rem rgba(0,0,0,.3); }
    </style>
</head>
<body class="d-flex justify-content-center align-items-start py-5 px-3">
<div class="card setup-card w-100 border-0 p-4">

    <div class="text-center mb-4">
        <i class="bi bi-shield-lock-fill fs-1 text-primary"></i>
        <h4 class="mt-2 fw-bold">Instalacja Generatora Licencji</h4>
        <p class="text-muted small">TachoSystem – jednorazowa konfiguracja</p>
    </div>

    <?php if ($success): ?>
        <!-- ── Step 3: Done ── -->
        <div class="alert alert-success text-center">
            <i class="bi bi-check-circle-fill fs-2 d-block mb-2"></i>
            <h5 class="fw-bold">Instalacja zakończona pomyślnie!</h5>
            <p class="mb-3">Konto administratora zostało utworzone, a plik <code>.env</code> zapisany.</p>
            <a href="/" class="btn btn-success px-4">
                <i class="bi bi-box-arrow-in-right me-2"></i>Przejdź do aplikacji
            </a>
        </div>
        <div class="alert alert-warning mt-3 small">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Ze względów bezpieczeństwa zaleca się usunięcie lub zablokowanie pliku
            <code>setup.php</code> na serwerze produkcyjnym.
        </div>

    <?php elseif ($step === 1): ?>
        <!-- ── Step 1: Requirements ── -->
        <h6 class="fw-semibold mb-3">Krok 1 – Wymagania systemowe</h6>
        <table class="table table-sm mb-4">
            <tbody>
                <?php foreach ($requirements as $label => $ok): ?>
                    <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td class="text-end">
                            <?php if ($ok): ?>
                                <span class="badge bg-success"><i class="bi bi-check2 me-1"></i>OK</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x me-1"></i>Brak</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($allOk): ?>
            <form method="POST">
                <input type="hidden" name="step" value="2">
                <button type="submit" class="btn btn-primary w-100">
                    Dalej <i class="bi bi-arrow-right ms-2"></i>
                </button>
            </form>
        <?php else: ?>
            <div class="alert alert-danger">
                Nie spełniono wszystkich wymagań. Uzupełnij brakujące rozszerzenia PHP i odśwież stronę.
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ── Step 2: Configuration ── -->
        <h6 class="fw-semibold mb-3">Krok 2 – Konfiguracja</h6>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="step" value="2">

            <h6 class="text-muted small text-uppercase mt-2 mb-2">Konto administratora</h6>
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold">Nazwa użytkownika</label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>"
                       required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label fw-semibold">Hasło (min. 8 znaków)</label>
                <input type="password" id="password" name="password" class="form-control"
                       autocomplete="new-password" required>
            </div>
            <div class="mb-4">
                <label for="password2" class="form-label fw-semibold">Powtórz hasło</label>
                <input type="password" id="password2" name="password2" class="form-control"
                       autocomplete="new-password" required>
            </div>

            <h6 class="text-muted small text-uppercase mt-2 mb-2">Sekret licencji</h6>
            <div class="mb-3">
                <label for="secret" class="form-label fw-semibold">
                    LICENSE_SECRET <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <input type="text" id="secret" name="secret" class="form-control font-monospace"
                           value="<?= htmlspecialchars($_POST['secret'] ?? $suggestedSecret) ?>"
                           required minlength="32">
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="document.getElementById('secret').value = '<?= bin2hex(random_bytes(32)) ?>'">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </div>
                <div class="form-text">
                    Ten sekret musi być identyczny z kluczem <code>LICENSE_SECRET</code>
                    skonfigurowanym w głównym systemie TachoSystem.
                    Przechowuj go w bezpiecznym miejscu – zmiana uniemożliwi weryfikację istniejących licencji.
                </div>
            </div>

            <div class="mb-4">
                <label for="db_path" class="form-label fw-semibold">Ścieżka do bazy danych</label>
                <input type="text" id="db_path" name="db_path" class="form-control font-monospace"
                       value="<?= htmlspecialchars($_POST['db_path'] ?? (__DIR__ . '/database/licenses.db')) ?>">
                <div class="form-text">Ścieżka bezwzględna do pliku SQLite.</div>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-check2-circle me-2"></i>Zainstaluj
            </button>
        </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmFEpFCFhbHVXedSvMkIxRGgDfHN"
        crossorigin="anonymous"></script>
</body>
</html>
