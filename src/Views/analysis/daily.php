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
  <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center">
    <h6 class="fw-semibold mb-0">
      <i class="bi bi-clock-history me-2 text-primary"></i>Oś czasu aktywności – <?= $date ? date('d.m.Y', strtotime($date)) : '' ?>
    </h6>
  </div>
  <div class="card-body">
    <?php if (empty($dayActivities)): ?>
    <div class="text-center text-muted py-5">
      <i class="bi bi-calendar-x fs-2 mb-2 d-block opacity-50"></i>
      Brak aktywności w tym dniu.
    </div>
    <?php else: ?>

    <!-- Professional SVG Gantt timeline -->
    <div class="timeline-pro mb-4" id="timelinePro">
      <?php
      $dayLen  = 1440;
      $svgW    = 1000; // viewBox width
      $svgH    = 56;   // total SVG height
      $barY    = 8;    // bar top
      $barH    = 28;   // bar height
      $tickY   = $barY + $barH + 6;
      $labelY  = $tickY + 10;
      ?>
      <svg viewBox="0 0 <?= $svgW ?> <?= $svgH ?>" preserveAspectRatio="none"
           style="width:100%;height:<?= $svgH ?>px;display:block" id="timelineSvg">
        <!-- Background track -->
        <rect x="0" y="<?= $barY ?>" width="<?= $svgW ?>" height="<?= $barH ?>"
              rx="4" fill="#0d0f16"/>

        <?php foreach ($dayActivities as $a):
            [$sh, $sm] = explode(':', $a['start_time']);
            [$eh, $em] = explode(':', $a['end_time']);
            $startMin = (int)$sh * 60 + (int)$sm;
            $endMin   = (int)$eh * 60 + (int)$em;
            if ($endMin <= $startMin) continue;
            $x = round(($startMin / $dayLen) * $svgW, 2);
            $w = max(round((($endMin - $startMin) / $dayLen) * $svgW, 2), 1);
            $color = $actColors[$a['activity_type']] ?? '#6b7280';
            $label = $actLabels[$a['activity_type']] ?? $a['activity_type'];
            $tip   = htmlspecialchars("$label  " . substr($a['start_time'],0,5) . '–' . substr($a['end_time'],0,5) . '  (' . intdiv($a['duration_minutes'],60) . 'h ' . ($a['duration_minutes']%60) . 'min)');
        ?>
        <rect x="<?= $x ?>" y="<?= $barY ?>" width="<?= $w ?>" height="<?= $barH ?>"
              fill="<?= $color ?>" opacity="0.92" rx="2">
          <title><?= $tip ?></title>
        </rect>
        <?php if ($w >= 28): ?>
        <text x="<?= $x + $w/2 ?>" y="<?= $barY + $barH/2 + 5 ?>"
              text-anchor="middle" fill="#fff" font-size="9" font-family="monospace" opacity="0.85">
          <?= substr($a['start_time'],0,5) ?>
        </text>
        <?php endif; ?>
        <?php endforeach; ?>

        <!-- Hour tick lines (every 2h) -->
        <?php for ($h = 0; $h <= 24; $h += 2):
            $tx = round(($h * 60 / $dayLen) * $svgW); ?>
        <line x1="<?= $tx ?>" y1="<?= $barY + $barH ?>" x2="<?= $tx ?>" y2="<?= $tickY ?>"
              stroke="#374151" stroke-width="1"/>
        <text x="<?= $tx ?>" y="<?= $labelY ?>" text-anchor="middle"
              fill="#6b7280" font-size="8.5" font-family="monospace">
          <?= sprintf('%02d:00', $h) ?>
        </text>
        <?php endfor; ?>
      </svg>
    </div>

    <!-- Legend -->
    <div class="d-flex flex-wrap gap-3 mb-4 px-1">
      <?php foreach ($actLabels as $type => $label):
          $used = array_filter($dayActivities, fn($a) => $a['activity_type'] === $type);
          $mins = array_sum(array_column(array_values($used), 'duration_minutes'));
      ?>
      <div class="d-flex align-items-center gap-2 small">
        <div style="width:12px;height:12px;border-radius:3px;background:<?= $actColors[$type] ?>;flex-shrink:0"></div>
        <span class="text-muted"><?= $label ?></span>
        <?php if ($mins > 0): ?>
        <span class="fw-semibold" style="color:<?= $actColors[$type] ?>">
          <?= intdiv($mins,60) ?>h&nbsp;<?= $mins%60 ?>m
        </span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Detail table -->
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead style="background:#12141e">
          <tr class="text-muted small">
            <th class="ps-3">Aktywność</th>
            <th>Start</th>
            <th>Koniec</th>
            <th>Czas trwania</th>
            <th>Kraj</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dayActivities as $a):
              $color = $actColors[$a['activity_type']] ?? '#6b7280';
              $label = $actLabels[$a['activity_type']] ?? $a['activity_type'];
          ?>
          <tr style="border-left:3px solid <?= $color ?>">
            <td class="ps-3">
              <div class="d-flex align-items-center gap-2">
                <div style="width:8px;height:8px;border-radius:50%;background:<?= $color ?>;flex-shrink:0"></div>
                <span class="small fw-medium"><?= $label ?></span>
              </div>
            </td>
            <td class="font-monospace small"><?= substr($a['start_time'],0,5) ?></td>
            <td class="font-monospace small"><?= substr($a['end_time'],0,5) ?></td>
            <td class="small">
              <?php $h = intdiv($a['duration_minutes'],60); $m = $a['duration_minutes']%60; ?>
              <span class="fw-medium"><?= $h ?>h</span>
              <?php if ($m): ?><span class="text-muted"> <?= $m ?>m</span><?php endif; ?>
            </td>
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
