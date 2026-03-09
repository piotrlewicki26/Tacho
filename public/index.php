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

// ── Router ─────────────────────────────────────────────────────────────────
$router = new Core\Router();

$router->addMiddleware('auth', static function () {
    Core\Auth::requireAuth();
});

// ── Routes ─────────────────────────────────────────────────────────────────

// Auth
$router->get('/login',  'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

// Dashboard
$router->get('/', 'DashboardController@index', ['auth']);

// Drivers
$router->get('/drivers',              'DriverController@index',  ['auth']);
$router->get('/drivers/create',       'DriverController@create', ['auth']);
$router->post('/drivers',             'DriverController@store',  ['auth']);
$router->get('/drivers/{id}',         'DriverController@show',   ['auth']);
$router->get('/drivers/{id}/edit',    'DriverController@edit',   ['auth']);
$router->post('/drivers/{id}',        'DriverController@update', ['auth']);
$router->post('/drivers/{id}/delete', 'DriverController@delete', ['auth']);

// Vehicles
$router->get('/vehicles',               'VehicleController@index',  ['auth']);
$router->get('/vehicles/create',        'VehicleController@create', ['auth']);
$router->post('/vehicles',              'VehicleController@store',  ['auth']);
$router->get('/vehicles/{id}/edit',     'VehicleController@edit',   ['auth']);
$router->post('/vehicles/{id}',         'VehicleController@update', ['auth']);
$router->post('/vehicles/{id}/delete',  'VehicleController@delete', ['auth']);

// Analysis
$router->get('/analysis',             'AnalysisController@index',  ['auth']);
$router->post('/analysis/upload',     'AnalysisController@upload', ['auth']);
$router->get('/analysis/{id}/daily',  'AnalysisController@daily',  ['auth']);
$router->get('/analysis/{id}/weekly', 'AnalysisController@weekly', ['auth']);

// Reports
$router->get('/reports/vacation',   'ReportController@vacation',   ['auth']);
$router->get('/reports/delegation', 'ReportController@delegation', ['auth']);

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
