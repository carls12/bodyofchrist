<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

db()->exec("CREATE TABLE IF NOT EXISTS prayer_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  assembly_id INT UNSIGNED NULL,
  date DATE NOT NULL,
  minutes DECIMAL(10,2) NOT NULL DEFAULT 0,
  seconds INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,
  is_fasting TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  INDEX (user_id, date),
  INDEX (assembly_id, date),
  CONSTRAINT fk_prayer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS prayer_goal_minutes INT NOT NULL DEFAULT 60");
db()->exec("ALTER TABLE prayer_logs ADD COLUMN IF NOT EXISTS seconds INT UNSIGNED NOT NULL DEFAULT 0");
db()->exec("ALTER TABLE prayer_logs ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE prayer_logs ADD INDEX IF NOT EXISTS idx_prayer_logs_assembly (assembly_id, date)");

db()->exec("CREATE TABLE IF NOT EXISTS daily_progress (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  assembly_id INT UNSIGNED NULL,
  day DATE NOT NULL,
  category VARCHAR(120) NOT NULL,
  value DECIMAL(10,2) NOT NULL DEFAULT 0,
  note TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  INDEX (user_id, day),
  INDEX (assembly_id, day),
  INDEX (category),
  CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");

$seconds = (int)($_POST['seconds'] ?? 0);
$minutes = parse_decimal_input($_POST['minutes'] ?? 0);
$notes = trim($_POST['notes'] ?? '');
$isFasting = isset($_POST['is_fasting']) ? 1 : 0;

if ($seconds <= 0 && $minutes <= 0) {
  flash_set('error', t('flash_progress_invalid'));
  redirect(base_url('prayer'));
}

$seconds = $seconds > 0 ? $seconds : (int)round($minutes * 60);
$minutes = $seconds / 60.0;

$today = now_ymd();
$aid = active_assembly_id();
$stmt = db()->prepare("INSERT INTO prayer_logs (user_id,assembly_id,date,minutes,notes,is_fasting,created_at)
  VALUES (?,?,?,?,?,?,NOW())");
$stmt->execute([auth_user()['id'], $aid, $today, $minutes, $notes ?: null, $isFasting]);

db()->prepare("UPDATE prayer_logs SET seconds=? WHERE id=LAST_INSERT_ID()")->execute([$seconds]);

$prog = db()->prepare("INSERT INTO daily_progress(user_id,assembly_id,day,category,value,note,created_at,updated_at)
  VALUES(?,?,?,?,?,?,NOW(),NOW())");
$prog->execute([auth_user()['id'], $aid, $today, 'prayer_minutes', $minutes, $notes ?: null]);

if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
  $sum = db()->prepare("SELECT SUM(COALESCE(seconds, minutes*60)) AS total_seconds FROM prayer_logs WHERE user_id=? AND date=? AND (assembly_id=? OR assembly_id IS NULL)");
  $sum->execute([auth_user()['id'], $today, $aid]);
  $totalSeconds = (int)($sum->fetch()['total_seconds'] ?? 0);
  $total = $totalSeconds / 60.0;
  header('Content-Type: application/json');
  echo json_encode(['ok' => true, 'total' => $total, 'total_seconds' => $totalSeconds]);
  exit;
}

flash_set('success', t('prayer_saved'));
redirect(base_url('prayer'));
