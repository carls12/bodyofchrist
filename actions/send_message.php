<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$ddl = "CREATE TABLE IF NOT EXISTS messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_user_id INT UNSIGNED NOT NULL,
  to_user_id INT UNSIGNED NOT NULL,
  assembly_id INT UNSIGNED NULL,
  content TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  created_at DATETIME NULL,
  INDEX (from_user_id, to_user_id),
  INDEX (assembly_id, created_at),
  CONSTRAINT fk_msg_from FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_to FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
db()->exec($ddl);
db()->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE messages ADD INDEX IF NOT EXISTS idx_messages_assembly (assembly_id, created_at)");

$to = (int)($_POST['to_user_id'] ?? 0);
$content = trim($_POST['content'] ?? '');
if ($to <= 0 || $content === '') {
  flash_set('error', t('flash_msg_invalid'));
  redirect(base_url('chat'));
}

$aid = active_assembly_id();
db()->prepare("INSERT INTO messages (from_user_id, to_user_id, assembly_id, content, created_at)
  VALUES (?,?,?,?,NOW())")->execute([auth_user()['id'], $to, $aid, $content]);

if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
  $id = (int)db()->lastInsertId();
  $stmt = db()->prepare("SELECT created_at FROM messages WHERE id=? LIMIT 1");
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

redirect(base_url('chat?uid=' . (int)$to));
