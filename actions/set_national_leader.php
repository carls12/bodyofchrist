<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

if (!is_main_admin()) { http_response_code(403); die('Forbidden'); }

db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_national_leader TINYINT(1) NOT NULL DEFAULT 0");
db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS country VARCHAR(120) NULL");

$userId = (int)($_POST['user_id'] ?? 0);
$country = trim($_POST['country'] ?? '');
$action = $_POST['action'] ?? 'save';
if ($userId <= 0) { redirect(base_url('admin/users')); }

$stmt = db()->prepare("SELECT is_national_leader FROM users WHERE id=?");
$stmt->execute([$userId]);
$row = $stmt->fetch();
if (!$row) { redirect(base_url('admin/users')); }

if ($action === 'remove') {
  db()->prepare("UPDATE users SET is_national_leader=0 WHERE id=?")->execute([$userId]);
} else {
  db()->prepare("UPDATE users SET is_national_leader=1, country=? WHERE id=?")
    ->execute([$country ?: null, $userId]);
}

flash_set('success', t('flash_leader_saved'));
redirect(base_url('admin/users'));
