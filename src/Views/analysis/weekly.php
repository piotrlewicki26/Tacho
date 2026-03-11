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

// ── Palette – Inelo/TachoScan-style ──────────────────────────────────────────
$actColors = [
    'driving'      => '#1a56db',  // TachoScan blue
    'work'         => '#f59e0b',  // amber
    'availability' => '#10b981',  // green
    'rest'         => '#6366f1',  // indigo
    'break'        => '#ec4899',  // pink
];
$actLabels = [
    'driving'      => 'Prowadzenie pojazdu',
    'work'         => 'Praca',
    'availability' => 'Dyspozycja',
    'rest'         => 'Odpoczynek',
    'break'        => 'Przerwa',
];

// TachoScan uses two "bands":
//   upper band: driving, work, availability  (activity while potentially moving)
//   lower band: rest, break                  (downtime)
$upperBand = ['driving', 'work', 'availability'];
$lowerBand = ['rest', 'break'];

// ── Weekly totals ─────────────────────────────────────────────────────────────
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

// ── Prev/next week navigation ─────────────────────────────────────────────────
$wkIdx   = array_search($weekStart, $weekKeys, true);
$prevWk  = ($wkIdx !== false && $wkIdx > 0) ? $weekKeys[$wkIdx - 1] : null;
$nextWk  = ($wkIdx !== false && $wkIdx < count($weekKeys) - 1) ? $weekKeys[$wkIdx + 1] : null;

// ── Build flat activity array for JS (minutes from week start) ────────────────
$flatActivities = [];
foreach ($weekDates as $i => $d) {
    $dayBase = $i * 1440;
    foreach ($weekActivitiesByDay[$d] ?? [] as $a) {
        $sp   = explode(':', $a['start_time']);
        $ep   = explode(':', $a['end_time']);
        $sMin = (int)$sp[0] * 60 + (int)$sp[1];
        $eMin = (int)$ep[0] * 60 + (int)$ep[1];
        if ($eMin <= $sMin) $eMin += 1440;
        $flatActivities[] = [
            't'       => $a['activity_type'],
            's'       => $dayBase + $sMin,
            'e'       => $dayBase + $eMin,
            'lbl'     => $actLabels[$a['activity_type']] ?? $a['activity_type'],
            'st'      => substr($a['start_time'], 0, 5),
            'et'      => substr($a['end_time'],   0, 5),
            'dur'     => intdiv($a['duration_minutes'], 60) . 'h ' . ($a['duration_minutes'] % 60) . 'min',
            'day'     => $i,
            'country' => $a['country_code'] ?? null,
        ];
    }
}

// ── Day info for JS ───────────────────────────────────────────────────────────
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

<!-- ── Navigation ────────────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Analiza tygodniowa</h4>
    <p class="text-muted small mb-0"><?= htmlspecialchars($file['original_name']) ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center">

    <!-- Prev week -->
    <?php if ($prevWk): ?>
    <a href="/analysis/<?= $fileId ?>/weekly?week=<?= $prevWk ?>"
       class="btn btn-sm btn-outline-secondary" title="Poprzedni tydzień">
      <i class="bi bi-chevron-left"></i>
    </a>
    <?php else: ?>
    <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
    <?php endif; ?>

    <!-- Week picker -->
    <?php if (count($weekKeys) > 1): ?>
    <select class="form-select form-select-sm" style="width:auto"
            onchange="window.location='/analysis/<?= $fileId ?>/weekly?week='+this.value">
      <?php foreach ($weekKeys as $wk): ?>
      <option value="<?= $wk ?>" <?= $wk === $weekStart ? 'selected' : '' ?>>
        Tydz. <?= date('W', strtotime($wk)) ?>: <?= date('d.m', strtotime($wk)) ?>–<?= date('d.m.Y', strtotime($wk.' +6 days')) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php else: ?>
    <span class="text-muted small fw-semibold">
      Tydz.&nbsp;<?= $weekNum ?>: <?= date('d.m.Y', strtotime($weekStart)) ?>&nbsp;–&nbsp;<?= date('d.m.Y', strtotime($weekEnd)) ?>
    </span>
    <?php endif; ?>

    <!-- Next week -->
    <?php if ($nextWk): ?>
    <a href="/analysis/<?= $fileId ?>/weekly?week=<?= $nextWk ?>"
       class="btn btn-sm btn-outline-secondary" title="Następny tydzień">
      <i class="bi bi-chevron-right"></i>
    </a>
    <?php else: ?>
    <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
    <?php endif; ?>

    <a href="/analysis/<?= $fileId ?>/daily" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-calendar-day me-1"></i>Dzienny
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-printer me-1"></i>Drukuj
    </button>
  </div>
