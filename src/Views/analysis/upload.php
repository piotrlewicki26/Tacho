<?php
/**
 * @var array $files
 * @var array $drivers
 * @var array $vehicles
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <h4 class="fw-bold mb-0">Analiza plików DDD</h4>
  <a href="/analysis/analyzer"
     class="btn btn-primary d-flex align-items-center gap-2"
     style="background:#1E88E5;border-color:#1E88E5">
    <i class="bi bi-graph-up-arrow"></i>
    <span>Otwórz Tacho Analyzer <span class="badge bg-white text-primary ms-1" style="font-size:.7rem">nowy</span></span>
  </a>
</div>

<!-- Upload form -->
<div class="card border-0 mb-4" style="background:#1a1d27;max-width:700px">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0"><i class="bi bi-upload me-2"></i>Wgraj plik DDD</h6>
  </div>
  <div class="card-body">
    <form method="POST" action="/analysis/upload" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="_token" value="<?= \Core\Auth::csrfToken() ?>">
      <div class="row g-3">
        <div class="col-md-12">
          <label class="form-label">Plik (.ddd, .c1b, .dt)</label>
          <input type="file" class="form-control" name="ddd_file" accept=".ddd,.c1b,.dt,.dtco" required>
          <div class="form-text text-muted">Maksymalny rozmiar: 50 MB</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Typ pliku</label>
          <select class="form-select" name="file_type">
            <option value="driver_card">Karta kierowcy</option>
            <option value="tachograph">Tachograf (VU)</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Kierowca</label>
          <select class="form-select" name="driver_id">
            <option value="">— wybierz opcjonalnie —</option>
            <?php foreach ($drivers as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['last_name'] . ' ' . $d['first_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Pojazd</label>
          <select class="form-select" name="vehicle_id">
            <option value="">— wybierz opcjonalnie —</option>
            <?php foreach ($vehicles as $v): ?>
            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['registration']) ?><?= $v['brand'] ? ' – ' . htmlspecialchars($v['brand']) : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-cloud-upload me-2"></i>Parsuj i analizuj
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Previous files -->
<div class="card border-0" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0">Przeanalizowane pliki</h6>
  </div>
  <div class="card-body p-0">
    <?php if (empty($files)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-file-earmark-binary fs-1 d-block mb-2"></i>Brak plików
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0">
        <thead class="text-muted small">
          <tr><th>Plik</th><th>Kierowca</th><th>Pojazd</th><th>Typ</th><th>Rozmiar</th><th>Data</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($files as $f): ?>
          <tr>
            <td class="small font-monospace"><?= htmlspecialchars($f['original_name']) ?></td>
            <td class="small"><?= htmlspecialchars($f['driver_name'] ?? '—') ?></td>
            <td class="small font-monospace"><?= htmlspecialchars($f['registration'] ?? '—') ?></td>
            <td class="small"><?= $f['file_type'] === 'driver_card' ? 'Karta' : 'VU' ?></td>
            <td class="small text-muted"><?= round($f['file_size']/1024, 0) ?> kB</td>
            <td class="small text-muted"><?= date('d.m.Y H:i', strtotime($f['created_at'])) ?></td>
            <td>
              <span class="badge bg-<?= $f['parse_status'] === 'success' ? 'success' : ($f['parse_status'] === 'error' ? 'danger' : 'secondary') ?>">
                <?= htmlspecialchars($f['parse_status']) ?>
              </span>
            </td>
            <td class="text-end">
              <?php if ($f['parse_status'] === 'success'): ?>
              <a href="/analysis/<?= $f['id'] ?>/daily" class="btn btn-sm btn-outline-secondary me-1" title="Dzienny">
                <i class="bi bi-calendar-day"></i>
              </a>
              <a href="/analysis/<?= $f['id'] ?>/weekly" class="btn btn-sm btn-outline-secondary" title="Tygodniowy">
                <i class="bi bi-calendar-week"></i>
              </a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
