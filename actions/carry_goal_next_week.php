<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS is_global TINYINT(1) NOT NULL DEFAULT 0");
db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE goals ADD INDEX IF NOT EXISTS idx_goals_assembly (assembly_id, week_start)");
ensure_goal_group_schema();

$uid = (int)auth_user()['id'];
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  flash_set('error', t('flash_goal_invalid'));
  redirect(base_url('goals'));
}

$weekStart = monday_of(now_ymd());
$nextWeekStart = (new DateTimeImmutable($weekStart))->modify('+7 days')->format('Y-m-d');
$aid = active_assembly_id();

$goalStmt = db()->prepare("SELECT * FROM goals
  WHERE id=? AND user_id=? AND week_start=? AND is_global=0 AND (assembly_id=? OR assembly_id IS NULL)
  LIMIT 1");
$goalStmt->execute([$id, $uid, $weekStart, $aid]);
$goal = $goalStmt->fetch();

if (!$goal) {
  flash_set('error', t('flash_forbidden'));
  redirect(base_url('goals'));
}

$actualStmt = db()->prepare("SELECT SUM(value) AS total
  FROM daily_progress
  WHERE user_id=? AND day BETWEEN ? AND ? AND category=? AND (assembly_id=? OR assembly_id IS NULL)");
$actualStmt->execute([$uid, $weekStart, (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d'), $goal['category'], $aid]);
$actual = (float)($actualStmt->fetch()['total'] ?? 0);
$remainingTarget = max(0.0, (float)$goal['target'] - $actual);

if ($remainingTarget <= 0) {
  flash_set('success', t('goals_carry_nothing_left'));
  redirect(base_url('goals'));
}

$existingStmt = db()->prepare("SELECT id FROM goals
  WHERE user_id=? AND week_start=? AND category=? AND (assembly_id=? OR assembly_id IS NULL)
  LIMIT 1");
$existingStmt->execute([$uid, $nextWeekStart, $goal['category'], $aid]);
$existing = $existingStmt->fetch();

if ($existing) {
  db()->prepare("UPDATE goals
    SET label=?, group_title=?, target=?, unit=?, assembly_id=?, updated_at=NOW()
    WHERE id=? AND user_id=?")
    ->execute([
      $goal['label'],
      $goal['group_title'] ?? null,
      $remainingTarget,
      $goal['unit'],
      $aid,
      (int)$existing['id'],
      $uid,
    ]);
} else {
  db()->prepare("INSERT INTO goals(user_id,assembly_id,week_start,category,label,group_title,target,unit,is_global,created_at,updated_at)
    VALUES(?,?,?,?,?,?,?,?,0,NOW(),NOW())")
    ->execute([
      $uid,
      $aid,
      $nextWeekStart,
      $goal['category'],
      $goal['label'],
      $goal['group_title'] ?? null,
      $remainingTarget,
      $goal['unit'],
    ]);
}

flash_set('success', t('goals_carry_success'));
redirect(base_url('goals'));
