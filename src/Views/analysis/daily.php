<?php
/**
 * @var array       $file
 * @var string      $date
 * @var array       $dates
 * @var array       $dayActivities
 * @var array       $totals
 * @var array       $violations
 * @var int         $fileId
 * @var string|null $prevDate
 * @var string|null $nextDate
 * @var string|null $weekStart
 */

// ── Consistent palette with weekly view ──────────────────────────────────────
$actColors = [
    'driving'      => '#e53e3e',
    'work'         => '#ed8936',
    'availability' => '#48bb78',
    'rest'         => '#4fd1c5',
    'break'        => '#f6e05e',
];
$actLabels = [
    'driving'      => 'Prowadzenie pojazdu',
    'work'         => 'Praca',
    'availability' => 'Dyspozycja',
    'rest'         => 'Odpoczynek',
    'break'        => 'Przerwa',
];

$fmt = fn(int $m): string => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

// ── EU limits per day ─────────────────────────────────────────────────────────
$EU_DRIVING_NORMAL  = 540;   // 9h
$EU_DRIVING_EXTENDED= 600;   // 10h (max 2×/week)
$EU_BREAK           = 45;    // 45 min break after 4.5h
$EU_REST_DAILY      = 660;   // 11h minimum daily rest
$EU_CONTINUOUS_MAX  = 270;   // 4.5h = 270 min continuous driving

$drivingMin = $totals['driving'];
$restMin    = $totals['rest'] + $totals['break'];
$workMin    = $totals['work'];
$avMin      = $totals['availability'];

$dayTotal   = array_sum($totals);

// ── Build JS activity array (minutes from midnight) ─────────────────────────
$jsActs = [];
foreach ($dayActivities as $a) {
    $sp   = explode(':', $a['start_time']);
    $ep   = explode(':', $a['end_time']);
    $sMin = (int)$sp[0] * 60 + (int)$sp[1];
    $eMin = (int)$ep[0] * 60 + (int)$ep[1];
    if ($eMin <= $sMin) $eMin += 1440; // activity spans midnight – extend into next-day range
    $jsActs[] = [
        't'   => $a['activity_type'],
        's'   => $sMin,
        'e'   => $eMin,
        'lbl' => $actLabels[$a['activity_type']] ?? $a['activity_type'],
        'st'  => substr($a['start_time'], 0, 5),
        'et'  => substr($a['end_time'],   0, 5),
        'dur' => intdiv($a['duration_minutes'], 60) . 'h ' . ($a['duration_minutes'] % 60) . 'min',
        'idx' => $a['id'] ?? null,
    ];
}

// ── Compliance checks ─────────────────────────────────────────────────────────
function compliance_badge(bool $ok, string $ok_label, string $fail_label): string {
    $cls = $ok ? 'success' : 'danger';
    $lbl = $ok ? $ok_label : $fail_label;
    return "<span class=\"badge bg-{$cls} bg-opacity-20 text-{$cls} border border-{$cls} border-opacity-50 fw-normal\">{$lbl}</span>";
}
$drivingOk = $drivingMin <= $EU_DRIVING_NORMAL;
$restOk    = $restMin >= $EU_REST_DAILY || $drivingMin === 0;
?>

<!-- ── Navigation ─────────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Analiza dzienna</h4>
    <p class="text-muted small mb-0"><?= htmlspecialchars($file['original_name']) ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <!-- Prev / date picker / next -->
    <?php if ($prevDate): ?>
    <a href="/analysis/<?= $fileId ?>/daily?date=<?= $prevDate ?>"
       class="btn btn-sm btn-outline-secondary" title="<?= date('d.m.Y', strtotime($prevDate)) ?>">
      <i class="bi bi-chevron-left"></i>
    </a>
    <?php else: ?>
    <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
    <?php endif; ?>

    <select class="form-select form-select-sm" style="width:auto"
            onchange="window.location='/analysis/<?= $fileId ?>/daily?date='+this.value">
      <?php foreach ($dates as $d): ?>
      <option value="<?= $d ?>" <?= $d === $date ? 'selected' : '' ?>>
        <?= date('d.m.Y (l)', strtotime($d)) ?>
      </option>
      <?php endforeach; ?>
    </select>

    <?php if ($nextDate): ?>
    <a href="/analysis/<?= $fileId ?>/daily?date=<?= $nextDate ?>"
       class="btn btn-sm btn-outline-secondary" title="<?= date('d.m.Y', strtotime($nextDate)) ?>">
      <i class="bi bi-chevron-right"></i>
    </a>
    <?php else: ?>
    <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
    <?php endif; ?>

    <a href="/analysis/<?= $fileId ?>/weekly<?= $weekStart ? '?week='.$weekStart : '' ?>"
       class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-calendar-week me-1"></i>Tygodniowy
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-printer me-1"></i>Drukuj
    </button>
  </div>
