<?php
/** @var array|null $license   – set when a license was successfully generated */
/** @var array|null $errors    – validation errors */
/** @var array|null $input     – previous form input on error */

use LicenseGenerator\LicenseManager;

$today    = date('Y-m-d');
$oneYear  = date('Y-m-d', strtotime('+1 year'));
$v        = $input ?? [];        // form restore helper
?>

<h4 class="fw-bold mb-4"><i class="bi bi-plus-circle me-2 text-primary"></i>Generuj nową licencję</h4>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Błąd walidacji:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($license)): ?>
    <!-- ── Generated key result ─────────────────────────────────────── -->
    <div class="alert alert-success d-flex align-items-start gap-3">
        <i class="bi bi-check-circle-fill fs-3 mt-1 flex-shrink-0"></i>
        <div class="w-100">
            <h5 class="fw-bold mb-1">Licencja wygenerowana pomyślnie!</h5>
            <p class="mb-2 text-muted small">Skopiuj klucz i przekaż go klientowi. Klucz jest przechowywany w bazie danych.</p>

            <label class="form-label fw-semibold mb-1">Klucz licencji</label>
            <div class="input-group mb-3">
                <input type="text" class="form-control license-key fw-bold fs-5"
                       id="generatedKey"
                       value="<?= htmlspecialchars($license['license_key']) ?>"
                       readonly>
                <button type="button" class="btn btn-outline-secondary" id="copyKeyBtn"
                        onclick="copyField('generatedKey','copyKeyBtn','btn-outline-secondary')">
                    <i class="bi bi-clipboard me-1"></i>Kopiuj
                </button>
            </div>

            <!-- Auto-generated per-license secret -->
            <div class="alert alert-warning p-3 mb-3">
                <div class="fw-bold mb-1">
                    <i class="bi bi-key-fill me-1"></i>Sekret weryfikacji
                    <span class="badge bg-danger ms-1">Zapisz i przekaż do systemu rozliczeń!</span>
                </div>
                <div class="input-group mb-1">
                    <input type="text" class="form-control font-monospace small"
                           id="generatedSecret"
                           value="<?= htmlspecialchars($license['used_secret']) ?>"
                           readonly>
                    <button type="button" class="btn btn-warning" id="copySecretBtn"
                            onclick="copyField('generatedSecret','copySecretBtn','btn-warning')">
                        <i class="bi bi-clipboard me-1"></i>Kopiuj
                    </button>
                </div>
                <div class="form-text text-dark">
                    <i class="bi bi-info-circle me-1"></i>
                    Ten sekret jest unikalny dla tej licencji. Skonfiguruj go w systemie rozliczeń kierowców –
                    bez niego system nie będzie mógł zweryfikować licencji offline.
                    Sekret jest bezpiecznie przechowywany w bazie danych generatora.
                </div>
            </div>

            <div class="row g-2 text-sm">
                <div class="col-sm-6">
                    <strong>Firma:</strong> <?= htmlspecialchars($license['company_name']) ?><br>
                    <strong>ID firmy:</strong> <?= htmlspecialchars($license['company_id']) ?><br>
                    <strong>Moduły:</strong>
                    <?php foreach ((array)$license['modules'] as $mod): ?>
                        <span class="badge bg-secondary"><?= htmlspecialchars(LicenseManager::MODULES[$mod] ?? $mod) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="col-sm-6">
                    <strong>Ważna od:</strong> <?= htmlspecialchars($license['valid_from']) ?><br>
                    <strong>Ważna do:</strong> <?= htmlspecialchars($license['valid_to']) ?><br>
                    <strong>Max operatorów:</strong> <?= (int)$license['max_operators'] ?><br>
                    <strong>Max kierowców:</strong>  <?= (int)$license['max_drivers'] ?>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <a href="/generate" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Generuj kolejną
                </a>
                <a href="/" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-grid-1x2 me-1"></i>Powrót do pulpitu
                </a>
            </div>
        </div>
    </div>

    <script>
    function copyField(inputId, btnId, originalClass) {
        const val = document.getElementById(inputId).value;
        const btn = document.getElementById(btnId);
        const originalHtml = btn.innerHTML;

        copyToClipboard(val, function() {
            btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Skopiowano!';
            btn.classList.remove(originalClass);
            btn.classList.add('btn-success');
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.classList.remove('btn-success');
                btn.classList.add(originalClass);
            }, 2000);
        });
    }
    </script>

