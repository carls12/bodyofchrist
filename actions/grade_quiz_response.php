<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

if (!auth_user()['is_leader']) { redirect(base_url('quizzes')); }

db()->exec("CREATE TABLE IF NOT EXISTS quiz_responses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  answers_json MEDIUMTEXT NOT NULL,
  score INT UNSIGNED NOT NULL DEFAULT 0,
  feedback TEXT NULL,
  graded_by INT UNSIGNED NULL,
  graded_at DATETIME NULL,
  completed_at DATETIME NULL,
  UNIQUE KEY uniq_quiz_user (quiz_id, user_id),
  INDEX (user_id),
  CONSTRAINT fk_qr_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
  CONSTRAINT fk_qr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

db()->exec("ALTER TABLE quiz_responses ADD COLUMN IF NOT EXISTS feedback TEXT NULL");
db()->exec("ALTER TABLE quiz_responses ADD COLUMN IF NOT EXISTS graded_by INT UNSIGNED NULL");
db()->exec("ALTER TABLE quiz_responses ADD COLUMN IF NOT EXISTS graded_at DATETIME NULL");

$responseId = (int)($_POST['response_id'] ?? 0);
$score = (int)($_POST['score'] ?? 0);
$feedback = trim($_POST['feedback'] ?? '');
if ($responseId <= 0) { redirect(base_url('quizzes')); }

$q = db()->prepare("SELECT quiz_id FROM quiz_responses WHERE id=? LIMIT 1");
$q->execute([$responseId]);
$row = $q->fetch();
$quizId = $row ? (int)$row['quiz_id'] : 0;

db()->prepare("UPDATE quiz_responses SET score=?, feedback=?, graded_by=?, graded_at=NOW() WHERE id=?")
  ->execute([$score, $feedback ?: null, auth_user()['id'], $responseId]);

flash_set('success', t('flash_quiz_saved'));
if ($quizId > 0) redirect(base_url('quiz/review?id=' . $quizId));
redirect(base_url('quizzes'));