</div>

<?php if (empty($dayActivities)): ?>
<!-- Empty state -->
<div class="card border-0 mb-4" style="background:#1a1d27">
  <div class="card-body py-6 text-center">
    <i class="bi bi-calendar-x fs-1 d-block mb-3 opacity-25"></i>
    <h5 class="text-muted">Brak danych dla tego dnia</h5>
    <p class="text-muted small">Nie znaleziono żadnych aktywności dla <?= $date ? date('d.m.Y', strtotime($date)) : 'wybranej daty' ?>.</p>
    <?php if ($prevDate || $nextDate): ?>
    <div class="d-flex justify-content-center gap-2 mt-3">
      <?php if ($prevDate): ?>
      <a href="/analysis/<?= $fileId ?>/daily?date=<?= $prevDate ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-chevron-left me-1"></i>Poprzedni dzień
      </a>
      <?php endif; ?>
      <?php if ($nextDate): ?>
      <a href="/analysis/<?= $fileId ?>/daily?date=<?= $nextDate ?>" class="btn btn-sm btn-outline-secondary">
        Następny dzień<i class="bi bi-chevron-right ms-1"></i>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>

<!-- ── Summary cards row ────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <?php
  $cards = [
      ['type'=>'driving',      'limit'=>$EU_DRIVING_NORMAL,  'limit_label'=>'limit 9:00'],
      ['type'=>'rest',         'limit'=>$EU_REST_DAILY,       'limit_label'=>'min 11:00'],
      ['type'=>'work',         'limit'=>null,                 'limit_label'=>null],
      ['type'=>'availability', 'limit'=>null,                 'limit_label'=>null],
      ['type'=>'break',        'limit'=>null,                 'limit_label'=>null],
  ];
  foreach ($cards as $card):
      $type  = $card['type'];
      $min   = ($type === 'rest') ? ($totals['rest'] + $totals['break']) : $totals[$type];
      $lim   = $card['limit'];
      $over  = $lim && $min > $lim;
      $under = ($type === 'rest') && $drivingMin > 0 && $min < $lim;
      $warn  = $over || $under;
  ?>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 h-100"
         style="background:#1a1d27;border-left:3px solid <?= $actColors[$type] ?> !important">
      <div class="card-body py-3 px-3">
        <div class="small text-muted"><?= $actLabels[$type] ?></div>
        <div class="fw-bold fs-5 mt-1 font-monospace" style="color:<?= $actColors[$type] ?>">
          <?= $min > 0 ? $fmt($min) : '—' ?>
        </div>
        <?php if ($lim && $min > 0): ?>
        <div class="mt-2">
          <div class="progress" style="height:3px;background:#1e2535">
            <div class="progress-bar" role="progressbar"
                 style="width:<?= min(100, round($min / $lim * 100)) ?>%;background:<?= $warn ? '#ef4444' : $actColors[$type] ?>"
                 aria-valuenow="<?= $min ?>" aria-valuemin="0" aria-valuemax="<?= $lim ?>"></div>
          </div>
          <div class="d-flex justify-content-between mt-1">
            <span class="text-muted" style="font-size:10px"><?= round($min / $lim * 100) ?>%</span>
            <span class="text-muted" style="font-size:10px"><?= $card['limit_label'] ?></span>
          </div>
        </div>
        <?php elseif ($lim && $type === 'rest' && $drivingMin > 0): ?>
        <div class="mt-2">
          <div class="progress" style="height:3px;background:#1e2535">
            <div class="progress-bar"
                 style="width:<?= min(100, round($min / $lim * 100)) ?>%;background:<?= $under ? '#ef4444' : $actColors[$type] ?>"
                 role="progressbar"></div>
          </div>
          <div class="d-flex justify-content-between mt-1">
            <span class="text-muted" style="font-size:10px"><?= round($min / $lim * 100) ?>%</span>
            <span class="text-muted" style="font-size:10px"><?= $card['limit_label'] ?></span>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── EU Compliance strip ───────────────────────────────────────────────────── -->
