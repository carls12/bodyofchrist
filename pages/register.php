<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php';
if (auth_check()) redirect(base_url('dashboard'));
include __DIR__ . '/_layout_top.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-6 col-xl-5">
    <div class="card">
      <div class="card-body p-4 p-md-5">
        <div class="text-muted small mb-2"><?= e(t('register_welcome')) ?></div>
        <h1 class="h4 mb-2"><?= e(t('register_title')) ?></h1>
        <div class="text-muted mb-4"><?= e(t('register_sub')) ?></div>

        <form method="post" action="<?= e(base_url('action/register')) ?>" class="d-grid gap-3">
          <div>
            <label class="form-label"><?= e(t('name')) ?></label>
            <input class="form-control" type="text" name="name" required>
          </div>
          <div>
            <label class="form-label"><?= e(t('email')) ?></label>
            <input class="form-control" type="email" name="email" required>
          </div>
          <div>
            <label class="form-label"><?= e(t('password')) ?></label>
            <div class="input-group">
              <input class="form-control" id="registerPassword" type="password" name="password" minlength="6" required>
              <button class="btn btn-outline-secondary" type="button" data-toggle="pw" data-target="registerPassword" data-show="<?= e(t('btn_show')) ?>" data-hide="<?= e(t('btn_hide')) ?>"><?= e(t('btn_show')) ?></button>
            </div>
          </div>
          <button class="btn btn-primary"><?= e(t('nav_register')) ?></button>
        </form>

        <div class="mt-3 small"><?= e(t('already_registered')) ?> <a href="<?= e(base_url('login')) ?>"><?= e(t('nav_login')) ?></a></div>
      </div>
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
