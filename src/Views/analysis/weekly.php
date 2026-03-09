<?php
/**
 * @var array  $file
 * @var string $weekStart
 * @var string $weekEnd
 * @var array  $weekDates
 * @var array  $weeklyData
 * @var array  $violations
 * @var array  $weekKeys
 * @var int    $fileId
 */
$actColors = [
    'driving'      => '#4f8ef7',
    'work'         => '#fbbf24',
    'availability' => '#34d399',
    'rest'         => '#6b7280',
    'break'        => '#a78bfa',
];
$actLabels = ['driving'=>'Jazda','work'=>'Praca','availability'=>'Dyspozycja','rest'=>'Odpoczynek','break'=>'Przerwa'];
$limits    = ['driving' => 540, 'work' => null, 'availability' => null, 'rest' => 660, 'break' => null];
?>
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
        Tydzień <?= date('d.m', strtotime($wk)) ?>–<?= date('d.m.Y', strtotime($wk.' +6 days')) ?>
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

<!-- Stacked bar chart -->
<div class="card border-0 mb-4" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0">
      Tydzień: <?= date('d.m.Y', strtotime($weekStart)) ?> – <?= date('d.m.Y', strtotime($weekEnd)) ?>
    </h6>
  </div>
  <div class="card-body">
    <canvas id="weeklyChart" height="120"></canvas>
  </div>
</div>

<!-- Summary table -->
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
        <?php
        $weekTotals = ['driving'=>0,'work'=>0,'availability'=>0,'rest'=>0,'break'=>0];
        foreach ($weekDates as $d):
            $row = $weeklyData[$d];
            $dayTotal = array_sum($row);
            foreach ($row as $t => $m) $weekTotals[$t] += $m;
        ?>
        <tr>
          <td class="fw-semibold small"><?= date('D d.m', strtotime($d)) ?></td>
          <?php foreach ($actLabels as $type => $label): ?>
          <td class="small <?= ($limits[$type] && $row[$type] > $limits[$type]) ? 'text-danger fw-semibold' : 'text-muted' ?>">
            <?php if ($row[$type]): ?>
            <?= intdiv($row[$type],60) ?>h <?= $row[$type]%60 ?>m
            <?php else: ?>—<?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td class="small fw-semibold"><?= intdiv($dayTotal,60) ?>h <?= $dayTotal%60 ?>m</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="fw-bold">
        <tr>
          <td>Łącznie</td>
          <?php foreach ($actLabels as $type => $label): ?>
          <td class="<?= $weekTotals[$type] > ($limits[$type] ?? PHP_INT_MAX) ? 'text-danger' : '' ?>">
            <?= intdiv($weekTotals[$type],60) ?>h <?= $weekTotals[$type]%60 ?>m
          </td>
          <?php endforeach; ?>
          <td><?= intdiv(array_sum($weekTotals),60) ?>h <?= array_sum($weekTotals)%60 ?>m</td>
        </tr>
        <tr class="text-muted small">
          <td>Limit EU</td>
          <td title="Art. 6(2)">56h</td>
          <td colspan="4">—</td>
          <td>90h/2tyg.</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- Violations -->
<?php if (!empty($violations)): ?>
<div class="card border-0" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Naruszenia – plik (<?= count($violations) ?>)</h6>
  </div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead class="text-muted small"><tr><th>Typ</th><th>Opis</th><th>Podstawa prawna</th><th>Grzywna</th><th>Waga</th></tr></thead>
      <tbody>
        <?php foreach ($violations as $v): ?>
        <tr>
          <td class="small"><?= htmlspecialchars($v['violation_type']) ?></td>
          <td class="small"><?= htmlspecialchars($v['description']) ?></td>
          <td class="small text-primary"><?= htmlspecialchars($v['regulation_ref'] ?? '') ?></td>
          <td class="small text-warning"><?= $v['fine_amount_min'] ? number_format($v['fine_amount_min'],0,',',' ') . '–' . number_format($v['fine_amount_max'],0,',',' ') . ' zł' : '—' ?></td>
          <td><span class="badge bg-<?= $v['severity'] === 'critical' ? 'danger' : ($v['severity'] === 'major' ? 'warning text-dark' : 'secondary') ?>"><?= $v['severity'] ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
(function() {
  const ctx = document.getElementById('weeklyChart');
  if (!ctx) return;
  const labels = <?= json_encode(array_map(fn($d) => date('D d.m', strtotime($d)), $weekDates)) ?>;
  const data   = <?= json_encode($weeklyData) ?>;
  const dates  = <?= json_encode($weekDates) ?>;
  const types  = ['driving','work','availability','rest','break'];
  const colors = <?= json_encode(array_values($actColors)) ?>;
  const lbls   = <?= json_encode(array_values($actLabels)) ?>;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: types.map((t,i) => ({
        label: lbls[i],
        data: dates.map(d => data[d] ? Math.round(data[d][t]/60*10)/10 : 0),
        backgroundColor: colors[i],
      }))
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      plugins: { legend: { labels: { color: '#9ca3af', boxWidth: 12 } } },
      scales: {
        x: { stacked: true, ticks: { color: '#9ca3af' }, grid: { color: '#2d3250' } },
        y: { stacked: true, ticks: { color: '#9ca3af' }, grid: { color: '#2d3250' },
             title: { display: true, text: 'Godziny', color: '#9ca3af' } }
      }
    }
  });
})();
</script>

<style>
@media print {
  #sidebar,.topbar,.btn,select{display:none!important}
  .main-wrapper{margin:0!important}
}
</style>
