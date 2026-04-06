<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

if (!auth_user()['is_leader']) { http_response_code(403); die('Forbidden'); }

db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS type ENUM('discipleship','assembly') NOT NULL DEFAULT 'assembly'");
db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");
db()->exec("ALTER TABLE bible_readings ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE bible_readings ADD INDEX IF NOT EXISTS idx_bible_assembly (assembly_id, read_date)");
db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE goals ADD INDEX IF NOT EXISTS idx_goals_assembly (assembly_id, week_start)");

$me = auth_user()['id'];
$groupsStmt = db()->prepare("SELECT id,name FROM assemblies WHERE leader_id=? AND type='discipleship' ORDER BY name ASC");
$groupsStmt->execute([$me]);
$groups = $groupsStmt->fetchAll();

$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : (int)($groups[0]['id'] ?? 0);

$today = now_ymd();
$weekStart = monday_of($today);
$weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
$days = [];
for ($i=0;$i<7;$i++) $days[] = (new DateTimeImmutable($weekStart))->modify("+$i days")->format('Y-m-d');

$members = [];
if ($groupId > 0) {
  $m = db()->prepare("SELECT u.id,u.name FROM assembly_members am
    JOIN users u ON u.id=am.user_id
    WHERE am.assembly_id=? AND am.active=1 AND am.status='active' ORDER BY u.name ASC");
  $m->execute([$groupId]);
  $members = $m->fetchAll();
}

$prayer = [];
$bible = [];
$goalsByUser = [];
$goalTotals = [];
$goalDaily = [];
if ($members) {
  $userIds = array_map(fn($r)=> (int)$r['id'], $members);
  $in = implode(',', array_fill(0, count($userIds), '?'));

  $q = db()->prepare("SELECT user_id, day, SUM(value) as minutes
    FROM daily_progress
    WHERE category IN ('prayer_minutes','gebet') AND user_id IN ($in) AND day BETWEEN ? AND ?
    AND (assembly_id=? OR assembly_id IS NULL)
    GROUP BY user_id, day");
  $params = array_merge($userIds, [$weekStart, $weekEnd, $groupId]);
  $q->execute($params);
  foreach ($q->fetchAll() as $r) {
    $prayer[(int)$r['user_id']][$r['day']] = (float)$r['minutes'];
  }

  $b = db()->prepare("SELECT user_id, read_date, COUNT(*) as chapters
    FROM bible_readings
    WHERE user_id IN ($in) AND read_date BETWEEN ? AND ?
    AND (assembly_id=? OR assembly_id IS NULL)
    GROUP BY user_id, read_date");
  $b->execute($params);
  foreach ($b->fetchAll() as $r) {
    $bible[(int)$r['user_id']][$r['read_date']] = (int)$r['chapters'];
  }

  $g = db()->prepare("SELECT user_id, category, label, target, unit
    FROM goals WHERE user_id IN ($in) AND week_start=? AND (assembly_id=? OR assembly_id IS NULL) ORDER BY user_id ASC, id ASC");
  $g->execute(array_merge($userIds, [$weekStart, $groupId]));
  foreach ($g->fetchAll() as $r) {
    $goalsByUser[(int)$r['user_id']][] = $r;
  }

  $gs = db()->prepare("SELECT user_id, category, SUM(value) as total
    FROM daily_progress WHERE user_id IN ($in) AND day BETWEEN ? AND ?
    AND (assembly_id=? OR assembly_id IS NULL)
    GROUP BY user_id, category");
  $gs->execute($params);
  foreach ($gs->fetchAll() as $r) {
    $goalTotals[(int)$r['user_id']][$r['category']] = (float)$r['total'];
  }

  $gd = db()->prepare("SELECT user_id, category, day, SUM(value) as total
    FROM daily_progress WHERE user_id IN ($in) AND day BETWEEN ? AND ?
    AND (assembly_id=? OR assembly_id IS NULL)
    GROUP BY user_id, category, day");
  $gd->execute($params);
  foreach ($gd->fetchAll() as $r) {
    $goalDaily[(int)$r['user_id']][$r['category']][$r['day']] = (float)$r['total'];
  }
}

$totalDisciples = count($members);
$totalMinutes = 0.0;
$engaged = 0;
foreach ($members as $m) {
  $uid = (int)$m['id'];
  $userTotal = 0.0;
  foreach ($days as $d) {
    $userTotal += (float)($prayer[$uid][$d] ?? 0);
  }
  if ($userTotal > 0) $engaged++;
  $totalMinutes += $userTotal;
}
$avgPrayer = $totalDisciples > 0 ? round($totalMinutes / $totalDisciples, 1) : 0;
$engagement = $totalDisciples > 0 ? round(($engaged / $totalDisciples) * 100, 0) : 0;

function label_day_i18n(string $ymd): string {
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
    <div class="text-muted small"><?= e(t('nav_reports')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('reports_title')) ?></h2>
    <div class="text-muted"><?= e(t('reports_sub')) ?></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
      <label class="form-label m-0"><?= e(t('reports_select_group')) ?></label>
      <select class="form-select" name="group_id" style="max-width:320px">
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int)$g['id'] ?>" <?= (int)$g['id']===$groupId ? 'selected' : '' ?>><?= e($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline-primary"><?= e(t('bible_show')) ?></button>
      <div class="text-muted small ms-auto"><?= e(t('reports_week', ['start' => $weekStart, 'end' => $weekEnd])) ?></div>
    </form>
    <?php if ($groupId > 0 && class_exists('Dompdf\\Dompdf')): ?>
      <div class="mt-2">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('action/download-report?group_id='.(int)$groupId.'&pdf=1')) ?>"><?= e(t('reports_download_pdf')) ?></a>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <div class="text-muted small"><?= e(t('reports_total_disciples')) ?></div>
        <div class="h4 mb-0"><?= (int)$totalDisciples ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <div class="text-muted small"><?= e(t('reports_avg_prayer')) ?></div>
        <div class="h4 mb-0"><?= e(number_format($avgPrayer,1)) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <div class="text-muted small"><?= e(t('reports_engagement')) ?></div>
        <div class="h4 mb-0"><?= (int)$engagement ?>%</div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="fw-semibold mb-2"><?= e(t('reports_table_prayer')) ?></div>
    <?php if (!$members): ?>
      <div class="text-muted"><?= e(t('groups_none')) ?></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>#</th><th><?= e(t('name')) ?></th>
              <?php foreach ($days as $d): ?><th><?= e(label_day_i18n($d)) ?></th><?php endforeach; ?>
              <th><?= e(t('reports_total_prayer')) ?></th>
              <th><?= e(t('reports_total_bible')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php $i=1; foreach ($members as $m): $uid=(int)$m['id']; $weekTotal=0; $bibleTotal=0; ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= e($m['name']) ?></td>
                <?php foreach ($days as $d): $mins=(float)($prayer[$uid][$d] ?? 0); $weekTotal += $mins; ?>
                  <td><?= e(rtrim(rtrim(number_format($mins,1,'.',''),'0'),'.')) ?></td>
                <?php endforeach; ?>
                <?php foreach ($days as $d): $bibleTotal += (int)($bible[$uid][$d] ?? 0); endforeach; ?>
                <td><?= e(rtrim(rtrim(number_format($weekTotal,1,'.',''),'0'),'.')) ?></td>
                <td><?= (int)$bibleTotal ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card mt-3">
  <div class="card-body">
    <div class="fw-semibold mb-2"><?= e(t('reports_goals_title')) ?></div>
    <?php if (!$members): ?>
      <div class="text-muted"><?= e(t('groups_none')) ?></div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($members as $m): $uid=(int)$m['id']; $userGoals=$goalsByUser[$uid] ?? []; ?>
          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-body">
                <div class="fw-semibold mb-2"><?= e($m['name']) ?></div>
                <?php if (!$userGoals): ?>
                  <div class="text-muted"><?= e(t('reports_no_goals')) ?></div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                      <thead>
                        <tr>
                          <th><?= e(t('goals_table_label')) ?></th>
                          <?php foreach ($days as $d): ?><th><?= e(label_day_i18n($d)) ?></th><?php endforeach; ?>
                          <th><?= e(t('goals_total')) ?></th>
                          <th><?= e(t('goals_target')) ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($userGoals as $g): 
                          $cat = $g['category'];
                          $actual = (float)($goalTotals[$uid][$cat] ?? 0);
                        ?>
                          <tr>
                            <td><?= e($g['label']) ?><div class="text-muted small"><code><?= e($cat) ?></code></div></td>
                            <?php foreach ($days as $d): ?>
                              <td><?= e(rtrim(rtrim(number_format((float)($goalDaily[$uid][$cat][$d] ?? 0), 2, '.', ''), '0'), '.')) ?></td>
                            <?php endforeach; ?>
                            <td><?= e(rtrim(rtrim(number_format($actual, 2, '.', ''), '0'), '.')) ?> <?= e($g['unit']) ?></td>
                            <td><?= e($g['target']) ?> <?= e($g['unit']) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
