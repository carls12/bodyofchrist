<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

if (!is_main_admin()) { http_response_code(403); die('Forbidden'); }

db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS is_global TINYINT(1) NOT NULL DEFAULT 0");
db()->exec("CREATE TABLE IF NOT EXISTS global_goals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  week_start DATE NOT NULL,
  category VARCHAR(120) NOT NULL,
  label VARCHAR(255) NOT NULL,
  target DECIMAL(10,2) NOT NULL DEFAULT 0,
  unit VARCHAR(50) NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NULL,
  INDEX (week_start, category),
  CONSTRAINT fk_global_goal_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$category = strtolower(trim($_POST['category'] ?? ''));
$label = trim($_POST['label'] ?? '');
$target = parse_decimal_input($_POST['target'] ?? 0);
$unit = trim($_POST['unit'] ?? '');

if ($category === '' || $label === '' || $target <= 0 || $unit === '') {
  flash_set('error', t('flash_goal_invalid'));
  redirect(base_url('admin/assemblies'));
}

$weekStart = monday_of(now_ymd());
$users = db()->query("SELECT id FROM users")->fetchAll();

$ins = db()->prepare("INSERT INTO goals(user_id,week_start,category,label,target,unit,is_global,created_at,updated_at)
  VALUES(?,?,?,?,?,?,1,NOW(),NOW())");
$upd = db()->prepare("UPDATE goals SET label=?, target=?, unit=?, is_global=1, updated_at=NOW()
  WHERE user_id=? AND week_start=? AND category=?");
$check = db()->prepare("SELECT id FROM goals WHERE user_id=? AND week_start=? AND category=? LIMIT 1");

foreach ($users as $u) {
  $uid = (int)$u['id'];
  $check->execute([$uid, $weekStart, $category]);
  if ($check->fetch()) {
    $upd->execute([$label, $target, $unit, $uid, $weekStart, $category]);
  } else {
    $ins->execute([$uid, $weekStart, $category, $label, $target, $unit]);
  }
}

db()->prepare("INSERT INTO global_goals(week_start,category,label,target,unit,created_by,created_at)
  VALUES(?,?,?,?,?,?,NOW())")->execute([$weekStart, $category, $label, $target, $unit, auth_user()['id']]);

flash_set('success', t('admin_global_goal_done'));
redirect(base_url('admin/assemblies'));
