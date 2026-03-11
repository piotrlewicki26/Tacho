<?php /** @var array $driver @var array $files @var array $violations */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><?= htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']) ?></h4>
    <p class="text-muted mb-0 small">
      Karta: <span class="font-monospace"><?= htmlspecialchars($driver['card_number'] ?? '—') ?></span>
      <?php if ($driver['card_expiry']): ?>
      · Ważna do: <?= date('d.m.Y', strtotime($driver['card_expiry'])) ?>
      <?php endif; ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="/drivers/<?= $driver['id'] ?>/edit" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edytuj</a>
    <a href="/drivers" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Wróć</a>
  </div>
</div>

<div class="row g-4">
  <!-- Info card -->
  <div class="col-lg-4">
    <div class="card border-0 mb-4" style="background:#1a1d27">
      <div class="card-body">
        <h6 class="text-muted mb-3">Dane osobowe</h6>
        <dl class="row small mb-0">
          <dt class="col-5 text-muted fw-normal">Imię i nazwisko</dt>
          <dd class="col-7"><?= htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']) ?></dd>
          <dt class="col-5 text-muted fw-normal">Data urodzenia</dt>
          <dd class="col-7"><?= $driver['birth_date'] ? date('d.m.Y', strtotime($driver['birth_date'])) : '—' ?></dd>
          <dt class="col-5 text-muted fw-normal">Narodowość</dt>
          <dd class="col-7"><?= htmlspecialchars($driver['nationality'] ?? '—') ?></dd>
          <dt class="col-5 text-muted fw-normal">Nr prawa jazdy</dt>
          <dd class="col-7 font-monospace"><?= htmlspecialchars($driver['license_number'] ?? '—') ?></dd>
          <dt class="col-5 text-muted fw-normal">Nr karty</dt>
          <dd class="col-7 font-monospace"><?= htmlspecialchars($driver['card_number'] ?? '—') ?></dd>
          <dt class="col-5 text-muted fw-normal">Ważność karty</dt>
          <dd class="col-7"><?= $driver['card_expiry'] ? date('d.m.Y', strtotime($driver['card_expiry'])) : '—' ?></dd>
          <dt class="col-5 text-muted fw-normal">Telefon</dt>
          <dd class="col-7"><?= htmlspecialchars($driver['phone'] ?? '—') ?></dd>
          <dt class="col-5 text-muted fw-normal">E-mail</dt>
          <dd class="col-7"><?= htmlspecialchars($driver['email'] ?? '—') ?></dd>
        </dl>
      </div>
    </div>
  </div>

  <!-- Files + violations -->
  <div class="col-lg-8">
    <div class="card border-0 mb-4" style="background:#1a1d27">
      <div class="card-header border-0 bg-transparent d-flex justify-content-between">
        <h6 class="fw-semibold mb-0">Pliki DDD (<?= count($files) ?>)</h6>
        <a href="/analysis" class="btn btn-sm btn-primary"><i class="bi bi-upload me-1"></i>Wgraj plik</a>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="text-muted small"><tr><th>Plik</th><th>Typ</th><th>Data</th><th>Status</th><th>Nar.</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($files as $f): ?>
            <tr>
              <td class="small font-monospace"><?= htmlspecialchars($f['original_name']) ?></td>
              <td class="small"><?= $f['file_type'] === 'driver_card' ? 'Karta' : 'VU' ?></td>
              <td class="text-muted small"><?= date('d.m.Y', strtotime($f['created_at'])) ?></td>
              <td>
                <span class="badge bg-<?= $f['parse_status'] === 'success' ? 'success' : ($f['parse_status'] === 'error' ? 'danger' : 'secondary') ?>">
                  <?= $f['parse_status'] ?>
                </span>
              </td>
              <td><?= $f['vio_count'] ? '<span class="badge bg-danger">' . $f['vio_count'] . '</span>' : '—' ?></td>
              <td>
                <a href="/analysis/<?= $f['id'] ?>/daily" class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-bar-chart"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($files)): ?>
            <tr><td colspan="6" class="text-muted text-center py-3">Brak plików</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($violations): ?>
    <div class="card border-0" style="background:#1a1d27">
      <div class="card-header border-0 bg-transparent">
        <h6 class="fw-semibold mb-0 text-danger">Naruszenia (<?= count($violations) ?>)</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="text-muted small"><tr><th>Typ</th><th>Opis</th><th>Przepis</th><th>Grzywna</th><th>Waga</th></tr></thead>
            <tbody>
              <?php foreach ($violations as $v): ?>
              <tr>
                <td class="small"><?= htmlspecialchars($v['violation_type']) ?></td>
                <td class="small"><?= htmlspecialchars($v['description']) ?></td>
                <td class="small text-muted"><?= htmlspecialchars($v['regulation_ref'] ?? '') ?></td>
                <td class="small text-warning text-nowrap">
                  <?= $v['fine_amount_min'] ? number_format($v['fine_amount_min'], 0, ',', ' ') . '–' . number_format($v['fine_amount_max'], 0, ',', ' ') . ' zł' : '—' ?>
                </td>
                <td>
                  <span class="badge bg-<?= $v['severity'] === 'critical' ? 'danger' : ($v['severity'] === 'major' ? 'warning text-dark' : 'secondary') ?>">
                    <?= $v['severity'] ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
