<?php /** @var array|null $vehicle */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= $vehicle ? 'Edytuj pojazd' : 'Nowy pojazd' ?></h4>
  <a href="/vehicles" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Wróć</a>
</div>

<?php if (!$vehicle): ?>
<div class="card border-0 mb-3" style="background:#12141e;max-width:700px">
  <div class="card-body p-3">
    <p class="mb-2 fw-semibold"><i class="bi bi-truck text-primary me-1"></i>Wczytaj dane z tachografu pojazdu (plik DDD)</p>
    <div class="d-flex gap-2 align-items-start flex-wrap">
      <input type="file" id="dddFileInput" accept=".ddd,.c1b,.dt,.dtco,.v1b,.m1,.vu"
             class="form-control" style="max-width:360px">
      <button type="button" class="btn btn-outline-primary" onclick="parseDddVehicle()">
        <i class="bi bi-upload me-1"></i>Wczytaj
      </button>
    </div>
    <div id="dddStatus" class="form-text mt-1"></div>
  </div>
</div>
<?php endif; ?>

<div class="card border-0" style="background:#1a1d27;max-width:700px">
  <div class="card-body p-4">
    <form method="POST" action="<?= $vehicle ? '/vehicles/' . $vehicle['id'] : '/vehicles' ?>">
      <input type="hidden" name="_token" value="<?= \Core\Auth::csrfToken() ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nr rejestracyjny *</label>
          <input type="text" class="form-control font-monospace text-uppercase" name="registration"
                 value="<?= htmlspecialchars($vehicle['registration'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Marka</label>
          <input type="text" class="form-control" name="brand"
                 value="<?= htmlspecialchars($vehicle['brand'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Model</label>
          <input type="text" class="form-control" name="model"
                 value="<?= htmlspecialchars($vehicle['model'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Rok produkcji</label>
          <input type="number" class="form-control" name="year" min="1990" max="2030"
                 value="<?= htmlspecialchars((string)($vehicle['year'] ?? '')) ?>">
        </div>
        <div class="col-md-12">
          <label class="form-label">VIN</label>
          <input type="text" class="form-control font-monospace text-uppercase" name="vin" maxlength="17"
                 value="<?= htmlspecialchars($vehicle['vin'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Nr seryjny tachografu</label>
          <input type="text" class="form-control font-monospace" name="tachograph_serial"
                 value="<?= htmlspecialchars($vehicle['tachograph_serial'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Typ tachografu</label>
          <select class="form-select" name="tachograph_type">
            <option value="">— wybierz —</option>
            <?php foreach (['Analogowy','Cyfrowy Gen.1','Cyfrowy Gen.2 (Smart)'] as $t): ?>
            <option value="<?= $t ?>" <?= ($vehicle['tachograph_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-12">
          <label class="form-label">Notatki</label>
          <textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars($vehicle['notes'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="mt-4">
        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-check-lg me-1"></i><?= $vehicle ? 'Zapisz zmiany' : 'Dodaj pojazd' ?>
        </button>
      </div>
    </form>
  </div>
</div>

<?php if (!$vehicle): ?>
<script>
function parseDddVehicle() {
  const input  = document.getElementById('dddFileInput');
  const status = document.getElementById('dddStatus');
  if (!input.files.length) {
    status.textContent = 'Wybierz plik DDD.';
    status.className   = 'form-text text-warning mt-1';
    return;
  }
  const btn = document.querySelector('[onclick="parseDddVehicle()"]');
  btn.disabled = true;
  status.textContent = 'Analizuję plik…';
  status.className   = 'form-text text-muted mt-1';

  const fd = new FormData();
  fd.append('ddd_file', input.files[0]);

  fetch('/vehicles/parse-ddd', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        status.textContent = data.error;
        status.className   = 'form-text text-danger mt-1';
        return;
      }
      const set = (name, val) => { if (val) { const el = document.querySelector(`[name="${name}"]`); if (el) el.value = val; } };
      set('registration', data.registration ? data.registration.toUpperCase() : '');
      set('vin',          data.vin          ? data.vin.toUpperCase()          : '');
      status.textContent = 'Dane wczytane pomyślnie. Sprawdź pola i zapisz.';
      status.className   = 'form-text text-success mt-1';
    })
    .catch(() => {
      status.textContent = 'Błąd komunikacji z serwerem.';
      status.className   = 'form-text text-danger mt-1';
    })
    .finally(() => { btn.disabled = false; });
}
</script>
<?php endif; ?>
