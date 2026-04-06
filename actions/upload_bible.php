<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

if (!is_main_admin()) { flash_set('error', t('flash_forbidden')); redirect(base_url('bible')); }

$lang = $_POST['lang'] ?? 'de';
if (!in_array($lang, ['de','en','fr'], true)) $lang = 'de';
$versionName = trim($_POST['version_name'] ?? '');
if ($versionName === '') {
  flash_set('error', t('flash_fields_required'));
  redirect(base_url('bible'));
}

if (!isset($_FILES['bible']) || $_FILES['bible']['error'] !== UPLOAD_ERR_OK) {
  flash_set('error', t('flash_bible_upload_fail'));
  redirect(base_url('bible'));
}

$uploadDir = __DIR__ . '/../data/bibles';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

$tmp = $_FILES['bible']['tmp_name'];
$raw = file_get_contents($tmp);
$json = json_decode($raw, true);
if (!is_array($json)) {
  flash_set('error', t('flash_bible_upload_fail'));
  redirect(base_url('bible'));
}

db()->exec("CREATE TABLE IF NOT EXISTS bible_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lang VARCHAR(5) NOT NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  UNIQUE KEY uniq_lang_slug (lang, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $versionName));
$slug = trim($slug, '_');
if ($slug === '') $slug = 'version';
$dest = $uploadDir . '/bible_' . $lang . '__' . $slug . '.json';
file_put_contents($dest, json_encode($json, JSON_UNESCAPED_UNICODE));

db()->prepare("INSERT INTO bible_versions(lang,name,slug,file_path,active,created_at)
  VALUES(?,?,?,?,1,NOW())
  ON DUPLICATE KEY UPDATE name=VALUES(name), file_path=VALUES(file_path), active=1")
  ->execute([$lang, $versionName, $slug, $dest]);

flash_set('success', t('flash_bible_upload_ok'));
redirect(base_url('bible'));
