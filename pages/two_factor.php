<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

if (!requires_two_factor()) { redirect(base_url('dashboard')); }

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('nav_login')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('two_factor_title')) ?></h2>
    <div class="text-muted"><?= e(t('two_factor_sub')) ?></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-body">
        <form method="post" action="<?= e(base_url('action/verify-two-factor')) ?>" class="d-grid gap-2">
          <div>
            <label class="form-label"><?= e(t('two_factor_code')) ?></label>
            <input class="form-control" name="code" placeholder="123456" required>
          </div>
          <button class="btn btn-primary"><?= e(t('two_factor_verify')) ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
