<?php /** @var array|null $vehicle */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= $vehicle ? 'Edytuj pojazd' : 'Nowy pojazd' ?></h4>
  <a href="/vehicles" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Wróć</a>
</div>
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
