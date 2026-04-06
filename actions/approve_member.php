<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE assembly_members MODIFY COLUMN status ENUM('pending','invited','active','inactive') NOT NULL DEFAULT 'active'");

$memberId = (int)($_POST['member_id'] ?? 0);
if ($memberId <= 0) {
  flash_set('error', t('flash_group_missing'));
  redirect(base_url('assemblies'));
}

$stmt = db()->prepare("SELECT am.id, am.assembly_id, a.leader_id, a.region
  FROM assembly_members am
  JOIN assemblies a ON a.id=am.assembly_id
  WHERE am.id=? LIMIT 1");
$stmt->execute([$memberId]);
$row = $stmt->fetch();

$isLeader = $row && (int)$row['leader_id'] === (int)auth_user()['id'];
$isRegional = $row && is_regional_leader() && user_region() && user_region() === ($row['region'] ?? null);
if (!$row || (!$isLeader && !$isRegional && !is_main_admin())) {
  flash_set('error', t('flash_forbidden'));
  redirect(base_url('assemblies'));
}

db()->prepare("UPDATE assembly_members SET status='active', active=1, updated_at=NOW() WHERE id=?")
  ->execute([$memberId]);

flash_set('success', t('flash_member_approved'));
redirect(base_url('assemblies/show?id='.(int)$row['assembly_id']));
