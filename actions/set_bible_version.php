<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$lang = $_POST['lang'] ?? 'all';
$versionId = (int)($_POST['version_id'] ?? 0);
if ($versionId <= 0) { redirect(base_url('bible')); }

db()->exec("CREATE TABLE IF NOT EXISTS user_bible_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  lang VARCHAR(5) NOT NULL,
  version_id INT UNSIGNED NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_user_lang (user_id, lang),
  INDEX (version_id),
  CONSTRAINT fk_ubv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ubv_version FOREIGN KEY (version_id) REFERENCES bible_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

db()->prepare("INSERT INTO user_bible_versions(user_id,lang,version_id,updated_at)
  VALUES(?,?,?,NOW())
  ON DUPLICATE KEY UPDATE version_id=VALUES(version_id), updated_at=NOW()")
  ->execute([auth_user()['id'], $lang, $versionId]);

redirect(base_url('bible?version_id=' . $versionId));
