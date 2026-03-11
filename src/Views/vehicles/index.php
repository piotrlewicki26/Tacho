<?php /** @var array $vehicles */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Pojazdy</h4>
  <?php if (\Core\Auth::hasRole('admin','superadmin')): ?>
  <a href="/vehicles/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Dodaj pojazd</a>
  <?php endif; ?>
</div>
<div class="card border-0" style="background:#1a1d27">
  <div class="card-body p-0">
    <?php if (empty($vehicles)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-truck fs-1 d-block mb-2"></i>
      Brak pojazdów. <a href="/vehicles/create">Dodaj pierwszy pojazd</a>.
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="text-muted small">
          <tr><th>Rej.</th><th>Marka</th><th>Model</th><th>Rok</th><th>VIN</th><th>Tachograf S/N</th><th>Typ</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($vehicles as $v): ?>
          <tr>
            <td><span class="fw-semibold font-monospace"><?= htmlspecialchars($v['registration']) ?></span></td>
            <td><?= htmlspecialchars($v['brand'] ?? '—') ?></td>
            <td><?= htmlspecialchars($v['model'] ?? '—') ?></td>
            <td class="text-muted small"><?= $v['year'] ?? '—' ?></td>
            <td class="text-muted small font-monospace"><?= htmlspecialchars($v['vin'] ?? '—') ?></td>
            <td class="text-muted small font-monospace"><?= htmlspecialchars($v['tachograph_serial'] ?? '—') ?></td>
            <td class="text-muted small"><?= htmlspecialchars($v['tachograph_type'] ?? '—') ?></td>
            <td class="text-end">
              <?php if (\Core\Auth::hasRole('admin','superadmin')): ?>
              <a href="/vehicles/<?= $v['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="POST" action="/vehicles/<?= $v['id'] ?>/delete" class="d-inline"
                    onsubmit="return confirm('Usunąć pojazd?')">
                <input type="hidden" name="_token" value="<?= \Core\Auth::csrfToken() ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
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
