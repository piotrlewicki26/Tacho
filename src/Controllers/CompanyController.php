<?php
declare(strict_types=1);
namespace Controllers;

use Core\Auth;
use Core\Database;
use Core\License;
use Models\Company;
use Models\User;

class CompanyController
{
    private Company $model;

    public function __construct() { $this->model = new Company(); }

    // ── Companies ─────────────────────────────────────────────────────────

    public function index(array $params): void
    {
        Auth::requireRole('superadmin');
        $companies = $this->model->all();
        $flash     = Auth::getFlash();
        $pageTitle = 'Firmy';
        $content   = $this->render('companies/index', compact('companies'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function create(array $params): void
    {
        Auth::requireRole('superadmin');
        $flash     = Auth::getFlash();
        $pageTitle = 'Dodaj firmę';
        $content   = $this->render('companies/create', ['company' => null]);
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function store(array $params): void
    {
        Auth::requireRole('superadmin');
        if (!Auth::validateCsrf()) { header('Location: /companies/create'); exit; }
        $id = $this->model->create($this->postData());
        Auth::log('company_created', "Firma ID $id");
        Auth::setFlash('success', 'Firma dodana.');
        header("Location: /companies/$id"); exit;
    }

    public function show(array $params): void
    {
        Auth::requireRole('superadmin');
        $company  = $this->findOr404((int)$params['id']);
        $users    = (new User())->allForCompany($company['id']);
        $license  = License::getActive($company['id']);
        $flash    = Auth::getFlash();
        $pageTitle = $company['name'];
        $content  = $this->render('companies/show', compact('company','users','license'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function edit(array $params): void
    {
        Auth::requireRole('superadmin');
        $company   = $this->findOr404((int)$params['id']);
        $flash     = Auth::getFlash();
        $pageTitle = 'Edytuj firmę';
        $content   = $this->render('companies/create', compact('company'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function update(array $params): void
    {
        Auth::requireRole('superadmin');
        if (!Auth::validateCsrf()) { header('Location: /companies'); exit; }
        $id = (int)$params['id'];
        $this->model->update($id, $this->postData());
        Auth::log('company_updated', "Firma ID $id");
        Auth::setFlash('success', 'Firma zaktualizowana.');
        header("Location: /companies/$id"); exit;
    }

    public function delete(array $params): void
    {
        Auth::requireRole('superadmin');
        if (!Auth::validateCsrf()) { header('Location: /companies'); exit; }
        $id = (int)$params['id'];
        $this->model->delete($id);
        Auth::log('company_deleted', "Firma ID $id");
        Auth::setFlash('success', 'Firma usunięta.');
        header('Location: /companies'); exit;
    }

    // ── Licenses ──────────────────────────────────────────────────────────

    public function licenses(array $params): void
    {
        Auth::requireRole('superadmin');
        $companies = $this->model->all();
        // Enrich each company with its active license summary
        foreach ($companies as &$c) {
            $c['active_license'] = License::getActive((int)$c['id']);
        }
        unset($c);
        $licenses  = Database::fetchAll(
            'SELECT l.*, c.name AS company_name
             FROM licenses l JOIN companies c ON c.id=l.company_id
             ORDER BY l.created_at DESC'
        );
        $flash     = Auth::getFlash();
        $pageTitle = 'Licencje';
        $content   = $this->render('admin/licenses', compact('companies','licenses'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function generateCompanySecret(array $params): void
    {
        Auth::requireRole('superadmin');
        if (!Auth::validateCsrf()) { header('Location: /admin/licenses'); exit; }

        $companyId = (int)$params['id'];
        $company   = $this->findOr404($companyId);

        $secret = License::generateSecret();
        $this->model->update($companyId, ['license_secret' => $secret]);

        Auth::log('license_secret_generated', "Nowy sekret dla firmy ID $companyId");
        Auth::setFlash('success', 'Nowy klucz SECRET wygenerowany dla: ' . $company['name']);
        header('Location: /admin/licenses'); exit;
    }

    public function activateLicense(array $params): void
    {
        Auth::requireRole('superadmin');
        if (!Auth::validateCsrf()) { header('Location: /admin/licenses'); exit; }

        $companyId    = (int)$params['id'];
        $company      = $this->findOr404($companyId);

        $licenseKey   = strtoupper(trim($_POST['license_key']    ?? ''));
        $modules      = $_POST['modules']                         ?? [];
        $maxOperators = (int)($_POST['max_operators']             ?? 5);
        $maxDrivers   = (int)($_POST['max_drivers']               ?? 50);
        $validFrom    = $_POST['valid_from']                      ?? date('Y-m-d');
        $validTo      = $_POST['valid_to']                        ?? date('Y-m-d', strtotime('+1 year'));
        $hardwareId   = trim($_POST['hardware_id']                ?? '') ?: null;

        if (strlen($licenseKey) > 25 || !$licenseKey || empty($modules)) {
            Auth::setFlash('error', 'Klucz licencji i co najmniej jeden moduł są wymagane.');
            header('Location: /admin/licenses'); exit;
        }

        // Verify key format
        if (!preg_match('/^TACHO(-[A-Z0-9]{4}){4}$/', $licenseKey)) {
            Auth::setFlash('error', 'Nieprawidłowy format klucza licencji (oczekiwano: TACHO-XXXX-XXXX-XXXX-XXXX).');
            header('Location: /admin/licenses'); exit;
        }

        // Deactivate any existing licenses for this company
        Database::update(
            'licenses', ['is_active' => 0],
            'company_id = :cid', ['cid' => $companyId]
        );

        $data               = License::buildFromKey($companyId, $licenseKey, $modules, $maxOperators, $maxDrivers, $validFrom, $validTo, $hardwareId);
        $data['company_id'] = $companyId;
        Database::insert('licenses', $data);

        Auth::log('license_activated', "Licencja aktywowana dla firmy $companyId: $licenseKey");
        Auth::setFlash('success', 'Licencja aktywowana dla firmy: ' . $company['name']);
        header('Location: /admin/licenses'); exit;
    }

    // ── Users ─────────────────────────────────────────────────────────────

    public function users(array $params): void
    {
        Auth::requireRole('superadmin', 'admin');
        $companyId = Auth::isSuperAdmin() ? null : Auth::companyId();
        $users     = (new User())->allForCompany($companyId);
        $flash     = Auth::getFlash();
        $pageTitle = 'Użytkownicy';
        $content   = $this->render('admin/users', compact('users'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function findOr404(int $id): array
    {
        $c = $this->model->find($id);
        if (!$c) { http_response_code(404); exit('Firma nie znaleziona.'); }
        return $c;
    }

    private function postData(): array
    {
        return [
            'name'    => trim($_POST['name']    ?? ''),
            'nip'     => trim($_POST['nip']     ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city'    => trim($_POST['city']    ?? ''),
            'country' => trim($_POST['country'] ?? 'Poland'),
            'phone'   => trim($_POST['phone']   ?? ''),
            'email'   => trim($_POST['email']   ?? ''),
        ];
    }

    private function render(string $view, array $data = []): string
    {
        extract($data);
        ob_start();
        require __DIR__ . '/../Views/' . $view . '.php';
        return ob_get_clean();
    }
}
