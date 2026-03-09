<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// Autoloader  (flat src/ directory – no sub-namespaces)
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

// ---------------------------------------------------------------------------
// Check setup
// ---------------------------------------------------------------------------
if (!file_exists(__DIR__ . '/.installed') && basename($_SERVER['SCRIPT_NAME']) !== 'setup.php') {
    header('Location: setup.php');
    exit;
}

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------
session_name('tacho_licgen');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ---------------------------------------------------------------------------
// Bootstrap services
// ---------------------------------------------------------------------------
use LicenseGenerator\Database;
use LicenseGenerator\Auth;
use LicenseGenerator\LicenseManager;

$db             = new Database(DATABASE_PATH);
$auth           = new Auth($db);
$licenseManager = new LicenseManager($db);

// ---------------------------------------------------------------------------
// Routing helpers
// ---------------------------------------------------------------------------

/**
 * Render a view inside the main layout.
 * Login page renders as a standalone full-page layout.
 *
 * @param array<string,mixed> $data
 */
function renderView(string $view, array $data = []): never
{
    extract($data, EXTR_SKIP);

    if ($view === 'login') {
        require VIEWS_PATH . '/login.php';
    } else {
        ob_start();
        require VIEWS_PATH . "/{$view}.php";
        $content = ob_get_clean();
        require VIEWS_PATH . '/layout.php';
    }
    exit;
}

/**
 * Redirect to the given application path and stop execution.
 */
function redirect(string $path): never
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    header('Location: ' . $base . $path);
    exit;
}

// ---------------------------------------------------------------------------
// Resolve request path
// ---------------------------------------------------------------------------
$requestUri  = $_SERVER['REQUEST_URI'];
$scriptBase  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$parsedPath  = parse_url($requestUri, PHP_URL_PATH);
$path        = '/' . ltrim(substr((string)$parsedPath, strlen($scriptBase)), '/');
$path        = rtrim($path, '/') ?: '/';
$method      = strtoupper($_SERVER['REQUEST_METHOD']);

// Flash messages
$flashMsg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---------------------------------------------------------------------------
// Routes
// ---------------------------------------------------------------------------

// ── Login ──────────────────────────────────────────────────────────────────
if ($path === '/login' && $method === 'GET') {
    if ($auth->isLoggedIn()) {
        redirect('/');
    }
    renderView('login');
}

if ($path === '/login' && $method === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($auth->login($username, $password)) {
        redirect('/');
    }
    renderView('login', ['error' => 'Nieprawidłowa nazwa użytkownika lub hasło.']);
}

// ── Logout ─────────────────────────────────────────────────────────────────
if ($path === '/logout') {
    $auth->logout();
    redirect('/login');
}

// ── All routes below require authentication ────────────────────────────────
$auth->requireAuth();

// ── Dashboard ──────────────────────────────────────────────────────────────
if ($path === '/' && $method === 'GET') {
    $licenses = $licenseManager->getAll();
    $stats    = $licenseManager->getStats();
    renderView('dashboard', compact('licenses', 'stats', 'flashMsg'));
}

// ── Generate – form ────────────────────────────────────────────────────────
if ($path === '/generate' && $method === 'GET') {
    renderView('generate');
}

