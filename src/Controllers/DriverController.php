<?php
declare(strict_types=1);
namespace Controllers;

use Core\Auth;
use Core\License;
use Models\Driver;

class DriverController
{
    private Driver $model;

    public function __construct() { $this->model = new Driver(); }

    public function index(array $params): void
    {
        Auth::requireAuth();
        $companyId = $this->companyId();
        $drivers   = $this->model->allForCompany($companyId);
        $flash     = Auth::getFlash();
        $pageTitle = 'Kierowcy';
        $content   = $this->render('drivers/index', compact('drivers'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function create(array $params): void
    {
        Auth::requireRole('admin', 'superadmin');
        $this->companyId(); // fail early with a clear message if no company context
        $flash     = Auth::getFlash();
        $pageTitle = 'Dodaj kierowcę';
        $content   = $this->render('drivers/create', ['driver' => null]);
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function store(array $params): void
    {
        Auth::requireRole('admin', 'superadmin');
        if (!Auth::validateCsrf()) { $this->back('/drivers/create'); return; }

        $cid = $this->companyId();
        if (!License::checkDriverLimit($cid)) {
            Auth::setFlash('error', 'Limit kierowców wyczerpany. Zaktualizuj licencję.');
            header('Location: /drivers'); exit;
        }

        $data = $this->postData();
        $errors = $this->validate($data);
        if ($errors) {
            Auth::setFlash('error', implode(' ', $errors));
            header('Location: /drivers/create'); exit;
        }

        $id = $this->model->create($cid, $data);
        Auth::log('driver_created', "Kierowca ID $id");
        Auth::setFlash('success', 'Kierowca dodany pomyślnie.');
        header("Location: /drivers/$id"); exit;
    }

    public function show(array $params): void
    {
        Auth::requireAuth();
        $driver = $this->findOr404((int)$params['id']);
        $files  = \Core\Database::fetchAll(
            'SELECT tf.*, (SELECT COUNT(*) FROM violations v WHERE v.tacho_file_id=tf.id) AS vio_count
             FROM tacho_files tf WHERE tf.driver_id=:did ORDER BY tf.created_at DESC',
            ['did' => $driver['id']]
        );
        $violations = (new \Models\Activity())->recentViolations($this->companyId(), 100);
        $violations = array_filter($violations, fn($v) => $v['driver_id'] == $driver['id']);
        $flash     = Auth::getFlash();
        $pageTitle = $driver['first_name'] . ' ' . $driver['last_name'];
        $content   = $this->render('drivers/show', compact('driver', 'files', 'violations'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function edit(array $params): void
    {
        Auth::requireRole('admin', 'superadmin');
        $driver    = $this->findOr404((int)$params['id']);
        $flash     = Auth::getFlash();
        $pageTitle = 'Edytuj kierowcę';
        $content   = $this->render('drivers/create', compact('driver'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function update(array $params): void
    {
        Auth::requireRole('admin', 'superadmin');
        if (!Auth::validateCsrf()) { $this->back('/drivers'); return; }
        $id = (int)$params['id'];
        $this->model->update($id, $this->companyId(), $this->postData());
        Auth::log('driver_updated', "Kierowca ID $id");
        Auth::setFlash('success', 'Dane zaktualizowane.');
        header("Location: /drivers/$id"); exit;
    }

    public function delete(array $params): void
    {
        Auth::requireRole('admin', 'superadmin');
        if (!Auth::validateCsrf()) { $this->back('/drivers'); return; }
        $id = (int)$params['id'];
        $this->model->delete($id, $this->companyId());
        Auth::log('driver_deleted', "Kierowca ID $id");
        Auth::setFlash('success', 'Kierowca usunięty.');
        header('Location: /drivers'); exit;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function companyId(): int
    {
        $cid = Auth::effectiveCompanyId();
        if (!$cid) {
            Auth::setFlash('error', 'Konto nie jest przypisane do żadnej firmy. Użyj konta firmowego lub wybierz firmę.');
            header('Location: /drivers');
            exit;
        }
        return $cid;
    }

    private function findOr404(int $id): array
    {
        $d = $this->model->find($id, $this->companyId());
        if (!$d) { http_response_code(404); exit('Kierowca nie znaleziony.'); }
        return $d;
    }

    private function postData(): array
    {
        return [
            'first_name'     => trim($_POST['first_name']     ?? ''),
            'last_name'      => trim($_POST['last_name']      ?? ''),
            'birth_date'     => $_POST['birth_date']           ?? null,
            'license_number' => trim($_POST['license_number'] ?? ''),
            'card_number'    => trim($_POST['card_number']    ?? ''),
            'card_expiry'    => $_POST['card_expiry']          ?? null,
            'nationality'    => trim($_POST['nationality']    ?? 'PL'),
            'phone'          => trim($_POST['phone']          ?? ''),
            'email'          => trim($_POST['email']          ?? ''),
            'notes'          => trim($_POST['notes']          ?? ''),
        ];
    }

    private function validate(array $d): array
    {
        $e = [];
        if (empty($d['first_name'])) $e[] = 'Imię jest wymagane.';
        if (empty($d['last_name']))  $e[] = 'Nazwisko jest wymagane.';
        return $e;
    }

    private function back(string $url): void
    {
        Auth::setFlash('error', 'Nieprawidłowy token bezpieczeństwa.');
        header("Location: $url"); exit;
    }

    private function render(string $view, array $data = []): string
    {
        extract($data);
        ob_start();
        require __DIR__ . '/../Views/' . $view . '.php';
        return ob_get_clean();
    }
}
