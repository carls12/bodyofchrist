<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

if (!is_main_admin()) { flash_set('error', t('flash_forbidden')); redirect(base_url('bible')); }

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
if ($id <= 0 || $name === '') { redirect(base_url('bible')); }

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

db()->prepare("UPDATE bible_versions SET name=? WHERE id=?")->execute([$name, $id]);
flash_set('success', t('flash_bible_upload_ok'));
redirect(base_url('bible'));
