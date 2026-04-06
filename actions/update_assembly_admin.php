<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

if (!is_main_admin()) { http_response_code(403); die('Forbidden'); }

db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL");
db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS country VARCHAR(120) NULL");
db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE assembly_members MODIFY COLUMN status ENUM('pending','invited','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_leader TINYINT(1) NOT NULL DEFAULT 0");

$assemblyId = (int)($_POST['assembly_id'] ?? 0);
$region = trim($_POST['region'] ?? '');
$country = trim($_POST['country'] ?? '');
$leaderId = (int)($_POST['leader_id'] ?? 0);
if ($assemblyId <= 0) { redirect(base_url('admin/assemblies')); }

$stmt = db()->prepare("SELECT leader_id FROM assemblies WHERE id=?");
$stmt->execute([$assemblyId]);
$row = $stmt->fetch();
if (!$row) { redirect(base_url('admin/assemblies')); }

db()->prepare("UPDATE assemblies SET region=?, country=? WHERE id=?")->execute([$region ?: null, $country ?: null, $assemblyId]);

if ($leaderId > 0 && $leaderId !== (int)$row['leader_id']) {
  db()->prepare("UPDATE assemblies SET leader_id=? WHERE id=?")->execute([$leaderId, $assemblyId]);
  db()->prepare("UPDATE users SET is_leader=1 WHERE id=?")->execute([$leaderId]);

  db()->prepare("UPDATE assembly_members SET role='member' WHERE assembly_id=? AND role='leader'")
    ->execute([$assemblyId]);

  $check = db()->prepare("SELECT id FROM assembly_members WHERE assembly_id=? AND user_id=? LIMIT 1");
  $check->execute([$assemblyId, $leaderId]);
  $mem = $check->fetch();
  if ($mem) {
    db()->prepare("UPDATE assembly_members SET role='leader', status='active', active=1, updated_at=NOW() WHERE id=?")
      ->execute([(int)$mem['id']]);
  } else {
    db()->prepare("INSERT INTO assembly_members(assembly_id,user_id,role,status,active,created_at,updated_at)
      VALUES(?,?,'leader','active',1,NOW(),NOW())")->execute([$assemblyId, $leaderId]);
  }
}

flash_set('success', t('flash_leader_saved'));
redirect(base_url('admin/assemblies'));
