<?php /** @var array|null $driver */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= $driver ? 'Edytuj kierowcę' : 'Nowy kierowca' ?></h4>
  <a href="/drivers" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Wróć</a>
</div>

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
