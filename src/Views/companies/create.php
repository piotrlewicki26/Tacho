<?php /** @var array|null $company */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= $company ? 'Edytuj firmę' : 'Nowa firma' ?></h4>
  <a href="/companies" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Wróć</a>
</div>
<div class="card border-0" style="background:#1a1d27;max-width:600px">
  <div class="card-body p-4">
    <form method="POST" action="<?= $company ? '/companies/' . $company['id'] : '/companies' ?>">
      <input type="hidden" name="_token" value="<?= \Core\Auth::csrfToken() ?>">
      <div class="row g-3">
        <div class="col-12"><label class="form-label">Nazwa firmy *</label><input type="text" class="form-control" name="name" value="<?= htmlspecialchars($company['name']??'') ?>" required></div>
        <div class="col-md-6"><label class="form-label">NIP</label><input type="text" class="form-control font-monospace" name="nip" value="<?= htmlspecialchars($company['nip']??'') ?>"></div>
        <div class="col-md-6"><label class="form-label">Telefon</label><input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($company['phone']??'') ?>"></div>
        <div class="col-12"><label class="form-label">Adres</label><input type="text" class="form-control" name="address" value="<?= htmlspecialchars($company['address']??'') ?>"></div>
        <div class="col-md-6"><label class="form-label">Miasto</label><input type="text" class="form-control" name="city" value="<?= htmlspecialchars($company['city']??'') ?>"></div>
        <div class="col-md-6"><label class="form-label">Kraj</label><input type="text" class="form-control" name="country" value="<?= htmlspecialchars($company['country']??'Poland') ?>"></div>
        <div class="col-12"><label class="form-label">E-mail</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($company['email']??'') ?>"></div>
      </div>
      <div class="mt-4"><button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i><?= $company ? 'Zapisz' : 'Dodaj firmę' ?></button></div>
    </form>
  </div>
</div>
