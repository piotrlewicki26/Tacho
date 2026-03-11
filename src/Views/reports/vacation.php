<?php
/**
 * @var array      $drivers
 * @var array|null $driver
 * @var string     $dateFrom
 * @var string     $dateTo
 * @var array      $activities
 * @var array      $totals
 * @var array|null $company
 */
$isPrint = !empty($_GET['print']);
$actLabels = ['driving'=>'Jazda','work'=>'Praca','availability'=>'Dyspozycja','rest'=>'Odpoczynek','break'=>'Przerwa'];

if ($isPrint): ?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>Urlopówka – <?= htmlspecialchars(($driver['first_name']??'').' '.($driver['last_name']??'')) ?></title>
<style>
  body { font-family: Arial, sans-serif; font-size: 11pt; color: #000; }
  .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; margin-top: 15px; }
  th, td { border: 1px solid #999; padding: 5px 8px; }
  th { background: #f0f0f0; }
  .signature-row { margin-top: 60px; display: flex; justify-content: space-between; }
  .signature-box { width: 45%; border-top: 1px solid #000; text-align: center; padding-top: 5px; }
</style>
</head>
<body>
<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <h4 class="fw-bold mb-0">Urlopówka</h4>
  <?php if ($driver): ?>
  <a href="/reports/vacation?<?= http_build_query(['driver_id'=>$driver['id'],'date_from'=>$dateFrom,'date_to'=>$dateTo,'print'=>1]) ?>"
     class="btn btn-sm btn-outline-secondary" target="_blank">
    <i class="bi bi-printer me-1"></i>Drukuj
  </a>
  <?php endif; ?>
</div>

<!-- Filter form -->
<div class="card border-0 mb-4" style="background:#1a1d27;max-width:700px">
  <div class="card-body">
    <form method="GET" action="/reports/vacation" class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Kierowca</label>
        <select class="form-select" name="driver_id">
          <option value="">— wybierz —</option>
          <?php foreach ($drivers as $d): ?>
          <option value="<?= $d['id'] ?>" <?= ($driver['id']??0) == $d['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($d['last_name'].' '.$d['first_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Data od</label>
        <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Data do</label>
        <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">Generuj</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($driver): ?>
<?php if ($isPrint): ?>
<div class="header">
  <h2>EWIDENCJA CZASU PRACY KIEROWCY</h2>
  <div>Firma: <?= htmlspecialchars($company['name'] ?? '') ?></div>
  <div>Kierowca: <?= htmlspecialchars($driver['first_name'].' '.$driver['last_name']) ?></div>
  <div>Okres: <?= date('d.m.Y', strtotime($dateFrom)) ?> – <?= date('d.m.Y', strtotime($dateTo)) ?></div>
</div>
<?php else: ?>
<div class="card border-0" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0">
      Ewidencja – <?= htmlspecialchars($driver['first_name'].' '.$driver['last_name']) ?>
      &nbsp;|&nbsp; <?= date('d.m.Y',strtotime($dateFrom)) ?> – <?= date('d.m.Y',strtotime($dateTo)) ?>
    </h6>
  </div>
  <div class="card-body">
<?php endif; ?>

  <!-- Totals summary -->
  <?php if (!$isPrint): ?><div class="row g-3 mb-4"><?php foreach ($actLabels as $t => $l): ?><div class="col-6 col-md-3"><div class="text-center p-3 rounded" style="background:#0f1117"><div class="text-muted small"><?= $l ?></div><div class="fw-bold fs-5"><?= intdiv($totals[$t],60) ?>h <?= $totals[$t]%60 ?>m</div></div></div><?php endforeach; ?></div><?php endif; ?>

  <table <?= $isPrint ? '' : 'class="table table-sm table-hover"' ?>>
    <thead>
      <tr>
        <th>Data</th>
        <?php foreach ($actLabels as $t => $l): ?><th><?= $l ?></th><?php endforeach; ?>
        <th>Razem</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $byDate = [];
      foreach ($activities as $a) {
          $byDate[$a['activity_date']][$a['activity_type']] = ($byDate[$a['activity_date']][$a['activity_type']] ?? 0) + $a['duration_minutes'];
      }
      $curDate = $dateFrom;
      while ($curDate <= $dateTo):
          $row = $byDate[$curDate] ?? [];
          $total = array_sum($row);
      ?>
      <tr>
        <td><?= date('d.m.Y', strtotime($curDate)) ?></td>
        <?php foreach ($actLabels as $t => $l): ?>
        <td><?= isset($row[$t]) && $row[$t] ? intdiv($row[$t],60).'h '.($row[$t]%60).'m' : '—' ?></td>
        <?php endforeach; ?>
        <td><?= $total ? intdiv($total,60).'h '.($total%60).'m' : '—' ?></td>
      </tr>
      <?php $curDate = date('Y-m-d', strtotime($curDate . ' +1 day')); endwhile; ?>
    </tbody>
    <tfoot>
      <tr>
        <th>Łącznie</th>
        <?php foreach ($actLabels as $t => $l): ?>
        <th><?= intdiv($totals[$t],60) ?>h <?= $totals[$t]%60 ?>m</th>
        <?php endforeach; ?>
        <th><?= intdiv(array_sum($totals),60) ?>h <?= array_sum($totals)%60 ?>m</th>
      </tr>
    </tfoot>
  </table>

  <?php if ($isPrint): ?>
  <div class="signature-row">
    <div class="signature-box">Kierowca</div>
    <div class="signature-box">Podpis pracodawcy / pieczęć</div>
  </div>
  <?php else: ?>
  </div></div>
  <?php endif; ?>

<?php endif; ?>

<?php if ($isPrint): ?>
</body></html>
<?php endif; ?>
