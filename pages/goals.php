<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS is_global TINYINT(1) NOT NULL DEFAULT 0");
db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE goals ADD INDEX IF NOT EXISTS idx_goals_assembly (assembly_id, week_start)");

$uid = auth_user()['id'];
$weekStart = monday_of(now_ymd());
$weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
$aid = active_assembly_id();

$stmt = db()->prepare("SELECT * FROM goals WHERE user_id=? AND week_start=? AND (assembly_id=? OR assembly_id IS NULL) ORDER BY is_global DESC, id DESC");
$stmt->execute([$uid, $weekStart, $aid]);
$goals = $stmt->fetchAll();

$editId = (int)($_GET['edit'] ?? 0);
$editGoal = null;
if ($editId > 0) {
    $e = db()->prepare("SELECT * FROM goals WHERE id=? AND user_id=? AND week_start=? AND is_global=0 AND (assembly_id=? OR assembly_id IS NULL) LIMIT 1");
    $e->execute([$editId, $uid, $weekStart, $aid]);
    $editGoal = $e->fetch();
}

$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = (new DateTimeImmutable($weekStart))->modify("+$i days")->format('Y-m-d');
}

$dailyByCategory = [];
$weeklyTotals = [];
if ($goals) {
    $categories = array_values(array_unique(array_map(static fn($goal) => (string)$goal['category'], $goals)));
    $in = implode(',', array_fill(0, count($categories), '?'));
    $progress = db()->prepare("SELECT category, day, SUM(value) AS total
        FROM daily_progress
        WHERE user_id=? AND category IN ($in) AND day BETWEEN ? AND ? AND (assembly_id=? OR assembly_id IS NULL)
        GROUP BY category, day");
    $progress->execute(array_merge([$uid], $categories, [$weekStart, $weekEnd, $aid]));
    foreach ($progress->fetchAll() as $row) {
        $category = (string)$row['category'];
        $day = (string)$row['day'];
        $value = (float)$row['total'];
        $dailyByCategory[$category][$day] = $value;
        $weeklyTotals[$category] = ($weeklyTotals[$category] ?? 0) + $value;
    }
}

function goals_day_label(string $ymd): string {
    $dt = new DateTimeImmutable($ymd);
    $w = (int)$dt->format('w');
    $loc = $_SESSION['locale'] ?? 'de';
    $map = [
        'de' => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        'en' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        'fr' => ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
    ];
    $arr = $map[$loc] ?? $map['en'];
    return $arr[$w] . ' ' . $dt->format('d.m');
}

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="h4 mb-1"><?= e(t('goals_title')) ?></h2>
    <div class="text-muted"><?= e(t('goals_week', ['date' => $weekStart])) ?></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e($editGoal ? t('goals_edit') : t('goals_new')) ?></div>
        <form method="post" action="<?= e(base_url('action/save-goal')) ?>" class="d-grid gap-2">
          <?php if ($editGoal): ?>
            <input type="hidden" name="id" value="<?= (int)$editGoal['id'] ?>">
          <?php endif; ?>
          <div><label class="form-label"><?= e(t('goals_label_hint')) ?></label><input class="form-control" name="label" value="<?= e($editGoal['label'] ?? '') ?>" required></div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label"><?= e(t('goals_target')) ?></label><input class="form-control" type="number" step="0.01" name="target" value="<?= e($editGoal['target'] ?? '') ?>" required></div>
            <div class="col-6"><label class="form-label"><?= e(t('goals_unit')) ?></label><input class="form-control" name="unit" value="<?= e($editGoal['unit'] ?? '') ?>" placeholder="<?= e(t('goals_unit_hint')) ?>" required></div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary"><?= e($editGoal ? t('goals_update') : t('goals_save')) ?></button>
            <?php if ($editGoal): ?>
              <a class="btn btn-outline-secondary" href="<?= e(base_url('goals')) ?>"><?= e(t('goals_cancel')) ?></a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('goals_list')) ?></div>
        <?php if (!$goals): ?>
          <div class="text-muted"><?= e(t('goals_none')) ?></div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th><?= e(t('goals_table_label')) ?></th><th class="text-end"><?= e(t('goals_table_target')) ?></th><th><?= e(t('goals_table_unit')) ?></th><th></th></tr></thead>
              <tbody>
                <?php foreach ($goals as $g): ?>
                  <tr>
                    <td><?= e($g['label']) ?></td>
                    <td class="text-end"><?= e($g['target']) ?></td>
                    <td><?= e($g['unit']) ?></td>
                    <td class="text-end">
                      <?php if (!(int)$g['is_global']): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('goals?edit='.(int)$g['id'])) ?>"><?= e(t('goals_edit')) ?></a>
                        <form method="post" action="<?= e(base_url('action/delete-goal')) ?>" class="d-inline">
                          <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                          <button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= e(t('goals_delete_confirm')) ?>')"><?= e(t('goals_delete')) ?></button>
                        </form>
                      <?php else: ?>
                        <span class="badge text-bg-warning"><?= e(t('admin_global_goal')) ?></span>
                      <?php endif; ?>
                    </td>
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

