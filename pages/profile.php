<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

$u = auth_user();
$name = $u['name'] ?? '';
$email = $u['email'] ?? '';
$locale = $u['locale'] ?? ($_SESSION['locale'] ?? 'de');
$avatarPath = $u['avatar_path'] ?? null;
$userRegion = $u['region'] ?? '';

db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL");
db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL");
$regionsStmt = db()->query("SELECT DISTINCT region FROM assemblies WHERE region IS NOT NULL AND region <> '' ORDER BY region ASC");
$regions = array_map(fn($r) => $r['region'], $regionsStmt->fetchAll());

$initials = '';
if ($name !== '') {
  $parts = preg_split('/\s+/', trim($name));
  $first = strtoupper(substr($parts[0] ?? '', 0, 1));
  $last = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
  $initials = trim($first . $last);
}

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('profile_title')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('profile_sub')) ?></h2>
    <div class="text-muted"><?= e(t('profile_help')) ?></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body text-center">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle border mb-3"
             style="width:120px;height:120px;background:#fff;overflow:hidden;">
          <?php if ($avatarPath): ?>
            <img src="<?= e($avatarPath) ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">
          <?php else: ?>
            <div style="font-size:36px;font-weight:700;color:#1E3A5F;">
              <?= e($initials !== '' ? $initials : 'BC') ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="fw-semibold"><?= e($name) ?></div>
        <div class="text-muted small"><?= e($email) ?></div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-body">
        <form method="post" action="<?= e(base_url('action/update-profile')) ?>" enctype="multipart/form-data" class="d-grid gap-3">
          <div>
            <label class="form-label"><?= e(t('full_name')) ?></label>
            <input class="form-control" name="name" value="<?= e($name) ?>" required>
          </div>
          <div>
            <label class="form-label"><?= e(t('email_readonly')) ?></label>
            <input class="form-control" value="<?= e($email) ?>" readonly>
          </div>
          <div>
            <label class="form-label"><?= e(t('language')) ?></label>
            <select class="form-select" name="locale">
              <option value="de" <?= $locale==='de'?'selected':'' ?>>Deutsch</option>
              <option value="en" <?= $locale==='en'?'selected':'' ?>>English</option>
              <option value="fr" <?= $locale==='fr'?'selected':'' ?>>Francais</option>
            </select>
            <div class="text-muted small mt-1"><?= e(t('language_help')) ?></div>
          </div>
          <div>
            <label class="form-label"><?= e(t('profile_region')) ?></label>
            <select class="form-select" name="region">
              <option value="">--</option>
              <?php foreach ($regions as $r): ?>
                <option value="<?= e($r) ?>" <?= $userRegion===$r?'selected':'' ?>><?= e($r) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="text-muted small mt-1"><?= e(t('profile_region_help')) ?></div>
          </div>
          <div>
            <label class="form-label"><?= e(t('avatar')) ?></label>
            <input class="form-control" type="file" name="avatar" accept="image/png,image/jpeg,image/webp">
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary"><?= e(t('btn_save')) ?></button>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('dashboard')) ?>"><?= e(t('btn_back')) ?></a>
          </div>
        </form>
      </div>
    </div>

    <?php if (requires_two_factor()): ?>
      <div class="card mt-3">
        <div class="card-body">
          <div class="fw-semibold mb-2"><?= e(t('two_factor_title')) ?></div>
          <div class="text-muted small mb-2"><?= e(t('two_factor_sub')) ?></div>
          <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('two-factor-setup')) ?>"><?= e(t('two_factor_setup_title')) ?></a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