<?php if ($drivingMin > 0): ?>
<div class="card border-0 mb-4" style="background:#1a1d27">
  <div class="card-body py-2 px-3">
    <div class="d-flex flex-wrap gap-3 align-items-center">
      <span class="small text-muted fw-semibold me-1">EU 561/2006:</span>

      <?php // Daily driving limit
      if ($drivingMin <= $EU_DRIVING_NORMAL): ?>
      <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 fw-normal">
        <i class="bi bi-check-circle me-1"></i>Jazda OK (<?= $fmt($drivingMin) ?> / max 09:00)
      </span>
      <?php elseif ($drivingMin <= $EU_DRIVING_EXTENDED): ?>
      <span class="badge bg-warning bg-opacity-15 text-warning border border-warning border-opacity-25 fw-normal">
        <i class="bi bi-exclamation-circle me-1"></i>Jazda wydłużona (<?= $fmt($drivingMin) ?> / 10:00 – maks. 2×/tydz.)
      </span>
      <?php else: ?>
      <span class="badge bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25 fw-normal">
        <i class="bi bi-x-circle me-1"></i>Przekroczenie limitu jazdy (<?= $fmt($drivingMin) ?> / max 09:00)
      </span>
      <?php endif; ?>

      <?php // Daily rest
      if ($restMin >= $EU_REST_DAILY): ?>
      <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 fw-normal">
        <i class="bi bi-check-circle me-1"></i>Odpoczynek OK (<?= $fmt($restMin) ?> / min 11:00)
      </span>
      <?php elseif ($restMin >= 540): // reduced rest 9h ?>
      <span class="badge bg-warning bg-opacity-15 text-warning border border-warning border-opacity-25 fw-normal">
        <i class="bi bi-exclamation-circle me-1"></i>Odpoczynek skrócony (<?= $fmt($restMin) ?> / min 09:00 skrócony)
      </span>
      <?php else: ?>
      <span class="badge bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25 fw-normal">
        <i class="bi bi-x-circle me-1"></i>Niewystarczający odpoczynek (<?= $fmt($restMin) ?> / min 11:00)
      </span>
      <?php endif; ?>

      <?php // Break after 4.5h (note: checks total daily driving vs EU_CONTINUOUS_MAX as a heuristic;
            // accurate continuous-segment tracking would require per-segment analysis)
      $breakMin = $totals['break'];
      if ($drivingMin <= $EU_CONTINUOUS_MAX): ?>
      <span class="badge bg-secondary bg-opacity-15 text-secondary border border-secondary border-opacity-25 fw-normal">
        <i class="bi bi-info-circle me-1"></i>Jazda ≤ 4:30 – przerwa n/d
      </span>
      <?php elseif ($breakMin >= $EU_BREAK): ?>
      <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 fw-normal">
        <i class="bi bi-check-circle me-1"></i>Przerwa OK (<?= $fmt($breakMin) ?> ≥ 45min)
      </span>
      <?php else: ?>
      <span class="badge bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25 fw-normal">
        <i class="bi bi-x-circle me-1"></i>Brak wymaganej przerwy (<?= $fmt($breakMin) ?> / min 00:45)
      </span>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Interactive 24h Canvas Timeline ─────────────────────────────────────── -->