</div>

<!-- ── TachoScan Timeline Card ───────────────────────────────────────────────── -->
<div class="card border-0 mb-4" id="tacho-card" style="background:#fff;color:#111">

  <div class="card-header border-bottom bg-white d-flex align-items-center justify-content-between flex-wrap gap-3 py-2 px-3">
    <div class="d-flex align-items-center gap-3">
      <span class="fw-semibold" style="font-size:.9rem;color:#111">
        Tydzień <?= $weekNum ?> &nbsp;
        <span class="text-muted fw-normal" style="font-size:.8rem">
          <?= date('d.m.Y', strtotime($weekStart)) ?> – <?= date('d.m.Y', strtotime($weekEnd)) ?>
        </span>
      </span>
      <span class="badge rounded-pill" style="background:#e8f0fe;color:#1a56db;font-size:.7rem">
        <?= count($weekDates) ?> dni
      </span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="text-muted small">Powiększenie:</span>
      <button id="tzoom-out"   class="btn btn-sm btn-light border px-2 py-1">−</button>
      <span   id="tzoom-label" class="small font-monospace text-muted" style="min-width:4.5rem;text-align:center">Cały tydzień</span>
      <button id="tzoom-in"    class="btn btn-sm btn-light border px-2 py-1">+</button>
      <button id="tzoom-reset" class="btn btn-sm btn-light border px-2 py-1" title="Cały tydzień">↺</button>
    </div>
  </div>

  <div class="card-body p-0" style="background:#f8fafc">

    <!-- Timeline layout: fixed left-label panel | scrollable canvas -->
    <div id="tacho-timeline-wrap"
         style="position:relative;display:flex;overflow:hidden;user-select:none;background:#fff">

      <!-- Fixed left: track labels -->
      <canvas id="tacho-labels"
              style="flex-shrink:0;display:block;cursor:default"></canvas>

      <!-- Scrollable timeline canvas -->
      <div id="tacho-scroll"
           style="flex:1;overflow-x:auto;overflow-y:hidden;cursor:grab;position:relative">
        <canvas id="tacho-canvas" style="display:block"></canvas>

        <!-- Floating tooltip -->
        <div id="tacho-tooltip"
             style="display:none;position:fixed;z-index:9999;pointer-events:none;
                    background:rgba(255,255,255,0.97);border:1px solid #d1d5db;
                    border-radius:6px;padding:6px 11px;font-size:12px;
                    color:#111827;max-width:230px;line-height:1.6;
                    box-shadow:0 4px 12px rgba(0,0,0,.1)"></div>
      </div>

    </div><!-- /wrap -->

    <!-- Legend -->
    <div class="d-flex flex-wrap gap-3 px-3 pb-3 pt-2"
         style="border-top:1px solid #e5e7eb">
      <?php foreach ($actLabels as $type => $label):
          if ($weekTotals[$type] <= 0) continue; ?>
      <div class="d-flex align-items-center gap-2 small">
        <span style="display:inline-block;width:13px;height:13px;border-radius:3px;
                     background:<?= $actColors[$type] ?>;flex-shrink:0;opacity:.9"></span>
        <span style="color:#6b7280"><?= $label ?></span>
        <span class="fw-semibold" style="color:<?= $actColors[$type] ?>"><?= $fmt($weekTotals[$type]) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

  </div><!-- /card-body -->
</div><!-- /card -->

<!-- ── Summary Table ─────────────────────────────────────────────────────────── -->
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
          <th>
            <span style="display:inline-block;width:8px;height:8px;border-radius:2px;
                         background:<?= $actColors[$type] ?>;margin-right:4px"></span>
            <?= $label ?>
          </th>
          <?php endforeach; ?>
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

<!-- ── Violations ─────────────────────────────────────────────────────────────── -->
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

