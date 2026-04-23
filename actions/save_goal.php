<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

$ddl = "CREATE TABLE IF NOT EXISTS goals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  assembly_id INT UNSIGNED NULL,
  week_start DATE NOT NULL,
  category VARCHAR(120) NOT NULL,
  label VARCHAR(255) NOT NULL,
  target DECIMAL(10,2) NOT NULL DEFAULT 0,
  unit VARCHAR(50) NOT NULL,
  is_global TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  INDEX (user_id, week_start),
  INDEX (assembly_id, week_start),
  CONSTRAINT fk_goals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
db()->exec($ddl);
db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS is_global TINYINT(1) NOT NULL DEFAULT 0");
db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE goals ADD INDEX IF NOT EXISTS idx_goals_assembly (assembly_id, week_start)");
ensure_goal_group_schema();

$uid = auth_user()['id'];
$id = (int)($_POST['id'] ?? 0);
$label = trim($_POST['label'] ?? '');
$category = trim($_POST['category'] ?? '');
$groupTitle = trim((string)($_POST['group_title'] ?? ''));
$target = parse_decimal_input($_POST['target'] ?? 0);
$unit = trim($_POST['unit'] ?? '');

if ($label === '' || $target <= 0 || $unit === '') { flash_set('error', t('flash_goal_invalid')); redirect(base_url('goals')); }

$weekStart = monday_of(now_ymd());
$aid = active_assembly_id();
if ($id > 0) {
  $g = db()->prepare("SELECT id, category, is_global FROM goals WHERE id=? AND user_id=? AND week_start=? AND (assembly_id=? OR assembly_id IS NULL) LIMIT 1");
  $g->execute([$id, $uid, $weekStart, $aid]);
  $row = $g->fetch();
  if ($row) {
    if ((int)$row['is_global'] && !is_main_admin()) {
      flash_set('error', t('flash_forbidden'));
      redirect(base_url('goals'));
    }
    db()->prepare("UPDATE goals SET label=?, group_title=?, target=?, unit=?, assembly_id=?, updated_at=NOW() WHERE id=? AND user_id=?")
      ->execute([$label, $groupTitle !== '' ? $groupTitle : null, $target, $unit, $aid, $id, $uid]);
    flash_set('success', t('flash_goal_saved'));
    redirect(base_url('goals'));
  }
}

if ($category === '') {
  $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label));
  $slug = trim($slug, '_');
  if ($slug === '') $slug = 'goal';
  $category = $slug;
} else {
  $category = strtolower(preg_replace('/[^a-z0-9_]+/', '_', $category));
}

$exists = db()->prepare("SELECT COUNT(*) AS c FROM goals WHERE user_id=? AND week_start=? AND category=? AND (assembly_id=? OR assembly_id IS NULL)");
$exists->execute([$uid, $weekStart, $category, $aid]);
if ((int)$exists->fetch()['c'] > 0) {
  $n = 2;
  do {
    $cat2 = $category . '_' . $n;
    $exists->execute([$uid, $weekStart, $cat2, $aid]);
    $n++;
  } while ((int)$exists->fetch()['c'] > 0);
  $category = $cat2;
}

$stmt = db()->prepare("INSERT INTO goals(user_id,assembly_id,week_start,category,label,group_title,target,unit,created_at,updated_at)
  VALUES(?,?,?,?,?,?,?,?,NOW(),NOW())");
$stmt->execute([$uid, $aid, $weekStart, $category, $label, $groupTitle !== '' ? $groupTitle : null, $target, $unit]);

flash_set('success', t('flash_goal_saved'));
redirect(base_url('goals'));
