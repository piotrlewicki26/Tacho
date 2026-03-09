<?php
declare(strict_types=1);
/**
 * TachoSystem – Installation Wizard
 * Access: http://your-domain/setup.php
 * Locked after first successful installation (.installed file).
 */

if (file_exists(__DIR__ . '/.installed')) {
    die('<h2 style="font-family:sans-serif;text-align:center;margin-top:50px">System jest już zainstalowany.<br><a href="/login">Przejdź do logowania</a></h2>');
}

session_start();
$step   = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$errors = [];
$info   = [];

// ── Step 2: Test DB connection ─────────────────────────────────────────────
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? 'tacho_system');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = $_POST['db_pass'] ?? '';

    try {
        $pdo = new PDO(
            "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
            $dbUser, $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $_SESSION['setup'] = compact('dbHost', 'dbPort', 'dbName', 'dbUser', 'dbPass');
        $step = 3;
    } catch (\PDOException $e) {
        $errors[] = 'Błąd połączenia: ' . $e->getMessage();
        $step = 2;
    }
}

// ── Step 3: Create tables ──────────────────────────────────────────────────
if ($step === 3 && isset($_POST['create_tables'])) {
    $cfg = $_SESSION['setup'] ?? null;
    if (!$cfg) { $step = 1; $errors[] = 'Sesja wygasła.'; goto render; }

    try {
        $pdo = new PDO(
            "mysql:host={$cfg['dbHost']};port={$cfg['dbPort']};dbname={$cfg['dbName']};charset=utf8mb4",
            $cfg['dbUser'], $cfg['dbPass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
        $pdo->exec($sql);
        $info[] = 'Tabele utworzone pomyślnie.';
        $step = 4;
    } catch (\Throwable $e) {
        $errors[] = 'Błąd tworzenia tabel: ' . $e->getMessage();
    }
}

// ── Step 4: Create superadmin ──────────────────────────────────────────────
if ($step === 4 && isset($_POST['create_admin'])) {
    $cfg = $_SESSION['setup'] ?? null;
    if (!$cfg) { $step = 1; $errors[] = 'Sesja wygasła.'; goto render; }

    $adminName  = trim($_POST['admin_name']  ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = $_POST['admin_pass'] ?? '';

    if (!$adminName || !$adminEmail || strlen($adminPass) < 8) {
        $errors[] = 'Wszystkie pola są wymagane (hasło min. 8 znaków).';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$cfg['dbHost']};port={$cfg['dbPort']};dbname={$cfg['dbName']};charset=utf8mb4",
                $cfg['dbUser'], $cfg['dbPass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO users (name,email,password_hash,role,is_active) VALUES (?,?,?,'superadmin',1)");
            $stmt->execute([$adminName, $adminEmail, $hash]);
            $info[] = 'Konto superadmin utworzone.';
            $step = 5;
        } catch (\Throwable $e) {
            $errors[] = 'Błąd: ' . $e->getMessage();
        }
    }
}

// ── Step 5: Finalize ───────────────────────────────────────────────────────
if ($step === 5 && isset($_POST['finalize'])) {
    $cfg    = $_SESSION['setup'] ?? null;
    $secret = bin2hex(random_bytes(32));

    // Write config file hint (or just .installed marker)
    file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));

    // Write an env template
    if ($cfg) {
        $envContent = "APP_URL=http://localhost\nAPP_DEBUG=false\nDB_HOST={$cfg['dbHost']}\nDB_PORT={$cfg['dbPort']}\nDB_NAME={$cfg['dbName']}\nDB_USER={$cfg['dbUser']}\nDB_PASS={$cfg['dbPass']}\nLICENSE_SECRET={$secret}\n";
        file_put_contents(__DIR__ . '/.env', $envContent);
    }

    $info[]  = 'Instalacja zakończona!';
    $step    = 6;
}

