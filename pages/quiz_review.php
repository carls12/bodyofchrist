<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

if (!auth_user()['is_leader']) { redirect(base_url('quizzes')); }

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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { redirect(base_url('quizzes')); }

$stmt = db()->prepare("SELECT * FROM quizzes WHERE id=? AND created_by=? LIMIT 1");
$stmt->execute([$id, auth_user()['id']]);
$quiz = $stmt->fetch();
if (!$quiz) { redirect(base_url('quizzes')); }

$questions = json_decode($quiz['questions_json'] ?? '[]', true);
if (!is_array($questions)) $questions = [];

$resp = db()->prepare("SELECT r.*, u.name FROM quiz_responses r
  JOIN users u ON u.id=r.user_id
  WHERE r.quiz_id=? ORDER BY r.completed_at DESC");
$resp->execute([$id]);
$responses = $resp->fetchAll();

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('nav_quiz')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('quiz_review')) ?></h2>
    <div class="text-muted"><?= e($quiz['title']) ?> • <?= e($quiz['bible_reference']) ?></div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <?php if (!$responses): ?>
      <div class="text-muted"><?= e(t('quiz_none')) ?></div>
    <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($responses as $r): ?>
          <?php $answers = json_decode($r['answers_json'] ?? '[]', true); ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
              <div class="fw-semibold"><?= e($r['name']) ?></div>
              <div class="small text-muted"><?= e($r['completed_at'] ?? '') ?></div>
            </div>
            <div class="mt-2 d-grid gap-2">
              <?php foreach ($questions as $idx => $q): ?>
                <div class="p-2 border rounded">
                  <div class="fw-semibold small"><?= ($idx+1) ?>. <?= e($q['q'] ?? '') ?></div>
                  <div class="small text-muted">
                    <?php if (($quiz['quiz_type'] ?? 'mcq') === 'free'): ?>
                      <?= e((string)($answers[$idx] ?? '')) ?>
                    <?php else: ?>
                      <?php
                        $ans = $answers[$idx] ?? null;
                        $opts = $q['options'] ?? [];
                        if (($quiz['quiz_type'] ?? 'mcq') === 'tf' && (!$opts || !is_array($opts))) {
                          $loc = $_SESSION['locale'] ?? 'de';
                          $opts = ($loc === 'en') ? ['True','False'] : (($loc === 'fr') ? ['Vrai','Faux'] : ['Wahr','Falsch']);
                        }
                        $opt = $opts[$ans] ?? '';
                      ?>
                      <?= e((string)$opt) ?>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <form method="post" action="<?= e(base_url('action/grade-quiz-response')) ?>" class="d-grid gap-2 mt-3">
              <input type="hidden" name="response_id" value="<?= (int)$r['id'] ?>">
              <div>
                <label class="form-label"><?= e(t('quiz_score')) ?></label>
                <input class="form-control" name="score" type="number" min="0" value="<?= (int)$r['score'] ?>">
              </div>
              <div>
                <label class="form-label"><?= e(t('quiz_feedback')) ?></label>
                <textarea class="form-control" name="feedback" rows="2"><?= e($r['feedback'] ?? '') ?></textarea>
              </div>
              <button class="btn btn-outline-primary"><?= e(t('quiz_save_grade')) ?></button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
