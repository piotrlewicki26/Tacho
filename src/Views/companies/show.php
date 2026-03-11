<?php /** @var array $company @var array $users @var array|null $license */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0"><?= htmlspecialchars($company['name']) ?></h4><p class="text-muted small mb-0">NIP: <?= htmlspecialchars($company['nip']??'—') ?></p></div>
  <div class="d-flex gap-2">
    <a href="/companies/<?= $company['id'] ?>/edit" class="btn btn-sm btn-outline-primary">Edytuj</a>
    <a href="/companies" class="btn btn-sm btn-outline-secondary">Wróć</a>
  </div>
</div>
<div class="row g-4">
  <div class="col-md-4">
    <div class="card border-0 mb-4" style="background:#1a1d27">
      <div class="card-body">
        <h6 class="text-muted mb-3">Dane firmy</h6>
        <dl class="row small mb-0">
          <dt class="col-5 text-muted fw-normal">Adres</dt><dd class="col-7"><?= htmlspecialchars($company['address']??'—') ?></dd>
          <dt class="col-5 text-muted fw-normal">Miasto</dt><dd class="col-7"><?= htmlspecialchars($company['city']??'—') ?></dd>
          <dt class="col-5 text-muted fw-normal">E-mail</dt><dd class="col-7"><?= htmlspecialchars($company['email']??'—') ?></dd>
          <dt class="col-5 text-muted fw-normal">Telefon</dt><dd class="col-7"><?= htmlspecialchars($company['phone']??'—') ?></dd>
        </dl>
      </div>
    </div>
    <?php if ($license): ?>
    <div class="card border-0" style="background:#1a1d27">
      <div class="card-body">
        <h6 class="text-muted mb-3">Licencja</h6>
        <div class="font-monospace small mb-2 text-success"><?= htmlspecialchars($license['license_key']) ?></div>
        <dl class="row small mb-0">
          <dt class="col-6 text-muted fw-normal">Ważna do</dt><dd class="col-6"><?= date('d.m.Y', strtotime($license['valid_to'])) ?></dd>
          <dt class="col-6 text-muted fw-normal">Operatorzy</dt><dd class="col-6">max <?= $license['max_operators'] ?></dd>
          <dt class="col-6 text-muted fw-normal">Kierowcy</dt><dd class="col-6">max <?= $license['max_drivers'] ?></dd>
          <dt class="col-6 text-muted fw-normal">Moduły</dt><dd class="col-6"><?= implode(', ', json_decode($license['modules']??'[]',true)?:['—']) ?></dd>
        </dl>
      </div>
    </div>
    <?php else: ?>
    <div class="alert alert-warning small"><i class="bi bi-key me-1"></i>Brak aktywnej licencji. <a href="/admin/licenses">Generuj licencję</a>.</div>
    <?php endif; ?>
  </div>
  <div class="col-md-8">
    <div class="card border-0" style="background:#1a1d27">
      <div class="card-header border-0 bg-transparent d-flex justify-content-between">
        <h6 class="fw-semibold mb-0">Użytkownicy (<?= count($users) ?>)</h6>
        <a href="/admin/users/create" class="btn btn-sm btn-primary"><i class="bi bi-plus me-1"></i>Dodaj</a>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="text-muted small"><tr><th>Imię i nazwisko</th><th>E-mail</th><th>Rola</th><th>Aktywny</th></tr></thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['name']) ?></td>
              <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="badge bg-<?= $u['role']==='superadmin'?'danger':($u['role']==='admin'?'warning text-dark':'secondary') ?>"><?= $u['role'] ?></span></td>
              <td><?= $u['is_active'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