<div class="card border-0 shadow-sm mt-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <div class="fw-semibold"><?= e(t('goals_daily_title')) ?></div>
        <div class="text-muted small"><?= e(t('goals_daily_sub')) ?></div>
      </div>
      <div class="text-muted small"><?= e($weekStart) ?> - <?= e($weekEnd) ?></div>
    </div>
    <?php if (!$goals): ?>
      <div class="text-muted"><?= e(t('goals_none')) ?></div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($goals as $goal): ?>
          <?php
            $category = (string)$goal['category'];
            $actual = (float)($weeklyTotals[$category] ?? 0);
            $target = (float)$goal['target'];
            $pct = $target > 0 ? min(100, (int)round(($actual / $target) * 100)) : 0;
          ?>
          <div class="col-12">
            <div class="border rounded-3 p-3">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div>
                  <div class="fw-semibold"><?= e($goal['label']) ?></div>
                  <div class="text-muted small"><code><?= e($category) ?></code></div>
                </div>
                <div class="text-end">
                  <div class="small text-muted"><?= e(t('goals_total')) ?></div>
                  <div class="fw-semibold"><?= e(rtrim(rtrim(number_format($actual, 2, '.', ''), '0'), '.')) ?> / <?= e($goal['target']) ?> <?= e($goal['unit']) ?></div>
                </div>
              </div>
              <div class="progress mb-3">
                <div class="progress-bar" style="width: <?= (int)$pct ?>%"></div>
              </div>
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead>
                    <tr>
                      <th><?= e(t('progress_day')) ?></th>
                      <th class="text-end"><?= e(t('goals_current')) ?></th>
                      <th><?= e(t('progress_value')) ?></th>
                      <th><?= e(t('progress_note')) ?></th>
                      <th class="text-end"><?= e(t('goals_save_day')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($days as $day): ?>
                      <?php
                        $dayTotal = (float)($dailyByCategory[$category][$day] ?? 0);
                        $formId = 'goal-' . (int)$goal['id'] . '-' . str_replace('-', '', $day);
                      ?>
                      <tr>
                        <td><?= e(goals_day_label($day)) ?></td>
                        <td class="text-end"><?= e(rtrim(rtrim(number_format($dayTotal, 2, '.', ''), '0'), '.')) ?></td>
                        <td>
                          <input class="form-control" form="<?= e($formId) ?>" type="number" step="0.01" min="0" name="value" placeholder="0" style="min-width:120px" required>
                        </td>
                        <td>
                          <input class="form-control" form="<?= e($formId) ?>" name="note" placeholder="<?= e(t('goals_note_hint')) ?>">
                        </td>
                        <td class="text-end">
                          <form method="post" action="<?= e(base_url('action/save-progress')) ?>" id="<?= e($formId) ?>" class="m-0">
                            <input type="hidden" name="day" value="<?= e($day) ?>">
                            <input type="hidden" name="category" value="<?= e($category) ?>">
                            <input type="hidden" name="return_to" value="<?= e(base_url('goals')) ?>">
                            <button class="btn btn-sm btn-primary"><?= e(t('goals_save_day')) ?></button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
