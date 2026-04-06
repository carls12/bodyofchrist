<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/daily_planner.php';

daily_planner_ensure_tables();

$uid = auth_user()['id'];
$id = (int)($_POST['id'] ?? 0);
$date = daily_planner_selected_date($_POST['date'] ?? null);
$redirectUrl = base_url('daily-planner?date=' . $date);

if ($id <= 0) {
  flash_set('error', t('flash_forbidden'));
  redirect($redirectUrl);
}

$deleteStmt = db()->prepare("DELETE FROM daily_planner_templates WHERE id=? AND user_id=?");
$deleteStmt->execute([$id, $uid]);

flash_set('success', t('flash_daily_template_deleted'));
redirect($redirectUrl);
