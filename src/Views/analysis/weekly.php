<?php
/**
 * @var array  $file
 * @var string $weekStart
 * @var string $weekEnd
 * @var array  $weekDates              – 7 Y-m-d strings (Mon–Sun)
 * @var array  $weeklyData             – [date => [type => minutes]]
 * @var array  $weekActivitiesByDay    – [date => [activity_record, ...]]
 * @var array  $violations
 * @var array  $weekKeys
 * @var int    $fileId
 */

// ── Palette matching professional tachograph reference ───────────────────
$actColors = [
    'driving'      => '#e53e3e',   // vivid red   – Prowadzenie pojazdu
    'work'         => '#ed8936',   // orange      – Praca
    'availability' => '#48bb78',   // green       – Dyspozycja
    'rest'         => '#4fd1c5',   // cyan        – Odpoczynek
    'break'        => '#f6e05e',   // yellow      – Przerwa
];
$actLabels = [
    'driving'      => 'Prowadzenie pojazdu',
    'work'         => 'Praca',
    'availability' => 'Dyspozycja',
    'rest'         => 'Odpoczynek',
    'break'        => 'Przerwa',
];

// ── Weekly totals ────────────────────────────────────────────────────────
$weekTotals = ['driving' => 0, 'work' => 0, 'availability' => 0, 'rest' => 0, 'break' => 0];
foreach ($weeklyData as $row) {
    foreach ($weekTotals as $type => $_) {
        $weekTotals[$type] += ($row[$type] ?? 0);
    }
}
$drivingTotal  = $weekTotals['driving'];
$workTotal     = $weekTotals['work'];
$combinedTotal = $drivingTotal + $workTotal;
$weekNum       = (int) date('W', strtotime($weekStart));
$fmt           = fn(int $m): string => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
$dayNames      = ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Nd'];

// Backward compat: if controller didn't pass weekActivitiesByDay yet
if (!isset($weekActivitiesByDay)) {
    $weekActivitiesByDay = array_fill_keys($weekDates, []);
}

// ── SVG chart layout constants ────────────────────────────────────────────
$svgW      = 980;   // viewBox total width
$colW      = 126;   // column content width
$colGap    = 14;    // gap between columns
$colStep   = $colW + $colGap;  // 140 per column
$topPad    = 20;    // space above bars (day name)
$barH      = 210;   // height of 24h bar area
$svgH      = $topPad + $barH + 62; // + stats + date zone
$dpm       = $barH / 1440;  // SVG units per minute
$statsY    = $topPad + $barH + 10; // driving total Y
$restY     = $statsY + 17;         // rest total Y
$dateY     = $restY + 17;          // date label Y
$limits    = ['driving' => 540, 'work' => null, 'availability' => null, 'rest' => 660, 'break' => null];
?>

<!-- ── Navigation ────────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Analiza tygodniowa</h4>
    <p class="text-muted small mb-0"><?= htmlspecialchars($file['original_name']) ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (count($weekKeys) > 1): ?>
    <select class="form-select form-select-sm" style="width:auto"
            onchange="window.location='/analysis/<?= $fileId ?>/weekly?week='+this.value">
      <?php foreach ($weekKeys as $wk): ?>
      <option value="<?= $wk ?>" <?= $wk === $weekStart ? 'selected' : '' ?>>
        Tydz. <?= date('W', strtotime($wk)) ?>: <?= date('d.m', strtotime($wk)) ?>–<?= date('d.m.Y', strtotime($wk.' +6 days')) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <a href="/analysis/<?= $fileId ?>/daily" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-calendar-day me-1"></i>Dzienny
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-printer me-1"></i>Drukuj
    </button>
  </div>
</div>

