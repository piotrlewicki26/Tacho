<?php
declare(strict_types=1);
namespace Controllers;

use Core\Auth;
use Core\License;
use Models\Activity;
use Models\Driver;
use Models\TachoFile;
use Models\Vehicle;
use Parsers\DDDParser;

class AnalysisController
{
    public function index(array $params): void
    {
        Auth::requireAuth();
        $cid = Auth::companyId();
        if (!Auth::isSuperAdmin() && !License::isModuleAllowed($cid ?? 0, 'analysis')) {
            Auth::setFlash('error', 'Brak licencji na moduł analizy.');
            header('Location: /'); exit;
        }

        $files    = $cid ? (new TachoFile())->allForCompany($cid) : [];
        $drivers  = $cid ? (new Driver())->allForCompany($cid) : [];
        $vehicles = $cid ? (new Vehicle())->allForCompany($cid) : [];
        $flash     = Auth::getFlash();
        $pageTitle = 'Analiza plików DDD';
        $content   = $this->render('analysis/upload', compact('files', 'drivers', 'vehicles'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function upload(array $params): void
    {
        Auth::requireRole('admin', 'superadmin');
        if (!Auth::validateCsrf()) { header('Location: /analysis'); exit; }

        $cid = Auth::companyId();
        if (!$cid) {
            Auth::setFlash('error', 'Konto superadmin nie ma przypisanej firmy. Użyj konta firmowego do wgrania pliku DDD.');
            header('Location: /analysis'); exit;
        }

        if (!Auth::isSuperAdmin() && !License::isModuleAllowed($cid, 'analysis')) {
            Auth::setFlash('error', 'Brak licencji na moduł analizy.');
            header('Location: /'); exit;
        }

        if (empty($_FILES['ddd_file']['tmp_name'])) {
            Auth::setFlash('error', 'Nie wybrano pliku.');
            header('Location: /analysis'); exit;
        }

        $file     = $_FILES['ddd_file'];
        $origName = basename($file['name']);
        $size     = (int) $file['size'];

        if ($size > MAX_UPLOAD_SIZE) {
            Auth::setFlash('error', 'Plik jest zbyt duży (max 50 MB).');
            header('Location: /analysis'); exit;
        }

        // Validate MIME
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $allowedMimes = ['application/octet-stream', 'application/x-binary', 'application/binary', 'text/plain'];
        if (!in_array($mime, $allowedMimes, true)) {
            // Also allow by extension
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['ddd', 'c1b', 'dt', 'dtco'], true)) {
                Auth::setFlash('error', 'Niedozwolony typ pliku. Akceptowane: .ddd, .c1b, .dt');
                header('Location: /analysis'); exit;
            }
        }

        // Store file
        if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
        $storedName = uniqid('ddd_', true) . '.bin';
        $destPath   = UPLOAD_PATH . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Auth::setFlash('error', 'Błąd przy zapisie pliku.');
            header('Location: /analysis'); exit;
        }

        $driverId  = (int)($_POST['driver_id']  ?? 0) ?: null;
        $vehicleId = (int)($_POST['vehicle_id'] ?? 0) ?: null;
        $fileType  = $_POST['file_type'] ?? 'driver_card';

        $fileModel = new TachoFile();
        $fileId    = $fileModel->create([
            'company_id'    => $cid,
            'driver_id'     => $driverId,
            'vehicle_id'    => $vehicleId,
            'file_type'     => $fileType,
            'original_name' => $origName,
            'stored_name'   => $storedName,
            'file_size'     => $size,
            'parse_status'  => 'pending',
        ]);

        // Parse
        try {
            $binary = file_get_contents($destPath);
            $parser = new DDDParser($binary);
            $result = $parser->parse();

            $activityModel = new Activity();
            $activities    = [];

            foreach ($result['activities'] as $a) {
                $activities[] = array_merge($a, [
                    'tacho_file_id' => $fileId,
                    'driver_id'     => $driverId,
                    'vehicle_id'    => $vehicleId,
                ]);
            }

            $activityModel->saveMany($activities);

            // Detect violations (daily)
            if ($driverId && !empty($activities)) {
                $byDate = [];
                foreach ($activities as $a) {
                    $byDate[$a['activity_date']][] = $a;
                }

                foreach ($byDate as $date => $dayActs) {
                    $violations = $activityModel->detectDailyViolations($dayActs, $driverId, $fileId);
                    $activityModel->saveViolations($violations);
                }

                // Weekly violations (use first week found)
                if (!empty($byDate)) {
                    $firstDate = min(array_keys($byDate));
                    $weekStart = date('Y-m-d', strtotime('Monday this week', strtotime($firstDate)));
                    $weekVios  = $activityModel->detectWeeklyViolations($driverId, $fileId, $weekStart);
                    $activityModel->saveViolations($weekVios);
                }
            }

            $fileModel->updateStatus($fileId, 'success');
            Auth::log('file_parsed', "Plik DDD ID $fileId, rekordów: " . count($activities));

            Auth::setFlash('success', sprintf(
                'Plik wczytany. Znaleziono %d rekordów aktywności.',
                count($activities)
            ));
        } catch (\Throwable $e) {
            $fileModel->updateStatus($fileId, 'error', $e->getMessage());
            Auth::setFlash('error', 'Błąd parsowania: ' . $e->getMessage());
        }

        header("Location: /analysis/$fileId/daily"); exit;
    }

