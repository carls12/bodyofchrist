<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE goals ADD INDEX IF NOT EXISTS idx_goals_assembly (assembly_id, week_start)");
db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");

$uid = auth_user()['id'];
$today = now_ymd();
$weekStart = monday_of($today);
$aid = active_assembly_id();

$g = db()->prepare("SELECT * FROM goals WHERE user_id=? AND week_start=? AND (assembly_id=? OR assembly_id IS NULL) ORDER BY id DESC");
$g->execute([$uid, $weekStart, $aid]);
$goals = $g->fetchAll();

$recent = db()->prepare("SELECT * FROM daily_progress WHERE user_id=? AND (assembly_id=? OR assembly_id IS NULL) ORDER BY id DESC LIMIT 20");
$recent->execute([$uid, $aid]);
$entries = $recent->fetchAll();

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="h4 mb-1"><?= e(t('progress_title')) ?></h2>
    <div class="text-muted"><?= e(t('progress_sub')) ?></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('progress_new')) ?></div>
        <form method="post" action="<?= e(base_url('action/save-progress')) ?>" class="d-grid gap-2">
          <div><label class="form-label"><?= e(t('progress_day')) ?></label><input class="form-control" type="date" name="day" value="<?= e($today) ?>" required></div>
          <div>
            <label class="form-label"><?= e(t('progress_category')) ?></label>
            <select class="form-select" name="category" required>
              <?php if ($goals): ?>
                <?php foreach ($goals as $goal): ?>
                  <option value="<?= e($goal['category']) ?>"><?= e($goal['label']) ?></option>
                <?php endforeach; ?>
              <?php else: ?>
                <option value="prayer_minutes"><?= e(t('progress_default_prayer')) ?></option>
                <option value="bible_chapters"><?= e(t('progress_default_bible')) ?></option>
                <option value="fasting_days"><?= e(t('progress_default_fasting')) ?></option>
                <option value="tracktate"><?= e(t('progress_default_tracktate')) ?></option>
              <?php endif; ?>
            </select>
          </div>
          <div><label class="form-label"><?= e(t('progress_value')) ?></label><input class="form-control" type="number" step="0.01" name="value" required></div>
          <div><label class="form-label"><?= e(t('progress_note')) ?></label><input class="form-control" name="note"></div>
          <button class="btn btn-primary"><?= e(t('goals_save')) ?></button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('progress_recent')) ?></div>
        <?php if (!$entries): ?>
          <div class="text-muted"><?= e(t('progress_none')) ?></div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th><?= e(t('progress_day')) ?></th><th><?= e(t('progress_category')) ?></th><th class="text-end"><?= e(t('progress_value')) ?></th><th><?= e(t('progress_note')) ?></th></tr></thead>
              <tbody>
                <?php foreach ($entries as $er): ?>
                  <tr>
                    <td><?= e($er['day']) ?></td>
                    <td><code><?= e($er['category']) ?></code></td>
                    <td class="text-end"><?= e($er['value']) ?></td>
                    <td class="text-muted small"><?= e((string)($er['note'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
