<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS is_global TINYINT(1) NOT NULL DEFAULT 0");
db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE goals ADD INDEX IF NOT EXISTS idx_goals_assembly (assembly_id, week_start)");
db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");
ensure_goal_group_schema();

$uid = (int)auth_user()['id'];
$aid = active_assembly_id();
$currentWeekStart = monday_of(now_ymd());

$weeksStmt = db()->prepare("SELECT week_start, COUNT(*) AS goal_count
  FROM goals
  WHERE user_id=? AND (assembly_id=? OR assembly_id IS NULL)
  GROUP BY week_start
  ORDER BY week_start DESC");
$weeksStmt->execute([$uid, $aid]);
$weeks = $weeksStmt->fetchAll();

$requestedWeek = (string)($_GET['week_start'] ?? '');
$selectedWeek = '';
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedWeek)) {
  $selectedWeek = monday_of($requestedWeek);
}
if ($selectedWeek === '' && $weeks) {
  $selectedWeek = (string)$weeks[0]['week_start'];
}

$weekEnd = $selectedWeek !== '' ? (new DateTimeImmutable($selectedWeek))->modify('+6 days')->format('Y-m-d') : '';
$days = [];
if ($selectedWeek !== '') {
  for ($i = 0; $i < 7; $i++) {
    $days[] = (new DateTimeImmutable($selectedWeek))->modify("+$i days")->format('Y-m-d');
  }
}

