<?php /** @var array $companies @var array $licenses */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Licencje</h4>
</div>

<!-- Generate form -->
<div class="card border-0 mb-4" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0"><i class="bi bi-key me-2"></i>Generuj licencję</h6>
  </div>
  <div class="card-body">
    <form method="POST" action="/admin/licenses/generate" class="row g-3">
      <input type="hidden" name="_token" value="<?= \Core\Auth::csrfToken() ?>">
      <div class="col-md-4">
        <label class="form-label">Firma *</label>
        <select class="form-select" name="company_id" required>
          <option value="">— wybierz —</option>
          <?php foreach ($companies as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Moduły *</label>
        <div class="d-flex flex-wrap gap-2 mt-1">
          <?php foreach (['analysis'=>'Analiza DDD','reports'=>'Raporty','violations'=>'Naruszenia','delegation'=>'Delegacje','vacation'=>'Urlopówki','all'=>'Wszystkie'] as $mod => $label): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="modules[]" value="<?= $mod ?>" id="mod_<?= $mod ?>">
            <label class="form-check-label small" for="mod_<?= $mod ?>"><?= $label ?></label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-md-2">
        <label class="form-label">Max operatorów</label>
        <input type="number" class="form-control" name="max_operators" value="5" min="1" max="999">
      </div>
      <div class="col-md-2">
        <label class="form-label">Max kierowców</label>
        <input type="number" class="form-control" name="max_drivers" value="50" min="1" max="9999">
      </div>
      <div class="col-md-3">
        <label class="form-label">Ważna od</label>
        <input type="date" class="form-control" name="valid_from" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Ważna do</label>
        <input type="date" class="form-control" name="valid_to" value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Hardware ID (opcjonalnie)</label>
        <input type="text" class="form-control font-monospace" name="hardware_id" placeholder="ID serwera">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus me-1"></i>Generuj</button>
      </div>
    </form>
  </div>
</div>

<!-- Existing licenses -->
<div class="card border-0" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent"><h6 class="fw-semibold mb-0">Istniejące licencje</h6></div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead class="text-muted small">
        <tr><th>Firma</th><th>Klucz</th><th>Moduły</th><th>Operatorzy</th><th>Kierowcy</th><th>Ważność</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($licenses as $l): ?>
        <tr>
          <td class="small"><?= htmlspecialchars($l['company_name'] ?? '') ?></td>
          <td class="font-monospace small text-success"><?= htmlspecialchars($l['license_key']) ?></td>
          <td class="small"><?= implode(', ', json_decode($l['modules']??'[]',true)?:[]) ?></td>
          <td class="small text-center"><?= $l['max_operators'] ?></td>
          <td class="small text-center"><?= $l['max_drivers'] ?></td>
          <td class="small"><?= date('d.m.Y', strtotime($l['valid_from'])) ?> – <?= date('d.m.Y', strtotime($l['valid_to'])) ?></td>
          <td>
            <?php
            $expired = $l['valid_to'] < date('Y-m-d');
            $active  = $l['is_active'] && !$expired;
            ?>
            <span class="badge bg-<?= $active ? 'success' : ($expired ? 'danger' : 'secondary') ?>">
              <?= $active ? 'AKTYWNA' : ($expired ? 'WYGASŁA' : 'NIEAKTYWNA') ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($licenses)): ?>
        <tr><td colspan="7" class="text-muted text-center py-3">Brak licencji</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
