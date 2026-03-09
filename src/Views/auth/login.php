<?php ob_start(); ?>
<form method="POST" action="/login" novalidate>
  <input type="hidden" name="_token" value="<?= \Core\Auth::csrfToken() ?>">
  <div class="mb-3">
    <label for="email" class="form-label text-secondary small">E-mail</label>
    <input type="email" class="form-control form-control-lg bg-dark border-secondary text-white"
           id="email" name="email" placeholder="operator@firma.pl" required autofocus autocomplete="username">
  </div>
  <div class="mb-4">
    <label for="password" class="form-label text-secondary small">Hasło</label>
    <input type="password" class="form-control form-control-lg bg-dark border-secondary text-white"
           id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
  </div>
  <button type="submit" class="btn btn-primary btn-lg w-100">
    <i class="bi bi-box-arrow-in-right me-2"></i>Zaloguj się
  </button>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/auth.php';
