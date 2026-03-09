<?php
/** @var array $licenses */
/** @var array $stats */
/** @var array|null $flashMsg – handled in layout */

use LicenseGenerator\LicenseManager;

$today = date('Y-m-d');

/**
 * Returns a Bootstrap badge class based on license state.
 */
function licenseStatusBadge(array $lic, string $today): string
{
    if (!(bool)$lic['is_active'])        return 'secondary';
    if ($lic['valid_to'] < $today)       return 'danger';
    if ($lic['valid_to'] <= date('Y-m-d', strtotime('+30 days'))) return 'warning';
    return 'success';
}

function licenseStatusLabel(array $lic, string $today): string
{
    if (!(bool)$lic['is_active'])        return 'Nieaktywna';
    if ($lic['valid_to'] < $today)       return 'Wygasła';
    if ($lic['valid_to'] <= date('Y-m-d', strtotime('+30 days'))) return 'Wygasa wkrótce';
    return 'Aktywna';
}
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-grid-1x2 me-2 text-primary"></i>Pulpit</h4>
    <a href="/generate" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Nowa licencja
    </a>
</div>

<!-- Statistics cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card stat-card total h-100 p-3 shadow-sm">
            <div class="text-muted small">Wszystkie licencje</div>
            <div class="fs-2 fw-bold"><?= $stats['total'] ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card active h-100 p-3 shadow-sm">
            <div class="text-success small">Aktywne</div>
            <div class="fs-2 fw-bold text-success"><?= $stats['valid'] ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card expired h-100 p-3 shadow-sm">
            <div class="text-danger small">Wygasłe</div>
            <div class="fs-2 fw-bold text-danger"><?= $stats['expired'] ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card inactive h-100 p-3 shadow-sm">
            <div class="text-warning small">Dezaktywowane</div>
            <div class="fs-2 fw-bold text-warning"><?= $stats['inactive'] ?></div>
        </div>
    </div>
</div>

<!-- License table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-table me-2"></i>Lista licencji
    </div>
    <div class="card-body p-0">
        <?php if (empty($licenses)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                Brak wygenerowanych licencji.
                <a href="/generate" class="d-block mt-2">Wygeneruj pierwszą licencję</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Firma</th>
                            <th>Klucz licencji</th>
                            <th>Moduły</th>
                            <th>Ważna do</th>
                            <th>Status</th>
                            <th class="text-end">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($licenses as $lic): ?>
                            <?php
                                $statusBadge = licenseStatusBadge($lic, $today);
                                $statusLabel = licenseStatusLabel($lic, $today);
                                $modules     = json_decode($lic['modules'], true) ?: [];
                            ?>
                            <tr>
                                <td class="text-muted small"><?= $lic['id'] ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($lic['company_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($lic['company_id']) ?></small>
                                </td>
                                <td>
                                    <code class="license-key small"><?= htmlspecialchars($lic['license_key']) ?></code>
                                </td>
                                <td>
                                    <?php foreach ($modules as $mod): ?>
                                        <span class="badge bg-secondary badge-module me-1">
                                            <?= htmlspecialchars(LicenseManager::MODULES[$mod] ?? $mod) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </td>
                                <td><?= htmlspecialchars($lic['valid_to']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $statusBadge ?>"><?= $statusLabel ?></span>
                                </td>
                                <td class="text-end">
                                    <!-- Toggle active -->
                                    <form method="POST"
                                          action="/license/<?= $lic['id'] ?>/<?= $lic['is_active'] ? 'deactivate' : 'activate' ?>"
                                          class="d-inline">
                                        <button type="submit"
                                                class="btn btn-sm btn-outline-<?= $lic['is_active'] ? 'warning' : 'success' ?>"
                                                title="<?= $lic['is_active'] ? 'Dezaktywuj' : 'Aktywuj' ?>">
                                            <i class="bi bi-<?= $lic['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                        </button>
                                    </form>
                                    <!-- Copy key button -->
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary ms-1"
                                            title="Kopiuj klucz"
                                            onclick="copyToClipboard('<?= htmlspecialchars($lic['license_key'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                    <!-- Delete -->
                                    <form method="POST"
                                          action="/license/<?= $lic['id'] ?>/delete"
                                          class="d-inline ms-1"
                                          onsubmit="return confirm('Czy na pewno usunąć tę licencję?')">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Usuń">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
