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

    public function generateLicense(array $params): void
    {
        Auth::requireRole('superadmin');
        if (!Auth::validateCsrf()) { header('Location: /admin/licenses'); exit; }

        $companyId    = (int)($_POST['company_id']    ?? 0);
        $modules      = $_POST['modules']             ?? [];
        $maxOperators = (int)($_POST['max_operators'] ?? 5);
        $maxDrivers   = (int)($_POST['max_drivers']   ?? 50);
        $validFrom    = $_POST['valid_from']           ?? date('Y-m-d');
        $validTo      = $_POST['valid_to']             ?? date('Y-m-d', strtotime('+1 year'));
        $hardwareId   = trim($_POST['hardware_id']     ?? '') ?: null;

        if (!$companyId || empty($modules)) {
            Auth::setFlash('error', 'Wybierz firmę i co najmniej jeden moduł.');
            header('Location: /admin/licenses'); exit;
        }

        $data = License::generate($companyId, $modules, $maxOperators, $maxDrivers, $validFrom, $validTo, $hardwareId);
        $data['company_id'] = $companyId;
        Database::insert('licenses', $data);

        Auth::log('license_generated', "Licencja dla firmy $companyId: {$data['license_key']}");
        Auth::setFlash('success', 'Licencja wygenerowana: ' . $data['license_key']);
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
