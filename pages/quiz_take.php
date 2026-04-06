<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

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

db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { redirect(base_url('quizzes')); }

$stmt = db()->prepare("SELECT * FROM quizzes WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$quiz = $stmt->fetch();
if (!$quiz) { redirect(base_url('quizzes')); }

if ($quiz['group_id']) {
  $m = db()->prepare("SELECT id FROM assembly_members WHERE assembly_id=? AND user_id=? AND status='active' LIMIT 1");
  $m->execute([(int)$quiz['group_id'], auth_user()['id']]);
  if (!$m->fetch()) {
    $lead = db()->prepare("SELECT id FROM assemblies WHERE id=? AND leader_id=? LIMIT 1");
    $lead->execute([(int)$quiz['group_id'], auth_user()['id']]);
    if (!$lead->fetch()) redirect(base_url('quizzes'));
  }
}

$questions = json_decode($quiz['questions_json'] ?? '[]', true);
if (!is_array($questions)) $questions = [];

$resp = db()->prepare("SELECT * FROM quiz_responses WHERE quiz_id=? AND user_id=? LIMIT 1");
$resp->execute([$id, auth_user()['id']]);
$existing = $resp->fetch();
$existingAnswers = $existing && isset($existing['answers_json']) ? json_decode($existing['answers_json'], true) : [];
if (!is_array($existingAnswers)) $existingAnswers = [];

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('nav_quiz')) ?></div>
    <h2 class="h4 mb-1"><?= e($quiz['title']) ?></h2>
    <div class="text-muted"><?= e($quiz['bible_reference']) ?></div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <?php if ($existing): ?>
      <?php if (auth_user()['is_leader']): ?>
        <div class="alert alert-success"><?= e(t('quiz_score')) ?>: <?= (int)$existing['score'] ?></div>
      <?php else: ?>
        <div class="alert alert-success"><?= e(t('quiz_submitted')) ?></div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!$questions): ?>
      <div class="text-muted"><?= e(t('quiz_none')) ?></div>
    <?php else: ?>
      <form method="post" action="<?= e(base_url('action/submit-quiz')) ?>" class="d-grid gap-3">
        <input type="hidden" name="quiz_id" value="<?= (int)$id ?>">
        <?php foreach ($questions as $idx => $q): ?>
          <div class="p-3 border rounded">
            <div class="fw-semibold mb-2"><?= ($idx+1) ?>. <?= e($q['q'] ?? '') ?></div>
            <?php if (($quiz['quiz_type'] ?? 'mcq') === 'free'): ?>
              <?php $prev = $existingAnswers[$idx] ?? ''; ?>
              <textarea class="form-control" name="answers[<?= (int)$idx ?>]" rows="3"><?= e((string)$prev) ?></textarea>
            <?php else: ?>
              <?php
                $options = $q['options'] ?? [];
                if (($quiz['quiz_type'] ?? 'mcq') === 'tf' && (!$options || !is_array($options))) {
                  $loc = $_SESSION['locale'] ?? 'de';
                  $options = ($loc === 'en') ? ['True','False'] : (($loc === 'fr') ? ['Vrai','Faux'] : ['Wahr','Falsch']);
                }
              ?>
              <?php foreach ($options as $oi => $opt): ?>
                <?php
                  $isSelected = isset($existingAnswers[$idx]) && (string)$existingAnswers[$idx] === (string)$oi;
                  $isCorrect = isset($q['answer']) && (string)$q['answer'] === (string)$oi;
                  $showResult = (bool)$existing;
                  $labelClass = $showResult ? ($isCorrect ? 'text-success fw-semibold' : ($isSelected ? 'text-danger' : '')) : '';
                ?>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="answers[<?= (int)$idx ?>]" value="<?= (int)$oi ?>" <?= $isSelected ? 'checked' : '' ?> <?= $existing ? 'disabled' : '' ?>>
                  <label class="form-check-label <?= $labelClass ?>">
                    <?= e($opt) ?>
                    <?php if ($showResult && $isCorrect): ?>
                      <span class="small text-success ms-1"><?= e(t('quiz_correct')) ?></span>
                    <?php elseif ($showResult && $isSelected && !$isCorrect): ?>
                      <span class="small text-danger ms-1"><?= e(t('quiz_wrong')) ?></span>
                    <?php endif; ?>
                  </label>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (!$existing): ?>
          <button class="btn btn-primary"><?= e(t('quiz_take')) ?></button>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