$goals = [];
if ($selectedWeek !== '') {
  $stmt = db()->prepare("SELECT * FROM goals
    WHERE user_id=? AND week_start=? AND (assembly_id=? OR assembly_id IS NULL)
    ORDER BY is_global DESC, group_title ASC, id ASC");
  $stmt->execute([$uid, $selectedWeek, $aid]);
  $goals = $stmt->fetchAll();
}

$dailyByCategory = [];
$notesByCategory = [];
$weeklyTotals = [];
if ($goals && $selectedWeek !== '' && $weekEnd !== '') {
  $categories = array_values(array_unique(array_map(static fn($goal) => (string)$goal['category'], $goals)));
  $in = implode(',', array_fill(0, count($categories), '?'));

  $progress = db()->prepare("SELECT category, day, SUM(value) AS total
    FROM daily_progress
    WHERE user_id=? AND category IN ($in) AND day BETWEEN ? AND ? AND (assembly_id=? OR assembly_id IS NULL)
    GROUP BY category, day");
  $progress->execute(array_merge([$uid], $categories, [$selectedWeek, $weekEnd, $aid]));
  foreach ($progress->fetchAll() as $row) {
    $category = (string)$row['category'];
    $day = (string)$row['day'];
    $value = (float)$row['total'];
    $dailyByCategory[$category][$day] = $value;
    $weeklyTotals[$category] = ($weeklyTotals[$category] ?? 0) + $value;
  }

  $notes = db()->prepare("SELECT category, day, note
    FROM daily_progress
    WHERE user_id=? AND category IN ($in) AND day BETWEEN ? AND ? AND (assembly_id=? OR assembly_id IS NULL)
      AND note IS NOT NULL AND note <> ''
    ORDER BY updated_at DESC, id DESC");
  $notes->execute(array_merge([$uid], $categories, [$selectedWeek, $weekEnd, $aid]));
  foreach ($notes->fetchAll() as $row) {
    $category = (string)$row['category'];
    $day = (string)$row['day'];
    if (!isset($notesByCategory[$category][$day])) {
      $notesByCategory[$category][$day] = (string)$row['note'];
    }
  }
}

function goal_history_day_label(string $ymd): string {
  $dt = new DateTimeImmutable($ymd);
  $w = (int)$dt->format('w');
  $loc = $_SESSION['locale'] ?? 'de';
  $map = [
    'de' => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
    'en' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    'fr' => ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
  ];
  $arr = $map[$loc] ?? $map['de'];
  return $arr[$w] . ' ' . $dt->format('d.m');
}

function goal_history_number(float $value, int $decimals = 2): string {
  return rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');
}

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('nav_goals')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('goal_history_title')) ?></h2>
    <div class="text-muted"><?= e(t('goal_history_sub')) ?></div>
  </div>
  <a class="btn btn-outline-primary" href="<?= e(base_url('goals')) ?>"><?= e(t('goal_history_current_week')) ?></a>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <?php if (!$weeks): ?>
      <div class="text-muted"><?= e(t('goal_history_empty')) ?></div>
    <?php else: ?>
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-5">
          <label class="form-label"><?= e(t('goal_history_select_week')) ?></label>
          <select class="form-select" name="week_start">
            <?php foreach ($weeks as $week): ?>
              <?php
                $start = new DateTimeImmutable((string)$week['week_start']);
                $end = $start->modify('+6 days');
                $label = $start->format('d.m.Y') . ' - ' . $end->format('d.m.Y') . ' (' . (int)$week['goal_count'] . ')';
              ?>
              <option value="<?= e((string)$week['week_start']) ?>" <?= (string)$week['week_start'] === $selectedWeek ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-primary"><?= e(t('bible_show')) ?></button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($selectedWeek !== ''): ?>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <div class="fw-semibold"><?= e(t('goal_history_week_title')) ?></div>
          <div class="text-muted small"><?= e($selectedWeek) ?> - <?= e($weekEnd) ?></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <?php if ($goals): ?>
            <form method="post" action="<?= e(base_url('action/repeat-goals-next-week')) ?>" class="m-0">
              <input type="hidden" name="source_week_start" value="<?= e($selectedWeek) ?>">
              <input type="hidden" name="target_week_start" value="<?= e($currentWeekStart) ?>">
              <input type="hidden" name="return_to" value="<?= e(base_url('goal-history?week_start=' . rawurlencode($selectedWeek))) ?>">
              <button class="btn btn-sm btn-outline-primary"><?= e(t('goal_history_copy_all_current_week')) ?></button>
            </form>
          <?php endif; ?>
          <?php if (class_exists('Dompdf\\Dompdf')): ?>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('action/download-my-report?period=week&week_start=' . rawurlencode($selectedWeek) . '&pdf=1')) ?>"><?= e(t('my_reports_download_pdf')) ?></a>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!$goals): ?>
        <div class="text-muted"><?= e(t('reports_no_goals')) ?></div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th><?= e(t('goals_table_label')) ?></th>
                <th><?= e(t('goals_group_title')) ?></th>
                <th class="text-end"><?= e(t('goals_total')) ?></th>
                <th class="text-end"><?= e(t('goals_target')) ?></th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($goals as $goal): ?>
                <?php
                  $category = (string)$goal['category'];
                  $actual = (float)($weeklyTotals[$category] ?? 0);
                ?>
                <tr>
                  <td><?= e($goal['label']) ?><div class="text-muted small"><code><?= e($category) ?></code></div></td>
                  <td><?= e(default_goal_group_title($goal)) ?></td>
                  <td class="text-end"><?= e(goal_history_number($actual)) ?> <?= e(compact_goal_unit((string)$goal['unit'])) ?></td>
                  <td class="text-end"><?= e(goal_history_number((float)$goal['target'])) ?> <?= e(compact_goal_unit((string)$goal['unit'])) ?></td>
                  <td class="text-end">
                    <?php if (!(int)$goal['is_global']): ?>
                      <form method="post" action="<?= e(base_url('action/copy-goal-to-week')) ?>" class="d-inline">
                        <input type="hidden" name="id" value="<?= (int)$goal['id'] ?>">
                        <input type="hidden" name="target_week_start" value="<?= e($currentWeekStart) ?>">
                        <input type="hidden" name="return_to" value="<?= e(base_url('goal-history?week_start=' . rawurlencode($selectedWeek))) ?>">
                        <button class="btn btn-sm btn-outline-secondary"><?= e(t('goal_history_copy_current_week')) ?></button>
                      </form>
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

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <div class="fw-semibold"><?= e(t('goal_history_backfill_title')) ?></div>
          <div class="text-muted small"><?= e(t('goal_history_backfill_sub')) ?></div>
        </div>
      </div>
      <?php if (!$goals): ?>
        <div class="text-muted"><?= e(t('reports_no_goals')) ?></div>
      <?php else: ?>
        <form method="post" action="<?= e(base_url('action/save-goal-progress-batch')) ?>">
          <input type="hidden" name="return_to" value="<?= e(base_url('goal-history?week_start=' . rawurlencode($selectedWeek))) ?>">
          <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-primary"><?= e(t('goals_save_all')) ?></button>
          </div>
          <div class="row g-3">
            <?php $entryIndex = 0; ?>
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
                      <div class="fw-semibold"><?= e(goal_history_number($actual)) ?> / <?= e(goal_history_number((float)$goal['target'])) ?> <?= e(compact_goal_unit((string)$goal['unit'])) ?></div>
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
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($days as $day): ?>
                          <?php
                            $dayTotal = (float)($dailyByCategory[$category][$day] ?? 0);
                            $dayValue = goal_history_number($dayTotal);
                            $dayNote = (string)($notesByCategory[$category][$day] ?? '');
                            $idx = $entryIndex++;
                          ?>
                          <tr>
                            <td><?= e(goal_history_day_label($day)) ?></td>
                            <td class="text-end"><?= e(goal_history_number($dayTotal)) ?></td>
                            <td>
                              <input type="hidden" name="entries[<?= (int)$idx ?>][day]" value="<?= e($day) ?>">
                              <input type="hidden" name="entries[<?= (int)$idx ?>][category]" value="<?= e($category) ?>">
                              <input class="form-control" type="number" step="0.01" min="0" name="entries[<?= (int)$idx ?>][value]" value="<?= e($dayValue) ?>" placeholder="0" style="min-width:120px">
                            </td>
                            <td>
                              <input class="form-control" name="entries[<?= (int)$idx ?>][note]" value="<?= e($dayNote) ?>" placeholder="<?= e(t('goals_note_hint')) ?>">
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
          <div class="d-flex justify-content-end mt-3">
            <button class="btn btn-primary"><?= e(t('goals_save_all')) ?></button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