    public function daily(array $params): void
    {
        Auth::requireAuth();
        $fileId   = (int)$params['id'];
        $file     = $this->findFileOr404($fileId);
        $date     = $_GET['date'] ?? null;

        $actModel  = new Activity();
        $activities = $actModel->forFile($fileId);

        // Available dates
        $dates = array_unique(array_map(fn($a) => $a['activity_date'], $activities));
        sort($dates);

        if (!$date && $dates) $date = $dates[0];

        // Day activities
        $dayActivities = array_filter($activities, fn($a) => $a['activity_date'] === $date);
        $dayActivities = array_values($dayActivities);

        $totals    = ['driving' => 0, 'work' => 0, 'availability' => 0, 'rest' => 0, 'break' => 0];
        foreach ($dayActivities as $a) {
            $totals[$a['activity_type']] += (int)$a['duration_minutes'];
        }

        $violations = $actModel->violationsForFile($fileId);

        $pageTitle = 'Analiza dzienna – ' . ($date ?? '');
        $flash     = Auth::getFlash();
        $content   = $this->render('analysis/daily', compact('file','date','dates','dayActivities','totals','violations','fileId'));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    public function weekly(array $params): void
    {
        Auth::requireAuth();
        $fileId    = (int)$params['id'];
        $file      = $this->findFileOr404($fileId);

        $actModel   = new Activity();
        $activities = $actModel->forFile($fileId);

        // Group by week
        $weeks = [];
        foreach ($activities as $a) {
            $wStart = date('Y-m-d', strtotime('Monday this week', strtotime($a['activity_date'])));
            $weeks[$wStart] = true;
        }
        $weekKeys = array_keys($weeks);
        sort($weekKeys);

        $weekStart   = $_GET['week'] ?? ($weekKeys[0] ?? date('Y-m-d', strtotime('Monday this week')));
        $weekEnd     = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        $weekDates   = [];
        for ($i = 0; $i < 7; $i++) $weekDates[] = date('Y-m-d', strtotime($weekStart . " +$i days"));

        // Totals per day
        $weeklyData = [];
        foreach ($weekDates as $d) {
            $weeklyData[$d] = ['driving' => 0, 'work' => 0, 'availability' => 0, 'rest' => 0, 'break' => 0];
        }
        foreach ($activities as $a) {
            if (isset($weeklyData[$a['activity_date']])) {
                $weeklyData[$a['activity_date']][$a['activity_type']] += (int)$a['duration_minutes'];
            }
        }

        $violations   = $actModel->violationsForFile($fileId);
        $weekViolations = array_filter($violations, function($v) use ($weekStart, $weekEnd) {
            $d = $v['created_at'] ?? $weekStart;
            return $d >= $weekStart && $d <= $weekEnd;
        });

        $pageTitle = 'Analiza tygodniowa';
        $flash     = Auth::getFlash();
        $content   = $this->render('analysis/weekly', compact(
            'file','weekStart','weekEnd','weekDates','weeklyData',
            'violations','weekViolations','weeks','weekKeys'
        ));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function findFileOr404(int $id): array
    {
        $f = (new TachoFile())->find($id, Auth::companyId() ?? 0);
        if (!$f) { http_response_code(404); exit('Plik nie znaleziony.'); }
        return $f;
    }

    private function render(string $view, array $data = []): string
    {
        extract($data);
        ob_start();
        require __DIR__ . '/../Views/' . $view . '.php';
        return ob_get_clean();
    }
}