<div class="card border-0 mb-4" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent pt-3 pb-2">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <span class="fw-bold" style="font-size:.9rem">
        <i class="bi bi-clock-history me-2 text-primary"></i>
        Oś czasu – <?= $date ? date('l, d.m.Y', strtotime($date)) : '' ?>
      </span>
      <div class="d-flex align-items-center gap-2">
        <span class="text-muted small me-1">Powiększenie:</span>
        <button id="dzoom-out"   class="btn btn-sm btn-outline-secondary px-2 py-1">−</button>
        <span   id="dzoom-label" class="small text-muted font-monospace" style="min-width:3.5rem;text-align:center">24h</span>
        <button id="dzoom-in"    class="btn btn-sm btn-outline-secondary px-2 py-1">+</button>
        <button id="dzoom-reset" class="btn btn-sm btn-outline-secondary px-2 py-1" title="Cały dzień">↺</button>
      </div>
    </div>
  </div>
  <div class="card-body p-0">
    <div id="dtacho-wrap" style="position:relative;display:flex;overflow:hidden;user-select:none">
      <!-- Fixed left labels -->
      <canvas id="dtacho-labels" style="flex-shrink:0;display:block"></canvas>
      <!-- Scrollable timeline -->
      <div id="dtacho-scroll" style="flex:1;overflow-x:auto;overflow-y:hidden;cursor:grab;position:relative">
        <canvas id="dtacho-canvas" style="display:block"></canvas>
        <div id="dtacho-tooltip"
             style="display:none;position:fixed;z-index:9999;pointer-events:none;
                    background:rgba(15,17,27,0.95);border:1px solid #374151;border-radius:6px;
                    padding:6px 10px;font-size:12px;color:#e5e7eb;max-width:240px;line-height:1.5"></div>
      </div>
    </div>
    <!-- Legend -->
    <div id="dtacho-legend" class="d-flex flex-wrap gap-3 px-3 pb-3 pt-2 border-top border-secondary border-opacity-25">
      <?php foreach ($actLabels as $type => $label):
          if ($totals[$type] <= 0 && !($type === 'rest' && ($totals['rest'] + $totals['break']) > 0)) continue;
          $m = ($type === 'rest') ? ($totals['rest'] + $totals['break']) : $totals[$type];
          if ($m <= 0) continue; ?>
      <div class="d-flex align-items-center gap-2 small">
        <span style="display:inline-block;width:14px;height:14px;border-radius:3px;
                     background:<?= $actColors[$type] ?>;flex-shrink:0"></span>
        <span class="text-muted"><?= $label ?></span>
        <span class="fw-semibold font-monospace" style="color:<?= $actColors[$type] ?>"><?= $fmt($m) ?></span>
        <?php if ($dayTotal > 0): ?>
        <span class="text-muted" style="font-size:10px">(<?= round($m / $dayTotal * 100) ?>%)</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ── Activity detail table ─────────────────────────────────────────────────── -->