<!-- ── JavaScript – TachoScan-style Canvas ───────────────────────────────────── -->
<script>
(function () {
  'use strict';

  // ── Data from PHP ─────────────────────────────────────────────────────────
  const ACTIVITIES = <?= json_encode($flatActivities, JSON_UNESCAPED_UNICODE) ?>;
  const DAY_INFO   = <?= json_encode($dayInfo,        JSON_UNESCAPED_UNICODE) ?>;

  // ── TachoScan palette (light theme) ──────────────────────────────────────
  const COLORS = {
    driving:      '#1a56db',
    work:         '#f59e0b',
    availability: '#10b981',
    rest:         '#6366f1',
    break:        '#ec4899',
  };

  // Two-band layout: upper = active types, lower = rest types
  const UPPER = ['driving', 'work', 'availability'];
  const LOWER = ['rest', 'break'];
  const ALL_TYPES = [...UPPER, ...LOWER];

  // ── Layout (CSS px) ───────────────────────────────────────────────────────
  const DPR        = window.devicePixelRatio || 1;
  const LABEL_W    = 90;   // left fixed label column
  const DAY_HDR_H  = 32;   // day header row
  const RULER_H    = 22;   // time ruler
  const BAND_H     = 46;   // height per activity band
  const GAP_H      = 10;   // gap between the two bands
  const BAND_PAD   = 4;    // vertical padding inside a band block
  const CANVAS_H   = DAY_HDR_H + RULER_H + BAND_H + GAP_H + BAND_H;
  const WEEK_MINS  = 7 * 1440;

  // ── State ─────────────────────────────────────────────────────────────────
  let zoom      = 1;
  const ZOOM_MIN  = 1;
  const ZOOM_MAX  = 48;
  const ZOOM_STEP = 1.4;
  let drag  = { active: false, startX: 0, startScroll: 0 };
  let pinch = { active: false, startDist: 0, startZoom: 1, startX: 0 };

  // ── DOM ───────────────────────────────────────────────────────────────────
  const labelsCanvas   = document.getElementById('tacho-labels');
  const timelineCanvas = document.getElementById('tacho-canvas');
  const scrollDiv      = document.getElementById('tacho-scroll');
  const tooltip        = document.getElementById('tacho-tooltip');
  const zoomLabel      = document.getElementById('tzoom-label');
  const lCtx = labelsCanvas.getContext('2d');
  const tCtx = timelineCanvas.getContext('2d');

  // ── Helpers ───────────────────────────────────────────────────────────────
  function ppm()     { return scrollDiv.clientWidth / WEEK_MINS * zoom; }
  function cssW()    { return Math.round(WEEK_MINS * ppm()); }

  function bandY(upper) {
    const base = DAY_HDR_H + RULER_H;
    return upper ? base : base + BAND_H + GAP_H;
  }

  function typeInUpper(t) { return UPPER.includes(t); }

  function updateZoomLabel() {
    const visibleHours = (scrollDiv.clientWidth / (ppm() * 60)).toFixed(1);
    zoomLabel.textContent = zoom <= 1.05 ? 'Cały tydzień' : visibleHours + 'h';
  }

  function setZoom(nz, pivotX) {
    const oldP = ppm();
    zoom = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, nz));
    if (pivotX !== undefined) {
      const rect    = scrollDiv.getBoundingClientRect();
      const off     = pivotX - rect.left;
      const pivMin  = (scrollDiv.scrollLeft + off) / oldP;
      scrollDiv.scrollLeft = pivMin * ppm() - off;
    }
    updateZoomLabel();
    render();
  }

  // ── Resize ────────────────────────────────────────────────────────────────
  function resizeCanvases() {
    const w = cssW();
    labelsCanvas.width  = LABEL_W * DPR; labelsCanvas.height = CANVAS_H * DPR;
    labelsCanvas.style.width  = LABEL_W + 'px'; labelsCanvas.style.height = CANVAS_H + 'px';
    timelineCanvas.width  = w * DPR;     timelineCanvas.height = CANVAS_H * DPR;
    timelineCanvas.style.width  = w + 'px'; timelineCanvas.style.height = CANVAS_H + 'px';
    document.getElementById('tacho-timeline-wrap').style.height = CANVAS_H + 'px';
  }

  function render() { resizeCanvases(); renderLabels(); renderTimeline(); }

  // ── Left labels ───────────────────────────────────────────────────────────
  function renderLabels() {
    const c = lCtx;
    c.setTransform(DPR, 0, 0, DPR, 0, 0);
    c.clearRect(0, 0, LABEL_W, CANVAS_H);

    // Background
    c.fillStyle = '#fff';
    c.fillRect(0, 0, LABEL_W, CANVAS_H);

    // Right border
    c.strokeStyle = '#e5e7eb';
    c.lineWidth = 1;
    c.beginPath(); c.moveTo(LABEL_W - 0.5, 0); c.lineTo(LABEL_W - 0.5, CANVAS_H); c.stroke();

    // Day header + ruler area
    c.fillStyle = '#f9fafb';
    c.fillRect(0, 0, LABEL_W, DAY_HDR_H + RULER_H);
    c.strokeStyle = '#e5e7eb'; c.lineWidth = 0.5;
    c.beginPath(); c.moveTo(0, DAY_HDR_H + RULER_H); c.lineTo(LABEL_W, DAY_HDR_H + RULER_H); c.stroke();

    // Upper band label
    const uy = bandY(true);
    c.fillStyle = '#f0f4ff';
    c.fillRect(0, uy, LABEL_W, BAND_H);
    // left accent
    c.fillStyle = COLORS.driving;
    c.fillRect(0, uy, 3, BAND_H);
    c.fillStyle = '#1e40af';
    c.font = 'bold 9.5px sans-serif'; c.textBaseline = 'top';
    c.fillText('Jazda / Praca', 7, uy + 6);
    c.fillStyle = '#6b7280';
    c.font = '8.5px sans-serif';
    c.fillText('Dyspozycja', 7, uy + 20);
    // upper band border
    c.strokeStyle = '#d1d5db'; c.lineWidth = 0.5;
    c.beginPath(); c.moveTo(0, uy + BAND_H); c.lineTo(LABEL_W, uy + BAND_H); c.stroke();

    // Gap area
    const gy = uy + BAND_H;
    c.fillStyle = '#f9fafb';
    c.fillRect(0, gy, LABEL_W, GAP_H);
    c.strokeStyle = '#e5e7eb'; c.lineWidth = 0.5;
    c.beginPath(); c.moveTo(0, gy + GAP_H); c.lineTo(LABEL_W, gy + GAP_H); c.stroke();

    // Lower band label
    const ly = bandY(false);
    c.fillStyle = '#f5f3ff';
    c.fillRect(0, ly, LABEL_W, BAND_H);
    c.fillStyle = COLORS.rest;
    c.fillRect(0, ly, 3, BAND_H);
    c.fillStyle = '#4338ca';
    c.font = 'bold 9.5px sans-serif'; c.textBaseline = 'top';
    c.fillText('Odpoczynek', 7, ly + 6);
    c.fillStyle = '#6b7280';
    c.font = '8.5px sans-serif';
    c.fillText('Przerwa', 7, ly + 20);
    c.strokeStyle = '#d1d5db'; c.lineWidth = 0.5;
    c.beginPath(); c.moveTo(0, ly + BAND_H); c.lineTo(LABEL_W, ly + BAND_H); c.stroke();
  }

  // ── Timeline canvas ───────────────────────────────────────────────────────
  function renderTimeline() {
    const c = tCtx;
    const p = ppm();
    const w = cssW();

    c.setTransform(DPR, 0, 0, DPR, 0, 0);
    c.clearRect(0, 0, w, CANVAS_H);
    c.fillStyle = '#fff';
    c.fillRect(0, 0, w, CANVAS_H);

    // Band backgrounds
    const uy = bandY(true), ly = bandY(false);
    c.fillStyle = '#f0f4ff'; c.fillRect(0, uy, w, BAND_H); // upper
    c.fillStyle = '#f9fafb'; c.fillRect(0, uy + BAND_H, w, GAP_H); // gap
    c.fillStyle = '#f5f3ff'; c.fillRect(0, ly, w, BAND_H); // lower

    // Day headers + dashed day dividers
    const hourPx = 60 * p;
    let tickInterval = 1;
    if (hourPx < 8)  tickInterval = 6;
    else if (hourPx < 16) tickInterval = 3;
    else if (hourPx < 32) tickInterval = 2;

    DAY_INFO.forEach((day, i) => {
      const dayX = i * 1440 * p;
      const dayW = 1440 * p;

      // Day header bg – alternating subtle tints
      c.fillStyle = i % 2 === 0 ? '#f8fafc' : '#f1f5f9';
      c.fillRect(dayX, 0, dayW, DAY_HDR_H);

      // Day label
      c.fillStyle = '#1e40af';
      c.font = 'bold 11px sans-serif';
      c.textBaseline = 'middle';
      c.textAlign = 'left';
      c.fillText(day.name, dayX + 6, DAY_HDR_H / 2);
      c.fillStyle = '#64748b';
      c.font = '10px sans-serif';
      const nameW = c.measureText(day.name + ' ').width;
      c.fillText(day.label, dayX + 6 + nameW, DAY_HDR_H / 2);

      // Click arrow hint »
      c.fillStyle = '#94a3b8';
      c.font = '10px sans-serif';
      c.textAlign = 'right';
      c.fillText('→', dayX + dayW - 5, DAY_HDR_H / 2);

      // Dashed day separator (right edge) – spans full height
      if (i < 6) {
        c.save();
        c.setLineDash([4, 3]);
        c.strokeStyle = '#9ca3af';
        c.lineWidth = 1;
        c.beginPath();
        c.moveTo(dayX + dayW - 0.5, 0);
        c.lineTo(dayX + dayW - 0.5, CANVAS_H);
        c.stroke();
        c.restore();
      }

      // Hour tick lines inside this day
      for (let h = 0; h < 24; h += tickInterval) {
        const tx      = (i * 1440 + h * 60) * p;
        const isMajor = h % 6 === 0;

        // faint vertical grid lines in bands
        c.strokeStyle = isMajor ? '#d1d5db' : '#e5e7eb';
        c.lineWidth   = isMajor ? 0.8 : 0.5;
        c.setLineDash([]);
        c.beginPath();
        c.moveTo(tx, DAY_HDR_H + RULER_H);
        c.lineTo(tx, CANVAS_H);
        c.stroke();

        // hour label in ruler
        if (hourPx * tickInterval > 12) {
          c.fillStyle  = isMajor ? '#374151' : '#9ca3af';
          c.font       = `${isMajor ? 9 : 8}px monospace`;
          c.textBaseline = 'middle';
          c.textAlign  = 'center';
          c.fillText(String(h).padStart(2, '0'), tx, DAY_HDR_H + RULER_H / 2);
        }
      }
    });

    // Ruler background
    c.fillStyle = '#f9fafb';
    c.fillRect(0, DAY_HDR_H, w, RULER_H);
    c.strokeStyle = '#e5e7eb'; c.lineWidth = 0.5; c.setLineDash([]);
    c.beginPath(); c.moveTo(0, DAY_HDR_H); c.lineTo(w, DAY_HDR_H); c.stroke();
    c.beginPath(); c.moveTo(0, DAY_HDR_H + RULER_H); c.lineTo(w, DAY_HDR_H + RULER_H); c.stroke();

    // Band border lines
    c.strokeStyle = '#cbd5e1'; c.lineWidth = 1;
    c.beginPath(); c.moveTo(0, uy); c.lineTo(w, uy); c.stroke();
    c.beginPath(); c.moveTo(0, uy + BAND_H); c.lineTo(w, uy + BAND_H); c.stroke();
    c.beginPath(); c.moveTo(0, ly); c.lineTo(w, ly); c.stroke();
    c.beginPath(); c.moveTo(0, ly + BAND_H); c.lineTo(w, ly + BAND_H); c.stroke();

    // Activity blocks
    ACTIVITIES.forEach(act => {
      const isUpper = UPPER.includes(act.t);
      const bY      = isUpper ? uy : ly;
      const x       = act.s * p;
      const bw      = Math.max((act.e - act.s) * p, 1.5);
      const y       = bY + BAND_PAD;
      const h       = BAND_H - BAND_PAD * 2;
      const col     = COLORS[act.t] || '#6b7280';

      // Block fill with subtle gradient feel (lighter top)
      c.fillStyle = col;
      c.globalAlpha = 0.88;
      const r = Math.min(3, h / 2, bw / 2);
      c.beginPath();
      if (c.roundRect) c.roundRect(x, y, bw, h, r);
      else              c.rect(x, y, bw, h);
      c.fill();
      c.globalAlpha = 1;

      // Start-time label inside block
      if (bw > 30) {
        c.fillStyle = '#fff';
        c.font = '8px monospace';
        c.textBaseline = 'middle';
        c.textAlign = 'left';
        c.fillText(act.st, x + 3, y + h / 2);
      }

      // Country code marker (flag pill) – shown if wide enough
      if (act.country && bw > 60) {
        const tag  = act.country.toUpperCase().substring(0, 2);
        const tw   = c.measureText(tag).width + 6;
        const tx   = x + bw - tw - 3;
        const ty   = y + (h - 12) / 2;
        c.fillStyle = 'rgba(0,0,0,0.28)';
        c.beginPath();
        if (c.roundRect) c.roundRect(tx, ty, tw, 12, 3);
        else              c.rect(tx, ty, tw, 12);
        c.fill();
        c.fillStyle = '#fff';
        c.font = 'bold 7.5px sans-serif';
        c.textBaseline = 'middle';
        c.textAlign = 'center';
        c.fillText(tag, tx + tw / 2, ty + 6);
      }
    });

    c.textAlign = 'left'; // reset
  }

  // ── Hit test ─────────────────────────────────────────────────────────────
  function hitTest(clientX, clientY) {
    const rect = timelineCanvas.getBoundingClientRect();
    const cx   = clientX - rect.left;
    const cy   = clientY - rect.top;
    const p    = ppm();
    const min  = cx / p;
    const uy   = bandY(true), ly = bandY(false);

    // Day header zone → navigate to daily
    if (cy < DAY_HDR_H + RULER_H) {
      const dayIdx = Math.floor(min / 1440);
      if (dayIdx >= 0 && dayIdx < 7) return { kind: 'day', day: dayIdx };
      return null;
    }

    // Upper band
    if (cy >= uy && cy < uy + BAND_H) {
      let best = null, bestW = Infinity;
      for (const act of ACTIVITIES) {
        if (!UPPER.includes(act.t)) continue;
        if (min >= act.s && min <= act.e) {
          const w = act.e - act.s;
          if (w < bestW) { bestW = w; best = act; }
        }
      }
      if (best) return { kind: 'activity', act: best };
      // click in empty band → go to day
      const dayIdx = Math.floor(min / 1440);
      if (dayIdx >= 0 && dayIdx < 7) return { kind: 'day', day: dayIdx };
      return null;
    }

    // Lower band
    if (cy >= ly && cy < ly + BAND_H) {
      let best = null, bestW = Infinity;
      for (const act of ACTIVITIES) {
        if (!LOWER.includes(act.t)) continue;
        if (min >= act.s && min <= act.e) {
          const w = act.e - act.s;
          if (w < bestW) { bestW = w; best = act; }
        }
      }
      if (best) return { kind: 'activity', act: best };
      const dayIdx = Math.floor(min / 1440);
      if (dayIdx >= 0 && dayIdx < 7) return { kind: 'day', day: dayIdx };
      return null;
    }

    return null;
  }

  function showTooltip(html, cx, cy) {
    tooltip.innerHTML = html;
    tooltip.style.display = 'block';
    const tw = tooltip.offsetWidth, th = tooltip.offsetHeight;
    let tx = cx + 14, ty = cy - 10;
    if (tx + tw > window.innerWidth - 10)  tx = cx - tw - 14;
    if (ty + th > window.innerHeight - 10) ty = cy - th - 10;
    tooltip.style.left = tx + 'px';
    tooltip.style.top  = ty + 'px';
  }
  function hideTooltip() { tooltip.style.display = 'none'; }

  // ── Events ────────────────────────────────────────────────────────────────
  document.getElementById('tzoom-in') .addEventListener('click', () => setZoom(zoom * ZOOM_STEP));
  document.getElementById('tzoom-out').addEventListener('click', () => setZoom(zoom / ZOOM_STEP));
  document.getElementById('tzoom-reset').addEventListener('click', () => setZoom(1));

  scrollDiv.addEventListener('wheel', e => {
    e.preventDefault();
    setZoom(zoom * (e.deltaY < 0 ? ZOOM_STEP : 1 / ZOOM_STEP), e.clientX);
  }, { passive: false });

  // Click → navigate to daily
  timelineCanvas.addEventListener('click', e => {
    const hit = hitTest(e.clientX, e.clientY);
    if (hit && hit.kind === 'day')      window.location.href = DAY_INFO[hit.day].url;
    else if (hit && hit.kind === 'activity') window.location.href = DAY_INFO[hit.act.day].url;
  });

  // Hover tooltip
  timelineCanvas.addEventListener('mousemove', e => {
    const hit = hitTest(e.clientX, e.clientY);
    if (!hit) { hideTooltip(); timelineCanvas.style.cursor = 'default'; return; }
    if (hit.kind === 'day') {
      const d = DAY_INFO[hit.day];
      showTooltip(
        `<div style="font-weight:600;color:#1e40af">${d.name} ${d.full}</div>
         <div style="color:#6b7280;font-size:11px">Kliknij, aby zobaczyć szczegóły dnia →</div>`,
        e.clientX, e.clientY
      );
      timelineCanvas.style.cursor = 'pointer';
    } else {
      const a = hit.act;
      showTooltip(
        `<div style="color:${COLORS[a.t]};font-weight:600">${a.lbl}</div>
         <div style="color:#374151">${a.st} – ${a.et}</div>
         <div style="font-weight:700">${a.dur}</div>
         ${a.country ? `<div style="color:#6b7280;font-size:11px">Kraj: ${a.country}</div>` : ''}`,
        e.clientX, e.clientY
      );
      timelineCanvas.style.cursor = 'pointer';
    }
  });
  timelineCanvas.addEventListener('mouseleave', hideTooltip);

  // Drag-to-pan
  scrollDiv.addEventListener('mousedown', e => {
    if (e.button !== 0) return;
    drag = { active: true, startX: e.clientX, startScroll: scrollDiv.scrollLeft };
    scrollDiv.style.cursor = 'grabbing';
  });
  window.addEventListener('mousemove', e => {
    if (!drag.active) return;
    scrollDiv.scrollLeft = drag.startScroll - (e.clientX - drag.startX);
  });
  window.addEventListener('mouseup', () => { drag.active = false; scrollDiv.style.cursor = 'grab'; });

  // Touch
  scrollDiv.addEventListener('touchstart', e => {
    if (e.touches.length === 2) {
      e.preventDefault();
      const dx = e.touches[0].clientX - e.touches[1].clientX;
      const dy = e.touches[0].clientY - e.touches[1].clientY;
      pinch = { active: true, startDist: Math.hypot(dx, dy), startZoom: zoom,
                startX: (e.touches[0].clientX + e.touches[1].clientX) / 2 };
    } else if (e.touches.length === 1) {
      drag = { active: true, startX: e.touches[0].clientX, startScroll: scrollDiv.scrollLeft };
    }
  }, { passive: false });
  scrollDiv.addEventListener('touchmove', e => {
    if (pinch.active && e.touches.length === 2) {
      e.preventDefault();
      const dx = e.touches[0].clientX - e.touches[1].clientX;
      const dy = e.touches[0].clientY - e.touches[1].clientY;
      setZoom(pinch.startZoom * Math.hypot(dx, dy) / pinch.startDist,
              (e.touches[0].clientX + e.touches[1].clientX) / 2);
    } else if (drag.active && e.touches.length === 1) {
      e.preventDefault();
      scrollDiv.scrollLeft = drag.startScroll - (e.touches[0].clientX - drag.startX);
    }
  }, { passive: false });
  scrollDiv.addEventListener('touchend', () => { pinch.active = false; drag.active = false; });

  // Resize
  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(render, 80);
  });

  render();
})();
</script>

<style>
#tacho-scroll { scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9; }
#tacho-scroll::-webkit-scrollbar { height: 6px; }
#tacho-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
#tacho-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
@media print {
  #sidebar, .topbar, .btn, select { display: none !important; }
  .main-wrapper { margin: 0 !important; }
  .card { border: 1px solid #ccc !important; }
}
</style>
