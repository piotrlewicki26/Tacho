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

// ── Palette ───────────────────────────────────────────────────────────────
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

// ── Weekly totals ─────────────────────────────────────────────────────────
$weekTotals = ['driving' => 0, 'work' => 0, 'availability' => 0, 'rest' => 0, 'break' => 0];
foreach ($weeklyData as $row) {
    foreach ($weekTotals as $type => $_) {
        $weekTotals[$type] += ($row[$type] ?? 0);
    }
}
$fmt     = fn(int $m): string => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
$weekNum = (int) date('W', strtotime($weekStart));
$limits  = ['driving' => 540, 'work' => null, 'availability' => null, 'rest' => 660, 'break' => null];

if (!isset($weekActivitiesByDay)) {
    $weekActivitiesByDay = array_fill_keys($weekDates, []);
}

// ── Build flat activity array for JS (minutes from week start) ────────────
$flatActivities = [];
foreach ($weekDates as $i => $d) {
    $dayBase = $i * 1440;
    foreach ($weekActivitiesByDay[$d] ?? [] as $a) {
        $sp = explode(':', $a['start_time']);
        $ep = explode(':', $a['end_time']);
        $sMin = (int)$sp[0] * 60 + (int)$sp[1];
        $eMin = (int)$ep[0] * 60 + (int)$ep[1];
        if ($eMin <= $sMin) $eMin = 1440;
        $flatActivities[] = [
            't'   => $a['activity_type'],
            's'   => $dayBase + $sMin,
            'e'   => $dayBase + $eMin,
            'lbl' => $actLabels[$a['activity_type']] ?? $a['activity_type'],
            'st'  => substr($a['start_time'], 0, 5),
            'et'  => substr($a['end_time'], 0, 5),
            'dur' => intdiv($a['duration_minutes'], 60) . 'h ' . ($a['duration_minutes'] % 60) . 'min',
            'day' => $i,
        ];
    }
}

// ── Day info for JS ───────────────────────────────────────────────────────
$polishDayNames = ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Nd'];
$dayInfo = [];
foreach ($weekDates as $i => $d) {
    $dayInfo[] = [
        'name'  => $polishDayNames[$i],
        'label' => date('d.m', strtotime($d)),
        'full'  => date('d.m.Y', strtotime($d)),
        'url'   => '/analysis/' . $fileId . '/daily?date=' . $d,
        'date'  => $d,
    ];
}
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

<!-- ── Timeline Card ─────────────────────────────────────────────────────── -->
<div class="card border-0 mb-4" style="background:#1a1d27">

  <div class="card-header border-0 bg-transparent pt-3 pb-2">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <span class="fw-bold" style="font-size:.95rem">
        Tydzień <?= $weekNum ?>
        <span class="text-muted fw-normal">(<?= date('d.m.Y', strtotime($weekStart)) ?> – <?= date('d.m.Y', strtotime($weekEnd)) ?>)</span>
      </span>
      <!-- Zoom controls -->
      <div class="d-flex align-items-center gap-2">
        <span class="text-muted small me-1">Powiększenie:</span>
        <button id="tzoom-out"  class="btn btn-sm btn-outline-secondary px-2 py-1" title="Pomniejsz (kółko myszy)">−</button>
        <span id="tzoom-label" class="small text-muted font-monospace" style="min-width:4.5rem;text-align:center">Cały tydzień</span>
        <button id="tzoom-in"   class="btn btn-sm btn-outline-secondary px-2 py-1" title="Powiększ (kółko myszy)">+</button>
        <button id="tzoom-reset" class="btn btn-sm btn-outline-secondary px-2 py-1" title="Cały tydzień">↺</button>
      </div>
    </div>
  </div>

  <div class="card-body p-0">

    <!-- Timeline layout: fixed labels | scrollable canvas -->
    <div id="tacho-timeline-wrap" style="position:relative;display:flex;overflow:hidden;user-select:none">

      <!-- Left: fixed activity-type labels -->
      <canvas id="tacho-labels"
              style="flex-shrink:0;display:block;cursor:default"></canvas>

      <!-- Right: scrollable timeline -->
      <div id="tacho-scroll"
           style="flex:1;overflow-x:auto;overflow-y:hidden;cursor:grab;position:relative">
        <canvas id="tacho-canvas" style="display:block"></canvas>

        <!-- Tooltip (HTML overlay for crisp text) -->
        <div id="tacho-tooltip"
             style="display:none;position:fixed;z-index:9999;pointer-events:none;
                    background:rgba(15,17,27,0.95);border:1px solid #374151;
                    border-radius:6px;padding:6px 10px;font-size:12px;
                    color:#e5e7eb;max-width:220px;line-height:1.5"></div>
      </div>

    </div><!-- /wrap -->

    <!-- Legend -->
    <div class="d-flex flex-wrap gap-3 px-3 pb-3 pt-2 border-top border-secondary border-opacity-25">
      <?php foreach ($actLabels as $type => $label):
          if ($weekTotals[$type] <= 0) continue; ?>
      <div class="d-flex align-items-center gap-2 small">
        <span style="display:inline-block;width:14px;height:14px;border-radius:3px;
                     background:<?= $actColors[$type] ?>;flex-shrink:0"></span>
        <span class="text-muted"><?= $label ?></span>
        <span class="fw-semibold" style="color:<?= $actColors[$type] ?>"><?= $fmt($weekTotals[$type]) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

  </div><!-- /card-body -->
