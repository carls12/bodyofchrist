<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php';
if (auth_check()) redirect(base_url('dashboard'));
include __DIR__ . '/_layout_top.php';
?>
<style>
  .login-report-shell{
    max-width:980px;
    margin:0 auto;
    display:grid;
    grid-template-columns:minmax(0, 1fr) minmax(320px, 430px);
    border:1px solid #dddddd;
    background:#fff;
    box-shadow:0 18px 40px rgba(44,62,80,.12);
  }
  .login-report-preview{
    background:#fff;
    border-right:1px solid #dddddd;
  }
  .login-report-header{
    background:#2c3e50;
    color:#fff;
    padding:34px 28px;
    text-align:center;
  }
  .login-report-header h1{
    margin:0 0 10px;
    font-size:1.35rem;
    line-height:1.25;
    font-weight:700;
    letter-spacing:1px;
    text-transform:uppercase;
  }
  .login-report-meta{
    color:#f8f9fa;
    font-size:.88rem;
  }
  .login-section-bar{
    margin:18px 24px 10px;
    background:#34495e;
    color:#fff;
    padding:10px 14px;
    font-size:.75rem;
    font-weight:700;
    letter-spacing:.6px;
    text-transform:uppercase;
  }
  .login-metric-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    margin:0 24px 20px;
    border-left:1px solid #dddddd;
    border-top:1px solid #dddddd;
  }
  .login-metric{
    background:#f8f9fa;
    border-right:1px solid #dddddd;
    border-bottom:1px solid #dddddd;
    padding:14px;
    min-height:74px;
  }
  .login-metric-label{
    display:block;
    color:#6c757d;
    font-size:.68rem;
    font-weight:700;
    letter-spacing:.5px;
    text-transform:uppercase;
  }
  .login-metric-value{
    display:block;
    margin-top:6px;
    color:#2c3e50;
    font-weight:700;
  }
  .login-form-panel{
    padding:32px;
  }
  .login-form-panel .form-control{
    border-color:#d8dee4;
    border-radius:4px;
    padding:.72rem .85rem;
  }
  .login-form-panel .btn{
    border-radius:4px;
  }
  .login-form-panel .btn-primary{
    background:#2c3e50;
  }
  .login-form-panel .btn-primary:hover{
    background:#34495e;
  }
  @media (max-width: 767.98px){
    .login-report-shell{ grid-template-columns:1fr; }
    .login-report-preview{ border-right:0; border-bottom:1px solid #dddddd; }
    .login-form-panel{ padding:24px; }
  }
</style>

<div class="login-report-shell">
  <section class="login-report-preview" aria-label="Discipleship report preview">
    <div class="login-report-header">
      <h1>Discipleship Progress Report</h1>
      <div class="login-report-meta">Disciple: <?= e(t('app')) ?> | Period: Today</div>
    </div>
    <div class="login-section-bar">Report Overview</div>
    <div class="login-metric-grid">
      <div class="login-metric">
        <span class="login-metric-label"><?= e(t('nav_prayer')) ?></span>
        <span class="login-metric-value"><?= e(t('my_reports_prayer')) ?></span>
      </div>
      <div class="login-metric">
        <span class="login-metric-label"><?= e(t('nav_bible')) ?></span>
        <span class="login-metric-value"><?= e(t('my_reports_bible')) ?></span>
      </div>
      <div class="login-metric">
        <span class="login-metric-label"><?= e(t('nav_goals')) ?></span>
        <span class="login-metric-value"><?= e(t('reports_goals_title')) ?></span>
      </div>
      <div class="login-metric">
        <span class="login-metric-label"><?= e(t('nav_reports')) ?></span>
        <span class="login-metric-value"><?= e(t('nav_my_reports')) ?></span>
      </div>
    </div>
  </section>

  <section class="login-form-panel">
    <div class="text-muted small mb-2"><?= e(t('login_welcome')) ?></div>
    <h2 class="h4 mb-2"><?= e(t('login_title')) ?></h2>
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
  </section>
</div>
<div class="text-center text-muted small mt-3">
  "Kommt her zu mir, alle, die ihr muehselig und beladen seid." - Mt 11,28
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
