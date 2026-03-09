<?php
/** @var array|null $companies */
?>
<div class="card-body">
  <h5 class="card-title mb-4">Nowy użytkownik</h5>
  <form method="POST" action="/admin/users/create" novalidate>
    <input type="hidden" name="_token" value="<?= \Core\Auth::csrfToken() ?>">
    <div class="mb-3">
      <label class="form-label">Imię i nazwisko</label>
      <input type="text" class="form-control" name="name" required>
    </div>
    <div class="mb-3">
      <label class="form-label">E-mail</label>
      <input type="email" class="form-control" name="email" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Hasło (min. 8 znaków)</label>
      <input type="password" class="form-control" name="password" minlength="8" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Rola</label>
      <select class="form-select" name="role">
        <option value="operator">Operator</option>
        <option value="admin">Admin</option>
        <?php if (\Core\Auth::isSuperAdmin()): ?>
        <option value="superadmin">Superadmin</option>
        <?php endif; ?>
      </select>
    </div>
    <div class="mb-4">
      <label class="form-label">Firma</label>
      <select class="form-select" name="company_id">
        <option value="">— brak —</option>
        <?php foreach ($companies as $c): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">Utwórz użytkownika</button>
      <a href="/admin/users" class="btn btn-secondary">Anuluj</a>
    </div>
  </form>
</div>
