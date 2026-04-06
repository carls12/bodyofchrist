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
  completed_at DATETIME NULL,
  UNIQUE KEY uniq_quiz_user (quiz_id, user_id),
  INDEX (user_id),
  CONSTRAINT fk_qr_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
  CONSTRAINT fk_qr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$me = auth_user()['id'];

$leaderGroups = [];
if (auth_user()['is_leader']) {
  $g = db()->prepare("SELECT id,name FROM assemblies WHERE leader_id=? ORDER BY name ASC");
  $g->execute([$me]);
  $leaderGroups = $g->fetchAll();
}

$activeGroups = db()->prepare("SELECT a.id FROM assemblies a
  JOIN assembly_members am ON am.assembly_id=a.id
  WHERE am.user_id=? AND (am.status='active' OR am.active=1)");
$activeGroups->execute([$me]);
$groupIds = array_map(fn($r)=> (int)$r['id'], $activeGroups->fetchAll());
if ($leaderGroups) {
  foreach ($leaderGroups as $g) $groupIds[] = (int)$g['id'];
}
$groupIds = array_values(array_unique($groupIds));
$in = $groupIds ? implode(',', array_fill(0, count($groupIds), '?')) : '';

if ($groupIds) {
  $q = db()->prepare("SELECT q.*, u.name AS creator FROM quizzes q
    JOIN users u ON u.id=q.created_by
    WHERE q.active=1 AND q.group_id IN ($in)
    ORDER BY q.created_at DESC");
  $q->execute($groupIds);
  $available = $q->fetchAll();
} else {
  $available = [];
}

$created = [];
if (auth_user()['is_leader']) {
  $c = db()->prepare("SELECT q.*, COUNT(r.id) AS responses
    FROM quizzes q
    LEFT JOIN quiz_responses r ON r.quiz_id=q.id
    WHERE q.created_by=?
    GROUP BY q.id ORDER BY q.created_at DESC");
  $c->execute([$me]);
  $created = $c->fetchAll();
}

$scores = [];
if ($available) {
  $ids = array_map(fn($r)=> (int)$r['id'], $available);
  $in2 = implode(',', array_fill(0, count($ids), '?'));
  $s = db()->prepare("SELECT quiz_id, score FROM quiz_responses WHERE user_id=? AND quiz_id IN ($in2)");
  $s->execute(array_merge([$me], $ids));
  foreach ($s->fetchAll() as $r) $scores[(int)$r['quiz_id']] = (int)$r['score'];
}

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('nav_quiz')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('quiz_title')) ?></h2>
    <div class="text-muted"><?= e(t('quiz_intro')) ?></div>
  </div>
</div>

<?php if (auth_user()['is_leader']): ?>
  <div class="card mb-3">
    <div class="card-body">
      <div class="fw-semibold mb-2"><?= e(t('quiz_create')) ?></div>
      <form method="post" action="<?= e(base_url('action/save-quiz')) ?>" class="d-grid gap-2" id="quizForm">
        <div><label class="form-label"><?= e(t('quiz_title')) ?></label><input class="form-control" name="title" required></div>
        <div><label class="form-label"><?= e(t('quiz_reference')) ?></label><input class="form-control" name="reference" id="quizRef" placeholder="John 3" required></div>
        <div>
          <label class="form-label"><?= e(t('quiz_type')) ?></label>
          <select class="form-select" name="quiz_type" id="quizType">
            <option value="mcq"><?= e(t('quiz_type_mcq')) ?></option>
            <option value="tf"><?= e(t('quiz_type_tf')) ?></option>
            <option value="free"><?= e(t('quiz_type_free')) ?></option>
          </select>
        </div>
        <div>
          <label class="form-label"><?= e(t('quiz_count')) ?></label>
          <select class="form-select" id="quizCount">
            <option value="4">4</option>
            <option value="6" selected>6</option>
            <option value="8">8</option>
            <option value="10">10</option>
          </select>
        </div>
        <div>
          <label class="form-label"><?= e(t('quiz_assign_group')) ?></label>
          <select class="form-select" name="group_id">
            <option value="">(optional)</option>
            <?php foreach ($leaderGroups as $g): ?>
              <option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label"><?= e(t('quiz_questions')) ?></label>
          <textarea class="form-control" name="questions_json" id="quizQuestions" rows="8" placeholder='[{"q":"...","options":["A","B","C","D"],"answer":0}]' required></textarea>
          <div class="text-muted small mt-1"><?= e(t('quiz_json_help')) ?></div>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" type="button" id="genQuestions"><?= e(t('quiz_generate')) ?></button>
          <button class="btn btn-primary"><?= e(t('quiz_submit')) ?></button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('quiz_available')) ?></div>
        <?php if (!$available): ?>
          <div class="text-muted"><?= e(t('quiz_none')) ?></div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($available as $qz): ?>
              <div class="list-group-item d-flex align-items-center justify-content-between">
                <div>
                  <div class="fw-semibold"><?= e($qz['title']) ?></div>
                  <div class="text-muted small"><?= e($qz['bible_reference']) ?> • <?= e(strtoupper($qz['quiz_type'] ?? 'MCQ')) ?> • <?= e($qz['creator']) ?></div>
                </div>
                <div class="text-end">
                  <?php if (auth_user()['is_leader'] && isset($scores[(int)$qz['id']])): ?>
                    <div class="small text-muted"><?= e(t('quiz_score')) ?>: <?= (int)$scores[(int)$qz['id']] ?></div>
                  <?php endif; ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('quiz/take?id='.(int)$qz['id'])) ?>"><?= e(t('quiz_take')) ?></a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('quiz_created')) ?></div>
        <?php if (!$created): ?>
          <div class="text-muted"><?= e(t('quiz_none')) ?></div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($created as $qz): ?>
              <div class="list-group-item d-flex align-items-center justify-content-between">
                <div>
                  <div class="fw-semibold"><?= e($qz['title']) ?></div>
                  <div class="text-muted small"><?= e($qz['bible_reference']) ?> • <?= e(strtoupper($qz['quiz_type'] ?? 'MCQ')) ?></div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1">
                  <div class="small text-muted"><?= (int)$qz['responses'] ?> <?= e(t('quiz_responses')) ?></div>
                  <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('quiz/review?id='.(int)$qz['id'])) ?>"><?= e(t('quiz_review')) ?></a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
  var btn = document.getElementById('genQuestions');
  if (btn) {
    btn.addEventListener('click', function(){
      var ref = document.getElementById('quizRef').value || '';
      var qtype = document.getElementById('quizType').value || 'mcq';
      var qcount = document.getElementById('quizCount').value || '6';
      var fd = new FormData();
      fd.set('reference', ref);
      fd.set('count', qcount);
      fd.set('quiz_type', qtype);
      fetch('<?= e(base_url('action/generate-quiz')) ?>', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (!data || !data.ok) {
            var msg = <?= json_encode(t('quiz_generate_error')) ?>;
            if (data && data.detail) msg = msg + "\n" + data.detail;
            if (data && data.error === 'book_not_found') msg = msg + "\n" + <?= json_encode(t('quiz_ref_book_hint')) ?>;
            if (data && data.error === 'chapter_not_found') msg = msg + "\n" + <?= json_encode(t('quiz_ref_chapter_hint')) ?>;
            alert(msg);
            return;
          }
          document.getElementById('quizQuestions').value = JSON.stringify(data.questions, null, 2);
        })
        .catch(function(){
          alert('Fehler beim Generieren.');
        });
    });
  }
</script>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
