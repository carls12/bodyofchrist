<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$name = trim($_POST['name'] ?? '');
$locale = $_POST['locale'] ?? 'de';
$region = trim($_POST['region'] ?? '');

db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL");
db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL");

if ($name === '') {
  flash_set('error', t('flash_name_empty'));
  redirect(base_url('profile'));
}

set_locale($locale);

$allowedRegions = [];
$regionsStmt = db()->query("SELECT DISTINCT region FROM assemblies WHERE region IS NOT NULL AND region <> ''");
foreach ($regionsStmt->fetchAll() as $r) $allowedRegions[] = $r['region'];
if ($region !== '' && !in_array($region, $allowedRegions, true)) {
  $region = '';
}

$avatarPath = null;
$uploadDir = __DIR__ . '/../public/uploads/avatars';
if (!is_dir($uploadDir)) {
  @mkdir($uploadDir, 0755, true);
}

if (!empty($_FILES['avatar']['name'])) {
  $file = $_FILES['avatar'];
  if ($file['error'] !== UPLOAD_ERR_OK) {
    flash_set('error', t('flash_upload_failed'));
    redirect(base_url('profile'));
  }
  if ($file['size'] > 2 * 1024 * 1024) {
    flash_set('error', t('flash_file_big'));
    redirect(base_url('profile'));
  }
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']);
  $ext = null;
  if ($mime === 'image/jpeg') $ext = 'jpg';
  if ($mime === 'image/png') $ext = 'png';
  if ($mime === 'image/webp') $ext = 'webp';
  if ($ext === null) {
    flash_set('error', t('flash_file_type'));
    redirect(base_url('profile'));
  }

  $fileName = 'avatar_' . auth_user()['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
  $dest = $uploadDir . '/' . $fileName;
  if (!move_uploaded_file($file['tmp_name'], $dest)) {
    flash_set('error', t('flash_file_save'));
    redirect(base_url('profile'));
  }
  $avatarPath = base_url('public/uploads/avatars/' . $fileName);
}

if ($avatarPath !== null) {
  db()->prepare("UPDATE users SET name=?, locale=?, region=?, avatar_path=?, updated_at=NOW() WHERE id=?")
    ->execute([$name, $_SESSION['locale'], $region ?: null, $avatarPath, auth_user()['id']]);
} else {
  db()->prepare("UPDATE users SET name=?, locale=?, region=?, updated_at=NOW() WHERE id=?")
    ->execute([$name, $_SESSION['locale'], $region ?: null, auth_user()['id']]);
}

refresh_auth_user();
flash_set('success', t('flash_profile_saved'));
redirect(base_url('profile'));