// ── Generate – submit ──────────────────────────────────────────────────────
if ($path === '/generate' && $method === 'POST') {
    $errors = [];

    $companyId    = trim((string)($_POST['company_id']    ?? ''));
    $companyName  = trim((string)($_POST['company_name']  ?? ''));
    $modules      = (array)($_POST['modules']             ?? ['all']);
    $maxOperators = (int)($_POST['max_operators']         ?? 5);
    $maxDrivers   = (int)($_POST['max_drivers']           ?? 50);
    $validFrom    = trim((string)($_POST['valid_from']    ?? date('Y-m-d')));
    $validTo      = trim((string)($_POST['valid_to']      ?? date('Y-m-d', strtotime('+1 year'))));
    $hardwareId   = trim((string)($_POST['hardware_id']   ?? ''));
    $notes        = trim((string)($_POST['notes']         ?? ''));

    if ($companyId === '') {
        $errors[] = 'ID firmy jest wymagane.';
    } elseif (!preg_match('/^[A-Za-z0-9_\-]+$/', $companyId)) {
        $errors[] = 'ID firmy może zawierać tylko litery, cyfry, myślniki i podkreślenia.';
    }

    if ($companyName === '') {
        $errors[] = 'Nazwa firmy jest wymagana.';
    }

    if (empty($modules)) {
        $errors[] = 'Wybierz przynajmniej jeden moduł.';
    }

    if ($maxOperators < 1 || $maxOperators > 9999) {
        $errors[] = 'Liczba operatorów musi być w zakresie 1–9999.';
    }

    if ($maxDrivers < 1 || $maxDrivers > 99999) {
        $errors[] = 'Liczba kierowców musi być w zakresie 1–99999.';
    }

    $today = date('Y-m-d');
    if ($validFrom === '' || $validFrom < $today) {
        // allow past valid_from for back-dating but warn
    }

    if ($validTo === '' || $validTo <= $validFrom) {
        $errors[] = 'Data końca ważności musi być późniejsza niż data początku.';
    }

    if (LICENSE_SECRET === '') {
        $errors[] = 'Brak skonfigurowanego sekretu (LICENSE_SECRET). Uzupełnij plik .env.';
    }

    $input = compact('companyId', 'companyName', 'modules', 'maxOperators', 'maxDrivers', 'validFrom', 'validTo', 'hardwareId', 'notes');

    if (!empty($errors)) {
        renderView('generate', compact('errors', 'input'));
    }

    $licenseData = [
        'company_id'    => $companyId,
        'company_name'  => $companyName,
        'modules'       => $modules,
        'max_operators' => $maxOperators,
        'max_drivers'   => $maxDrivers,
        'valid_from'    => $validFrom,
        'valid_to'      => $validTo,
        'hardware_id'   => $hardwareId,
        'notes'         => $notes,
    ];

    $license = $licenseManager->generate($licenseData, $auth->userId());
    renderView('generate', compact('license'));
}

// ── Verify – form ──────────────────────────────────────────────────────────
if ($path === '/verify' && $method === 'GET') {
    renderView('verify');
}

// ── Verify – submit ────────────────────────────────────────────────────────
if ($path === '/verify' && $method === 'POST') {
    $licenseKey = trim((string)($_POST['license_key'] ?? ''));
    $companyId  = trim((string)($_POST['company_id']  ?? ''));
    $result     = $licenseManager->verify($licenseKey, $companyId);
    renderView('verify', compact('result', 'licenseKey', 'companyId'));
}

// ── Toggle active state ────────────────────────────────────────────────────
if (preg_match('#^/license/(\d+)/(activate|deactivate)$#', $path, $m) && $method === 'POST') {
    $id     = (int)$m[1];
    $action = $m[2];
    if ($action === 'activate') {
        $licenseManager->activate($id);
        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Licencja została aktywowana.'];
    } else {
        $licenseManager->deactivate($id);
        $_SESSION['flash'] = ['type' => 'warning', 'text' => 'Licencja została dezaktywowana.'];
    }
    redirect('/');
}

// ── Delete ─────────────────────────────────────────────────────────────────
if (preg_match('#^/license/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    $licenseManager->delete((int)$m[1]);
    $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Licencja została usunięta.'];
    redirect('/');
}

// ── License detail ─────────────────────────────────────────────────────────
if (preg_match('#^/license/(\d+)$#', $path, $m) && $method === 'GET') {
    $license = $licenseManager->getById((int)$m[1]);
    if (!$license) {
        http_response_code(404);
        echo '<p>Licencja nie znaleziona.</p>';
        exit;
    }
    renderView('license_detail', compact('license'));
}

// ── 404 ────────────────────────────────────────────────────────────────────
http_response_code(404);
echo '<h1>404 – Strona nie znaleziona</h1>';
exit;
