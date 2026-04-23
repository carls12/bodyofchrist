<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");

$uid = (int)auth_user()['id'];
$aid = active_assembly_id();
$entries = $_POST['entries'] ?? [];

if (!is_array($entries)) {
  flash_set('error', t('flash_progress_invalid'));
  redirect(base_url('goals'));
}

$deleteWithAssembly = db()->prepare("DELETE FROM daily_progress WHERE user_id=? AND day=? AND category=? AND (assembly_id=? OR assembly_id IS NULL)");
$deleteWithoutAssembly = db()->prepare("DELETE FROM daily_progress WHERE user_id=? AND assembly_id IS NULL AND day=? AND category=?");
$stmt = db()->prepare("INSERT INTO daily_progress(user_id,assembly_id,day,category,value,note,created_at,updated_at)
  VALUES(?,?,?,?,?,?,NOW(),NOW())");

$saved = 0;
foreach ($entries as $entry) {
  if (!is_array($entry)) continue;

  $day = (string)($entry['day'] ?? '');
  $category = trim((string)($entry['category'] ?? ''));
  $valueRaw = trim((string)($entry['value'] ?? ''));
  $note = trim((string)($entry['note'] ?? ''));

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) continue;
  if ($category === '') continue;

  $value = parse_decimal_input($valueRaw);
  if ($value < 0) continue;

  if ($aid === null) {
    $deleteWithoutAssembly->execute([$uid, $day, $category]);
  } else {
    $deleteWithAssembly->execute([$uid, $day, $category, $aid]);
  }

  if ($value > 0 || $note !== '') {
    $stmt->execute([$uid, $aid, $day, $category, $value, $note !== '' ? $note : null]);
  }
  $saved++;
}

if ($saved > 0) {
  flash_set('success', t('flash_progress_saved'));
} else {
  flash_set('error', t('flash_progress_invalid'));
}

redirect(base_url('goals'));
