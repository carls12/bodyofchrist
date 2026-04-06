<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS is_global TINYINT(1) NOT NULL DEFAULT 0");
db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE goals ADD INDEX IF NOT EXISTS idx_goals_assembly (assembly_id, week_start)");

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { redirect(base_url('goals')); }

$weekStart = monday_of(now_ymd());
$aid = active_assembly_id();
$g = db()->prepare("SELECT id, is_global FROM goals WHERE id=? AND user_id=? AND week_start=? AND (assembly_id=? OR assembly_id IS NULL) LIMIT 1");
$g->execute([$id, auth_user()['id'], $weekStart, $aid]);
$row = $g->fetch();
if (!$row) {
  flash_set('error', t('flash_forbidden'));
  redirect(base_url('goals'));
}
if ((int)$row['is_global'] && !is_main_admin()) {
  flash_set('error', t('flash_forbidden'));
  redirect(base_url('goals'));
}

db()->prepare("DELETE FROM goals WHERE id=? AND user_id=? AND week_start=? AND (assembly_id=? OR assembly_id IS NULL)")
  ->execute([$id, auth_user()['id'], $weekStart, $aid]);

flash_set('success', t('flash_goal_saved'));
redirect(base_url('goals'));