render:
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalacja – TachoSystem</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
  body { background:#0f1117; color:#e2e8f0; font-family:'Segoe UI',sans-serif; }
  .setup-card { max-width:600px; background:#1a1d27; border:1px solid #2d3250; border-radius:12px; }
  .step-indicator span { width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700; }
</style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
<div class="setup-card w-100 mx-3 p-4 p-md-5 shadow-lg">
  <h3 class="fw-bold text-center mb-1">Tacho<span class="text-primary">System</span></h3>
  <p class="text-muted text-center small mb-4">Kreator instalacji</p>

  <!-- Steps -->
  <div class="d-flex justify-content-center gap-2 mb-4 step-indicator">
    <?php for ($i = 1; $i <= 5; $i++): ?>
    <span class="<?= $i < $step ? 'bg-success text-white' : ($i === $step ? 'bg-primary text-white' : 'bg-secondary text-white') ?>">
      <?= $i < $step ? '✓' : $i ?>
    </span>
    <?php if ($i < 5): ?><div class="flex-grow-1 border-top align-self-center" style="border-color:#2d3250!important"></div><?php endif; ?>
    <?php endfor; ?>
  </div>

  <?php foreach ($errors as $e): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>
  <?php foreach ($info as $i_msg): ?>
  <div class="alert alert-success"><?= htmlspecialchars($i_msg) ?></div>
  <?php endforeach; ?>

  <?php if ($step === 1): ?>
  <!-- Step 1: Requirements -->
  <h5 class="mb-3">Krok 1 – Wymagania systemowe</h5>
  <table class="table table-sm">
    <tbody>
      <?php
      $checks = [
          'PHP ' . PHP_VERSION                     => version_compare(PHP_VERSION, '7.4.0', '>='),
          'PDO MySQL'                              => extension_loaded('pdo_mysql'),
          'JSON'                                   => extension_loaded('json'),
          'mbstring'                               => extension_loaded('mbstring'),
          'fileinfo'                               => extension_loaded('fileinfo'),
          'Katalog uploads/ – zapis'               => is_writable(__DIR__ . '/uploads') || mkdir(__DIR__ . '/uploads', 0755, true),
          'Katalog root – zapis (.installed)'      => is_writable(__DIR__),
      ];
      $ok = true;
      foreach ($checks as $name => $passed):
          if (!$passed) $ok = false;
      ?>
      <tr>
        <td><?= htmlspecialchars($name) ?></td>
        <td><?= $passed ? '<span class="text-success">✓</span>' : '<span class="text-danger">✗</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($ok): ?>
  <form method="POST"><input type="hidden" name="step" value="2"><button class="btn btn-primary w-100">Dalej</button></form>
  <?php else: ?>
  <div class="alert alert-warning">Nie wszystkie wymagania są spełnione. Popraw je przed kontynuacją.</div>
  <?php endif; ?>

  <?php elseif ($step === 2): ?>
  <!-- Step 2: DB config -->
  <h5 class="mb-3">Krok 2 – Baza danych</h5>
  <form method="POST">
    <input type="hidden" name="step" value="2">
    <div class="row g-3">
      <div class="col-8"><label class="form-label">Host MySQL</label><input class="form-control" name="db_host" value="localhost"></div>
      <div class="col-4"><label class="form-label">Port</label><input class="form-control" name="db_port" value="3306"></div>
      <div class="col-12"><label class="form-label">Nazwa bazy</label><input class="form-control" name="db_name" value="tacho_system"></div>
      <div class="col-6"><label class="form-label">Użytkownik</label><input class="form-control" name="db_user" value="root"></div>
      <div class="col-6"><label class="form-label">Hasło</label><input type="password" class="form-control" name="db_pass"></div>
    </div>
    <button class="btn btn-primary w-100 mt-3">Testuj połączenie i kontynuuj</button>
  </form>

  <?php elseif ($step === 3): ?>
  <!-- Step 3: Create tables -->
  <h5 class="mb-3">Krok 3 – Utwórz tabele</h5>
  <p class="text-muted">Zostanie uruchomiony skrypt SQL tworzący 10 tabel w bazie <strong><?= htmlspecialchars($_SESSION['setup']['dbName'] ?? '') ?></strong>.</p>
  <form method="POST"><input type="hidden" name="step" value="3"><input type="hidden" name="create_tables" value="1"><button class="btn btn-primary w-100">Utwórz tabele</button></form>

  <?php elseif ($step === 4): ?>
  <!-- Step 4: Superadmin -->
  <h5 class="mb-3">Krok 4 – Konto superadmin</h5>
  <form method="POST">
    <input type="hidden" name="step" value="4">
    <input type="hidden" name="create_admin" value="1">
    <div class="mb-3"><label class="form-label">Imię i nazwisko</label><input class="form-control" name="admin_name" required></div>
    <div class="mb-3"><label class="form-label">E-mail</label><input type="email" class="form-control" name="admin_email" required></div>
    <div class="mb-3"><label class="form-label">Hasło (min. 8 znaków)</label><input type="password" class="form-control" name="admin_pass" minlength="8" required></div>
    <button class="btn btn-primary w-100">Utwórz konto superadmin</button>
  </form>

  <?php elseif ($step === 5): ?>
  <!-- Step 5: Finalize -->
  <h5 class="mb-3">Krok 5 – Finalizacja</h5>
  <div class="alert alert-info small">
    <strong>Ważne!</strong> Skopiuj dane konfiguracyjne poniżej do pliku <code>.env</code>
    lub ustaw je jako zmienne środowiskowe na serwerze. Tajny klucz licencji jest generowany automatycznie.
  </div>
  <form method="POST">
    <input type="hidden" name="step" value="5">
    <input type="hidden" name="finalize" value="1">
    <button class="btn btn-success w-100">Zakończ instalację</button>
  </form>

  <?php elseif ($step === 6): ?>
  <!-- Done -->
  <div class="text-center py-3">
    <div class="fs-1 mb-3">🎉</div>
    <h5 class="text-success">Instalacja zakończona!</h5>
    <p class="text-muted">Plik konfiguracyjny <code>.env</code> został wygenerowany automatycznie z danymi podanymi podczas instalacji.</p>
    <a href="/login" class="btn btn-primary mt-2">Przejdź do logowania</a>
  </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