<!-- ── Tachograph weekly chart card ─────────────────────────────────────── -->
<div class="card border-0 mb-4" style="background:#1a1d27">

  <!-- Week header matching professional tacho software -->
  <div class="card-header border-0 bg-transparent pb-1 pt-3">
    <div class="d-flex flex-wrap align-items-center gap-3">
      <span class="fw-bold" style="font-size:.95rem">
        Tydzień <?= $weekNum ?> (od <?= date('d.m.Y', strtotime($weekStart)) ?> do <?= date('d.m.Y', strtotime($weekEnd)) ?>)
      </span>
      <?php if ($drivingTotal > 0): ?>
      <span class="d-flex align-items-center gap-1 small">
        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:<?= $actColors['driving'] ?>"></span>
        <span class="text-muted">Prowadzenie pojazdu (<?= $fmt($drivingTotal) ?>)</span>
      </span>
      <?php endif; ?>
      <?php if ($workTotal > 0): ?>
      <span class="d-flex align-items-center gap-1 small">
        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:<?= $actColors['work'] ?>"></span>
        <span class="text-muted">Praca (<?= $fmt($workTotal) ?>)</span>
      </span>
      <?php endif; ?>
      <?php if ($combinedTotal > 0): ?>
      <span class="d-flex align-items-center gap-1 small">
        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#f97316"></span>
        <span class="text-muted">Prowadzenie+Praca (<?= $fmt($combinedTotal) ?>)</span>
      </span>
      <?php endif; ?>
    </div>
  </div>

  <div class="card-body px-3 pb-3 pt-2">

    <!-- SVG tachograph timeline (24h per-day vertical columns) -->
    <svg viewBox="0 0 <?= $svgW ?> <?= $svgH ?>"
         style="width:100%;display:block"
         preserveAspectRatio="xMidYMid meet">
      <defs>
        <?php foreach ($weekDates as $i => $d): ?>
        <clipPath id="tacho-clip-<?= $i ?>">
          <rect x="<?= $i * $colStep ?>" y="<?= $topPad ?>"
                width="<?= $colW ?>" height="<?= $barH ?>"/>
        </clipPath>
        <?php endforeach; ?>
      </defs>

      <?php
      // ── Shared hour reference lines across all columns ────────────────
      foreach ([0, 6, 12, 18, 24] as $rh) {
          $ry = round($topPad + $rh * 60 * $dpm, 1);
          foreach ($weekDates as $i => $d) {
              $cx = $i * $colStep;
              echo sprintf(
                  '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="#1e2535" stroke-width="0.7"/>',
                  $cx, $ry, $cx + $colW, $ry
              );
          }
      }

      // ── Per-column (day) rendering ────────────────────────────────────
      foreach ($weekDates as $i => $d):
          $colX    = $i * $colStep;
          $dayActs = $weekActivitiesByDay[$d] ?? [];
          $dayRow  = $weeklyData[$d]          ?? ['driving'=>0,'work'=>0,'availability'=>0,'rest'=>0,'break'=>0];
          $drivMin = (int)($dayRow['driving']  ?? 0);
          $restMin = (int)($dayRow['rest'] ?? 0) + (int)($dayRow['break'] ?? 0);
          $anyData = array_sum($dayRow) > 0;
      ?>
      <!-- Day <?= $i+1 ?> – <?= $d ?> -->
      <rect x="<?= $colX ?>" y="<?= $topPad ?>" width="<?= $colW ?>" height="<?= $barH ?>"
            fill="<?= $anyData ? '#0d0f19' : '#090b12' ?>" rx="2"/>

      <?php foreach ($dayActs as $a):
          [$sh, $sm] = explode(':', $a['start_time']);
          [$eh, $em] = explode(':', $a['end_time']);
          $startMin = (int)$sh * 60 + (int)$sm;
          $endMin   = (int)$eh * 60 + (int)$em;
          // Handle activities that span midnight: clamp end to 1440 (midnight)
          if ($endMin <= $startMin) {
              $endMin = 1440; // render from start to end of day; next day carries the rest
          }
          $ay    = round($topPad + $startMin * $dpm, 2);
          $ah    = max(round(($endMin - $startMin) * $dpm, 2), 0.5);
          $color = $actColors[$a['activity_type']] ?? '#6b7280';
          $lbl   = $actLabels[$a['activity_type']] ?? $a['activity_type'];
          $tip   = htmlspecialchars(
              $lbl . '  ' . substr($a['start_time'],0,5) . '–' . substr($a['end_time'],0,5)
              . '  (' . intdiv($a['duration_minutes'],60) . 'h ' . ($a['duration_minutes']%60) . 'min)'
          );
      ?>
      <rect x="<?= $colX ?>" y="<?= $ay ?>" width="<?= $colW ?>" height="<?= $ah ?>"
            fill="<?= $color ?>" opacity="0.95"
            clip-path="url(#tacho-clip-<?= $i ?>)">
        <title><?= $tip ?></title>
      </rect>
      <?php endforeach; ?>

      <!-- Column border -->
      <rect x="<?= $colX ?>" y="<?= $topPad ?>" width="<?= $colW ?>" height="<?= $barH ?>"
            fill="none" stroke="#2d3260" stroke-width="0.5" rx="2"/>

      <!-- Day name above column -->
      <text x="<?= $colX + $colW/2 ?>" y="<?= $topPad - 4 ?>"
            text-anchor="middle" fill="#6b7280" font-size="9" font-family="sans-serif">
        <?= $dayNames[$i] ?>
      </text>

      <!-- Daily driving total (red) -->
      <text x="<?= $colX + $colW/2 ?>" y="<?= $statsY ?>"
            text-anchor="middle"
            fill="<?= $drivMin > 0 ? $actColors['driving'] : '#374151' ?>"
            font-size="10.5" font-family="monospace" font-weight="bold">
        <?= $drivMin > 0 ? $fmt($drivMin) : '—' ?>
      </text>

      <!-- Daily rest total (cyan) -->
      <?php if ($restMin > 0): ?>
      <text x="<?= $colX + $colW/2 ?>" y="<?= $restY ?>"
            text-anchor="middle" fill="<?= $actColors['rest'] ?>"
            font-size="9" font-family="monospace">
        <?= $fmt($restMin) ?>
      </text>
      <?php endif; ?>

      <!-- Date label (blue, links to daily view) -->
      <a href="/analysis/<?= $fileId ?>/daily?date=<?= $d ?>">
        <text x="<?= $colX + $colW/2 ?>" y="<?= $dateY ?>"
              text-anchor="middle" fill="#60a5fa"
              font-size="9.5" font-family="sans-serif"
              text-decoration="underline">
          <?= date('d.m.Y', strtotime($d)) ?>
        </text>
      </a>

      <?php endforeach; // day columns ?>

    </svg><!-- /svg -->

    <!-- Legend row -->
    <div class="d-flex flex-wrap gap-3 mt-3">
      <?php foreach ($actLabels as $type => $label):
          if ($weekTotals[$type] <= 0) continue; ?>
      <div class="d-flex align-items-center gap-2 small">
        <span style="display:inline-block;width:11px;height:11px;border-radius:2px;background:<?= $actColors[$type] ?>"></span>
        <span class="text-muted"><?= $label ?></span>
        <span class="fw-semibold" style="color:<?= $actColors[$type] ?>"><?= $fmt($weekTotals[$type]) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

  </div><!-- /card-body -->
