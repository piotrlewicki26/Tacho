<?php /** @var array $companies @var array $licenses */ ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Licencje</h4>
    <p class="text-muted small mb-0">Zarządzaj sekretami i aktywuj licencje dla firm</p>
  </div>
</div>

<!-- ═══════════════ Companies + secrets ═══════════════ -->
<div class="card mb-4">
  <div class="card-header border-0 bg-transparent d-flex align-items-center gap-2">
    <i class="bi bi-shield-lock-fill text-primary"></i>
    <h6 class="fw-semibold mb-0">Firmy — zarządzanie licencjami</h6>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0 align-middle">
      <thead>
        <tr>
          <th>Firma</th>
          <th>Klucz SECRET</th>
          <th>Aktywna licencja</th>
          <th class="text-end">Akcje</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $co): ?>
        <?php
          $lic         = $co['active_license'] ?? null;
          $hasSecret   = !empty($co['license_secret']);
          $secretMask  = $hasSecret ? str_repeat('•', 8) . substr($co['license_secret'], -6) : null;
        ?>
        <tr>
          <!-- Company name -->
          <td>
            <div class="fw-semibold small"><?= htmlspecialchars($co['name']) ?></div>
            <?php if ($co['nip']): ?>
            <div class="text-muted" style="font-size:.72rem">NIP: <?= htmlspecialchars($co['nip']) ?></div>
            <?php endif; ?>
          </td>

          <!-- Secret -->
          <td>
            <?php if ($hasSecret): ?>
            <div class="d-flex align-items-center gap-2">
              <code class="secret-display small user-select-all" id="secret-<?= $co['id'] ?>"
                    data-full="<?= htmlspecialchars($co['license_secret']) ?>"
                    data-masked="<?= htmlspecialchars($secretMask) ?>">
                <?= htmlspecialchars($secretMask) ?>
              </code>
              <button type="button" class="btn btn-xs btn-outline-secondary secret-reveal-btn"
                      data-target="secret-<?= $co['id'] ?>" title="Pokaż/ukryj">
                <i class="bi bi-eye"></i>
              </button>
              <button type="button" class="btn btn-xs btn-outline-secondary secret-copy-btn"
                      data-target="secret-<?= $co['id'] ?>" title="Kopiuj">
                <i class="bi bi-clipboard"></i>
              </button>
            </div>
            <?php else: ?>
            <span class="text-muted small fst-italic">Brak sekretu</span>
            <?php endif; ?>
          </td>

          <!-- Active license -->
          <td>
            <?php if ($lic): ?>
            <div>
              <span class="badge bg-success-subtle text-success border border-success-subtle small">AKTYWNA</span>
              <div class="text-muted mt-1" style="font-size:.72rem">
                Ważna do: <strong><?= date('d.m.Y', strtotime($lic['valid_to'])) ?></strong>
                &nbsp;·&nbsp; Moduły: <?= implode(', ', json_decode($lic['modules']??'[]',true)?:[]) ?>
              </div>
            </div>
            <?php else: ?>
            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small">BRAK</span>
            <?php endif; ?>
          </td>

          <!-- Actions -->
          <td class="text-end">
            <div class="d-flex justify-content-end gap-2">
              <!-- Generate / regenerate secret -->
              <form method="POST" action="/admin/licenses/<?= $co['id'] ?>/generate-secret"
                    class="d-inline"
                    onsubmit="return confirm('<?= $hasSecret ? 'Regeneracja sekretu unieważni istniejące licencje. Kontynuować?' : 'Wygenerować nowy sekret dla tej firmy?' ?>');">
                <input type="hidden" name="_token" value="<?= \Core\Auth::csrfToken() ?>">
                <button type="submit" class="btn btn-xs <?= $hasSecret ? 'btn-outline-warning' : 'btn-outline-primary' ?>">
                  <i class="bi bi-<?= $hasSecret ? 'arrow-clockwise' : 'key' ?> me-1"></i>
                  <?= $hasSecret ? 'Regeneruj SECRET' : 'Generuj SECRET' ?>
                </button>
              </form>

              <!-- Activate license (only if secret exists) -->
              <?php if ($hasSecret): ?>
              <button type="button" class="btn btn-xs btn-primary"
                      data-bs-toggle="modal"
                      data-bs-target="#activateModal"
                      data-company-id="<?= $co['id'] ?>"
                      data-company-name="<?= htmlspecialchars($co['name']) ?>">
                <i class="bi bi-patch-check me-1"></i>Aktywuj licencję
              </button>
              <?php else: ?>
              <button type="button" class="btn btn-xs btn-outline-secondary" disabled title="Najpierw wygeneruj SECRET">
                <i class="bi bi-patch-check me-1"></i>Aktywuj licencję
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($companies)): ?>
        <tr><td colspan="4" class="text-muted text-center py-4">Brak firm w systemie</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══════════════ License history ═══════════════ -->