</div><!-- /card -->

<!-- ── Summary Table ─────────────────────────────────────────────────────── -->
<div class="card border-0 mb-4" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0">Podsumowanie tygodnia</h6>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="text-muted small">
        <tr>
          <th>Dzień</th>
          <?php foreach ($actLabels as $type => $label): ?><th><?= $label ?></th><?php endforeach; ?>
          <th>Razem</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($weekDates as $d):
            $row      = $weeklyData[$d];
            $dayTotal = array_sum($row); ?>
        <tr>
          <td>
            <a href="/analysis/<?= $fileId ?>/daily?date=<?= $d ?>"
               class="text-decoration-none fw-semibold small">
              <?= date('D d.m', strtotime($d)) ?>
            </a>
          </td>
          <?php foreach ($actLabels as $type => $label): ?>
          <td class="small <?= ($limits[$type] && $row[$type] > $limits[$type]) ? 'text-danger fw-semibold' : 'text-muted' ?>">
            <?= $row[$type] > 0 ? $fmt($row[$type]) : '—' ?>
          </td>
          <?php endforeach; ?>
          <td class="small fw-semibold"><?= $dayTotal > 0 ? $fmt($dayTotal) : '—' ?></td>
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

<!-- ── JavaScript – TachoTimeline ────────────────────────────────────────── -->
<script>
(function () {
  'use strict';

  // ── Data from PHP ────────────────────────────────────────────────────────
  const ACTIVITIES = <?= json_encode($flatActivities, JSON_UNESCAPED_UNICODE) ?>;
  const DAY_INFO   = <?= json_encode($dayInfo,        JSON_UNESCAPED_UNICODE) ?>;

  // ── Color palette ────────────────────────────────────────────────────────
  const COLORS = {
    driving:      '#e53e3e',
    work:         '#ed8936',
    availability: '#48bb78',
    rest:         '#4fd1c5',
    break:        '#f6e05e',
  };
  const ROW_ORDER = ['driving', 'work', 'availability', 'rest', 'break'];

  // ── Layout constants (device-pixel-ratio aware) ───────────────────────────
  const DPR         = window.devicePixelRatio || 1;
  const LABEL_W     = 100;  // CSS px – width of the fixed label panel
  const DAY_HDR_H   = 28;   // CSS px – day-header row height
  const RULER_H     = 24;   // CSS px – time ruler height
  const ROW_H       = 38;   // CSS px – each activity-type row
  const CANVAS_H    = DAY_HDR_H + RULER_H + ROW_H * ROW_ORDER.length; // 242 px
  const WEEK_MINS   = 7 * 1440; // 10080

  // ── State ────────────────────────────────────────────────────────────────
  let zoom     = 1;     // 1 = full week, higher = more zoomed-in
  const ZOOM_MIN  = 1;
  const ZOOM_MAX  = 48; // ≈ 3.5h visible at max zoom
  const ZOOM_STEP = 1.4;

  // Drag state
  let drag = { active: false, startX: 0, startScroll: 0 };
  // Touch pinch
  let pinch = { active: false, startDist: 0, startZoom: 1, startX: 0, startScroll: 0 };

  // ── DOM refs ─────────────────────────────────────────────────────────────
  const labelsCanvas  = document.getElementById('tacho-labels');
  const timelineCanvas= document.getElementById('tacho-canvas');
  const scrollDiv     = document.getElementById('tacho-scroll');
  const tooltip       = document.getElementById('tacho-tooltip');
  const zoomLabel     = document.getElementById('tzoom-label');

  const lCtx = labelsCanvas.getContext('2d');
  const tCtx = timelineCanvas.getContext('2d');

  // ── Helpers ───────────────────────────────────────────────────────────────
  function ppm() {
    // pixels per minute (CSS px)
    return scrollDiv.clientWidth / WEEK_MINS * zoom;
  }

  function totalCSSWidth() {
    return Math.round(WEEK_MINS * ppm());
  }

  function updateZoomLabel() {
    const visibleHours = (scrollDiv.clientWidth / (ppm() * 60)).toFixed(1);
    zoomLabel.textContent = zoom <= 1.05
      ? 'Cały tydzień'
      : visibleHours + 'h';
  }

  function setZoom(newZoom, pivotClientX) {
    const oldPpm = ppm();
    zoom = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, newZoom));
    const newPpm = ppm();
    // Maintain pivot point: shift scroll so the point under cursor stays fixed
    if (pivotClientX !== undefined) {
      const rect        = scrollDiv.getBoundingClientRect();
      const pivotOffset = pivotClientX - rect.left; // px from left edge of scroll area
      const pivotMinute = (scrollDiv.scrollLeft + pivotOffset) / oldPpm;
      scrollDiv.scrollLeft = pivotMinute * newPpm - pivotOffset;
    }
    updateZoomLabel();
    render();
  }

  // ── Resize canvas buffers ─────────────────────────────────────────────────
  function resizeCanvases() {
    const cssW = totalCSSWidth();

    // labels
    labelsCanvas.width  = LABEL_W * DPR;
    labelsCanvas.height = CANVAS_H * DPR;
    labelsCanvas.style.width  = LABEL_W + 'px';
    labelsCanvas.style.height = CANVAS_H + 'px';

    // timeline
    timelineCanvas.width  = cssW * DPR;
    timelineCanvas.height = CANVAS_H * DPR;
    timelineCanvas.style.width  = cssW + 'px';
    timelineCanvas.style.height = CANVAS_H + 'px';

    // wrapper height
    document.getElementById('tacho-timeline-wrap').style.height = CANVAS_H + 'px';
  }

  // ── Main render ───────────────────────────────────────────────────────────
  function render() {
    resizeCanvases();
    renderLabels();
    renderTimeline();
  }

  // ── Labels canvas (left column) ───────────────────────────────────────────
  function renderLabels() {
    const c = lCtx;
    c.setTransform(DPR, 0, 0, DPR, 0, 0);
    c.clearRect(0, 0, LABEL_W, CANVAS_H);

    // Background
    c.fillStyle = '#12141f';
    c.fillRect(0, 0, LABEL_W, CANVAS_H);

    // Right border
    c.strokeStyle = '#2d3260';
    c.lineWidth = 1;
    c.beginPath();
    c.moveTo(LABEL_W - 0.5, 0);
    c.lineTo(LABEL_W - 0.5, CANVAS_H);
    c.stroke();

    // Day header & ruler placeholders
    c.fillStyle = '#1a1d2e';
    c.fillRect(0, 0, LABEL_W, DAY_HDR_H + RULER_H);
    c.strokeStyle = '#1e2535';
    c.lineWidth = 0.5;
    [[0, DAY_HDR_H], [0, DAY_HDR_H + RULER_H]].forEach(([x, y]) => {
      c.beginPath(); c.moveTo(0, y + 0.5); c.lineTo(LABEL_W, y + 0.5); c.stroke();
    });

    // Activity type labels
    c.font = 'bold 11px sans-serif';
    c.textBaseline = 'middle';
    ROW_ORDER.forEach((type, i) => {
      const y = DAY_HDR_H + RULER_H + i * ROW_H;
      // row background alternating
      c.fillStyle = i % 2 === 0 ? '#0f1120' : '#0a0d18';
      c.fillRect(0, y, LABEL_W, ROW_H);
      // color strip
      c.fillStyle = COLORS[type];
      c.fillRect(0, y, 4, ROW_H);
      // text
      c.fillStyle = '#cbd5e0';
      c.font = `11px sans-serif`;
      const labels = {
        driving: 'Prowadzenie', work: 'Praca', availability: 'Dyspozycja',
        rest: 'Odpoczynek', break: 'Przerwa'
      };
      c.fillText(labels[type], 10, y + ROW_H / 2);
      // row separator
      c.strokeStyle = '#1e2535';
      c.lineWidth = 0.5;
      c.beginPath(); c.moveTo(0, y + ROW_H - 0.5); c.lineTo(LABEL_W, y + ROW_H - 0.5); c.stroke();
    });
  }

  // ── Timeline canvas (scrollable) ──────────────────────────────────────────
  function renderTimeline() {
    const c    = tCtx;
    const p    = ppm();
    const cssW = totalCSSWidth();

    c.setTransform(DPR, 0, 0, DPR, 0, 0);
    c.clearRect(0, 0, cssW, CANVAS_H);

    // Full background
    c.fillStyle = '#0d0f1a';
    c.fillRect(0, 0, cssW, CANVAS_H);

    // ── Draw activity rows background ─────────────────────────────────────
    ROW_ORDER.forEach((type, i) => {
      const y = DAY_HDR_H + RULER_H + i * ROW_H;
      c.fillStyle = i % 2 === 0 ? '#0f1120' : '#0a0d18';
      c.fillRect(0, y, cssW, ROW_H);
    });

    // ── Draw activity blocks ──────────────────────────────────────────────
    ACTIVITIES.forEach(act => {
      const ri = ROW_ORDER.indexOf(act.t);
      if (ri === -1) return;
      const x  = act.s * p;
      const w  = Math.max((act.e - act.s) * p, 1);
      const y  = DAY_HDR_H + RULER_H + ri * ROW_H + 3;
      const h  = ROW_H - 6;
      c.fillStyle = COLORS[act.t] || '#6b7280';
      // Rounded rect
      const r = Math.min(3, h / 2, w / 2);
      c.beginPath();
      c.roundRect
        ? c.roundRect(x, y, w, h, r)
        : c.rect(x, y, w, h);
      c.fill();
      // Label inside block (only if wide enough)
      if (w > 36) {
        c.fillStyle = 'rgba(0,0,0,0.65)';
        c.font = '9px monospace';
        c.textBaseline = 'middle';
        c.fillText(act.st, x + 4, y + h / 2);
      }
    });

    // ── Day separators + headers ──────────────────────────────────────────
    DAY_INFO.forEach((day, i) => {
      const dayX  = i * 1440 * p;
      const dayW  = 1440 * p;

      // Header background (alternating subtle shade)
      c.fillStyle = i % 2 === 0 ? '#13162a' : '#111429';
      c.fillRect(dayX, 0, dayW, DAY_HDR_H);

      // Day name
      c.fillStyle = '#60a5fa';
      c.font = 'bold 11px sans-serif';
      c.textBaseline = 'middle';
      c.textAlign = 'left';
      const labelTxt = day.name + ' ' + day.label;
      const txtX = dayX + 6;
      const txtY = DAY_HDR_H / 2;
      c.fillText(labelTxt, txtX, txtY);

      // Separator line (right edge)
      c.strokeStyle = '#2d3260';
      c.lineWidth = 1;
      c.beginPath();
      c.moveTo(dayX + dayW - 0.5, 0);
      c.lineTo(dayX + dayW - 0.5, CANVAS_H);
      c.stroke();

      // Lighter header separator
      c.strokeStyle = '#374151';
      c.lineWidth = 0.5;
      c.beginPath();
      c.moveTo(dayX, DAY_HDR_H - 0.5);
      c.lineTo(dayX + dayW, DAY_HDR_H - 0.5);
      c.stroke();
    });

    // ── Time ruler (hour marks) ────────────────────────────────────────────
    c.fillStyle = '#1a1d2e';
    c.fillRect(0, DAY_HDR_H, cssW, RULER_H);

    const hourPx    = 60 * p;
    // Choose tick interval based on zoom level
    let tickInterval = 1; // hours
    if (hourPx < 8)  tickInterval = 6;
    else if (hourPx < 16) tickInterval = 3;
    else if (hourPx < 32) tickInterval = 2;

    for (let d = 0; d < 7; d++) {
      for (let h = 0; h < 24; h += tickInterval) {
        const min = d * 1440 + h * 60;
        const tx  = min * p;
        const isMajor = h % 6 === 0;

        c.strokeStyle = isMajor ? '#4a5568' : '#2d3748';
        c.lineWidth   = isMajor ? 0.8 : 0.5;
        c.beginPath();
        c.moveTo(tx, DAY_HDR_H);
        c.lineTo(tx, DAY_HDR_H + RULER_H + CANVAS_H); // extend into rows
        c.stroke();

        // Hour label
        if (hourPx * tickInterval > 14) {
          c.fillStyle = isMajor ? '#9ca3af' : '#4a5568';
          c.font = `${isMajor ? 9.5 : 8.5}px monospace`;
          c.textBaseline = 'middle';
          c.textAlign = 'center';
          c.fillText(String(h).padStart(2, '0'), tx, DAY_HDR_H + RULER_H / 2);
        }
      }
    }

    // Row separators over the whole width
    ROW_ORDER.forEach((_, i) => {
      const y = DAY_HDR_H + RULER_H + (i + 1) * ROW_H - 0.5;
      c.strokeStyle = '#1e2535';
      c.lineWidth = 0.5;
      c.beginPath();
      c.moveTo(0, y);
      c.lineTo(cssW, y);
      c.stroke();
    });
  }

  // ── Tooltip logic ─────────────────────────────────────────────────────────
  function hitTest(clientX, clientY) {
    const rect = timelineCanvas.getBoundingClientRect();
    const cx   = clientX - rect.left;   // local x on canvas
    const cy   = clientY - rect.top;    // local y on canvas
    const p    = ppm();
    const min  = cx / p;

    const rowTop = DAY_HDR_H + RULER_H;
    if (cy < rowTop) {
      // Click in day header → detect which day
      const dayIdx = Math.floor(min / 1440);
      if (dayIdx >= 0 && dayIdx < 7) return { kind: 'day', day: dayIdx };
      return null;
    }
    const rowIdx = Math.floor((cy - rowTop) / ROW_H);
    if (rowIdx < 0 || rowIdx >= ROW_ORDER.length) return null;
    const type = ROW_ORDER[rowIdx];

    // Find best-matching activity
    let best = null, bestW = Infinity;
    for (const act of ACTIVITIES) {
      if (act.t !== type) continue;
      if (min >= act.s && min <= act.e) {
        const w = act.e - act.s;
        if (w < bestW) { bestW = w; best = act; }
      }
    }
    if (best) return { kind: 'activity', act: best };
    return null;
  }

  function showTooltip(html, clientX, clientY) {
    tooltip.innerHTML = html;
    tooltip.style.display = 'block';
    const tw = tooltip.offsetWidth;
    const th = tooltip.offsetHeight;
    let tx = clientX + 14;
    let ty = clientY - 10;
    if (tx + tw > window.innerWidth - 10) tx = clientX - tw - 14;
    if (ty + th > window.innerHeight - 10) ty = clientY - th - 10;
    tooltip.style.left = tx + 'px';
    tooltip.style.top  = ty + 'px';
  }

  function hideTooltip() {
    tooltip.style.display = 'none';
  }

  // ── Events ────────────────────────────────────────────────────────────────

  // Zoom buttons
  document.getElementById('tzoom-in') .addEventListener('click', () => setZoom(zoom * ZOOM_STEP));
  document.getElementById('tzoom-out').addEventListener('click', () => setZoom(zoom / ZOOM_STEP));
  document.getElementById('tzoom-reset').addEventListener('click', () => setZoom(1));

  // Mouse wheel zoom (on the scroll container)
  scrollDiv.addEventListener('wheel', (e) => {
    e.preventDefault();
    const factor = e.deltaY < 0 ? ZOOM_STEP : 1 / ZOOM_STEP;
    setZoom(zoom * factor, e.clientX);
  }, { passive: false });

  // Mouse click → navigate to day view
  timelineCanvas.addEventListener('click', (e) => {
    const hit = hitTest(e.clientX, e.clientY);
    if (hit && hit.kind === 'day') {
      window.location.href = DAY_INFO[hit.day].url;
    } else if (hit && hit.kind === 'activity') {
      window.location.href = DAY_INFO[hit.act.day].url;
    }
  });

  // Hover tooltip
  timelineCanvas.addEventListener('mousemove', (e) => {
    const hit = hitTest(e.clientX, e.clientY);
    if (!hit) { hideTooltip(); timelineCanvas.style.cursor = 'default'; return; }
    if (hit.kind === 'day') {
      const d = DAY_INFO[hit.day];
      showTooltip(
        `<div class="fw-semibold" style="color:#60a5fa">${d.name} ${d.full}</div>
         <div class="text-muted small">Kliknij, aby zobaczyć szczegóły dnia</div>`,
        e.clientX, e.clientY
      );
      timelineCanvas.style.cursor = 'pointer';
    } else {
      const a = hit.act;
      const color = COLORS[a.t] || '#9ca3af';
      showTooltip(
        `<div style="color:${color};font-weight:600">${a.lbl}</div>
         <div class="text-muted">${a.st} – ${a.et}</div>
         <div><strong>${a.dur}</strong></div>`,
        e.clientX, e.clientY
      );
      timelineCanvas.style.cursor = 'pointer';
    }
  });

  timelineCanvas.addEventListener('mouseleave', hideTooltip);

  // Touch pinch-to-zoom
  scrollDiv.addEventListener('touchstart', (e) => {
    if (e.touches.length === 2) {
      e.preventDefault();
      const dx = e.touches[0].clientX - e.touches[1].clientX;
      const dy = e.touches[0].clientY - e.touches[1].clientY;
      pinch = {
        active: true,
        startDist: Math.hypot(dx, dy),
        startZoom: zoom,
        startX: (e.touches[0].clientX + e.touches[1].clientX) / 2,
        startScroll: scrollDiv.scrollLeft,
      };
    } else if (e.touches.length === 1) {
      drag = { active: true, startX: e.touches[0].clientX, startScroll: scrollDiv.scrollLeft };
    }
  }, { passive: false });

  scrollDiv.addEventListener('touchmove', (e) => {
    if (pinch.active && e.touches.length === 2) {
      e.preventDefault();
      const dx   = e.touches[0].clientX - e.touches[1].clientX;
      const dy   = e.touches[0].clientY - e.touches[1].clientY;
      const dist = Math.hypot(dx, dy);
      const midX = (e.touches[0].clientX + e.touches[1].clientX) / 2;
      setZoom(pinch.startZoom * dist / pinch.startDist, midX);
    } else if (drag.active && e.touches.length === 1) {
      e.preventDefault();
      const dx = e.touches[0].clientX - drag.startX;
      scrollDiv.scrollLeft = drag.startScroll - dx;
    }
  }, { passive: false });

  scrollDiv.addEventListener('touchend', () => {
    pinch.active = false; drag.active = false;
  });

  // Mouse drag-to-pan
  scrollDiv.addEventListener('mousedown', (e) => {
    if (e.button !== 0) return;
    drag = { active: true, startX: e.clientX, startScroll: scrollDiv.scrollLeft };
    scrollDiv.style.cursor = 'grabbing';
  });
  window.addEventListener('mousemove', (e) => {
    if (!drag.active) return;
    scrollDiv.scrollLeft = drag.startScroll - (e.clientX - drag.startX);
  });
  window.addEventListener('mouseup', () => {
    drag.active = false;
    scrollDiv.style.cursor = 'grab';
  });

  // Window resize
  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => { if (zoom < ZOOM_MIN) zoom = ZOOM_MIN; render(); }, 80);
  });

  // ── Init ──────────────────────────────────────────────────────────────────
  render();

})();
</script>

<style>
#tacho-scroll { scrollbar-width: thin; scrollbar-color: #374151 #0d0f1a; }
#tacho-scroll::-webkit-scrollbar { height: 6px; }
#tacho-scroll::-webkit-scrollbar-track { background: #0d0f1a; }
#tacho-scroll::-webkit-scrollbar-thumb { background: #374151; border-radius: 3px; }
@media print {
  #sidebar, .topbar, .btn, select { display: none !important; }
  .main-wrapper { margin: 0 !important; }
  .card { border: 1px solid #ccc !important; }
}
</style>
