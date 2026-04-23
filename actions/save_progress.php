<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");

$uid = auth_user()['id'];
$day = $_POST['day'] ?? now_ymd();
$category = trim($_POST['category'] ?? '');
$value = parse_decimal_input($_POST['value'] ?? 0);
$note = trim($_POST['note'] ?? '');
$returnTo = trim((string)($_POST['return_to'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) $day = now_ymd();
if ($category === '' || $value < 0) {
  flash_set('error', t('flash_progress_invalid'));
  redirect($returnTo !== '' ? $returnTo : base_url('progress'));
}

$aid = active_assembly_id();
$stmt = db()->prepare("INSERT INTO daily_progress(user_id,assembly_id,day,category,value,note,created_at,updated_at)
  VALUES(?,?,?,?,?,?,NOW(),NOW())");
$stmt->execute([$uid, $aid, $day, $category, $value, $note ?: null]);

flash_set('success', t('flash_progress_saved'));
redirect($returnTo !== '' ? $returnTo : base_url('progress'));
