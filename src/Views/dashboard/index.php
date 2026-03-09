<?php
/**
 * @var array      $stats
 * @var array      $recentViolations
 * @var array      $recentFiles
 * @var array      $drivers
 * @var array      $vehicles
 * @var array      $chartData
 * @var array|null $licenseInfo
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Dashboard</h4>
    <p class="text-muted mb-0 small"><?= date('l, d F Y') ?></p>
  </div>
  <?php if ($licenseInfo): ?>
  <div class="d-none d-md-flex gap-2 align-items-center">
    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
      <i class="bi bi-person-badge me-1"></i>
      Kierowcy: <?= $licenseInfo['used_drivers'] ?> / <?= $licenseInfo['max_drivers'] ?>
    </span>
    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
      <i class="bi bi-people me-1"></i>
      Operatorzy: <?= $licenseInfo['used_operators'] ?> / <?= $licenseInfo['max_operators'] ?>
    </span>
    <span class="badge <?= strtotime($licenseInfo['valid_to']) < strtotime('+30 days') ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-success-subtle text-success border border-success-subtle' ?>">
      <i class="bi bi-patch-check me-1"></i>
      Licencja do: <?= date('d.m.Y', strtotime($licenseInfo['valid_to'])) ?>
    </span>
  </div>
  <?php endif; ?>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
  <?php
  $kpis = [
    ['Kierowcy',    $stats['drivers'],    'person-badge',   'primary'],
    ['Pojazdy',     $stats['vehicles'],   'truck',          'success'],
    ['Pliki DDD',   $stats['files'],      'file-earmark-binary', 'info'],
    ['Naruszenia',  $stats['violations'], 'exclamation-triangle', 'danger'],
  ];
  foreach ($kpis as [$label, $value, $icon, $color]):
  ?>
  <div class="col-6 col-xl-3">
    <div class="card border-0 h-100" style="background:#1a1d27">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 bg-<?= $color ?>-subtle text-<?= $color ?>">
          <i class="bi bi-<?= $icon ?> fs-3"></i>
        </div>
        <div>
          <div class="fs-2 fw-bold lh-1"><?= number_format($value) ?></div>
          <div class="text-muted small mt-1"><?= $label ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Chart + Violations -->
<div class="row g-4 mb-4">
  <div class="col-lg-7">
    <div class="card border-0 h-100" style="background:#1a1d27">
      <div class="card-header border-0 bg-transparent">
        <h6 class="fw-semibold mb-0">Aktywność – ostatnie 7 dni (godz.)</h6>
      </div>
      <div class="card-body">
        <canvas id="activityChart" height="200"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 h-100" style="background:#1a1d27">
      <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="fw-semibold mb-0">Ostatnie naruszenia</h6>
        <a href="/analysis" class="btn btn-sm btn-outline-secondary">Wszystkie</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentViolations)): ?>
        <div class="text-center py-5 text-muted"><i class="bi bi-check-circle fs-3"></i><br>Brak naruszeń</div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach (array_slice($recentViolations, 0, 8) as $v): ?>
          <div class="list-group-item bg-transparent border-0 py-2">
            <div class="d-flex align-items-start gap-2">
              <span class="badge rounded-pill bg-<?= $v['severity'] === 'critical' ? 'danger' : ($v['severity'] === 'major' ? 'warning text-dark' : 'secondary') ?> mt-1">
                <?= strtoupper(substr($v['severity'], 0, 3)) ?>
              </span>
              <div class="flex-grow-1">
                <div class="small fw-semibold"><?= htmlspecialchars($v['driver_name'] ?? '') ?></div>
                <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($v['violation_type']) ?></div>
                <div class="text-muted" style="font-size:.7rem"><?= htmlspecialchars($v['regulation_ref'] ?? '') ?></div>
              </div>
              <?php if ($v['fine_amount_min']): ?>
              <div class="text-end text-nowrap">
                <div class="text-warning small"><?= number_format($v['fine_amount_min'], 0, ',', ' ') ?>–<?= number_format($v['fine_amount_max'], 0, ',', ' ') ?> zł</div>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Drivers + Vehicles -->
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card border-0" style="background:#1a1d27">
      <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-start">
        <div>
          <h6 class="fw-semibold mb-0">Kierowcy</h6>
          <?php if ($licenseInfo): ?>
          <div class="mt-1" style="min-width:120px">
            <div class="d-flex justify-content-between" style="font-size:.7rem">
              <span class="text-muted"><?= $licenseInfo['used_drivers'] ?> / <?= $licenseInfo['max_drivers'] ?> (limit)</span>
              <span class="text-muted"><?= $licenseInfo['driver_pct'] ?>%</span>
            </div>
            <div class="progress" style="height:4px">
              <div class="progress-bar bg-<?= $licenseInfo['driver_pct'] >= 100 ? 'danger' : ($licenseInfo['driver_pct'] >= 80 ? 'warning' : 'primary') ?>"
                   style="width:<?= min($licenseInfo['driver_pct'], 100) ?>%"></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <a href="/drivers" class="btn btn-sm btn-primary"><i class="bi bi-plus me-1"></i>Zarządzaj</a>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="text-muted small"><tr><th>Nazwisko</th><th>Karta</th><th>Ważność</th><th>Naruszenia</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($drivers, 0, 8) as $d): ?>
            <tr>
              <td><a href="/drivers/<?= $d['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($d['last_name'] . ' ' . $d['first_name']) ?></a></td>
              <td class="text-muted small"><?= htmlspecialchars($d['card_number'] ?? '—') ?></td>
              <td class="small <?= !empty($d['card_expiry']) && $d['card_expiry'] < date('Y-m-d', strtotime('+30 days')) ? 'text-warning' : 'text-muted' ?>">
                <?= $d['card_expiry'] ? date('d.m.Y', strtotime($d['card_expiry'])) : '—' ?>
              </td>
              <td><?php if ($d['violation_count'] ?? 0): ?><span class="badge bg-danger"><?= $d['violation_count'] ?></span><?php else: ?><span class="text-muted small">0</span><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0" style="background:#1a1d27">
      <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-start">
        <div>
          <h6 class="fw-semibold mb-0">Pojazdy</h6>
        </div>
        <a href="/vehicles" class="btn btn-sm btn-primary"><i class="bi bi-plus me-1"></i>Zarządzaj</a>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="text-muted small"><tr><th>Rej.</th><th>Marka / Model</th><th>Typ tacho</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($vehicles, 0, 8) as $v): ?>
            <tr>
              <td><span class="fw-mono fw-semibold"><?= htmlspecialchars($v['registration']) ?></span></td>
              <td class="text-muted small"><?= htmlspecialchars(($v['brand'] ?? '') . ' ' . ($v['model'] ?? '')) ?></td>
              <td class="text-muted small"><?= htmlspecialchars($v['tachograph_type'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
// Queue the chart config – actual init runs after Chart.js loads at end of layout
(window.__chartQueue = window.__chartQueue || []).push({
  id: 'activityChart',
  config: {
    type: 'bar',
    data: {
      labels: <?= json_encode($chartData['labels']) ?>,
      datasets: [
        { label: 'Jazda',       data: <?= json_encode($chartData['driving']) ?>, backgroundColor: '#4f8ef7' },
        { label: 'Praca',       data: <?= json_encode($chartData['work'])    ?>, backgroundColor: '#fbbf24' },
        { label: 'Odpoczynek',  data: <?= json_encode($chartData['rest'])    ?>, backgroundColor: '#6b7280' },
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      plugins: { legend: { labels: { color: '#9ca3af', boxWidth: 12 } } },
      scales: {
        x: { stacked: true, ticks: { color: '#9ca3af' }, grid: { color: '#2d3250' } },
        y: { stacked: true, ticks: { color: '#9ca3af' }, grid: { color: '#2d3250' } }
      }
    }
  }
});
</script>
