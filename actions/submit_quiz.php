<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

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

$quizId = (int)($_POST['quiz_id'] ?? 0);
if ($quizId <= 0) {
  flash_set('error', t('flash_fields_required'));
  redirect(base_url('quizzes'));
}

$stmt = db()->prepare("SELECT * FROM quizzes WHERE id=? LIMIT 1");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();
if (!$quiz) {
  flash_set('error', t('flash_fields_required'));
  redirect(base_url('quizzes'));
}

$questions = json_decode($quiz['questions_json'] ?? '[]', true);
if (!is_array($questions)) $questions = [];
$quizType = $quiz['quiz_type'] ?? 'mcq';

$answers = $_POST['answers'] ?? [];
$score = 0;
$total = 0;
if ($quizType === 'free') {
  $score = 0;
} else {
  foreach ($questions as $idx => $q) {
    $total++;
    $correct = $q['answer'] ?? null;
    $ans = $answers[$idx] ?? null;
    if ($correct !== null && (string)$ans === (string)$correct) $score++;
  }
}

db()->prepare("INSERT INTO quiz_responses (quiz_id,user_id,answers_json,score,completed_at)
  VALUES (?,?,?,?,NOW())
  ON DUPLICATE KEY UPDATE answers_json=VALUES(answers_json), score=VALUES(score), completed_at=NOW(), graded_by=NULL, graded_at=NULL, feedback=NULL")
  ->execute([$quizId, auth_user()['id'], json_encode($answers), $score]);

flash_set('success', t('flash_quiz_submitted'));
redirect(base_url('quiz/take?id=' . $quizId));
