<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$q = trim($_GET['q'] ?? '');
$assemblyId = (int)($_GET['assembly_id'] ?? 0);
if ($assemblyId <= 0 || mb_strlen($q) < 2) {
  header('Content-Type: application/json');
  echo json_encode(['items' => []]);
  exit;
}

$leaderCheck = db()->prepare("SELECT leader_id, region FROM assemblies WHERE id=? LIMIT 1");
$leaderCheck->execute([$assemblyId]);
$row = $leaderCheck->fetch();
if (!$row) {
  header('Content-Type: application/json');
  echo json_encode(['items' => []]);
  exit;
}
$isLeader = (int)$row['leader_id'] === (int)auth_user()['id'];
$isRegional = is_regional_leader() && user_region() && user_region() === ($row['region'] ?? null);
if (!$isLeader && !$isRegional && !is_main_admin()) {
  header('Content-Type: application/json');
  echo json_encode(['items' => []]);
  exit;
}

$like = $q . '%';
$stmt = db()->prepare("SELECT id,name,email FROM users
  WHERE (name LIKE ? OR email LIKE ?) AND id NOT IN (
    SELECT user_id FROM assembly_members WHERE assembly_id=?
  )
  ORDER BY name ASC LIMIT 10");
$stmt->execute([$like, $like, $assemblyId]);

$items = [];
foreach ($stmt->fetchAll() as $u) {
  $items[] = ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email']];
}

header('Content-Type: application/json');
echo json_encode(['items' => $items]);
