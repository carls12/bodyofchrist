<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php';
if (auth_check()) redirect(base_url('dashboard'));
include __DIR__ . '/_layout_top.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-6 col-xl-5">
    <div class="card">
      <div class="card-body p-4 p-md-5">
        <div class="text-muted small mb-2"><?= e(t('login_welcome')) ?></div>
        <h1 class="h4 mb-2"><?= e(t('login_title')) ?></h1>
        <div class="text-muted mb-4"><?= e(t('login_sub')) ?></div>

        <form method="post" action="<?= e(base_url('action/login')) ?>" class="d-grid gap-3">
          <div>
            <label class="form-label"><?= e(t('email')) ?></label>
            <input class="form-control" type="email" name="email" required>
          </div>
          <div>
            <label class="form-label"><?= e(t('password')) ?></label>
            <div class="input-group">
              <input class="form-control" id="loginPassword" type="password" name="password" required>
              <button class="btn btn-outline-secondary" type="button" data-toggle="pw" data-target="loginPassword" data-show="<?= e(t('btn_show')) ?>" data-hide="<?= e(t('btn_hide')) ?>"><?= e(t('btn_show')) ?></button>
            </div>
          </div>
          <button class="btn btn-primary"><?= e(t('login_title')) ?></button>
        </form>

        <div class="d-flex justify-content-between align-items-center mt-4 small">
          <a href="<?= e(base_url('forgot-password')) ?>"><?= e(t('forgot')) ?></a>
          <a href="<?= e(base_url('register')) ?>"><?= e(t('nav_register')) ?></a>
        </div>
      </div>
    </div>
    <div class="text-center text-muted small mt-3">
      "Kommt her zu mir, alle, die ihr muehselig und beladen seid." - Mt 11,28
    </div>
  </div>
</div>
<script>
  document.querySelectorAll('[data-toggle="pw"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = btn.getAttribute('data-target');
      var input = document.getElementById(id);
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? (btn.getAttribute('data-hide') || 'Hide') : (btn.getAttribute('data-show') || 'Show');
    });
  });
</script>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
