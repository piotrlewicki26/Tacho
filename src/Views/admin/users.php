<?php /** @var array $users */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Użytkownicy</h4>
  <a href="/admin/users/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Dodaj użytkownika</a>
</div>
<div class="card border-0" style="background:#1a1d27">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="text-muted small">
        <tr><th>Imię i nazwisko</th><th>E-mail</th><th>Firma</th><th>Rola</th><th>Aktywny</th><th>Ostatnie logowanie</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($u['name']) ?></td>
          <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
          <td class="small"><?= htmlspecialchars($u['company_name'] ?? '—') ?></td>
          <td>
            <span class="badge bg-<?= $u['role']==='superadmin'?'danger':($u['role']==='admin'?'warning text-dark':'secondary') ?>">
              <?= $u['role'] ?>
            </span>
          </td>
          <td>
            <form method="POST" action="/admin/users/<?= $u['id'] ?>/toggle" class="d-inline">
              <input type="hidden" name="_token" value="<?= \Core\Auth::csrfToken() ?>">
              <button type="submit" class="btn btn-sm btn-link p-0">
                <?= $u['is_active'] ? '<i class="bi bi-toggle-on text-success fs-5"></i>' : '<i class="bi bi-toggle-off text-muted fs-5"></i>' ?>
              </button>
            </form>
          </td>
          <td class="text-muted small">
            <?= $u['last_login'] ? date('d.m.Y H:i', strtotime($u['last_login'])) : '—' ?>
          </td>
          <td class="text-end">
            <form method="POST" action="/admin/users/<?= $u['id'] ?>/delete" class="d-inline"
                  onsubmit="return confirm('Usunąć użytkownika <?= htmlspecialchars(addslashes($u['name'])) ?>?')">
              <input type="hidden" name="_token" value="<?= \Core\Auth::csrfToken() ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
        <tr><td colspan="7" class="text-muted text-center py-4">Brak użytkowników</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
