<?php
/**
 * @var array  $file
 * @var string $date
 * @var array  $dates
 * @var array  $dayActivities
 * @var array  $totals
 * @var array  $violations
 * @var int    $fileId
 */
$actColors = [
    'driving'      => '#4f8ef7',
    'work'         => '#fbbf24',
    'availability' => '#34d399',
    'rest'         => '#6b7280',
    'break'        => '#a78bfa',
];
$actLabels = [
    'driving'      => 'Jazda',
    'work'         => 'Praca',
    'availability' => 'Dyspozycja',
    'rest'         => 'Odpoczynek',
    'break'        => 'Przerwa',
];
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Analiza dzienna</h4>
    <p class="text-muted small mb-0"><?= htmlspecialchars($file['original_name']) ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <!-- Date navigation -->
    <select class="form-select form-select-sm" style="width:auto"
            onchange="window.location='/analysis/<?= $fileId ?>/daily?date='+this.value">
      <?php foreach ($dates as $d): ?>
      <option value="<?= $d ?>" <?= $d === $date ? 'selected' : '' ?>><?= date('d.m.Y (l)', strtotime($d)) ?></option>
      <?php endforeach; ?>
    </select>
    <a href="/analysis/<?= $fileId ?>/weekly" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-calendar-week me-1"></i>Tygodniowy
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-printer me-1"></i>Drukuj
    </button>
  </div>
</div>

<!-- Totals -->
<div class="row g-3 mb-4">
  <?php foreach ($actLabels as $type => $label): ?>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 h-100" style="background:#1a1d27;border-left:3px solid <?= $actColors[$type] ?>!important">
      <div class="card-body py-3 px-3">
        <div class="small text-muted"><?= $label ?></div>
        <div class="fw-bold fs-5 mt-1" style="color:<?= $actColors[$type] ?>">
          <?= intdiv($totals[$type], 60) ?>h <?= $totals[$type] % 60 ?>m
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Timeline -->
<div class="card border-0 mb-4" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0">Oś czasu aktywności – <?= $date ? date('d.m.Y', strtotime($date)) : '' ?></h6>
  </div>
  <div class="card-body">
    <?php if (empty($dayActivities)): ?>
    <div class="text-center text-muted py-3">Brak aktywności w tym dniu.</div>
    <?php else: ?>
    <!-- Gantt bar -->
    <div class="timeline-container mb-3">
      <div class="timeline-bar" style="position:relative;height:40px;background:#0f1117;border-radius:4px;overflow:hidden">
        <?php
        $dayStart = 0; $dayEnd = 1440; $dayLen = $dayEnd - $dayStart;
        foreach ($dayActivities as $a):
            list($sh,$sm) = explode(':', $a['start_time']);
            list($eh,$em) = explode(':', $a['end_time']);
            $startMin  = (int)$sh * 60 + (int)$sm;
            $endMin    = (int)$eh * 60 + (int)$em;
            if ($endMin <= $startMin) continue;
            $left  = round(($startMin / $dayLen) * 100, 2);
            $width = round((($endMin - $startMin) / $dayLen) * 100, 2);
            $color = $actColors[$a['activity_type']] ?? '#6b7280';
            $title = $actLabels[$a['activity_type']] . ' ' . substr($a['start_time'],0,5) . '–' . substr($a['end_time'],0,5) . ' (' . $a['duration_minutes'] . ' min)';
        ?>
        <div title="<?= htmlspecialchars($title) ?>"
             style="position:absolute;top:0;bottom:0;left:<?= $left ?>%;width:<?= max($width,0.3) ?>%;background:<?= $color ?>;opacity:.9"></div>
        <?php endforeach; ?>
      </div>
      <!-- Hour markers -->
      <div class="d-flex justify-content-between mt-1" style="font-size:.65rem;color:#6b7280">
        <?php for ($h = 0; $h <= 24; $h += 2): ?>
        <span><?= sprintf('%02d:00', $h) ?></span>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Legend -->
    <div class="d-flex flex-wrap gap-3 mb-4">
      <?php foreach ($actLabels as $type => $label): ?>
      <div class="d-flex align-items-center gap-1 small">
        <div style="width:14px;height:14px;border-radius:3px;background:<?= $actColors[$type] ?>"></div>
        <span class="text-muted"><?= $label ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Detail table -->
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="text-muted small"><tr><th>Aktywność</th><th>Start</th><th>Koniec</th><th>Czas trwania</th><th>Kraj</th></tr></thead>
        <tbody>
          <?php foreach ($dayActivities as $a): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div style="width:10px;height:10px;border-radius:50%;background:<?= $actColors[$a['activity_type']] ?? '#ccc' ?>"></div>
                <span class="small"><?= $actLabels[$a['activity_type']] ?? $a['activity_type'] ?></span>
              </div>
            </td>
            <td class="font-monospace small"><?= substr($a['start_time'],0,5) ?></td>
            <td class="font-monospace small"><?= substr($a['end_time'],0,5) ?></td>
            <td class="text-muted small"><?= intdiv($a['duration_minutes'],60) ?>h <?= $a['duration_minutes']%60 ?>m</td>
            <td class="text-muted small"><?= htmlspecialchars($a['country_code'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Violations -->
<?php $dayVio = array_filter($violations, fn($v) => true /* all */); ?>
<?php if (!empty($violations)): ?>
<div class="card border-0 border-danger" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Naruszenia przepisów (<?= count($violations) ?>)</h6>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="text-muted small"><tr><th>Typ naruszenia</th><th>Opis</th><th>Podstawa prawna</th><th>Grzywna (zł)</th><th>Waga</th></tr></thead>
        <tbody>
          <?php foreach ($violations as $v): ?>
          <tr>
            <td class="small fw-semibold"><?= htmlspecialchars($v['violation_type']) ?></td>
            <td class="small"><?= htmlspecialchars($v['description']) ?></td>
            <td class="small text-primary"><?= htmlspecialchars($v['regulation_ref'] ?? '') ?></td>
            <td class="small text-warning text-nowrap">
              <?= $v['fine_amount_min'] ? number_format($v['fine_amount_min'],0,',',' ') . ' – ' . number_format($v['fine_amount_max'],0,',',' ') : '—' ?>
            </td>
            <td>
              <span class="badge bg-<?= $v['severity'] === 'critical' ? 'danger' : ($v['severity'] === 'major' ? 'warning text-dark' : 'secondary') ?>">
                <?= $v['severity'] === 'critical' ? 'KRYTYCZNE' : ($v['severity'] === 'major' ? 'POWAŻNE' : 'DROBNE') ?>
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

<style>
@media print {
  #sidebar, .topbar, .btn, select { display: none !important; }
  .main-wrapper { margin: 0 !important; }
  .card { border: 1px solid #ccc !important; }
}
</style>
