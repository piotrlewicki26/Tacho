<?php /** @var array $drivers */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Kierowcy</h4>
  <?php if (\Core\Auth::hasRole('admin','superadmin')): ?>
  <a href="/drivers/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Dodaj kierowcę</a>
  <?php endif; ?>
</div>

<div class="card border-0" style="background:#1a1d27">
  <div class="card-body p-0">
    <?php if (empty($drivers)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-person-badge fs-1 d-block mb-2"></i>
      Brak kierowców. <a href="/drivers/create">Dodaj pierwszego kierowcę</a>.
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="text-muted small">
          <tr>
            <th>Kierowca</th><th>Karta kierowcy</th><th>Ważność karty</th>
            <th>Telefon</th><th>Pliki</th><th>Naruszenia</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($drivers as $d): ?>
          <tr>
            <td>
              <a href="/drivers/<?= $d['id'] ?>" class="fw-semibold text-decoration-none">
                <?= htmlspecialchars($d['last_name'] . ' ' . $d['first_name']) ?>
              </a>
              <?php if ($d['nationality'] && $d['nationality'] !== 'PL'): ?>
              <span class="badge bg-secondary ms-1"><?= htmlspecialchars($d['nationality']) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-muted small font-monospace"><?= htmlspecialchars($d['card_number'] ?? '—') ?></td>
            <td class="small <?= !empty($d['card_expiry']) && $d['card_expiry'] < date('Y-m-d', strtotime('+30 days')) ? 'text-warning fw-semibold' : 'text-muted' ?>">
              <?= $d['card_expiry'] ? date('d.m.Y', strtotime($d['card_expiry'])) : '—' ?>
              <?php if (!empty($d['card_expiry']) && $d['card_expiry'] < date('Y-m-d')): ?>
              <span class="badge bg-danger ms-1">WYGASŁA</span>
              <?php elseif (!empty($d['card_expiry']) && $d['card_expiry'] < date('Y-m-d', strtotime('+30 days'))): ?>
              <span class="badge bg-warning text-dark ms-1">WKRÓTCE</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small"><?= htmlspecialchars($d['phone'] ?? '—') ?></td>
            <td><span class="badge bg-secondary"><?= $d['file_count'] ?? 0 ?></span></td>
            <td>
              <?php if ($d['violation_count'] ?? 0): ?>
              <span class="badge bg-danger"><?= $d['violation_count'] ?></span>
              <?php else: ?>
              <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a href="/drivers/<?= $d['id'] ?>" class="btn btn-sm btn-outline-secondary me-1"><i class="bi bi-eye"></i></a>
              <?php if (\Core\Auth::hasRole('admin','superadmin')): ?>
              <a href="/drivers/<?= $d['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
