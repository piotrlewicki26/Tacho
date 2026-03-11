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
    use \Core\StringHelper;
    public function index(array $params): void
    {
        Auth::requireAuth();
        $cid = Auth::effectiveCompanyId();
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

        $cid = Auth::effectiveCompanyId();
        if (!$cid) {
            Auth::setFlash('error', 'Brak kontekstu firmy. Użyj konta firmowego lub wybierz firmę z panelu administratora.');
            header('Location: /analysis'); exit;
        }

        if (!Auth::isSuperAdmin() && !License::isModuleAllowed($cid, 'analysis')) {
            Auth::setFlash('error', 'Brak licencji na moduł analizy.');
            header('Location: /'); exit;
        }

        if (empty($_FILES['ddd_file']['tmp_name']) || $_FILES['ddd_file']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'Plik przekracza limit rozmiaru serwera.',
                UPLOAD_ERR_FORM_SIZE  => 'Plik jest zbyt duży.',
                UPLOAD_ERR_PARTIAL    => 'Plik wgrany tylko częściowo.',
                UPLOAD_ERR_NO_FILE    => 'Nie wybrano pliku.',
                UPLOAD_ERR_NO_TMP_DIR => 'Brak katalogu tymczasowego.',
                UPLOAD_ERR_CANT_WRITE => 'Nie można zapisać pliku na dysku.',
            ];
            $errCode = $_FILES['ddd_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            Auth::setFlash('error', $uploadErrors[$errCode] ?? 'Błąd przesyłania pliku (kod: ' . $errCode . ').');
            header('Location: /analysis'); exit;
        }

        $file     = $_FILES['ddd_file'];
        $origName = basename($file['name']);
        $size     = (int) $file['size'];

        if ($size > MAX_UPLOAD_SIZE) {
            Auth::setFlash('error', 'Plik jest zbyt duży (max 50 MB).');
            header('Location: /analysis'); exit;
        }

        // Validate by extension first (DDD files have many possible MIME types)
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExts  = ['ddd', 'c1b', 'dt', 'dtco', 'v1b', 'm1', 'vu'];
        $allowedMimes = [
            'application/octet-stream', 'application/x-binary',
            'application/binary', 'text/plain',
            'application/x-dosexec', 'application/x-ddd',
        ];
        if (!in_array($ext, $allowedExts, true)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            if (!in_array($mime, $allowedMimes, true)) {
                Auth::setFlash('error', 'Niedozwolony typ pliku. Akceptowane rozszerzenia: .ddd, .c1b, .dt, .dtco, .v1b, .m1, .vu');
                header('Location: /analysis'); exit;
            }
        }

        // Ensure upload directory exists
        if (!is_dir(UPLOAD_PATH) && !mkdir(UPLOAD_PATH, 0755, true) && !is_dir(UPLOAD_PATH)) {
            Auth::setFlash('error', 'Nie można utworzyć katalogu uploads. Sprawdź uprawnienia serwera.');
            header('Location: /analysis'); exit;
        }

        $storedName = uniqid('ddd_', true) . '.bin';
        $destPath   = UPLOAD_PATH . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Auth::setFlash('error', 'Błąd przy zapisie pliku. Sprawdź uprawnienia katalogu uploads.');
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
        $autoMessages = [];
        try {
            $binary = file_get_contents($destPath);
            $parser = new DDDParser($binary);
            $result = $parser->parse();

            // ── Auto-create driver from parsed data ────────────────────────
            if (!$driverId && !empty($result['driver'])) {
                $drvInfo = $result['driver'];
                $cardNo  = $this->cleanString($drvInfo['card_number'] ?? '');
                $surname = $this->cleanString($drvInfo['surname'] ?? '');
                $fname   = $this->cleanString($drvInfo['first_name'] ?? '');

                // Try to match existing driver by card number
                if ($cardNo) {
                    $existing = \Core\Database::fetchOne(
                        'SELECT id FROM drivers WHERE company_id=:cid AND card_number=:cn AND is_active=1 LIMIT 1',
                        ['cid' => $cid, 'cn' => $cardNo]
                    );
                    if ($existing) {
                        $driverId = (int)$existing['id'];
                    }
                }

                // Create new driver if not found and we have a name
                if (!$driverId && ($surname || $fname)) {
                    if (License::checkDriverLimit($cid)) {
                        $driverModel = new Driver();
                        $driverId    = $driverModel->create($cid, [
                            'first_name'  => $fname  ?: 'Nieznany',
                            'last_name'   => $surname ?: 'Kierowca',
                            'card_number' => $cardNo  ?: null,
                            'nationality' => $this->cleanString($drvInfo['nationality'] ?? 'PL') ?: 'PL',
                        ]);
                        $autoMessages[] = "Automatycznie dodano kierowcę: $fname $surname";
                        Auth::log('driver_auto_created', "Kierowca z pliku DDD ID $fileId, ID kierowcy: $driverId");
                    } else {
                        // Limit reached – flag it clearly so it surfaces to the user
                        $autoMessages[] = '⚠ Limit kierowców wyczerpany – kierowca z pliku DDD nie został dodany. Zaktualizuj licencję.';
                    }
                }

                // Update the file record with the resolved driver
                if ($driverId) {
                    \Core\Database::update('tacho_files', ['driver_id' => $driverId], 'id=:id', ['id' => $fileId]);
                }
            }

            // ── Auto-create vehicle from parsed data ───────────────────────
            if (!$vehicleId && !empty($result['vehicle'])) {
                $vehInfo = $result['vehicle'];
                $reg     = $this->cleanString($vehInfo['registration'] ?? '');
                $vin     = $this->cleanString($vehInfo['vin'] ?? '');

                if ($reg) {
                    $existingVeh = \Core\Database::fetchOne(
                        'SELECT id FROM vehicles WHERE company_id=:cid AND registration=:r AND is_active=1 LIMIT 1',
                        ['cid' => $cid, 'r' => $reg]
                    );
                    if ($existingVeh) {
                        $vehicleId = (int)$existingVeh['id'];
                    } else {
                        $vehicleModel = new Vehicle();
                        $vehicleId    = $vehicleModel->create($cid, [
                            'registration' => $reg,
                            'vin'          => $vin ?: null,
                        ]);
                        $autoMessages[] = "Automatycznie dodano pojazd: $reg";
                        Auth::log('vehicle_auto_created', "Pojazd z pliku DDD ID $fileId, rejestracja: $reg");
                    }

                    \Core\Database::update('tacho_files', ['vehicle_id' => $vehicleId], 'id=:id', ['id' => $fileId]);
                }
            }

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

            $msg = sprintf('Plik wczytany. Znaleziono %d rekordów aktywności.', count($activities));
            if ($autoMessages) {
                $msg .= ' ' . implode(' ', $autoMessages);
            }
            Auth::setFlash('success', $msg);
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

        // Per-day activity records for the SVG timeline chart
        $weekActivitiesByDay = [];
        foreach ($weekDates as $d) {
            $weekActivitiesByDay[$d] = [];
        }
        foreach ($activities as $a) {
            if (isset($weekActivitiesByDay[$a['activity_date']])) {
                $weekActivitiesByDay[$a['activity_date']][] = $a;
            }
        }

        $pageTitle = 'Analiza tygodniowa';
        $flash     = Auth::getFlash();
        $content   = $this->render('analysis/weekly', compact(
            'file','weekStart','weekEnd','weekDates','weeklyData',
            'weekActivitiesByDay','violations','weekViolations','weeks','weekKeys'
        ));
        require __DIR__ . '/../Views/layouts/main.php';
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function findFileOr404(int $id): array
    {
        $f = (new TachoFile())->find($id, Auth::effectiveCompanyId() ?? 0);
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
