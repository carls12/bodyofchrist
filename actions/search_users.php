<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
  header('Content-Type: application/json');
  echo json_encode(['items' => []]);
  exit;
}

$me = auth_user()['id'];
$stmt = db()->prepare("SELECT id,name,email FROM users WHERE id<>? AND name LIKE ? ORDER BY name ASC LIMIT 10");
$stmt->execute([$me, $q . '%']);
$items = [];
foreach ($stmt->fetchAll() as $u) {
  $items[] = ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email']];
}

header('Content-Type: application/json');
echo json_encode(['items' => $items]);
