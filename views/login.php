<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(APP_TITLE) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: linear-gradient(135deg, #1e2d40 0%, #2d4560 100%); min-height: 100vh; }
        .login-card { border: none; border-radius: 1rem; box-shadow: 0 1rem 3rem rgba(0,0,0,.3); }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
<div class="card login-card p-4" style="width:100%;max-width:400px">
    <div class="text-center mb-4">
        <i class="bi bi-shield-lock-fill fs-1 text-primary"></i>
        <h4 class="mt-2 mb-0 fw-bold">Generator Licencji</h4>
        <small class="text-muted">TachoSystem</small>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/login" novalidate>
        <div class="mb-3">
            <label for="username" class="form-label fw-semibold">Użytkownik</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" id="username" name="username" class="form-control"
                       autocomplete="username" required autofocus>
            </div>
        </div>
        <div class="mb-4">
            <label for="password" class="form-label fw-semibold">Hasło</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" id="password" name="password" class="form-control"
                       autocomplete="current-password" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-box-arrow-in-right me-2"></i>Zaloguj się
        </button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmFEpFCFhbHVXedSvMkIxRGgDfHN"
        crossorigin="anonymous"></script>
</body>
</html>