<?php else: ?>
    <!-- ── Generation form ──────────────────────────────────────────── -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="POST" action="/generate" novalidate>

                <h6 class="text-muted text-uppercase small fw-bold mb-3">Dane firmy</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="company_id" class="form-label fw-semibold">ID firmy <span class="text-danger">*</span></label>
                        <input type="text" id="company_id" name="company_id" class="form-control"
                               placeholder="np. FIRMA01"
                               pattern="[A-Za-z0-9_\-]+"
                               value="<?= htmlspecialchars($v['companyId'] ?? '') ?>"
                               required>
                        <div class="form-text">Tylko litery, cyfry, myślniki i podkreślenia.</div>
                    </div>
                    <div class="col-md-8">
                        <label for="company_name" class="form-label fw-semibold">Nazwa firmy <span class="text-danger">*</span></label>
                        <input type="text" id="company_name" name="company_name" class="form-control"
                               placeholder="np. Transport Nowak Sp. z o.o."
                               value="<?= htmlspecialchars($v['companyName'] ?? '') ?>"
                               required>
                    </div>
                </div>

                <hr class="my-3">
                <h6 class="text-muted text-uppercase small fw-bold mb-3">Dostępne moduły</h6>
                <div class="row g-2 mb-4">
                    <?php foreach (LicenseManager::MODULES as $key => $label): ?>
                        <?php
                            $checked = empty($v)
                                ? ($key === 'all')
                                : in_array($key, (array)($v['modules'] ?? []), true);
                        ?>
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="form-check border rounded p-3 h-100">
                                <input class="form-check-input" type="checkbox"
                                       name="modules[]" id="mod_<?= $key ?>"
                                       value="<?= $key ?>"
                                       <?= $checked ? 'checked' : '' ?>>
                                <label class="form-check-label" for="mod_<?= $key ?>">
                                    <?= htmlspecialchars($label) ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr class="my-3">
                <h6 class="text-muted text-uppercase small fw-bold mb-3">Limity i ważność</h6>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <label for="max_operators" class="form-label fw-semibold">Max operatorów</label>
                        <input type="number" id="max_operators" name="max_operators" class="form-control"
                               min="1" max="9999"
                               value="<?= (int)($v['maxOperators'] ?? 5) ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="max_drivers" class="form-label fw-semibold">Max kierowców</label>
                        <input type="number" id="max_drivers" name="max_drivers" class="form-control"
                               min="1" max="99999"
                               value="<?= (int)($v['maxDrivers'] ?? 50) ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="valid_from" class="form-label fw-semibold">Data od</label>
                        <input type="date" id="valid_from" name="valid_from" class="form-control"
                               value="<?= htmlspecialchars($v['validFrom'] ?? $today) ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="valid_to" class="form-label fw-semibold">Data do <span class="text-danger">*</span></label>
                        <input type="date" id="valid_to" name="valid_to" class="form-control"
                               value="<?= htmlspecialchars($v['validTo'] ?? $oneYear) ?>"
                               required>
                    </div>
                </div>

                <hr class="my-3">
                <h6 class="text-muted text-uppercase small fw-bold mb-3">Opcje zaawansowane</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="hardware_id" class="form-label fw-semibold">ID sprzętu <span class="text-muted">(opcjonalnie)</span></label>
                        <input type="text" id="hardware_id" name="hardware_id" class="form-control"
                               placeholder="np. MAC-adres lub numer seryjny serwera"
                               value="<?= htmlspecialchars($v['hardwareId'] ?? '') ?>">
                        <div class="form-text">Powiąż licencję z konkretnym serwerem (hardware binding).</div>
                    </div>
                    <div class="col-md-6">
                        <label for="notes" class="form-label fw-semibold">Notatki <span class="text-muted">(opcjonalnie)</span></label>
                        <textarea id="notes" name="notes" class="form-control" rows="2"
                                  placeholder="Uwagi wewnętrzne..."><?= htmlspecialchars($v['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="alert alert-info py-2 px-3 small">
                    <i class="bi bi-info-circle me-1"></i>
                    Sekret HMAC dla tej licencji zostanie <strong>wygenerowany automatycznie</strong> i wyświetlony po kliknięciu „Generuj licencję".
                    Przekaż go do systemu rozliczeń kierowców.
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-key me-2"></i>Generuj licencję
                    </button>
                    <a href="/" class="btn btn-outline-secondary">Anuluj</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
