<?php
/**
 * @var array      $drivers
 * @var array|null $driver
 * @var string     $dateFrom
 * @var string     $dateTo
 * @var array      $byCountry
 * @var array|null $company
 */
$isPrint = !empty($_GET['print']);
$actLabels = ['driving'=>'Jazda','work'=>'Praca','availability'=>'Dyspozycja','rest'=>'Odpoczynek','break'=>'Przerwa'];

$countryNames = [
    'PL'=>'Polska','DE'=>'Niemcy','FR'=>'Francja','NL'=>'Niderlandy',
    'BE'=>'Belgia','LU'=>'Luksemburg','AT'=>'Austria','CZ'=>'Czechy',
    'SK'=>'Słowacja','HU'=>'Węgry','RO'=>'Rumunia','BG'=>'Bułgaria',
    'HR'=>'Chorwacja','IT'=>'Włochy','ES'=>'Hiszpania','PT'=>'Portugalia',
    'UK'=>'Wielka Brytania','DK'=>'Dania','SE'=>'Szwecja','FI'=>'Finlandia',
    'EE'=>'Estonia','LV'=>'Łotwa','LT'=>'Litwa','NO'=>'Norwegia',
];

if ($isPrint): ?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>Delegacja – <?= htmlspecialchars(($driver['first_name']??'').' '.($driver['last_name']??'')) ?></title>
<style>
  body { font-family: Arial, sans-serif; font-size: 11pt; }
  .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; margin-top: 15px; }
  th, td { border: 1px solid #999; padding: 5px 8px; }
  th { background: #f0f0f0; }
  .note { font-size:9pt; color:#555; margin-top:10px; }
  .signature-row { margin-top: 60px; display: flex; justify-content: space-between; }
  .signature-box { width: 45%; border-top: 1px solid #000; text-align: center; padding-top: 5px; }
</style>
</head>
<body>
<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <h4 class="fw-bold mb-0">Delegacja – podział na państwa</h4>
  <?php if ($driver && !empty($byCountry)): ?>
  <a href="/reports/delegation?<?= http_build_query(['driver_id'=>$driver['id'],'date_from'=>$dateFrom,'date_to'=>$dateTo,'print'=>1]) ?>"
     class="btn btn-sm btn-outline-secondary" target="_blank">
    <i class="bi bi-printer me-1"></i>Drukuj
  </a>
  <?php endif; ?>
</div>

<div class="card border-0 mb-4" style="background:#1a1d27;max-width:700px">
  <div class="card-body">
    <form method="GET" action="/reports/delegation" class="row g-3">
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

<?php if ($driver && !empty($byCountry)): ?>
<?php if ($isPrint): ?>
<div class="header">
  <h2>ROZLICZENIE DELEGACJI ZAGRANICZNEJ</h2>
  <div>Firma: <?= htmlspecialchars($company['name'] ?? '') ?></div>
  <div>Kierowca: <?= htmlspecialchars($driver['first_name'].' '.$driver['last_name']) ?></div>
  <div>Okres: <?= date('d.m.Y', strtotime($dateFrom)) ?> – <?= date('d.m.Y', strtotime($dateTo)) ?></div>
  <div style="margin-top:5px;font-size:9pt">Zgodnie z Pakietem Mobilności (dyrektywa 2020/1057/UE)</div>
</div>
<?php else: ?>
<div class="card border-0" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0">
      <?= htmlspecialchars($driver['first_name'].' '.$driver['last_name']) ?>
      &nbsp;|&nbsp; <?= date('d.m.Y',strtotime($dateFrom)) ?> – <?= date('d.m.Y',strtotime($dateTo)) ?>
    </h6>
    <p class="text-muted small mb-0">Pakiet Mobilności (2020/1057/UE)</p>
  </div>
  <div class="card-body">
<?php endif; ?>

  <table <?= $isPrint ? '' : 'class="table table-hover table-sm"' ?>>
    <thead>
      <tr>
        <th>Kraj</th>
        <th>Dni</th>
        <th>Jazda</th>
        <th>Praca</th>
        <th>Odpoczynek</th>
        <th>Razem aktyw.</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($byCountry as $cc => $data): ?>
      <tr>
        <td>
          <span style="font-weight:bold"><?= htmlspecialchars($cc) ?></span>
          <?php if (isset($countryNames[$cc])): ?> – <?= $countryNames[$cc] ?><?php endif; ?>
        </td>
        <td><?= $data['day_count'] ?></td>
        <td><?= intdiv($data['driving'],60) ?>h <?= $data['driving']%60 ?>m</td>
        <td><?= intdiv($data['work'],60) ?>h <?= $data['work']%60 ?>m</td>
        <td><?= intdiv($data['rest']+$data['break'],60) ?>h <?= ($data['rest']+$data['break'])%60 ?>m</td>
        <td>
          <?php $total = $data['driving']+$data['work']+$data['availability']+$data['rest']+$data['break']; ?>
          <?= intdiv($total,60) ?>h <?= $total%60 ?>m
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th>Razem</th>
        <th><?= array_sum(array_column($byCountry,'day_count')) ?></th>
        <?php
        $sumD = array_sum(array_column($byCountry,'driving'));
        $sumW = array_sum(array_column($byCountry,'work'));
        $sumR = array_sum(array_map(fn($c)=>$c['rest']+$c['break'], $byCountry));
        $sumAll = $sumD+$sumW+$sumR+array_sum(array_column($byCountry,'availability'));
        ?>
        <th><?= intdiv($sumD,60) ?>h <?= $sumD%60 ?>m</th>
        <th><?= intdiv($sumW,60) ?>h <?= $sumW%60 ?>m</th>
        <th><?= intdiv($sumR,60) ?>h <?= $sumR%60 ?>m</th>
        <th><?= intdiv($sumAll,60) ?>h <?= $sumAll%60 ?>m</th>
      </tr>
    </tfoot>
  </table>

  <?php if ($isPrint): ?>
  <p class="note">Raport wygenerowany przez TachoSystem <?= date('d.m.Y H:i') ?>. Dane z analizy pliku DDD tachografu.</p>
  <div class="signature-row">
    <div class="signature-box">Kierowca</div>
    <div class="signature-box">Podpis pracodawcy / pieczęć</div>
  </div>
  <?php else: ?>
  </div></div>
  <?php endif; ?>

<?php elseif ($driver): ?>
<div class="alert alert-info">Brak danych aktywności dla wybranego okresu.</div>
<?php endif; ?>

<?php if ($isPrint): ?>
</body></html>
<?php endif; ?>
