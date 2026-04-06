<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$loc = $_POST['locale'] ?? 'de';
set_locale($loc);

if (auth_check()) {
  db()->prepare("UPDATE users SET locale=?, updated_at=NOW() WHERE id=?")
    ->execute([$_SESSION['locale'], auth_user()['id']]);
  refresh_auth_user();
}

redirect($_SERVER['HTTP_REFERER'] ?? base_url('dashboard'));
