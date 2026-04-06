<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS chat_enabled TINYINT(1) NOT NULL DEFAULT 1");
db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");

$ddl = "CREATE TABLE IF NOT EXISTS group_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assembly_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  content TEXT NOT NULL,
  created_at DATETIME NULL,
  INDEX (assembly_id),
  INDEX (user_id),
  CONSTRAINT fk_group_msg_assembly FOREIGN KEY (assembly_id) REFERENCES assemblies(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_msg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
db()->exec($ddl);

$assemblyId = (int)($_POST['assembly_id'] ?? 0);
$content = trim($_POST['content'] ?? '');
if ($assemblyId <= 0 || $content === '') {
  flash_set('error', t('flash_group_msg_invalid'));
  redirect(base_url('chat'));
}

$check = db()->prepare("SELECT am.id, a.chat_enabled FROM assembly_members am
  JOIN assemblies a ON a.id=am.assembly_id
  WHERE am.assembly_id=? AND am.user_id=? AND am.active=1 AND am.status='active' LIMIT 1");
$check->execute([$assemblyId, auth_user()['id']]);
$row = $check->fetch();
if (!$row || !(int)$row['chat_enabled']) {
  flash_set('error', t('flash_group_msg_forbidden'));
  redirect(base_url('chat'));
}

db()->prepare("INSERT INTO group_messages (assembly_id, user_id, content, created_at)
  VALUES (?,?,?,NOW())")->execute([$assemblyId, auth_user()['id'], $content]);

if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
  $id = (int)db()->lastInsertId();
  $stmt = db()->prepare("SELECT created_at FROM group_messages WHERE id=? LIMIT 1");
  $stmt->execute([$id]);
  $row = $stmt->fetch();
  $dt = $row['created_at'] ?? date('Y-m-d H:i:s');
  $d = new DateTimeImmutable($dt);
  $today = new DateTimeImmutable('today');
  $diffDays = (int)$today->diff($d->setTime(0,0))->format('%r%a');
  $loc = $_SESSION['locale'] ?? 'de';
  $todayLabel = ['de' => 'Heute', 'en' => 'Today', 'fr' => 'Aujourd hui'][$loc] ?? 'Heute';
  $yLabel = ['de' => 'Gestern', 'en' => 'Yesterday', 'fr' => 'Hier'][$loc] ?? 'Gestern';
  $days = [
    'de' => ['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'],
    'en' => ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],
    'fr' => ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'],
  ];
  if ($diffDays === 0) $day = $todayLabel;
  elseif ($diffDays === -1) $day = $yLabel;
  elseif ($diffDays >= -6 && $diffDays <= -2) {
    $idx = (int)$d->format('N') - 1;
    $day = $days[$loc][$idx] ?? $days['de'][$idx];
  } else {
    $day = $d->format('d.m.Y');
  }
  header('Content-Type: application/json');
  echo json_encode([
    'ok' => true,
    'message' => [
      'id' => $id,
      'mine' => true,
      'name' => t('chat_you'),
      'content' => $content,
      'day' => $day,
      'time' => (new DateTimeImmutable($dt))->format('H:i'),
    ],
  ]);
  exit;
}

redirect(base_url('chat?gid=' . (int)$assemblyId));
