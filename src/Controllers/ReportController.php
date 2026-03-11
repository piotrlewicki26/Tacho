<?php
declare(strict_types=1);
namespace Controllers;

use Core\Auth;
use Core\License;
use Core\Database;
use Models\Driver;
use Models\Activity;

class ReportController
{
    public function vacation(array $params): void
    {
        Auth::requireAuth();
        $cid = Auth::companyId();
        if (!License::isModuleAllowed($cid ?? 0, 'vacation') && !License::isModuleAllowed($cid ?? 0, 'reports')) {
            Auth::setFlash('error', 'Brak licencji na moduł urlopówek.');
            header('Location: /'); exit;
        }

        $drivers    = (new Driver())->allForCompany($cid);
        $driverId   = (int)($_GET['driver_id'] ?? 0);
        $dateFrom   = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo     = $_GET['date_to']   ?? date('Y-m-t');
        $driver     = $driverId ? (new Driver())->find($driverId, $cid) : null;
        $activities = [];
        $totals     = [];

        if ($driver && $dateFrom && $dateTo) {
            $activities = (new Activity())->forRange($driverId, $dateFrom, $dateTo);
            $totals     = ['driving' => 0, 'work' => 0, 'availability' => 0, 'rest' => 0, 'break' => 0];
            foreach ($activities as $a) {
                $totals[$a['activity_type']] += (int)$a['duration_minutes'];
            }
        }

        $company   = $cid ? Database::fetchOne('SELECT * FROM companies WHERE id=:id', ['id' => $cid]) : null;
        $pageTitle = 'Urlopówka';
        $isPrint   = !empty($_GET['print']);
        $flash     = Auth::getFlash();

        if ($isPrint) {
            require __DIR__ . '/../Views/reports/vacation.php';
        } else {
            $content = $this->render('reports/vacation', compact('drivers','driver','dateFrom','dateTo','activities','totals','company'));
            require __DIR__ . '/../Views/layouts/main.php';
        }
    }

    public function delegation(array $params): void
    {
        Auth::requireAuth();
        $cid = Auth::effectiveCompanyId() ?? Auth::companyId(); // effectiveCompanyId() honours superadmin's viewed-company selection; falls back to logged-in user's company
        if (!Auth::isSuperAdmin() && !License::isModuleAllowed($cid ?? 0, 'delegation') && !License::isModuleAllowed($cid ?? 0, 'reports')) {
            Auth::setFlash('error', 'Brak licencji na moduł delegacji.');
            header('Location: /'); exit;
        }

        $drivers   = $cid ? (new Driver())->allForCompany($cid) : [];
        $pageTitle = 'Delegacja – Pakiet Mobilności UE';
        $flash     = Auth::getFlash();
        $content   = $this->render('reports/delegation', compact('drivers'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    private function render(string $view, array $data = []): string
    {
        extract($data);
        ob_start();
        require __DIR__ . '/../Views/' . $view . '.php';
        return ob_get_clean();
    }
}