<div class="card">
  <div class="card-header border-0 bg-transparent d-flex align-items-center gap-2">
    <i class="bi bi-clock-history text-muted"></i>
    <h6 class="fw-semibold mb-0">Historia licencji</h6>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead>
        <tr><th>Firma</th><th>Klucz</th><th>Moduły</th><th class="text-center">Operatorzy</th><th class="text-center">Kierowcy</th><th>Ważność</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($licenses as $l): ?>
        <?php
          $expired = $l['valid_to'] < date('Y-m-d');
          $active  = $l['is_active'] && !$expired;
        ?>
        <tr>
          <td class="small"><?= htmlspecialchars($l['company_name'] ?? '') ?></td>
          <td class="font-monospace small text-primary-emphasis"><?= htmlspecialchars($l['license_key']) ?></td>
          <td class="small"><?= implode(', ', json_decode($l['modules']??'[]',true)?:[]) ?></td>
          <td class="small text-center"><?= $l['max_operators'] ?></td>
          <td class="small text-center"><?= $l['max_drivers'] ?></td>
          <td class="small"><?= date('d.m.Y', strtotime($l['valid_from'])) ?> – <?= date('d.m.Y', strtotime($l['valid_to'])) ?></td>
          <td>
            <span class="badge bg-<?= $active ? 'success' : ($expired ? 'danger' : 'secondary') ?>-subtle
                                   text-<?= $active ? 'success' : ($expired ? 'danger' : 'secondary') ?>
                                   border border-<?= $active ? 'success' : ($expired ? 'danger' : 'secondary') ?>-subtle">
              <?= $active ? 'AKTYWNA' : ($expired ? 'WYGASŁA' : 'NIEAKTYWNA') ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($licenses)): ?>
        <tr><td colspan="7" class="text-muted text-center py-4">Brak licencji</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══════════════ Activate License Modal ═══════════════ -->
