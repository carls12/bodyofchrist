<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$uid = auth_user()['id'];
$today = now_ymd();
$weekStart = monday_of($today);
$weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
$aid = active_assembly_id();

$g = db()->prepare("SELECT category, label, target, unit FROM goals WHERE user_id=? AND week_start=? AND (assembly_id=? OR assembly_id IS NULL) ORDER BY id DESC");
$g->execute([$uid, $weekStart, $aid]);
$goals = $g->fetchAll();

$stmt = db()->prepare("SELECT category, SUM(value) as total FROM daily_progress
  WHERE user_id=? AND day BETWEEN ? AND ? AND (assembly_id=? OR assembly_id IS NULL) GROUP BY category");
$stmt->execute([$uid, $weekStart, $weekEnd, $aid]);
$sums = [];
foreach ($stmt->fetchAll() as $r) $sums[$r['category']] = (float)$r['total'];

header('Content-Type: application/json');
echo json_encode([
  'ok' => true,
  'goals' => $goals,
  'sums' => $sums,
]);
exit;
