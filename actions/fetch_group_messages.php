<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

function human_day_ajax(string $dt): string {
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
  if ($diffDays === 0) return $todayLabel;
  if ($diffDays === -1) return $yLabel;
  if ($diffDays >= -6 && $diffDays <= -2) {
    $idx = (int)$d->format('N') - 1;
    return $days[$loc][$idx] ?? $days['de'][$idx];
  }
  return $d->format('d.m.Y');
}

function human_time_ajax(string $dt): string {
  $d = new DateTimeImmutable($dt);
  return $d->format('H:i');
}

$gid = (int)($_GET['gid'] ?? 0);
$since = (int)($_GET['since_id'] ?? 0);
if ($gid <= 0) { echo json_encode(['items' => []]); exit; }

$me = auth_user()['id'];
$check = db()->prepare("SELECT am.id FROM assembly_members am
  JOIN assemblies a ON a.id=am.assembly_id
  WHERE am.assembly_id=? AND am.user_id=? AND am.status='active' AND am.active=1 AND a.chat_enabled=1 LIMIT 1");
$check->execute([$gid, $me]);
if (!$check->fetch()) { echo json_encode(['items' => []]); exit; }

$stmt = db()->prepare("SELECT gm.*, u.name FROM group_messages gm
  JOIN users u ON u.id=gm.user_id
  WHERE gm.id>? AND gm.assembly_id=? ORDER BY gm.id ASC LIMIT 300");
$stmt->execute([$since, $gid]);
$items = [];
foreach ($stmt->fetchAll() as $m) {
  $items[] = [
    'id' => (int)$m['id'],
    'mine' => (int)$m['user_id'] === (int)$me,
    'name' => (int)$m['user_id'] === (int)$me ? t('chat_you') : $m['name'],
    'content' => $m['content'],
    'day' => human_day_ajax($m['created_at']),
    'time' => human_time_ajax($m['created_at']),
  ];
}

header('Content-Type: application/json');
echo json_encode(['items' => $items]);
