<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/totp.php';

if (!requires_two_factor()) { redirect(base_url('dashboard')); }

db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL");
db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0");

$u = auth_user();
$secret = null;
$stmt = db()->prepare("SELECT totp_secret, totp_enabled FROM users WHERE id=?");
$stmt->execute([$u['id']]);
$row = $stmt->fetch();
if ($row && (int)$row['totp_enabled'] === 1) {
  redirect(base_url('two-factor'));
}

if ($row && $row['totp_secret']) {
  $secret = $row['totp_secret'];
} else {
  $secret = totp_generate_secret(16);
  db()->prepare("UPDATE users SET totp_secret=? WHERE id=?")->execute([$secret, $u['id']]);
}

$issuer = 'BodyOfChrist';
$account = $u['email'] ?? $u['name'] ?? 'user';
$uri = totp_otpauth_uri($account, $issuer, $secret);
$qr = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($uri);

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('nav_profile')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('two_factor_setup_title')) ?></h2>
    <div class="text-muted"><?= e(t('two_factor_setup_sub')) ?></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('two_factor_qr')) ?></div>
        <img src="<?= e($qr) ?>" alt="QR" style="max-width:200px;">
        <div class="text-muted small mt-2"><?= e(t('two_factor_secret')) ?>: <code><?= e($secret) ?></code></div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card">
      <div class="card-body">
        <form method="post" action="<?= e(base_url('action/setup-two-factor')) ?>" class="d-grid gap-2">
          <div>
            <label class="form-label"><?= e(t('two_factor_code')) ?></label>
            <input class="form-control" name="code" placeholder="123456" required>
          </div>
          <button class="btn btn-primary"><?= e(t('two_factor_enable')) ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
