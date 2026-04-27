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
$targetWeekRaw = (string)($_POST['target_week_start'] ?? '');
$returnTo = trim((string)($_POST['return_to'] ?? ''));

if ($id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetWeekRaw)) {
  flash_set('error', t('flash_goal_invalid'));
  redirect($returnTo !== '' ? $returnTo : base_url('goal-history'));
}

$targetWeekStart = monday_of($targetWeekRaw);
$aid = active_assembly_id();

$goalStmt = db()->prepare("SELECT * FROM goals
  WHERE id=? AND user_id=? AND is_global=0 AND (assembly_id=? OR assembly_id IS NULL)
  LIMIT 1");
$goalStmt->execute([$id, $uid, $aid]);
$goal = $goalStmt->fetch();

if (!$goal) {
  flash_set('error', t('flash_forbidden'));
  redirect($returnTo !== '' ? $returnTo : base_url('goal-history'));
}

$existingStmt = db()->prepare("SELECT id FROM goals
  WHERE user_id=? AND week_start=? AND category=? AND (assembly_id=? OR assembly_id IS NULL)
  LIMIT 1");
$existingStmt->execute([$uid, $targetWeekStart, $goal['category'], $aid]);
$existing = $existingStmt->fetch();

if ($existing) {
  db()->prepare("UPDATE goals
    SET label=?, group_title=?, target=?, unit=?, assembly_id=?, updated_at=NOW()
    WHERE id=? AND user_id=?")
    ->execute([
      $goal['label'],
      $goal['group_title'] ?? null,
      $goal['target'],
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
      $targetWeekStart,
      $goal['category'],
      $goal['label'],
      $goal['group_title'] ?? null,
      $goal['target'],
      $goal['unit'],
    ]);
}

flash_set('success', t('goal_history_copy_success'));
redirect($returnTo !== '' ? $returnTo : base_url('goals'));
