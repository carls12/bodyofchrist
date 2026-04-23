<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("CREATE TABLE IF NOT EXISTS prayer_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  assembly_id INT UNSIGNED NULL,
  date DATE NOT NULL,
  minutes DECIMAL(10,2) NOT NULL DEFAULT 0,
  seconds INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,
  is_fasting TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  INDEX (user_id, date),
  INDEX (assembly_id, date),
  CONSTRAINT fk_prayer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

db()->exec("ALTER TABLE prayer_logs ADD COLUMN IF NOT EXISTS seconds INT UNSIGNED NOT NULL DEFAULT 0");
db()->exec("ALTER TABLE prayer_logs ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE prayer_logs ADD INDEX IF NOT EXISTS idx_prayer_logs_assembly (assembly_id, date)");
db()->exec("ALTER TABLE bible_readings ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE bible_readings ADD INDEX IF NOT EXISTS idx_bible_assembly (assembly_id, read_date)");
ensure_goal_group_schema();

$uid = auth_user()['id'];
$today = new DateTimeImmutable('today');
$aid = active_assembly_id();

function range_sum($uid, DateTimeImmutable $start, DateTimeImmutable $end): array {
  $aid = active_assembly_id();
  $p = db()->prepare("SELECT SUM(COALESCE(seconds, minutes*60)) as total_seconds FROM prayer_logs WHERE user_id=? AND date BETWEEN ? AND ? AND (assembly_id=? OR assembly_id IS NULL)");
  $p->execute([$uid, $start->format('Y-m-d'), $end->format('Y-m-d'), $aid]);
  $prayerSeconds = (int)($p->fetch()['total_seconds'] ?? 0);
  $prayer = $prayerSeconds / 60.0;

  $b = db()->prepare("SELECT COUNT(*) as chapters FROM bible_readings WHERE user_id=? AND read_date BETWEEN ? AND ? AND (assembly_id=? OR assembly_id IS NULL)");
  $b->execute([$uid, $start->format('Y-m-d'), $end->format('Y-m-d'), $aid]);
  $bible = (int)($b->fetch()['chapters'] ?? 0);
  return [$prayer, $bible];
}

$weekStart = new DateTimeImmutable(monday_of($today->format('Y-m-d')));
$weekEnd = $weekStart->modify('+6 days');
$weekNo = $weekStart->format('W');
$monthStart = $today->modify('first day of this month');
$monthEnd = $today->modify('last day of this month');
$yearStart = $today->setDate((int)$today->format('Y'), 1, 1);
$yearEnd = $today->setDate((int)$today->format('Y'), 12, 31);

[$weekPrayer, $weekBible] = range_sum($uid, $weekStart, $weekEnd);
[$monthPrayer, $monthBible] = range_sum($uid, $monthStart, $monthEnd);
[$yearPrayer, $yearBible] = range_sum($uid, $yearStart, $yearEnd);

$goalStmt = db()->prepare("SELECT category, label, group_title, target, unit FROM goals
  WHERE user_id=? AND week_start=? AND (assembly_id=? OR assembly_id IS NULL)
  ORDER BY group_title ASC, id ASC");
$goalStmt->execute([$uid, $weekStart->format('Y-m-d'), $aid]);
$weeklyGoals = $goalStmt->fetchAll();

$goalDaily = [];
$goalTotals = [];
if ($weeklyGoals) {
  $categories = array_values(array_unique(array_map(static fn($goal) => (string)$goal['category'], $weeklyGoals)));
  $in = implode(',', array_fill(0, count($categories), '?'));
  $goalProgress = db()->prepare("SELECT category, day, SUM(value) AS total
    FROM daily_progress
    WHERE user_id=? AND category IN ($in) AND day BETWEEN ? AND ? AND (assembly_id=? OR assembly_id IS NULL)
    GROUP BY category, day");
  $goalProgress->execute(array_merge([$uid], $categories, [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d'), $aid]));
  foreach ($goalProgress->fetchAll() as $row) {
    $category = (string)$row['category'];
    $day = (string)$row['day'];
    $value = (float)$row['total'];
    $goalDaily[$category][$day] = $value;
    $goalTotals[$category] = ($goalTotals[$category] ?? 0) + $value;
  }
}

$days = [];
for ($d=$weekStart; $d <= $weekEnd; $d = $d->modify('+1 day')) {
  $days[] = $d->format('Y-m-d');
}
$dailyPrayer = [];
$p = db()->prepare("SELECT date, SUM(COALESCE(seconds, minutes*60)) as total_seconds FROM prayer_logs
  WHERE user_id=? AND date BETWEEN ? AND ? AND (assembly_id=? OR assembly_id IS NULL) GROUP BY date");
$p->execute([$uid, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d'), $aid]);
foreach ($p->fetchAll() as $r) $dailyPrayer[$r['date']] = ((int)$r['total_seconds']) / 60.0;

$dailyBible = [];
$b = db()->prepare("SELECT read_date, COUNT(*) as chapters FROM bible_readings
  WHERE user_id=? AND read_date BETWEEN ? AND ? AND (assembly_id=? OR assembly_id IS NULL) GROUP BY read_date");
$b->execute([$uid, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d'), $aid]);
foreach ($b->fetchAll() as $r) $dailyBible[$r['read_date']] = (int)$r['chapters'];

function label_day_short(string $ymd): string {
  $dt = new DateTimeImmutable($ymd);
  $w = (int)$dt->format('w');
  $loc = $_SESSION['locale'] ?? 'de';
  $map = [
    'de' => ['So','Mo','Di','Mi','Do','Fr','Sa'],
    'en' => ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
    'fr' => ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'],
  ];
  $arr = $map[$loc] ?? $map['de'];
  return $arr[$w] . ' ' . $dt->format('d.m');
}

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('nav_my_reports')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('my_reports_title')) ?></h2>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('my_reports_weekly')) ?></div>
        <div class="text-muted small mb-2"><?= e($weekStart->format('d.m.Y')) ?> - <?= e($weekEnd->format('d.m.Y')) ?></div>
        <div class="text-muted small"><?= e(t('my_reports_prayer')) ?>: <?= e(number_format($weekPrayer,1)) ?></div>
        <div class="text-muted small"><?= e(t('my_reports_bible')) ?>: <?= (int)$weekBible ?></div>
        <?php if (class_exists('Dompdf\\Dompdf')): ?>
          <div class="d-flex gap-2 mt-2">
            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('action/download-my-report?period=week&pdf=1')) ?>"><?= e(t('my_reports_download_pdf')) ?></a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('my_reports_monthly')) ?></div>
        <div class="text-muted small mb-2"><?= e($monthStart->format('F Y')) ?></div>
        <div class="text-muted small"><?= e(t('my_reports_prayer')) ?>: <?= e(number_format($monthPrayer,1)) ?></div>
        <div class="text-muted small"><?= e(t('my_reports_bible')) ?>: <?= (int)$monthBible ?></div>
        <?php if (class_exists('Dompdf\\Dompdf')): ?>
          <div class="d-flex gap-2 mt-2">
            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('action/download-my-report?period=month&pdf=1')) ?>"><?= e(t('my_reports_download_pdf')) ?></a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('my_reports_yearly')) ?></div>
        <div class="text-muted small mb-2"><?= e($yearStart->format('Y')) ?></div>
        <div class="text-muted small"><?= e(t('my_reports_prayer')) ?>: <?= e(number_format($yearPrayer,1)) ?></div>
        <div class="text-muted small"><?= e(t('my_reports_bible')) ?>: <?= (int)$yearBible ?></div>
        <?php if (class_exists('Dompdf\\Dompdf')): ?>
          <div class="d-flex gap-2 mt-2">
            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('action/download-my-report?period=year&pdf=1')) ?>"><?= e(t('my_reports_download_pdf')) ?></a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <div class="fw-semibold"><?= e(t('my_reports_week_header', ['name' => auth_user()['name'], 'week' => $weekNo])) ?></div>
        <div class="text-muted small"><?= e($weekStart->format('d.m.Y')) ?> - <?= e($weekEnd->format('d.m.Y')) ?></div>
      </div>
      <div class="text-muted small"><?= e($today->format('F Y')) ?></div>
    </div>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th><?= e(t('progress_day')) ?></th>
            <th><?= e(t('my_reports_prayer')) ?></th>
            <th><?= e(t('my_reports_bible')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php $i=1; $sumPrayer=0.0; $sumBible=0; foreach ($days as $d): ?>
            <?php $pval = (float)($dailyPrayer[$d] ?? 0); $bval = (int)($dailyBible[$d] ?? 0); $sumPrayer += $pval; $sumBible += $bval; ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= e(label_day_short($d)) ?></td>
              <td><?= e(rtrim(rtrim(number_format($pval,1,'.',''),'0'),'.')) ?></td>
              <td><?= (int)$bval ?></td>
            </tr>
          <?php endforeach; ?>
          <tr class="table-light">
            <td colspan="2" class="fw-semibold"><?= e(t('dashboard_today')) ?> / <?= e(t('my_reports_weekly')) ?></td>
            <td class="fw-semibold"><?= e(rtrim(rtrim(number_format($sumPrayer,1,'.',''),'0'),'.')) ?></td>
            <td class="fw-semibold"><?= (int)$sumBible ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card mt-3">
  <div class="card-body">
    <div class="fw-semibold mb-2"><?= e(t('reports_goals_title')) ?></div>
    <?php if (!$weeklyGoals): ?>
      <div class="text-muted"><?= e(t('reports_no_goals')) ?></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th><?= e(t('goals_table_label')) ?></th>
              <?php foreach ($days as $d): ?><th><?= e(label_day_short($d)) ?></th><?php endforeach; ?>
              <th><?= e(t('goals_total')) ?></th>
              <th><?= e(t('goals_target')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($weeklyGoals as $goal): ?>
              <?php $category = (string)$goal['category']; ?>
              <tr>
                <td><?= e($goal['label']) ?><div class="text-muted small"><code><?= e($category) ?></code></div></td>
                <?php foreach ($days as $d): ?>
                  <td><?= e(rtrim(rtrim(number_format((float)($goalDaily[$category][$d] ?? 0), 2, '.', ''), '0'), '.')) ?></td>
                <?php endforeach; ?>
                <td><?= e(rtrim(rtrim(number_format((float)($goalTotals[$category] ?? 0), 2, '.', ''), '0'), '.')) ?> <?= e(compact_goal_unit((string)$goal['unit'])) ?></td>
                <td><?= e($goal['target']) ?> <?= e(compact_goal_unit((string)$goal['unit'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