<div class="card border-0 mb-4" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between">
    <h6 class="fw-semibold mb-0">Szczegóły aktywności</h6>
    <span class="badge bg-secondary bg-opacity-30 text-muted fw-normal"><?= count($dayActivities) ?> wpisów</span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0" id="actTable">
      <thead style="background:#12141e">
        <tr class="text-muted small">
          <th class="ps-3" style="width:36px"></th>
          <th>Aktywność</th>
          <th>Start</th>
          <th>Koniec</th>
          <th>Czas trwania</th>
          <th>Narastająco jazda</th>
          <th>Kraj</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $cumulDriving = 0;
        foreach ($dayActivities as $idx => $a):
            $color = $actColors[$a['activity_type']] ?? '#6b7280';
            $label = $actLabels[$a['activity_type']] ?? $a['activity_type'];
            if ($a['activity_type'] === 'driving') {
                $cumulDriving += (int)$a['duration_minutes'];
            }
            $overDriving = $a['activity_type'] === 'driving' && $cumulDriving > $EU_DRIVING_NORMAL;
        ?>
        <tr data-act-idx="<?= $idx ?>"
            style="border-left:3px solid <?= $color ?>;cursor:pointer"
            onmouseenter="highlightAct(<?= $idx ?>)"
            onmouseleave="clearHighlight()">
          <td class="ps-3">
            <div style="width:10px;height:10px;border-radius:50%;background:<?= $color ?>"></div>
          </td>
          <td><span class="small fw-medium"><?= $label ?></span></td>
          <td class="font-monospace small"><?= substr($a['start_time'],0,5) ?></td>
          <td class="font-monospace small"><?= substr($a['end_time'],0,5) ?></td>
          <td class="small">
            <?php $h = intdiv($a['duration_minutes'],60); $m = $a['duration_minutes']%60; ?>
            <span class="fw-medium"><?= $h ?>h</span>
            <?php if ($m): ?><span class="text-muted"> <?= $m ?>m</span><?php endif; ?>
          </td>
          <td class="small font-monospace <?= $overDriving ? 'text-danger fw-semibold' : 'text-muted' ?>">
            <?= $a['activity_type'] === 'driving' ? $fmt($cumulDriving) : '—' ?>
            <?php if ($overDriving): ?><i class="bi bi-exclamation-triangle ms-1 text-danger"></i><?php endif; ?>
          </td>
          <td class="text-muted small"><?= htmlspecialchars($a['country_code'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="border-top" style="background:#12141e">
        <tr class="text-muted small fw-semibold">
          <td colspan="4" class="ps-3">Suma</td>
          <td class="font-monospace"><?= $fmt($dayTotal) ?></td>
          <td class="font-monospace <?= $drivingMin > $EU_DRIVING_NORMAL ? 'text-danger' : '' ?>"><?= $drivingMin > 0 ? $fmt($drivingMin) : '—' ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ── Violations ────────────────────────────────────────────────────────────── -->
<?php if (!empty($violations)): ?>
<div class="card border-0" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0 text-danger">
      <i class="bi bi-exclamation-triangle me-2"></i>Naruszenia (<?= count($violations) ?>)
    </h6>
  </div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead class="text-muted small">
        <tr><th>Typ</th><th>Opis</th><th>Podstawa prawna</th><th>Grzywna (zł)</th><th>Waga</th></tr>
      </thead>
      <tbody>
        <?php foreach ($violations as $v): ?>
        <tr>
          <td class="small fw-semibold"><?= htmlspecialchars($v['violation_type']) ?></td>
          <td class="small"><?= htmlspecialchars($v['description']) ?></td>
          <td class="small text-primary"><?= htmlspecialchars($v['regulation_ref'] ?? '') ?></td>
          <td class="small text-warning text-nowrap">
            <?= $v['fine_amount_min'] ? number_format($v['fine_amount_min'],0,',',' ') . '–' . number_format($v['fine_amount_max'],0,',',' ') : '—' ?>
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
<?php endif; ?>

<!-- ── JavaScript – Daily TachoTimeline ──────────────────────────────────────── -->
<script>
(function () {
  'use strict';

  const ACTIVITIES = <?= json_encode($jsActs, JSON_UNESCAPED_UNICODE) ?>;

  const COLORS = {
    driving:'#e53e3e', work:'#ed8936', availability:'#48bb78', rest:'#4fd1c5', break:'#f6e05e'
  };
  const ROW_ORDER = ['driving','work','availability','rest','break'];
  const ROW_LABELS= {driving:'Prowadzenie',work:'Praca',availability:'Dyspozycja',rest:'Odpoczynek',break:'Przerwa'};

  const DPR       = window.devicePixelRatio || 1;
  const LABEL_W   = 100;
  const RULER_H   = 26;
  const ROW_H     = 36;
  const CANVAS_H  = RULER_H + ROW_H * ROW_ORDER.length;
  const DAY_MINS  = 1440;

  let zoom      = 1;
  const ZOOM_MIN  = 1;
  const ZOOM_MAX  = 48;
  const ZOOM_STEP = 1.4;

  let drag = { active:false, startX:0, startScroll:0 };
  let pinch= { active:false, startDist:0, startZoom:1, startX:0 };

  let highlightedIdx = null;

  const labelsCanvas  = document.getElementById('dtacho-labels');
  const timelineCanvas= document.getElementById('dtacho-canvas');
  const scrollDiv     = document.getElementById('dtacho-scroll');
  const tooltip       = document.getElementById('dtacho-tooltip');
  const zoomLabel     = document.getElementById('dzoom-label');

  const lCtx = labelsCanvas.getContext('2d');
  const tCtx = timelineCanvas.getContext('2d');

  function ppm()  { return scrollDiv.clientWidth / DAY_MINS * zoom; }
  function cssW() { return Math.round(DAY_MINS * ppm()); }

  function updateZoomLabel() {
    const visibleHours = (scrollDiv.clientWidth / (ppm() * 60)).toFixed(1);
    zoomLabel.textContent = zoom <= 1.05 ? '24h' : visibleHours + 'h';
  }

  function setZoom(nz, pivotX) {
    const oldP = ppm();
    zoom = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, nz));
    const newP = ppm();
    if (pivotX !== undefined) {
      const rect = scrollDiv.getBoundingClientRect();
      const off  = pivotX - rect.left;
      const min  = (scrollDiv.scrollLeft + off) / oldP;
      scrollDiv.scrollLeft = min * newP - off;
    }
    updateZoomLabel();
    render();
  }

  function resizeCanvases() {
    const w = cssW();
    labelsCanvas.width  = LABEL_W * DPR; labelsCanvas.height = CANVAS_H * DPR;
    labelsCanvas.style.width  = LABEL_W + 'px'; labelsCanvas.style.height = CANVAS_H + 'px';
    timelineCanvas.width  = w * DPR; timelineCanvas.height = CANVAS_H * DPR;
    timelineCanvas.style.width  = w + 'px'; timelineCanvas.style.height = CANVAS_H + 'px';
    document.getElementById('dtacho-wrap').style.height = CANVAS_H + 'px';
  }

  function render() { resizeCanvases(); renderLabels(); renderTimeline(); }

  function renderLabels() {
    const c = lCtx;
    c.setTransform(DPR,0,0,DPR,0,0);
    c.clearRect(0,0,LABEL_W,CANVAS_H);
    c.fillStyle='#12141f'; c.fillRect(0,0,LABEL_W,CANVAS_H);
    // ruler placeholder
    c.fillStyle='#1a1d2e'; c.fillRect(0,0,LABEL_W,RULER_H);
    c.strokeStyle='#1e2535'; c.lineWidth=0.5;
    c.beginPath(); c.moveTo(0,RULER_H-0.5); c.lineTo(LABEL_W,RULER_H-0.5); c.stroke();
    // right border
    c.strokeStyle='#2d3260'; c.lineWidth=1;
    c.beginPath(); c.moveTo(LABEL_W-0.5,0); c.lineTo(LABEL_W-0.5,CANVAS_H); c.stroke();
    // rows
    ROW_ORDER.forEach((type,i)=>{
      const y = RULER_H + i*ROW_H;
      const isHi = highlightedIdx !== null && ACTIVITIES[highlightedIdx]?.t === type;
      c.fillStyle = isHi ? '#1c2040' : (i%2===0?'#0f1120':'#0a0d18');
      c.fillRect(0,y,LABEL_W,ROW_H);
      c.fillStyle = COLORS[type]; c.fillRect(0,y,4,ROW_H);
      c.fillStyle = isHi ? '#e5e7eb' : '#cbd5e0';
      c.font='11px sans-serif'; c.textBaseline='middle';
      c.fillText(ROW_LABELS[type], 10, y+ROW_H/2);
      c.strokeStyle='#1e2535'; c.lineWidth=0.5;
      c.beginPath(); c.moveTo(0,y+ROW_H-0.5); c.lineTo(LABEL_W,y+ROW_H-0.5); c.stroke();
    });
  }

  function renderTimeline() {
    const c = tCtx;
    const p = ppm();
    const w = cssW();
    c.setTransform(DPR,0,0,DPR,0,0);
    c.clearRect(0,0,w,CANVAS_H);
    c.fillStyle='#0d0f1a'; c.fillRect(0,0,w,CANVAS_H);

    // Row backgrounds
    ROW_ORDER.forEach((type,i)=>{
      const y = RULER_H + i*ROW_H;
      const isHi = highlightedIdx !== null && ACTIVITIES[highlightedIdx]?.t === type;
      c.fillStyle = isHi ? '#1c2040' : (i%2===0?'#0f1120':'#0a0d18');
      c.fillRect(0,y,w,ROW_H);
    });

    // Activity blocks
    ACTIVITIES.forEach((act,i)=>{
      const ri = ROW_ORDER.indexOf(act.t);
      if (ri===-1) return;
      const x  = act.s * p;
      const bw = Math.max((act.e-act.s)*p, 1.5);
      const y  = RULER_H + ri*ROW_H + 3;
      const h  = ROW_H - 6;
      const isHi = i === highlightedIdx;
      c.fillStyle = COLORS[act.t]||'#6b7280';
      c.globalAlpha = isHi ? 1 : 0.9;
      const r = Math.min(3, h/2, bw/2);
      c.beginPath();
      if(c.roundRect) c.roundRect(x,y,bw,h,r); else c.rect(x,y,bw,h);
      c.fill();
      if (isHi) {
        c.strokeStyle='#fff'; c.lineWidth=1.5; c.globalAlpha=0.7;
        c.beginPath();
        if(c.roundRect) c.roundRect(x,y,bw,h,r); else c.rect(x,y,bw,h);
        c.stroke();
      }
      c.globalAlpha = 1;
      if (bw > 36) {
        c.fillStyle='rgba(0,0,0,0.7)';
        c.font='9px monospace'; c.textBaseline='middle';
        c.fillText(act.st, x+4, y+h/2);
      }
    });

    // Ruler
    c.fillStyle='#1a1d2e'; c.fillRect(0,0,w,RULER_H);
    c.strokeStyle='#374151'; c.lineWidth=0.5;
    c.beginPath(); c.moveTo(0,RULER_H-0.5); c.lineTo(w,RULER_H-0.5); c.stroke();

    const hourPx = 60*p;
    let tick=1;
    if(hourPx<8)  tick=6;
    else if(hourPx<16) tick=3;
    else if(hourPx<30) tick=2;

    for(let h=0;h<=24;h+=tick){
      const tx = h*60*p;
      const isMaj = h%6===0;
      c.strokeStyle = isMaj?'#4a5568':'#2d3748';
      c.lineWidth   = isMaj?0.8:0.5;
      c.beginPath(); c.moveTo(tx,0); c.lineTo(tx,CANVAS_H); c.stroke();
      if(hourPx*tick>14){
        c.fillStyle  = isMaj?'#9ca3af':'#4a5568';
        c.font       = `${isMaj?9.5:8.5}px monospace`;
        c.textBaseline='middle'; c.textAlign='center';
        c.fillText(String(h).padStart(2,'0')+':00', tx, RULER_H/2);
      }
    }

    // Row separators
    ROW_ORDER.forEach((_,i)=>{
      const y = RULER_H+(i+1)*ROW_H-0.5;
      c.strokeStyle='#1e2535'; c.lineWidth=0.5;
      c.beginPath(); c.moveTo(0,y); c.lineTo(w,y); c.stroke();
    });
  }

  // ── Tooltip & hit test ──────────────────────────────────────────────────────
  function hitTest(cx,cy){
    const p = ppm();
    const min = cx/p;
    const rowTop = RULER_H;
    if(cy<rowTop) return null;
    const ri = Math.floor((cy-rowTop)/ROW_H);
    if(ri<0||ri>=ROW_ORDER.length) return null;
    const type = ROW_ORDER[ri];
    let best=null,bw=Infinity;
    ACTIVITIES.forEach((a,i)=>{
      if(a.t!==type) return;
      if(min>=a.s&&min<=a.e){ const w=a.e-a.s; if(w<bw){bw=w;best={a,i};} }
    });
    return best;
  }

  function showTip(html,cx,cy){
    tooltip.innerHTML=html; tooltip.style.display='block';
    const tw=tooltip.offsetWidth, th=tooltip.offsetHeight;
    let tx=cx+14, ty=cy-10;
    if(tx+tw>window.innerWidth-10) tx=cx-tw-14;
    if(ty+th>window.innerHeight-10) ty=cy-th-10;
    tooltip.style.left=tx+'px'; tooltip.style.top=ty+'px';
  }
  function hideTip(){ tooltip.style.display='none'; }

  // ── JS↔table highlight bridge ────────────────────────────────────────────
  window.highlightAct = function(idx) {
    highlightedIdx = idx;
    render();
    // scroll canvas so the activity is visible
    if(ACTIVITIES[idx]){
      const a = ACTIVITIES[idx];
      const p = ppm();
      const cx = (a.s+a.e)/2*p;
      const half = scrollDiv.clientWidth/2;
      scrollDiv.scrollLeft = Math.max(0, cx-half);
    }
  };
  window.clearHighlight = function() { highlightedIdx=null; render(); };

  // ── Events ──────────────────────────────────────────────────────────────────
  document.getElementById('dzoom-in').addEventListener('click', ()=>setZoom(zoom*ZOOM_STEP));
  document.getElementById('dzoom-out').addEventListener('click',()=>setZoom(zoom/ZOOM_STEP));
  document.getElementById('dzoom-reset').addEventListener('click',()=>setZoom(1));

  scrollDiv.addEventListener('wheel',e=>{
    e.preventDefault();
    setZoom(zoom*(e.deltaY<0?ZOOM_STEP:1/ZOOM_STEP), e.clientX);
  },{passive:false});

  timelineCanvas.addEventListener('mousemove',e=>{
    const rect=timelineCanvas.getBoundingClientRect();
    const hit=hitTest(e.clientX-rect.left, e.clientY-rect.top);
    if(!hit){hideTip();timelineCanvas.style.cursor='default';return;}
    const {a,i}=hit;
    showTip(
      `<div style="color:${COLORS[a.t]};font-weight:600">${a.lbl}</div>
       <div class="text-muted">${a.st} – ${a.et}</div>
       <div><strong>${a.dur}</strong></div>`,
      e.clientX, e.clientY
    );
    timelineCanvas.style.cursor='pointer';
    if(highlightedIdx!==i){ highlightedIdx=i; render(); }
  });
  timelineCanvas.addEventListener('mouseleave',()=>{ hideTip(); highlightedIdx=null; render(); });

  scrollDiv.addEventListener('mousedown',e=>{
    if(e.button!==0)return;
    drag={active:true,startX:e.clientX,startScroll:scrollDiv.scrollLeft};
    scrollDiv.style.cursor='grabbing';
  });
  window.addEventListener('mousemove',e=>{ if(!drag.active)return; scrollDiv.scrollLeft=drag.startScroll-(e.clientX-drag.startX); });
  window.addEventListener('mouseup',()=>{ drag.active=false; scrollDiv.style.cursor='grab'; });

  scrollDiv.addEventListener('touchstart',e=>{
    if(e.touches.length===2){
      e.preventDefault();
      const dx=e.touches[0].clientX-e.touches[1].clientX, dy=e.touches[0].clientY-e.touches[1].clientY;
      pinch={active:true,startDist:Math.hypot(dx,dy),startZoom:zoom,startX:(e.touches[0].clientX+e.touches[1].clientX)/2};
    } else if(e.touches.length===1){
      drag={active:true,startX:e.touches[0].clientX,startScroll:scrollDiv.scrollLeft};
    }
  },{passive:false});
  scrollDiv.addEventListener('touchmove',e=>{
    if(pinch.active&&e.touches.length===2){
      e.preventDefault();
      const dx=e.touches[0].clientX-e.touches[1].clientX,dy=e.touches[0].clientY-e.touches[1].clientY;
      setZoom(pinch.startZoom*Math.hypot(dx,dy)/pinch.startDist,(e.touches[0].clientX+e.touches[1].clientX)/2);
    } else if(drag.active&&e.touches.length===1){
      e.preventDefault();
      scrollDiv.scrollLeft=drag.startScroll-(e.touches[0].clientX-drag.startX);
    }
  },{passive:false});
  scrollDiv.addEventListener('touchend',()=>{ pinch.active=false; drag.active=false; });

  let resizeT;
  window.addEventListener('resize',()=>{ clearTimeout(resizeT); resizeT=setTimeout(render,80); });

  render();
})();
</script>

<?php endif; ?>

<style>
#dtacho-scroll { scrollbar-width:thin; scrollbar-color:#374151 #0d0f1a; }
#dtacho-scroll::-webkit-scrollbar { height:6px; }
#dtacho-scroll::-webkit-scrollbar-track { background:#0d0f1a; }
#dtacho-scroll::-webkit-scrollbar-thumb { background:#374151; border-radius:3px; }
#actTable tbody tr:hover { background: rgba(255,255,255,0.03) !important; }
@media print {
  #sidebar,.topbar,.btn,select{ display:none !important; }
  .main-wrapper{ margin:0 !important; }
  .card{ border:1px solid #ccc !important; }
}
</style>
