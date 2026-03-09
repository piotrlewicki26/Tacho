<?php
declare(strict_types=1);

/** @var array|null  $result     - database verification result */
/** @var string|null $licenseKey - form input restore */
/** @var string|null $companyId  - form input restore */
/** @var array|null  $keyData    - offline-decoded key payload */

use LicenseGenerator\LicenseManager;
use LicenseGenerator\LicenseDecoder;
?>

<h4 class="fw-bold mb-4"><i class="bi bi-patch-check me-2 text-primary"></i>Weryfikacja klucza licencji</h4>

<!-- Verification form -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        <form method="POST" action="/verify" novalidate>
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="license_key" class="form-label fw-semibold">Klucz licencji</label>
                    <input type="text" id="license_key" name="license_key" class="form-control license-key"
                           placeholder="TACHO-XXXX-XXXX-XXXX-XXXX"
                           value="<?= htmlspecialchars($licenseKey ?? '') ?>"
                           required>
                </div>
                <div class="col-md-4">
                    <label for="company_id" class="form-label fw-semibold">ID firmy</label>
                    <input type="text" id="company_id" name="company_id" class="form-control"
                           placeholder="np. FIRMA01"
                           value="<?= htmlspecialchars($companyId ?? '') ?>"
                           required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i>Sprawdź
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (isset($result)): ?>

    <!-- ── Database verification result ──────────────────────────────── -->
    <?php if ($result['valid']): ?>
        <div class="alert alert-success d-flex align-items-start gap-3">
            <i class="bi bi-check-circle-fill fs-2 flex-shrink-0 mt-1"></i>
            <div>
                <h5 class="fw-bold mb-1">Licencja prawidłowa</h5>
                <p class="mb-0"><?= htmlspecialchars($result['message']) ?></p>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger d-flex align-items-start gap-3">
            <i class="bi bi-x-circle-fill fs-2 flex-shrink-0 mt-1"></i>
            <div>
                <h5 class="fw-bold mb-1">Nieprawidłowa licencja</h5>
                <p class="mb-0"><?= htmlspecialchars($result['message']) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($result['license'])): ?>
        <?php $lic = $result['license']; $modules = json_decode($lic['modules'], true) ?: []; ?>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-database me-2"></i>Szczegóły licencji (z bazy danych)
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted small">Firma</div>
                        <div class="fw-semibold"><?= htmlspecialchars($lic['company_name']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($lic['company_id']) ?></div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted small">Ważność</div>
                        <div><?= htmlspecialchars($lic['valid_from']) ?> → <?= htmlspecialchars($lic['valid_to']) ?></div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted small">Limity</div>
                        <div>Operatorzy: <strong><?= (int)$lic['max_operators'] ?></strong></div>
                        <div>Kierowcy: <strong><?= (int)$lic['max_drivers'] ?></strong></div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted small">Moduły</div>
                        <div>
                            <?php foreach ($modules as $mod): ?>
                                <span class="badge bg-secondary me-1">
                                    <?= htmlspecialchars(LicenseManager::MODULES[$mod] ?? $mod) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (!empty($lic['hardware_id'])): ?>
                    <div class="col-12">
                        <div class="text-muted small">ID sprzętu</div>
                        <code><?= htmlspecialchars($lic['hardware_id']) ?></code>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($lic['notes'])): ?>
                    <div class="col-12">
                        <div class="text-muted small">Notatki</div>
                        <div><?= htmlspecialchars($lic['notes']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <div class="text-muted small">Skrót SHA-256</div>
                        <code class="small text-break"><?= htmlspecialchars($lic['sha256_hash']) ?></code>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php if (!empty($keyData)): ?>
    <!-- ── Offline key decode result ──────────────────────────────────── -->
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
            <i class="bi bi-key text-success"></i>
            Dane zakodowane w kluczu
            <span class="badge bg-success ms-1">weryfikacja offline</span>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Poniższe dane zostały odczytane bezpośrednio z klucza licencji (bez dostępu do bazy danych)
                przy użyciu skonfigurowanego sekretu <code>LICENSE_SECRET</code>.
                Integralność klucza potwierdza wbudowany skrót HMAC-SHA256.
            </p>
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small">Ważność (w kluczu)</div>
                    <div><?= htmlspecialchars($keyData['valid_from']) ?> → <?= htmlspecialchars($keyData['valid_to']) ?></div>
                    <?php
                        $today  = date('Y-m-d');
                        $active = $keyData['valid_from'] <= $today && $today <= $keyData['valid_to'];
                    ?>
                    <span class="badge <?= $active ? 'bg-success' : 'bg-danger' ?> mt-1">
                        <?= $active ? 'Aktywna' : 'Nieaktywna / wygasła' ?>
                    </span>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small">Limity (w kluczu)</div>
                    <div>Operatorzy: <strong><?= (int)$keyData['max_operators'] ?></strong></div>
                    <div>Kierowcy: <strong><?= (int)$keyData['max_drivers'] ?></strong></div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small">Moduły (w kluczu)</div>
                    <div>
                        <?php foreach ($keyData['modules'] as $mod): ?>
                            <span class="badge bg-primary me-1">
                                <?= htmlspecialchars(LicenseManager::MODULES[$mod] ?? $mod) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small">Integralność HMAC</div>
                    <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Zweryfikowana</span>
                </div>
            </div>
        </div>
    </div>
<?php elseif (isset($result) && empty($keyData)): ?>
    <div class="alert alert-warning mt-3">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Nie można odczytać danych z klucza przy użyciu skonfigurowanego sekretu
        <code>LICENSE_SECRET</code>.  Sprawdź, czy klucz jest poprawny i czy sekret jest właściwy.
    </div>
<?php endif; ?>
