<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE assembly_members MODIFY COLUMN status ENUM('pending','invited','active','inactive') NOT NULL DEFAULT 'active'");

$assemblyId = (int)($_POST['assembly_id'] ?? 0);
$userId = (int)($_POST['user_id'] ?? 0);
if ($assemblyId <= 0 || $userId <= 0) {
  header('Content-Type: application/json');
  echo json_encode(['ok' => false]);
  exit;
}

$leaderCheck = db()->prepare("SELECT leader_id, region FROM assemblies WHERE id=? LIMIT 1");
$leaderCheck->execute([$assemblyId]);
$row = $leaderCheck->fetch();
if (!$row) {
  header('Content-Type: application/json');
  echo json_encode(['ok' => false]);
  exit;
}
$isLeader = (int)$row['leader_id'] === (int)auth_user()['id'];
$isRegional = is_regional_leader() && user_region() && user_region() === ($row['region'] ?? null);
if (!$isLeader && !$isRegional && !is_main_admin()) {
  header('Content-Type: application/json');
  echo json_encode(['ok' => false]);
  exit;
}

$exists = db()->prepare("SELECT id, status FROM assembly_members WHERE assembly_id=? AND user_id=? LIMIT 1");
$exists->execute([$assemblyId, $userId]);
$mem = $exists->fetch();
if ($mem) {
  if ($mem['status'] === 'active' || $mem['status'] === 'invited') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'exists']);
    exit;
  }
  db()->prepare("UPDATE assembly_members SET status='invited', active=0, role='member', updated_at=NOW() WHERE id=?")
    ->execute([(int)$mem['id']]);
} else {
  db()->prepare("INSERT INTO assembly_members(assembly_id,user_id,role,status,active,created_at,updated_at)
    VALUES(?,?, 'member', 'invited', 0, NOW(), NOW())")->execute([$assemblyId, $userId]);
}

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
