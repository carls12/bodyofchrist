<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

db()->exec("CREATE TABLE IF NOT EXISTS calendar_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  assembly_id INT UNSIGNED NULL,
  group_id INT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  date DATE NOT NULL,
  time TIME NULL,
  end_time TIME NULL,
  type ENUM('personal','group','assembly') NOT NULL DEFAULT 'personal',
  created_at DATETIME NULL,
  INDEX (user_id, date),
  INDEX (assembly_id, date),
  INDEX (group_id, date),
  CONSTRAINT fk_event_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_event_group FOREIGN KEY (group_id) REFERENCES assemblies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
db()->exec("ALTER TABLE calendar_events ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE calendar_events ADD INDEX IF NOT EXISTS idx_calendar_events_assembly (assembly_id, date)");
db()->exec("ALTER TABLE calendar_events ADD COLUMN IF NOT EXISTS end_time TIME NULL");

$title = trim($_POST['title'] ?? '');
$desc = trim($_POST['description'] ?? '');
$date = $_POST['date'] ?? '';
$time = $_POST['time'] ?? null;
$endTime = $_POST['end_time'] ?? null;
$allDay = isset($_POST['all_day']);
$type = $_POST['type'] ?? 'personal';
$groupId = (int)($_POST['group_id'] ?? 0);
$id = (int)($_POST['id'] ?? 0);
$redirectMonth = $_POST['redirect_month'] ?? '';
$redirectDate = $_POST['redirect_date'] ?? '';
$redirectView = $_POST['redirect_view'] ?? 'week';
$hideAfterSave = ($_POST['hide_after_save'] ?? '') === '1';
$redirectUrl = base_url('calendar');
$q = ['view=' . $redirectView];
if ($redirectView === 'week' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $redirectDate)) {
  $q[] = 'week_date=' . $redirectDate;
} elseif ($redirectView === 'month' && preg_match('/^\d{4}-\d{2}$/', $redirectMonth)) {
  $q[] = 'month=' . $redirectMonth;
} elseif ($redirectView === 'daily_planner' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $redirectDate)) {
  $redirectUrl = base_url('daily-planner?date=' . $redirectDate);
  $q = [];
}
if (!$hideAfterSave && preg_match('/^\d{4}-\d{2}-\d{2}$/', $redirectDate)) $q[] = 'date=' . $redirectDate;
if ($q) $redirectUrl = base_url('calendar?' . implode('&', $q));

if ($title === '' || $date === '') {
  flash_set('error', t('flash_fields_required'));
  redirect($redirectUrl);
}
if (!in_array($type, ['personal','group','assembly'], true)) $type = 'personal';

// Handle all day events
if ($allDay || $time === '') {
  $time = null;
  $endTime = null;
}

if ($type !== 'personal' && $groupId > 0) {
  $check = db()->prepare("SELECT id FROM assembly_members WHERE assembly_id=? AND user_id=? AND status='active' LIMIT 1");
  $check->execute([$groupId, auth_user()['id']]);
  if (!$check->fetch()) {
    flash_set('error', t('flash_forbidden'));
    redirect($redirectUrl);
  }
} else {
  $groupId = null;
}
$assemblyId = $groupId ?: active_assembly_id();

if ($id > 0) {
  $own = db()->prepare("SELECT id FROM calendar_events WHERE id=? AND user_id=? LIMIT 1");
  $own->execute([$id, auth_user()['id']]);
  if (!$own->fetch()) {
    flash_set('error', t('flash_forbidden'));
    redirect($redirectUrl);
  }
  db()->prepare("UPDATE calendar_events SET group_id=?, assembly_id=?, title=?, description=?, date=?, time=?, end_time=?, type=? WHERE id=? AND user_id=?")
    ->execute([$groupId, $assemblyId, $title, $desc ?: null, $date, $time ?: null, $endTime ?: null, $type, $id, auth_user()['id']]);
} else {
  db()->prepare("INSERT INTO calendar_events (user_id,assembly_id,group_id,title,description,date,time,end_time,type,created_at)
    VALUES (?,?,?,?,?,?,?,?,?,NOW())")
    ->execute([auth_user()['id'], $assemblyId, $groupId, $title, $desc ?: null, $date, $time ?: null, $endTime ?: null, $type]);
}

flash_set('success', t('flash_event_saved'));
redirect($redirectUrl);
