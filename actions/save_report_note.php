<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

$uid = (int)auth_user()['id'];
$aid = active_assembly_id();
$weekStart = monday_of(now_ymd());
$notes = trim((string)($_POST['next_week_notes'] ?? ''));

ensure_report_notes_schema();

$existing = db()->prepare("SELECT id FROM report_next_week_notes WHERE user_id=? AND week_start=? AND assembly_id=? LIMIT 1");
$existing->execute([$uid, $weekStart, $aid]);
$row = $existing->fetch();

if ($row) {
  db()->prepare("UPDATE report_next_week_notes SET notes=?, updated_at=NOW() WHERE id=? AND user_id=?")
    ->execute([$notes !== '' ? $notes : null, (int)$row['id'], $uid]);
} else {
  db()->prepare("INSERT INTO report_next_week_notes(user_id,assembly_id,week_start,notes,created_at,updated_at)
    VALUES(?,?,?,?,NOW(),NOW())")
    ->execute([$uid, $aid, $weekStart, $notes !== '' ? $notes : null]);
}

flash_set('success', t('flash_goal_saved'));
redirect(base_url('goals'));