</div><!-- /chart card -->

<!-- ── Summary table ─────────────────────────────────────────────────────── -->
<div class="card border-0 mb-4" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0">Podsumowanie tygodnia</h6>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="text-muted small">
        <tr>
          <th>Dzień</th>
          <?php foreach ($actLabels as $type => $label): ?>
          <th><?= $label ?></th>
          <?php endforeach; ?>
          <th>Razem</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($weekDates as $d):
            $row      = $weeklyData[$d];
            $dayTotal = array_sum($row);
        ?>
        <tr>
          <td>
            <a href="/analysis/<?= $fileId ?>/daily?date=<?= $d ?>" class="text-decoration-none fw-semibold small">
              <?= date('D d.m', strtotime($d)) ?>
            </a>
          </td>
          <?php foreach ($actLabels as $type => $label): ?>
          <td class="small <?= ($limits[$type] && $row[$type] > $limits[$type]) ? 'text-danger fw-semibold' : 'text-muted' ?>">
            <?= $row[$type] > 0 ? $fmt($row[$type]) : '—' ?>
          </td>
          <?php endforeach; ?>
          <td class="small fw-semibold"><?= $dayTotal ? $fmt($dayTotal) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="fw-bold border-top">
        <tr>
          <td>Łącznie</td>
          <?php foreach ($actLabels as $type => $label): ?>
          <td style="color:<?= $weekTotals[$type] > ($limits[$type] ?? PHP_INT_MAX) ? '#ef4444' : 'inherit' ?>">
            <?= $fmt($weekTotals[$type]) ?>
          </td>
          <?php endforeach; ?>
          <td><?= $fmt(array_sum($weekTotals)) ?></td>
        </tr>
        <tr class="text-muted small">
          <td>Limit EU 561/2006</td>
          <td title="Art. 6(2) – max 56h/tydzień">56:00</td>
          <td colspan="3">—</td>
          <td title="Art. 6(3) – max 90h/2 tygodnie">90:00 /2tyg.</td>
          <td>—</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ── Violations ─────────────────────────────────────────────────────────── -->
<?php if (!empty($violations)): ?>
<div class="card border-0" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0 text-danger">
      <i class="bi bi-exclamation-triangle me-2"></i>Naruszenia – plik (<?= count($violations) ?>)
    </h6>
  </div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead class="text-muted small">
        <tr><th>Typ</th><th>Opis</th><th>Podstawa prawna</th><th>Grzywna</th><th>Waga</th></tr>
      </thead>
      <tbody>
        <?php foreach ($violations as $v): ?>
        <tr>
          <td class="small"><?= htmlspecialchars($v['violation_type']) ?></td>
          <td class="small"><?= htmlspecialchars($v['description']) ?></td>
          <td class="small text-primary"><?= htmlspecialchars($v['regulation_ref'] ?? '') ?></td>
          <td class="small text-warning">
            <?= $v['fine_amount_min'] ? number_format($v['fine_amount_min'],0,',',' ') . '–' . number_format($v['fine_amount_max'],0,',',' ') . ' zł' : '—' ?>
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
<?php endif; ?>

<style>
@media print {
  #sidebar, .topbar, .btn, select { display: none !important; }
  .main-wrapper { margin: 0 !important; }
  .card { border: 1px solid #ccc !important; }
}
</style>
