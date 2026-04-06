<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
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

db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE assembly_members MODIFY COLUMN status ENUM('pending','invited','active','inactive') NOT NULL DEFAULT 'active'");

$assemblyId = (int)($_POST['assembly_id'] ?? 0);
$userId = (int)($_POST['user_id'] ?? 0);
$day = $_POST['day'] ?? now_ymd();
$type = $_POST['type'] ?? '';
$value = (float)($_POST['value'] ?? 0);

if ($assemblyId <= 0 || $userId <= 0 || $value <= 0) {
  flash_set('error', t('flash_progress_invalid'));
  redirect(base_url('assemblies/show?id=' . $assemblyId));
}

$leaderCheck = db()->prepare("SELECT leader_id, region FROM assemblies WHERE id=? LIMIT 1");
$leaderCheck->execute([$assemblyId]);
$row = $leaderCheck->fetch();
if (!$row) {
  flash_set('error', t('flash_forbidden'));
  redirect(base_url('assemblies'));
}
$isLeader = (int)$row['leader_id'] === (int)auth_user()['id'];
$isRegional = is_regional_leader() && user_region() && user_region() === ($row['region'] ?? null);
if (!$isLeader && !$isRegional && !is_main_admin()) {
  flash_set('error', t('flash_forbidden'));
  redirect(base_url('assemblies'));
}

$memberCheck = db()->prepare("SELECT id FROM assembly_members WHERE assembly_id=? AND user_id=? AND status='active' LIMIT 1");
$memberCheck->execute([$assemblyId, $userId]);
if (!$memberCheck->fetch()) {
  flash_set('error', t('flash_not_member'));
  redirect(base_url('assemblies/show?id=' . $assemblyId));
}

if ($type === 'prayer_minutes') {
  $seconds = (int)round($value * 60);
  db()->prepare("INSERT INTO prayer_logs (user_id,assembly_id,date,minutes,seconds,notes,is_fasting,created_at)
    VALUES (?,?,?,?,?,?,0,NOW())")->execute([$userId, $assemblyId, $day, $value, $seconds, '']);

  db()->prepare("INSERT INTO daily_progress (user_id,assembly_id,day,category,value,note,created_at,updated_at)
    VALUES (?,?,?,?,?,?,NOW(),NOW())")->execute([$userId, $assemblyId, $day, 'prayer_minutes', $value, '']);
} elseif ($type === 'tracktate') {
  db()->prepare("INSERT INTO daily_progress (user_id,assembly_id,day,category,value,note,created_at,updated_at)
    VALUES (?,?,?,?,?,?,NOW(),NOW())")->execute([$userId, $assemblyId, $day, 'tracktate', $value, '']);
} else {
  flash_set('error', t('flash_progress_invalid'));
  redirect(base_url('assemblies/show?id=' . $assemblyId));
}

flash_set('success', t('flash_progress_saved'));
redirect(base_url('assemblies/show?id=' . $assemblyId));
