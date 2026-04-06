<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS type ENUM('discipleship','assembly') NOT NULL DEFAULT 'assembly'");
db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS chat_enabled TINYINT(1) NOT NULL DEFAULT 1");
db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");

$uid = auth_user()['id'];
$name = trim($_POST['name'] ?? '');
$desc = trim($_POST['description'] ?? '');
$type = $_POST['type'] ?? 'assembly';
if (!in_array($type, ['discipleship','assembly'], true)) $type = 'assembly';

if ($name === '') { flash_set('error', t('flash_name_missing')); redirect(base_url('assemblies')); }

if (!is_main_admin()) {
  flash_set('error', t('flash_not_admin'));
  redirect(base_url('assemblies'));
}

do {
  $code = strtoupper(substr(str_replace(['0','O','I','1'], '', bin2hex(random_bytes(4))), 0, 6));
  $check = db()->prepare("SELECT id FROM assemblies WHERE join_code=? LIMIT 1");
  $check->execute([$code]);
} while ($check->fetch());

$stmt = db()->prepare("INSERT INTO assemblies(name,type,description,leader_id,join_code,chat_enabled,created_at,updated_at)
  VALUES(?,?,?,?,?,1,NOW(),NOW())");
$stmt->execute([$name, $type, $desc ?: null, $uid, $code]);
$aid = (int)db()->lastInsertId();

$m = db()->prepare("INSERT INTO assembly_members(assembly_id,user_id,role,status,active,created_at,updated_at)
  VALUES(?,?, 'leader', 'active', 1, NOW(), NOW())");
$m->execute([$aid, $uid]);

flash_set('success', t('flash_group_created'));
redirect(base_url('assemblies/show?id='.$aid));
