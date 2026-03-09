<?php /** @var array $companies */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Firmy</h4>
  <a href="/companies/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Dodaj firmę</a>
</div>
<div class="card border-0" style="background:#1a1d27">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="text-muted small"><tr><th>Nazwa</th><th>NIP</th><th>Miasto</th><th>E-mail</th><th>Kierowcy</th><th>Pojazdy</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($companies as $c): ?>
        <tr>
          <td><a href="/companies/<?= $c['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($c['name']) ?></a></td>
          <td class="font-monospace small"><?= htmlspecialchars($c['nip'] ?? '—') ?></td>
          <td class="text-muted small"><?= htmlspecialchars($c['city'] ?? '—') ?></td>
          <td class="text-muted small"><?= htmlspecialchars($c['email'] ?? '—') ?></td>
          <td><span class="badge bg-secondary"><?= $c['driver_count'] ?? 0 ?></span></td>
          <td><span class="badge bg-secondary"><?= $c['vehicle_count'] ?? 0 ?></span></td>
          <td class="text-end">
            <a href="/companies/<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary me-1"><i class="bi bi-eye"></i></a>
            <a href="/companies/<?= $c['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($companies)): ?>
        <tr><td colspan="7" class="text-muted text-center py-4">Brak firm</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
