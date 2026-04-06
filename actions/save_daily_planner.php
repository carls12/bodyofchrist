<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/daily_planner.php';

daily_planner_ensure_tables();

$uid = auth_user()['id'];
$formType = $_POST['form_type'] ?? '';
$date = daily_planner_selected_date($_POST['date'] ?? null);
$redirectUrl = base_url('daily-planner?date=' . $date);

if ($formType === 'template') {
  $id = (int)($_POST['id'] ?? 0);
  $weekday = (int)($_POST['weekday'] ?? 0);
  $title = trim($_POST['title'] ?? '');
  $plannedTime = trim($_POST['planned_time'] ?? '');
  $plannedEndTime = trim($_POST['planned_end_time'] ?? '');
  $notes = trim($_POST['notes'] ?? '');

  if ($weekday < 1 || $weekday > 7 || $title === '' || !preg_match('/^\d{2}:\d{2}$/', $plannedTime)) {
    flash_set('error', t('flash_fields_required'));
    redirect($redirectUrl);
  }

  // Validate end time if provided
  if ($plannedEndTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $plannedEndTime)) {
    flash_set('error', t('flash_fields_required'));
    redirect($redirectUrl);
  }

  if ($id > 0) {
    $checkStmt = db()->prepare("SELECT id FROM daily_planner_templates WHERE id=? AND user_id=? LIMIT 1");
    $checkStmt->execute([$id, $uid]);
    if (!$checkStmt->fetch()) {
      flash_set('error', t('flash_forbidden'));
      redirect($redirectUrl);
    }

    db()->prepare("UPDATE daily_planner_templates
      SET weekday=?, title=?, planned_time=?, planned_end_time=?, notes=?, updated_at=NOW()
      WHERE id=? AND user_id=?")
      ->execute([$weekday, $title, $plannedTime . ':00', ($plannedEndTime ? $plannedEndTime . ':00' : null), $notes ?: null, $id, $uid]);
  } else {
    $sortStmt = db()->prepare("SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM daily_planner_templates WHERE user_id=? AND weekday=?");
    $sortStmt->execute([$uid, $weekday]);
    $sortOrder = ((int)$sortStmt->fetch()['max_sort']) + 1;

    db()->prepare("INSERT INTO daily_planner_templates
      (user_id, weekday, title, planned_time, planned_end_time, notes, sort_order, created_at, updated_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())")
      ->execute([$uid, $weekday, $title, $plannedTime . ':00', ($plannedEndTime ? $plannedEndTime . ':00' : null), $notes ?: null, $sortOrder]);
  }

  flash_set('success', t('flash_daily_template_saved'));
  redirect($redirectUrl);
}

if ($formType === 'entries') {
  $entryIds = array_map('intval', $_POST['entry_ids'] ?? []);
  $completedIds = array_fill_keys(array_map('intval', $_POST['completed_ids'] ?? []), true);

  if ($entryIds) {
    $selectStmt = db()->prepare("SELECT id FROM daily_planner_entries WHERE user_id=? AND plan_date=?");
    $selectStmt->execute([$uid, $date]);
    $allowed = array_fill_keys(array_map(static fn($row) => (int)$row['id'], $selectStmt->fetchAll()), true);

    $updateStmt = db()->prepare("UPDATE daily_planner_entries
      SET completed=?, completed_at=?, updated_at=NOW()
      WHERE id=? AND user_id=?");
    foreach ($entryIds as $entryId) {
      if (!isset($allowed[$entryId])) {
        continue;
      }
      $isDone = isset($completedIds[$entryId]);
      $updateStmt->execute([$isDone ? 1 : 0, $isDone ? date('Y-m-d H:i:s') : null, $entryId, $uid]);
    }
  }

  flash_set('success', t('flash_daily_planner_saved'));
  redirect($redirectUrl);
}

if ($formType === 'quick_update') {
  $entryId = (int)($_POST['entry_id'] ?? 0);
  $completed = (int)($_POST['completed'] ?? 0);

  $checkStmt = db()->prepare("SELECT id FROM daily_planner_entries WHERE id=? AND user_id=? LIMIT 1");
  $checkStmt->execute([$entryId, $uid]);
  if (!$checkStmt->fetch()) {
    http_response_code(403);
    exit;
  }

  db()->prepare("UPDATE daily_planner_entries
    SET completed=?, completed_at=?, updated_at=NOW()
    WHERE id=? AND user_id=?")
    ->execute([$completed ? 1 : 0, $completed ? date('Y-m-d H:i:s') : null, $entryId, $uid]);

  http_response_code(200);
  exit;
}

flash_set('error', t('flash_forbidden'));
redirect($redirectUrl);
