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
if ($email === '') {
  flash_set('error', t('flash_email_required'));
  redirect(base_url('forgot-password'));
}

$stmt = db()->prepare("SELECT id,email FROM users WHERE email=? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
  flash_set('error', t('flash_email_not_found'));
  redirect(base_url('forgot-password'));
}

$code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$codeHash = password_hash($code, PASSWORD_DEFAULT);
$expiresAt = (new DateTimeImmutable('now'))->modify('+15 minutes')->format('Y-m-d H:i:s');

db()->prepare("UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL")
  ->execute([(int)$user['id']]);

db()->prepare("INSERT INTO password_resets (user_id, code_hash, expires_at, created_at) VALUES (?,?,?,NOW())")
  ->execute([(int)$user['id'], $codeHash, $expiresAt]);

flash_set('success', t('flash_reset_code', ['code' => $code]));
redirect(base_url('reset-password?email=' . urlencode($email)));