<div class="modal fade" id="activateModal" tabindex="-1" aria-labelledby="activateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="background: var(--bg-card); border: 1px solid var(--border);">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="activateModalLabel">
          <i class="bi bi-patch-check-fill text-primary me-2"></i>Aktywuj licencję
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="activateForm" action="">
        <div class="modal-body">
          <input type="hidden" name="_token" value="<?= \Core\Auth::csrfToken() ?>">

          <div class="alert alert-info border-0 d-flex gap-2 small py-2 mb-3" style="background:rgba(59,130,246,.1)">
            <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
            <span>Wprowadź klucz licencji dostarczony przez wystawcę licencji wraz z parametrami licencji. Istniejące aktywne licencje tej firmy zostaną dezaktywowane.</span>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Firma</label>
            <div class="form-control bg-transparent" id="activateCompanyName" style="cursor:default">—</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Klucz licencji *</label>
            <input type="text" class="form-control font-monospace text-uppercase"
                   name="license_key" id="activateLicenseKey"
                   placeholder="TACHO-XXXX-XXXX-XXXX-XXXX"
                   pattern="TACHO(-[A-Z0-9]{4}){4}"
                   maxlength="25" required
                   oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9-]/g,'')">
            <div class="form-text">Format: TACHO-XXXX-XXXX-XXXX-XXXX</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Moduły *</label>
            <fieldset aria-required="true">
              <legend class="visually-hidden">Wybierz moduły licencji (wymagany co najmniej jeden)</legend>
              <div class="d-flex flex-wrap gap-3 mt-1">
                <?php foreach (['analysis'=>'Analiza DDD','reports'=>'Raporty','violations'=>'Naruszenia','delegation'=>'Delegacje','vacation'=>'Urlopówki','all'=>'Wszystkie'] as $mod => $lbl): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="modules[]" value="<?= $mod ?>" id="amod_<?= $mod ?>">
                  <label class="form-check-label small" for="amod_<?= $mod ?>"><?= $lbl ?></label>
                </div>
                <?php endforeach; ?>
              </div>
            </fieldset>
          </div>

          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Max operatorów</label>
              <input type="number" class="form-control" name="max_operators" value="5" min="1" max="999">
            </div>
            <div class="col-md-3">
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
            <div class="col-12">
              <label class="form-label">Hardware ID (opcjonalnie)</label>
              <input type="text" class="form-control font-monospace" name="hardware_id" placeholder="ID serwera / sprzętu">
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-patch-check me-1"></i>Aktywuj licencję
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.btn-xs { padding: .2rem .55rem; font-size: .75rem; border-radius: 5px; }
.secret-display { background: rgba(255,255,255,.04); border-radius: 4px; padding: 2px 6px; letter-spacing: .05em; }
</style>

<script>
(function () {
  // ── Activate modal – inject company data ──────────────────────────────
  const modal = document.getElementById('activateModal');
  if (modal) {
    modal.addEventListener('show.bs.modal', function (e) {
      const btn       = e.relatedTarget;
      const companyId = btn.dataset.companyId;
      const name      = btn.dataset.companyName;
      document.getElementById('activateCompanyName').textContent = name;
      document.getElementById('activateForm').action = '/admin/licenses/' + companyId + '/activate';
      document.getElementById('activateLicenseKey').value = '';
      // Reset all form inputs to defaults
      modal.querySelectorAll('input[name="modules[]"]').forEach(cb => { cb.checked = false; });
      const opsEl = modal.querySelector('input[name="max_operators"]');
      const drvEl = modal.querySelector('input[name="max_drivers"]');
      const fromEl = modal.querySelector('input[name="valid_from"]');
      const toEl   = modal.querySelector('input[name="valid_to"]');
      const hwEl   = modal.querySelector('input[name="hardware_id"]');
      if (opsEl)  opsEl.value  = '5';
      if (drvEl)  drvEl.value  = '50';
      if (fromEl) fromEl.value = new Date().toISOString().slice(0, 10);
      if (toEl) {
        const nextYear = new Date(); nextYear.setFullYear(nextYear.getFullYear() + 1);
        toEl.value = nextYear.toISOString().slice(0, 10);
      }
      if (hwEl)   hwEl.value   = '';
    });
  }

  // ── Secret reveal / hide ──────────────────────────────────────────────
  document.querySelectorAll('.secret-reveal-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const el = document.getElementById(btn.dataset.target);
      if (!el) return;
      const isRevealed = el.dataset.revealed === '1';
      el.textContent   = isRevealed ? el.dataset.masked : el.dataset.full;
      el.dataset.revealed = isRevealed ? '0' : '1';
      btn.querySelector('i').className = 'bi bi-' + (isRevealed ? 'eye' : 'eye-slash');
    });
  });

  // ── Secret copy ───────────────────────────────────────────────────────
  document.querySelectorAll('.secret-copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const el = document.getElementById(btn.dataset.target);
      if (!el) return;
      navigator.clipboard.writeText(el.dataset.full).then(function () {
        const icon = btn.querySelector('i');
        icon.className = 'bi bi-clipboard-check text-success';
        setTimeout(function () { icon.className = 'bi bi-clipboard'; }, 2000);
      });
    });
  });
})();
</script>
