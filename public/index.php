<?php
declare(strict_types=1);

// ── Bootstrap ──────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/src/config/config.php';

// Session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── PSR-4-style Autoloader ─────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $map = [
        'Core\\'        => __DIR__ . '/../src/Core/',
        'Models\\'      => __DIR__ . '/../src/Models/',
        'Controllers\\' => __DIR__ . '/../src/Controllers/',
        'Parsers\\'     => __DIR__ . '/../src/Parsers/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . substr($class, strlen($prefix)) . '.php';
            if (is_file($file)) { require_once $file; return; }
        }
    }
});

// ── Setup redirect ─────────────────────────────────────────────────────────
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isSetupPage = str_starts_with($requestPath, '/setup');

if (!$isSetupPage && !file_exists(dirname(__DIR__) . '/.installed')) {
    try {
        $testPdo = new \PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME),
            DB_USER, DB_PASS,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 2]
        );
        $testPdo->query('SELECT 1 FROM users LIMIT 1');
    } catch (\Throwable $e) {
        header('Location: /setup.php');
        exit;
    }
}

// ── Database migrations (safe no-op on each boot) ──────────────────────────
if (!$isSetupPage) {
    try {
        $db = Core\Database::getInstance();
        $hasLicenseSecret = (bool) $db->query(
            "SELECT EXISTS(
               SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME   = 'companies'
                 AND COLUMN_NAME  = 'license_secret'
             )"
        )->fetchColumn();
        if (!$hasLicenseSecret) {
            $db->exec(
                "ALTER TABLE `companies`
                 ADD COLUMN `license_secret` VARCHAR(64) DEFAULT NULL
                 COMMENT 'Per-company HMAC secret for license verification'"
            );
        }
    } catch (\Throwable $e) {
        // DB not yet available (first-time setup) – skip silently
    }
}

// ── Router ─────────────────────────────────────────────────────────────────
$router = new Core\Router();

$router->addMiddleware('auth', static function () {
    Core\Auth::requireAuth();
});

$router->addMiddleware('license', static function () {
    Core\Auth::requireAuth();
    Core\Auth::requireActiveLicense();
});

// ── Routes ─────────────────────────────────────────────────────────────────

// Auth
$router->get('/login',  'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

// License required page (no license guard needed here to avoid redirect loops)
$router->get('/license-required', static function (array $p) {
    Core\Auth::requireAuth();
    $pageTitle = 'Licencja wymagana';
    $flash     = Core\Auth::getFlash();
    ob_start();
    require __DIR__ . '/../src/Views/license_required.php';
    $content = ob_get_clean();
    require __DIR__ . '/../src/Views/layouts/main.php';
}, ['auth']);

// Dashboard
$router->get('/', 'DashboardController@index', ['license']);

// Drivers
$router->get('/drivers',              'DriverController@index',  ['license']);
$router->get('/drivers/create',       'DriverController@create', ['license']);
$router->post('/drivers',             'DriverController@store',  ['license']);
$router->get('/drivers/{id}',         'DriverController@show',   ['license']);
$router->get('/drivers/{id}/edit',    'DriverController@edit',   ['license']);
$router->post('/drivers/{id}',        'DriverController@update', ['license']);
$router->post('/drivers/{id}/delete', 'DriverController@delete', ['license']);

// Vehicles
$router->get('/vehicles',               'VehicleController@index',  ['license']);
$router->get('/vehicles/create',        'VehicleController@create', ['license']);
$router->post('/vehicles',              'VehicleController@store',  ['license']);
$router->get('/vehicles/{id}/edit',     'VehicleController@edit',   ['license']);
$router->post('/vehicles/{id}',         'VehicleController@update', ['license']);
$router->post('/vehicles/{id}/delete',  'VehicleController@delete', ['license']);

// Analysis
$router->get('/analysis',             'AnalysisController@index',  ['license']);
$router->post('/analysis/upload',     'AnalysisController@upload', ['license']);
$router->get('/analysis/{id}/daily',  'AnalysisController@daily',  ['license']);
$router->get('/analysis/{id}/weekly', 'AnalysisController@weekly', ['license']);

// Reports
$router->get('/reports/vacation',   'ReportController@vacation',   ['license']);
$router->get('/reports/delegation', 'ReportController@delegation', ['license']);

// Companies (superadmin)
$router->get('/companies',               'CompanyController@index',  ['auth']);
$router->get('/companies/create',        'CompanyController@create', ['auth']);
$router->post('/companies',              'CompanyController@store',  ['auth']);
$router->get('/companies/{id}',          'CompanyController@show',   ['auth']);
$router->get('/companies/{id}/edit',     'CompanyController@edit',   ['auth']);
$router->post('/companies/{id}',         'CompanyController@update', ['auth']);
$router->post('/companies/{id}/delete',  'CompanyController@delete', ['auth']);

// Admin – Licenses
$router->get('/admin/licenses',                          'CompanyController@licenses',             ['auth']);
$router->post('/admin/licenses/{id}/generate-secret',   'CompanyController@generateCompanySecret', ['auth']);
$router->post('/admin/licenses/{id}/activate',          'CompanyController@activateLicense',       ['auth']);

// Admin – Users
$router->get('/admin/users',        'CompanyController@users',        ['auth']);
$router->get('/admin/users/create', 'AuthController@showRegister',    ['auth']);
$router->post('/admin/users/create','AuthController@register',        ['auth']);

$router->post('/admin/users/{id}/toggle', static function (array $p) {
    Core\Auth::requireRole('superadmin');
    (new Models\User())->toggleActive((int)$p['id']);
    Core\Auth::setFlash('success', 'Status zmieniony.');
    header('Location: /admin/users'); exit;
}, ['auth']);

$router->post('/admin/users/{id}/delete', static function (array $p) {
    Core\Auth::requireRole('superadmin');
    if (!Core\Auth::validateCsrf()) { header('Location: /admin/users'); exit; }
    (new Models\User())->delete((int)$p['id']);
    Core\Auth::log('user_deleted', 'ID '.$p['id']);
    Core\Auth::setFlash('success', 'Użytkownik usunięty.');
    header('Location: /admin/users'); exit;
}, ['auth']);

// ── 404 ────────────────────────────────────────────────────────────────────
$router->setNotFound(static function () {
    http_response_code(404);
    if (Core\Auth::check()) {
        $pageTitle = '404 – Nie znaleziono';
        $content   = '<div class="text-center py-5"><div class="display-1 text-muted fw-bold">404</div>'
                   . '<p class="lead">Strona nie istnieje.</p>'
                   . '<a href="/" class="btn btn-primary">Powrót do dashboardu</a></div>';
        require __DIR__ . '/../src/Views/layouts/main.php';
    } else {
        header('Location: /login'); exit;
    }
});

// ── Dispatch ───────────────────────────────────────────────────────────────
$router->dispatch();
