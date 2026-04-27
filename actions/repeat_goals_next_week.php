<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS is_global TINYINT(1) NOT NULL DEFAULT 0");
db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE goals ADD INDEX IF NOT EXISTS idx_goals_assembly (assembly_id, week_start)");
ensure_goal_group_schema();

$uid = (int)auth_user()['id'];
$sourceWeekRaw = (string)($_POST['source_week_start'] ?? '');
$targetWeekRaw = (string)($_POST['target_week_start'] ?? '');
$returnTo = trim((string)($_POST['return_to'] ?? ''));
$weekStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $sourceWeekRaw) ? monday_of($sourceWeekRaw) : monday_of(now_ymd());
$nextWeekStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetWeekRaw)
  ? monday_of($targetWeekRaw)
  : (new DateTimeImmutable($weekStart))->modify('+7 days')->format('Y-m-d');
$aid = active_assembly_id();

$goalsStmt = db()->prepare("SELECT * FROM goals
  WHERE user_id=? AND week_start=? AND is_global=0 AND (assembly_id=? OR assembly_id IS NULL)
  ORDER BY group_title ASC, id ASC");
$goalsStmt->execute([$uid, $weekStart, $aid]);
$goals = $goalsStmt->fetchAll();

if (!$goals) {
  flash_set('error', t('goals_none'));
  redirect($returnTo !== '' ? $returnTo : base_url('goals'));
}

$existingStmt = db()->prepare("SELECT id FROM goals
  WHERE user_id=? AND week_start=? AND category=? AND (assembly_id=? OR assembly_id IS NULL)
  LIMIT 1");
$updateStmt = db()->prepare("UPDATE goals
  SET label=?, group_title=?, target=?, unit=?, assembly_id=?, updated_at=NOW()
  WHERE id=? AND user_id=?");
$insertStmt = db()->prepare("INSERT INTO goals(user_id,assembly_id,week_start,category,label,group_title,target,unit,is_global,created_at,updated_at)
  VALUES(?,?,?,?,?,?,?,?,0,NOW(),NOW())");

foreach ($goals as $goal) {
  $existingStmt->execute([$uid, $nextWeekStart, $goal['category'], $aid]);
  $existing = $existingStmt->fetch();

  if ($existing) {
    $updateStmt->execute([
      $goal['label'],
      $goal['group_title'] ?? null,
      $goal['target'],
      $goal['unit'],
      $aid,
      (int)$existing['id'],
      $uid,
    ]);
  } else {
    $insertStmt->execute([
      $uid,
      $aid,
      $nextWeekStart,
      $goal['category'],
      $goal['label'],
      $goal['group_title'] ?? null,
      $goal['target'],
      $goal['unit'],
    ]);
  }
}

flash_set('success', t('goals_repeat_success'));
redirect($returnTo !== '' ? $returnTo : base_url('goals'));
