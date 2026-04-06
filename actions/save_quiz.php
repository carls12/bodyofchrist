<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

if (!auth_user()['is_leader']) { flash_set('error', t('flash_not_leader')); redirect(base_url('quizzes')); }

db()->exec("CREATE TABLE IF NOT EXISTS quizzes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_by INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  bible_reference VARCHAR(120) NOT NULL,
  group_id INT UNSIGNED NULL,
  quiz_type VARCHAR(20) NOT NULL DEFAULT 'mcq',
  questions_json MEDIUMTEXT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  INDEX (created_by, group_id),
  CONSTRAINT fk_quiz_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_quiz_group FOREIGN KEY (group_id) REFERENCES assemblies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

db()->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS quiz_type VARCHAR(20) NOT NULL DEFAULT 'mcq'");

$title = trim($_POST['title'] ?? '');
$reference = trim($_POST['reference'] ?? '');
$groupId = (int)($_POST['group_id'] ?? 0);
$questionsJson = trim($_POST['questions_json'] ?? '');
$quizType = trim($_POST['quiz_type'] ?? 'mcq');
if (!in_array($quizType, ['mcq','tf','free'], true)) $quizType = 'mcq';

if ($title === '' || $reference === '' || $questionsJson === '') {
  flash_set('error', t('flash_fields_required'));
  redirect(base_url('quizzes'));
}

if ($groupId > 0) {
  $g = db()->prepare("SELECT id FROM assemblies WHERE id=? AND leader_id=? LIMIT 1");
  $g->execute([$groupId, auth_user()['id']]);
  if (!$g->fetch()) {
    flash_set('error', t('flash_forbidden'));
    redirect(base_url('quizzes'));
  }
}

$decoded = json_decode($questionsJson, true);
if (!is_array($decoded) || !$decoded) {
  flash_set('error', t('flash_fields_required'));
  redirect(base_url('quizzes'));
}

$stmt = db()->prepare("INSERT INTO quizzes (created_by,title,bible_reference,group_id,quiz_type,questions_json,active,created_at)
  VALUES (?,?,?,?,?,?,1,NOW())");
$stmt->execute([auth_user()['id'], $title, $reference, $groupId ?: null, $quizType, json_encode($decoded)]);

flash_set('success', t('flash_quiz_saved'));
redirect(base_url('quizzes'));
