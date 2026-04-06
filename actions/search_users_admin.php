<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

if (!is_main_admin()) { http_response_code(403); die('Forbidden'); }

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
  header('Content-Type: application/json');
  echo json_encode(['items' => []]);
  exit;
}

$like = $q . '%';
$stmt = db()->prepare("SELECT id,name,email FROM users
  WHERE name LIKE ? OR email LIKE ?
  ORDER BY name ASC LIMIT 15");
$stmt->execute([$like, $like]);
$items = [];
foreach ($stmt->fetchAll() as $u) {
  $items[] = ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email']];
}

header('Content-Type: application/json');
echo json_encode(['items' => $items]);
