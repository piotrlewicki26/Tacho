<?php /** @var array|null $driver */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= $driver ? 'Edytuj kierowcę' : 'Nowy kierowca' ?></h4>
  <a href="/drivers" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Wróć</a>
</div>

<?php if (!$driver): ?>
<div class="card border-0 mb-3" style="background:#12141e;max-width:700px">
  <div class="card-body p-3">
    <p class="mb-2 fw-semibold"><i class="bi bi-credit-card text-primary me-1"></i>Wczytaj dane z karty kierowcy (plik DDD)</p>
    <div class="d-flex gap-2 align-items-start flex-wrap">
      <input type="file" id="dddFileInput" accept=".ddd,.c1b,.dt,.dtco,.v1b,.m1,.vu"
             class="form-control" style="max-width:360px">
      <button type="button" class="btn btn-outline-primary" onclick="parseDddDriver()">
        <i class="bi bi-upload me-1"></i>Wczytaj
      </button>
    </div>
    <div id="dddStatus" class="form-text mt-1"></div>
  </div>
</div>
<?php endif; ?>

<div class="card border-0" style="background:#1a1d27;max-width:700px">
  <div class="card-body p-4">
    <form method="POST" action="<?= $driver ? '/drivers/' . $driver['id'] : '/drivers' ?>" novalidate>
      <input type="hidden" name="_token" value="<?= \Core\Auth::csrfToken() ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Imię *</label>
          <input type="text" class="form-control" name="first_name"
                 value="<?= htmlspecialchars($driver['first_name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Nazwisko *</label>
          <input type="text" class="form-control" name="last_name"
                 value="<?= htmlspecialchars($driver['last_name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Data urodzenia</label>
          <input type="date" class="form-control" name="birth_date"
                 value="<?= htmlspecialchars($driver['birth_date'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Narodowość (kod ISO)</label>
          <input type="text" class="form-control" name="nationality" maxlength="3"
                 value="<?= htmlspecialchars($driver['nationality'] ?? 'PL') ?>" placeholder="PL">
        </div>
        <div class="col-md-6">
          <label class="form-label">Nr prawa jazdy</label>
          <input type="text" class="form-control" name="license_number"
                 value="<?= htmlspecialchars($driver['license_number'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Nr karty kierowcy</label>
          <input type="text" class="form-control font-monospace" name="card_number"
                 value="<?= htmlspecialchars($driver['card_number'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Ważność karty</label>
          <input type="date" class="form-control" name="card_expiry"
                 value="<?= htmlspecialchars($driver['card_expiry'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Telefon</label>
          <input type="tel" class="form-control" name="phone"
                 value="<?= htmlspecialchars($driver['phone'] ?? '') ?>">
        </div>
        <div class="col-md-12">
          <label class="form-label">E-mail</label>
          <input type="email" class="form-control" name="email"
                 value="<?= htmlspecialchars($driver['email'] ?? '') ?>">
        </div>
        <div class="col-md-12">
          <label class="form-label">Notatki</label>
          <textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars($driver['notes'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-check-lg me-1"></i><?= $driver ? 'Zapisz zmiany' : 'Dodaj kierowcę' ?>
        </button>
        <?php if ($driver && \Core\Auth::hasRole('admin','superadmin')): ?>
        <form method="POST" action="/drivers/<?= $driver['id'] ?>/delete" class="d-inline"
              onsubmit="return confirm('Usunąć kierowcę?')">
          <input type="hidden" name="_token" value="<?= \Core\Auth::csrfToken() ?>">
          <button type="submit" class="btn btn-outline-danger">Usuń</button>
        </form>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if (!$driver): ?>
<script>
function parseDddDriver() {
  const input  = document.getElementById('dddFileInput');
  const status = document.getElementById('dddStatus');
  if (!input.files.length) {
    status.textContent = 'Wybierz plik DDD.';
    status.className   = 'form-text text-warning mt-1';
    return;
  }
  const btn = document.querySelector('[onclick="parseDddDriver()"]');
  btn.disabled = true;
  status.textContent = 'Analizuję plik…';
  status.className   = 'form-text text-muted mt-1';

  const fd = new FormData();
  fd.append('ddd_file', input.files[0]);

  fetch('/drivers/parse-ddd', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        status.textContent = data.error;
        status.className   = 'form-text text-danger mt-1';
        return;
      }
      const set = (name, val) => { if (val) { const el = document.querySelector(`[name="${name}"]`); if (el) el.value = val; } };
      set('first_name',  data.first_name);
      set('last_name',   data.last_name);
      set('card_number', data.card_number);
      set('nationality', data.nationality);
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
