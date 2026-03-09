<?php
declare(strict_types=1);
namespace Controllers;

use Core\Auth;
use Models\Vehicle;

class VehicleController
{
    private Vehicle $model;

    public function __construct() { $this->model = new Vehicle(); }

    public function index(array $params): void
    {
        Auth::requireAuth();
        $vehicles  = $this->model->allForCompany($this->companyId());
        $flash     = Auth::getFlash();
        $pageTitle = 'Pojazdy';
        $content   = $this->render('vehicles/index', compact('vehicles'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function create(array $params): void
    {
        Auth::requireRole('admin', 'superadmin');
        $this->companyId(); // fail early with a clear message if no company context
        $flash     = Auth::getFlash();
        $pageTitle = 'Dodaj pojazd';
        $content   = $this->render('vehicles/create', ['vehicle' => null]);
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function store(array $params): void
    {
        Auth::requireRole('admin', 'superadmin');
        if (!Auth::validateCsrf()) { header('Location: /vehicles/create'); exit; }
        $id = $this->model->create($this->companyId(), $this->postData());
        Auth::log('vehicle_created', "Pojazd ID $id");
        Auth::setFlash('success', 'Pojazd dodany.');
        header('Location: /vehicles'); exit;
    }

    public function edit(array $params): void
    {
        Auth::requireRole('admin', 'superadmin');
        $vehicle   = $this->findOr404((int)$params['id']);
        $flash     = Auth::getFlash();
        $pageTitle = 'Edytuj pojazd';
        $content   = $this->render('vehicles/create', compact('vehicle'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function update(array $params): void
    {
        Auth::requireRole('admin', 'superadmin');
        if (!Auth::validateCsrf()) { header('Location: /vehicles'); exit; }
        $id = (int)$params['id'];
        $this->model->update($id, $this->companyId(), $this->postData());
        Auth::log('vehicle_updated', "Pojazd ID $id");
        Auth::setFlash('success', 'Pojazd zaktualizowany.');
        header('Location: /vehicles'); exit;
    }

    public function delete(array $params): void
    {
        Auth::requireRole('admin', 'superadmin');
        if (!Auth::validateCsrf()) { header('Location: /vehicles'); exit; }
        $id = (int)$params['id'];
        $this->model->delete($id, $this->companyId());
        Auth::log('vehicle_deleted', "Pojazd ID $id");
        Auth::setFlash('success', 'Pojazd usunięty.');
        header('Location: /vehicles'); exit;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function companyId(): int
    {
        $cid = Auth::effectiveCompanyId();
        if (!$cid) {
            Auth::setFlash('error', 'Konto nie jest przypisane do żadnej firmy. Użyj konta firmowego lub wybierz firmę.');
            header('Location: /vehicles');
            exit;
        }
        return $cid;
    }

    private function findOr404(int $id): array
    {
        $v = $this->model->find($id, $this->companyId());
        if (!$v) { http_response_code(404); exit('Pojazd nie znaleziony.'); }
        return $v;
    }

    private function postData(): array
    {
        return [
            'registration'      => strtoupper(trim($_POST['registration']      ?? '')),
            'brand'             => trim($_POST['brand']             ?? ''),
            'model'             => trim($_POST['model']             ?? ''),
            'year'              => (int)($_POST['year']             ?? 0) ?: null,
            'vin'               => strtoupper(trim($_POST['vin']   ?? '')),
            'tachograph_serial' => trim($_POST['tachograph_serial'] ?? ''),
            'tachograph_type'   => trim($_POST['tachograph_type']   ?? ''),
            'notes'             => trim($_POST['notes']             ?? ''),
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
