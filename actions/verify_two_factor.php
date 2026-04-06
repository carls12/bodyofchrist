<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/totp.php';

if (!requires_two_factor()) { redirect(base_url('dashboard')); }

db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL");
db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0");
db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_2fa_at DATETIME NULL");

$code = trim($_POST['code'] ?? '');
$stmt = db()->prepare("SELECT totp_secret, totp_enabled FROM users WHERE id=?");
$stmt->execute([auth_user()['id']]);
$row = $stmt->fetch();
if (!$row || !(int)$row['totp_enabled'] || !$row['totp_secret']) {
  redirect(base_url('two-factor-setup'));
}

if (!totp_verify($row['totp_secret'], $code)) {
  flash_set('error', t('two_factor_invalid'));
  redirect(base_url('two-factor'));
}

db()->prepare("UPDATE users SET last_2fa_at=NOW() WHERE id=?")
  ->execute([auth_user()['id']]);
refresh_auth_user();
flash_set('success', t('two_factor_ok'));
redirect(base_url('dashboard'));
