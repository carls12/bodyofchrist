<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  flash_set('error', t('flash_fields_required'));
  redirect(base_url('daily-planner'));
}

// Check if event belongs to current user
$stmt = db()->prepare("SELECT id FROM calendar_events WHERE id=? AND user_id=? LIMIT 1");
$stmt->execute([$id, auth_user()['id']]);
if (!$stmt->fetch()) {
  flash_set('error', t('flash_forbidden'));
  redirect(base_url('daily-planner'));
}

// Delete the event
db()->prepare("DELETE FROM calendar_events WHERE id=? AND user_id=?")->execute([$id, auth_user()['id']]);

$redirectDate = $_POST['redirect_date'] ?? '';
$redirectUrl = base_url('daily-planner');
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $redirectDate)) {
  $redirectUrl = base_url('daily-planner?date=' . $redirectDate);
}

flash_set('success', t('flash_event_saved'));
redirect($redirectUrl);
