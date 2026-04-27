<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
ensure_goal_group_schema();

db()->exec("CREATE TABLE IF NOT EXISTS prayer_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  assembly_id INT UNSIGNED NULL,
  date DATE NOT NULL,
  minutes DECIMAL(10,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  is_fasting TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  INDEX (user_id, date),
  INDEX (assembly_id, date),
  CONSTRAINT fk_prayer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
db()->exec("ALTER TABLE prayer_logs ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE prayer_logs ADD INDEX IF NOT EXISTS idx_prayer_logs_assembly (assembly_id, date)");
db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");
db()->exec("ALTER TABLE bible_readings ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE bible_readings ADD INDEX IF NOT EXISTS idx_bible_assembly (assembly_id, read_date)");
db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE goals ADD INDEX IF NOT EXISTS idx_goals_assembly (assembly_id, week_start)");

$period = $_GET['period'] ?? 'week';
$today = new DateTimeImmutable('today');
$requestedWeek = (string)($_GET['week_start'] ?? '');

if ($period === 'month') {
  $start = $today->modify('first day of this month');
  $end = $today->modify('last day of this month');
} elseif ($period === 'year') {
  $start = $today->setDate((int)$today->format('Y'), 1, 1);
  $end = $today->setDate((int)$today->format('Y'), 12, 31);
} else {
  $weekDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedWeek) ? $requestedWeek : $today->format('Y-m-d');
  $start = new DateTimeImmutable(monday_of($weekDate));
  $end = $start->modify('+6 days');
}

$uid = auth_user()['id'];
$aid = active_assembly_id();
ensure_report_notes_schema();

$prayer = [];
$p = db()->prepare("SELECT date, SUM(COALESCE(seconds, minutes*60)) as total_seconds FROM prayer_logs
  WHERE user_id=? AND date BETWEEN ? AND ? AND (assembly_id=? OR assembly_id IS NULL) GROUP BY date");
$p->execute([$uid, $start->format('Y-m-d'), $end->format('Y-m-d'), $aid]);
foreach ($p->fetchAll() as $r) $prayer[$r['date']] = ((int)$r['total_seconds']) / 60.0;

$bible = [];
$b = db()->prepare("SELECT read_date, COUNT(*) as chapters FROM bible_readings
  WHERE user_id=? AND read_date BETWEEN ? AND ? AND (assembly_id=? OR assembly_id IS NULL) GROUP BY read_date");
$b->execute([$uid, $start->format('Y-m-d'), $end->format('Y-m-d'), $aid]);
foreach ($b->fetchAll() as $r) $bible[$r['read_date']] = (int)$r['chapters'];

$data = [];
if ($period === 'year') {
  $months = [];
  $cursor = $start->modify('first day of this month');
  while ($cursor <= $end) {
    $key = $cursor->format('Y-m');
    $months[$key] = [
      'log_date' => $cursor->format('Y-m'),
      'chapters' => 0,
      'prayer_minutes' => 0,
    ];
    $cursor = $cursor->modify('+1 month');
  }
  foreach ($prayer as $d => $v) {
    $key = substr($d, 0, 7);
    if (isset($months[$key])) $months[$key]['prayer_minutes'] += (float)$v;
  }
  foreach ($bible as $d => $v) {
    $key = substr($d, 0, 7);
    if (isset($months[$key])) $months[$key]['chapters'] += (int)$v;
  }
  $data = array_values($months);
} else {
  $days = [];
  for ($d=$start; $d <= $end; $d = $d->modify('+1 day')) {
    $days[] = $d->format('Y-m-d');
  }
  foreach ($days as $d) {
    $data[] = [
      'log_date' => $d,
      'chapters' => (int)($bible[$d] ?? 0),
      'prayer_minutes' => (float)($prayer[$d] ?? 0),
    ];
  }
}

$weekStart = $period === 'week' ? $start->format('Y-m-d') : monday_of($today->format('Y-m-d'));
$goalsStmt = db()->prepare("SELECT category, label, group_title, target, unit FROM goals WHERE user_id=? AND week_start=? AND (assembly_id=? OR assembly_id IS NULL) ORDER BY group_title ASC, id ASC");
$goalsStmt->execute([$uid, $weekStart, $aid]);
$personalGoals = $goalsStmt->fetchAll();
$goalBreakdown = [];

// Always populate goal breakdown for all periods
$goalMetaStmt = db()->prepare("SELECT category, label, group_title, target, unit FROM goals
  WHERE user_id=? AND week_start=? AND (assembly_id=? OR assembly_id IS NULL) ORDER BY group_title ASC, id ASC");
$goalMetaStmt->execute([$uid, $weekStart, $aid]);
$goalMeta = $goalMetaStmt->fetchAll();

if ($goalMeta) {
  $categories = array_values(array_unique(array_map(static fn($goal) => (string)$goal['category'], $goalMeta)));
  if ($categories) {
    $in = implode(',', array_fill(0, count($categories), '?'));
    $goalDataStmt = db()->prepare("SELECT category, day, SUM(value) as total FROM daily_progress
      WHERE user_id=? AND category IN ($in) AND day BETWEEN ? AND ? AND (assembly_id=? OR assembly_id IS NULL)
      GROUP BY category, day");
    $goalDataStmt->execute(array_merge([$uid], $categories, [$start->format('Y-m-d'), $end->format('Y-m-d'), $aid]));
    foreach ($goalDataStmt->fetchAll() as $row) {
      $goalBreakdown[(string)$row['category']][(string)$row['day']] = (float)$row['total'];
    }
  }
  $personalGoals = $goalMeta;
}

$dompdfInstalled = class_exists('Dompdf\\Dompdf');

if (!$dompdfInstalled) {
  http_response_code(400);
  echo 'PDF export unavailable (DOMPDF missing).';
  exit;
}

$period_text = match($period) {
  'month' => $start->format('F Y'),
  'year' => $start->format('Y'),
  default => t('report_week_label', ['week' => (int)$start->format('W')])
};

$html = generate_professional_report_html(
  t('report_title'),
  auth_user()['name'],
  $period_text,
  $personalGoals,
  $goalBreakdown,
  $start,
  $end,
  report_next_week_note((int)$uid, $weekStart, $aid)
);

$dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="my-report-'.$period.'-'.$start->format('Y-m-d').'.pdf"');
echo $dompdf->output();
exit;
