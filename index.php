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
    global $auth;   // make the Auth instance available to layout.php

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

// ── JSON API – verify license (used by TachoSystem for online check) ───────
// Accepts GET or POST.  Authenticate with:
//   • Authorization: Bearer <API_KEY>  header, or
//   • ?api_key=<API_KEY>  query parameter.
// Returns JSON: {valid, message} and, on success, {modules, valid_from,
// valid_to, max_operators, max_drivers, hardware_id}.
if ($path === '/api/verify') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    // Reject if no API key is configured on this server.
    $configuredKey = API_KEY;
    if ($configuredKey === '') {
        http_response_code(503);
        echo json_encode(['valid' => false, 'error' => 'API key is not configured on the license server.']);
        exit;
    }

    // Extract the provided key from Authorization header or query/body param.
    $provided = '';
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (str_starts_with($authHeader, 'Bearer ')) {
        $provided = substr($authHeader, 7);
    } else {
        $provided = trim((string)($_GET['api_key'] ?? $_POST['api_key'] ?? ''));
    }

    if (!hash_equals($configuredKey, $provided)) {
        http_response_code(401);
        echo json_encode(['valid' => false, 'error' => 'Invalid or missing API key.']);
        exit;
    }

    // Validate required parameters.
    $licenseKey = trim((string)($_GET['license_key'] ?? $_POST['license_key'] ?? ''));
    $companyId  = trim((string)($_GET['company_id']  ?? $_POST['company_id']  ?? ''));

    if ($licenseKey === '' || $companyId === '') {
        http_response_code(400);
        echo json_encode(['valid' => false, 'error' => 'Parameters license_key and company_id are required.']);
        exit;
    }

    // Run three-layer verification.
    $result  = $licenseManager->verify($licenseKey, $companyId);
    $license = $result['license'] ?? null;

    $response = ['valid' => $result['valid'], 'message' => $result['message']];

    if ($result['valid'] && $license !== null) {
        $rawModules = json_decode((string)$license['modules'], true);
        if (!is_array($rawModules)) {
            http_response_code(500);
            echo json_encode(['valid' => false, 'error' => 'License record contains corrupted module data.']);
            exit;
        }
        $response['modules']       = $rawModules;
        $response['valid_from']    = $license['valid_from'];
        $response['valid_to']      = $license['valid_to'];
        $response['max_operators'] = (int)$license['max_operators'];
        $response['max_drivers']   = (int)$license['max_drivers'];
        $response['hardware_id']   = $license['hardware_id'];
    }

    http_response_code($result['valid'] ? 200 : 403);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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

    $companyId      = trim((string)($_POST['company_id']       ?? ''));
    $companyName    = trim((string)($_POST['company_name']     ?? ''));
    $modules        = (array)($_POST['modules']                ?? ['all']);
    $maxOperators   = (int)($_POST['max_operators']            ?? 5);
    $maxDrivers     = (int)($_POST['max_drivers']              ?? 50);
    $validFrom      = trim((string)($_POST['valid_from']       ?? date('Y-m-d')));
    $validTo        = trim((string)($_POST['valid_to']         ?? date('Y-m-d', strtotime('+1 year'))));
    $hardwareId     = trim((string)($_POST['hardware_id']      ?? ''));
    $notes          = trim((string)($_POST['notes']            ?? ''));
    $licenseSecret  = trim((string)($_POST['license_secret']   ?? ''));

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

    if ($validTo === '' || $validTo <= $validFrom) {
        $errors[] = 'Data końca ważności musi być późniejsza niż data początku.';
    } elseif ($validFrom < '2020-01-01') {
        $errors[] = 'Data początku ważności musi być nie wcześniejsza niż 2020-01-01 (wymaganie formatu klucza).';
    } elseif ($validTo > '2064-11-08') {
        $errors[] = 'Data końca ważności nie może przekraczać 2064-11-08 (limit formatu klucza).';
    }

    // The secret must be provided from the TachoSystem (48 hex characters = 24 bytes).
    if ($licenseSecret === '') {
        $errors[] = 'Sekret licencji jest wymagany. Skopiuj go z ustawień firmy w systemie TachoSystem.';
    } elseif (!preg_match('/^[0-9a-fA-F]{48}$/', $licenseSecret)) {
        $errors[] = 'Sekret licencji musi mieć dokładnie 48 znaków szesnastkowych (litery a-f i cyfry 0-9).';
    }

    $input = compact('companyId', 'companyName', 'modules', 'maxOperators', 'maxDrivers', 'validFrom', 'validTo', 'hardwareId', 'notes', 'licenseSecret');

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

    try {
        // Use the secret provided from the TachoSystem for this company.
        $license = $licenseManager->generate($licenseData, $auth->userId(), $licenseSecret);
    } catch (\Throwable $e) {
        $errors[] = 'Błąd generowania licencji: ' . $e->getMessage();
        renderView('generate', compact('errors', 'input'));
    }

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
    // Offline decode using the per-license secret stored in the DB record.
    $keyData      = null;
    $storedSecret = $result['license']['used_secret'] ?? '';
    if ($storedSecret !== '') {
        $keyData = $licenseManager->decodeKey($licenseKey, $storedSecret);
    }
    renderView('verify', compact('result', 'licenseKey', 'companyId', 'keyData'));
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
