<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

if (!is_main_admin()) { http_response_code(403); die('Forbidden'); }

$uid = (int)($_POST['user_id'] ?? 0);
if ($uid <= 0) redirect(base_url('admin/users'));

$u = db()->prepare("SELECT is_leader FROM users WHERE id=?");
$u->execute([$uid]);
$row = $u->fetch();
if (!$row) redirect(base_url('admin/users'));

$new = ((int)$row['is_leader'] === 1) ? 0 : 1;
db()->prepare("UPDATE users SET is_leader=?, updated_at=NOW() WHERE id=?")->execute([$new, $uid]);

flash_set('success', t('flash_leader_saved'));
redirect(base_url('admin/users'));
