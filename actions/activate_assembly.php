<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE assembly_members MODIFY COLUMN status ENUM('pending','invited','active','inactive') NOT NULL DEFAULT 'active'");

$assemblyId = (int)($_POST['assembly_id'] ?? 0);
if ($assemblyId <= 0) {
  flash_set('error', t('flash_group_missing'));
  redirect(base_url('assemblies'));
}

$uid = auth_user()['id'];
$check = db()->prepare("SELECT id FROM assembly_members WHERE assembly_id=? AND user_id=? AND status='active' LIMIT 1");
$check->execute([$assemblyId, $uid]);
if (!$check->fetch()) {
  flash_set('error', t('flash_not_member'));
  redirect(base_url('assemblies'));
}

db()->prepare("UPDATE assembly_members SET active=1, status='active', updated_at=NOW() WHERE assembly_id=? AND user_id=?")
  ->execute([$assemblyId, $uid]);

flash_set('success', t('flash_group_activated'));
redirect(base_url('assemblies'));
