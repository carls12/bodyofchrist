<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php';
if (auth_check()) redirect(base_url('dashboard'));
include __DIR__ . '/_layout_top.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-6 col-xl-5">
    <div class="card">
      <div class="card-body p-4 p-md-5">
        <div class="text-muted small mb-2"><?= e(t('forgot_title')) ?></div>
        <h1 class="h4 mb-2"><?= e(t('forgot_title')) ?></h1>
        <div class="text-muted mb-4"><?= e(t('forgot_sub')) ?></div>

        <form method="post" action="<?= e(base_url('action/request-password-reset')) ?>" class="d-grid gap-3">
          <div>
            <label class="form-label"><?= e(t('email')) ?></label>
            <input class="form-control" type="email" name="email" required>
          </div>
          <button class="btn btn-primary"><?= e(t('create_code')) ?></button>
        </form>

        <div class="mt-3 small">
          <?= e(t('have_code')) ?> <a href="<?= e(base_url('reset-password')) ?>"><?= e(t('reset_title')) ?></a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
