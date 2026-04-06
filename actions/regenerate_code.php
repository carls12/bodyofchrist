<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

$uid = auth_user()['id'];
$aid = (int)($_POST['assembly_id'] ?? 0);
if ($aid <= 0) redirect(base_url('assemblies'));

$stmt = db()->prepare("SELECT id FROM assemblies WHERE id=? AND leader_id=?");
$stmt->execute([$aid, $uid]);
if (!$stmt->fetch()) { flash_set('error', t('flash_forbidden')); redirect(base_url('assemblies')); }

do {
  $code = strtoupper(substr(str_replace(['0','O','I','1'], '', bin2hex(random_bytes(4))), 0, 6));
  $check = db()->prepare("SELECT id FROM assemblies WHERE join_code=? LIMIT 1");
  $check->execute([$code]);
} while ($check->fetch());
db()->prepare("UPDATE assemblies SET join_code=?, updated_at=NOW() WHERE id=?")->execute([$code, $aid]);

flash_set('success', t('flash_new_code'));
redirect(base_url('assemblies/show?id='.$aid));
