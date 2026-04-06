<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$ddl = "CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NULL,
  INDEX (user_id, expires_at),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
db()->exec($ddl);

$email = strtolower(trim($_POST['email'] ?? ''));
$code = trim($_POST['code'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($email === '' || $code === '' || $password === '') {
  flash_set('error', t('flash_fields_required'));
  redirect(base_url('reset-password'));
}
if (strlen($password) < 6) {
  flash_set('error', t('flash_pw_short'));
  redirect(base_url('reset-password'));
}

$stmt = db()->prepare("SELECT id,email FROM users WHERE email=? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user) {
  flash_set('error', t('flash_email_not_found'));
  redirect(base_url('reset-password'));
}

$stmt = db()->prepare("SELECT * FROM password_resets
  WHERE user_id=? AND used_at IS NULL AND expires_at > NOW()
  ORDER BY id DESC LIMIT 1");
$stmt->execute([(int)$user['id']]);
$reset = $stmt->fetch();

if (!$reset || !password_verify($code, $reset['code_hash'])) {
  flash_set('error', t('flash_code_invalid'));
  redirect(base_url('reset-password?email=' . urlencode($email)));
}

$hash = password_hash($password, PASSWORD_DEFAULT);
db()->prepare("UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?")
  ->execute([$hash, (int)$user['id']]);
db()->prepare("UPDATE password_resets SET used_at=NOW() WHERE id=?")
  ->execute([(int)$reset['id']]);

flash_set('success', t('flash_pw_saved'));
redirect(base_url('login'));
