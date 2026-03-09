<?php
declare(strict_types=1);
namespace Controllers;

use Core\Auth;
use Core\Database;
use Models\Activity;
use Models\Driver;
use Models\Vehicle;

class DashboardController
{
    public function index(array $params): void
    {
        Auth::requireAuth();
        $companyId = Auth::companyId();

        // KPI stats
        $stats = [
            'drivers'    => 0,
            'vehicles'   => 0,
            'files'      => 0,
            'violations' => 0,
        ];

        if ($companyId) {
            $stats['drivers']    = (int) Database::fetchColumn('SELECT COUNT(*) FROM drivers WHERE company_id=:c AND is_active=1', ['c' => $companyId]);
            $stats['vehicles']   = (int) Database::fetchColumn('SELECT COUNT(*) FROM vehicles WHERE company_id=:c AND is_active=1', ['c' => $companyId]);
            $stats['files']      = (int) Database::fetchColumn('SELECT COUNT(*) FROM tacho_files WHERE company_id=:c', ['c' => $companyId]);
            $stats['violations'] = (int) Database::fetchColumn(
                'SELECT COUNT(*) FROM violations v JOIN drivers d ON d.id=v.driver_id WHERE d.company_id=:c',
                ['c' => $companyId]
            );
        } else {
            // Superadmin – show totals across all companies
            $stats['drivers']    = (int) Database::fetchColumn('SELECT COUNT(*) FROM drivers WHERE is_active=1');
            $stats['vehicles']   = (int) Database::fetchColumn('SELECT COUNT(*) FROM vehicles WHERE is_active=1');
            $stats['files']      = (int) Database::fetchColumn('SELECT COUNT(*) FROM tacho_files');
            $stats['violations'] = (int) Database::fetchColumn('SELECT COUNT(*) FROM violations');
        }

        // Recent violations
        $recentViolations = $companyId
            ? (new Activity())->recentViolations($companyId)
            : Database::fetchAll(
                'SELECT v.*, CONCAT(d.first_name," ",d.last_name) AS driver_name
                 FROM violations v JOIN drivers d ON d.id=v.driver_id
                 ORDER BY v.created_at DESC LIMIT 10'
            );

        // Recent files
        $recentFiles = $companyId
            ? (new \Models\TachoFile())->allForCompany($companyId, 5)
            : Database::fetchAll(
                'SELECT tf.*, CONCAT(d.first_name," ",d.last_name) AS driver_name
                 FROM tacho_files tf LEFT JOIN drivers d ON d.id=tf.driver_id
                 ORDER BY tf.created_at DESC LIMIT 5'
            );

        // Driver list
        $drivers = $companyId
            ? (new Driver())->allForCompany($companyId)
            : Database::fetchAll('SELECT d.*, c.name AS company_name FROM drivers d JOIN companies c ON c.id=d.company_id WHERE d.is_active=1 ORDER BY d.last_name LIMIT 20');

        // Vehicle list
        $vehicles = $companyId
            ? (new Vehicle())->allForCompany($companyId)
            : Database::fetchAll('SELECT v.*, c.name AS company_name FROM vehicles v JOIN companies c ON c.id=v.company_id WHERE v.is_active=1 ORDER BY v.registration LIMIT 20');

        // Chart data: last 7 days activity summary
        $chartData = $this->getChartData($companyId);

        $pageTitle = 'Dashboard';
        $flash     = Auth::getFlash();
        $content   = $this->render('dashboard/index', compact('stats','recentViolations','recentFiles','drivers','vehicles','chartData'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    private function getChartData(?int $companyId): array
    {
        $labels   = [];
        $driving  = [];
        $rest     = [];
        $work     = [];

        for ($i = 6; $i >= 0; $i--) {
            $date     = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('d.m', strtotime($date));

            $cond   = $companyId ? 'AND d.company_id = ' . (int)$companyId : '';
            $dMin   = (int) Database::fetchColumn(
                "SELECT COALESCE(SUM(a.duration_minutes),0) FROM activities a
                 JOIN drivers d ON d.id=a.driver_id
                 WHERE a.activity_date=:dt AND a.activity_type='driving' $cond",
                ['dt' => $date]
            );
            $rMin   = (int) Database::fetchColumn(
                "SELECT COALESCE(SUM(a.duration_minutes),0) FROM activities a
                 JOIN drivers d ON d.id=a.driver_id
                 WHERE a.activity_date=:dt AND a.activity_type IN ('rest','break') $cond",
                ['dt' => $date]
            );
            $wMin   = (int) Database::fetchColumn(
                "SELECT COALESCE(SUM(a.duration_minutes),0) FROM activities a
                 JOIN drivers d ON d.id=a.driver_id
                 WHERE a.activity_date=:dt AND a.activity_type='work' $cond",
                ['dt' => $date]
            );

            $driving[] = round($dMin / 60, 1);
            $rest[]    = round($rMin / 60, 1);
            $work[]    = round($wMin / 60, 1);
        }

        return compact('labels', 'driving', 'rest', 'work');
    }

    private function render(string $view, array $data = []): string
    {
        extract($data);
        ob_start();
        require __DIR__ . '/../Views/' . $view . '.php';
        return ob_get_clean();
    }
}
