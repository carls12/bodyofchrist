<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE goals ADD INDEX IF NOT EXISTS idx_goals_assembly (assembly_id, week_start)");
db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");
db()->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE messages ADD INDEX IF NOT EXISTS idx_messages_assembly (assembly_id, created_at)");
db()->exec("ALTER TABLE calendar_events ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE calendar_events ADD INDEX IF NOT EXISTS idx_calendar_events_assembly (assembly_id, date)");

$uid = auth_user()['id'];
$today = now_ymd();
$weekStart = monday_of($today);
$weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
$aid = active_assembly_id();

$loc = $_SESSION['locale'] ?? 'de';
$labels = [
  'de' => ['Gebet', 'Bibel'],
  'en' => ['Prayer', 'Bible'],
  'fr' => ['Priere', 'Bible'],
];
$units = [
  'de' => ['min', 'Kapitel'],
  'en' => ['min', 'chapters'],
  'fr' => ['min', 'chapitres'],
];
$lab = $labels[$loc] ?? $labels['de'];
$uni = $units[$loc] ?? $units['de'];
$defaults = [
  ['category' => 'prayer_minutes', 'label' => $lab[0], 'target' => 300, 'unit' => $uni[0]],
  ['category' => 'bible_chapters', 'label' => $lab[1], 'target' => 7, 'unit' => $uni[1]],
];
$existingStmt = db()->prepare("SELECT category FROM goals WHERE user_id=? AND week_start=? AND (assembly_id=? OR assembly_id IS NULL)");
$existingStmt->execute([$uid, $weekStart, $aid]);
$existingCats = array_map(fn($r) => $r['category'], $existingStmt->fetchAll());
$ins = db()->prepare("INSERT INTO goals(user_id,assembly_id,week_start,category,label,target,unit,created_at,updated_at)
  VALUES (?,?,?,?,?,?,?,NOW(),NOW())");
foreach ($defaults as $d) {
  if (!in_array($d['category'], $existingCats, true)) {
    $ins->execute([$uid, $aid, $weekStart, $d['category'], $d['label'], $d['target'], $d['unit']]);
  }
}

$stmt = db()->prepare("SELECT category, SUM(value) as total FROM daily_progress
  WHERE user_id=? AND day BETWEEN ? AND ? AND (assembly_id=? OR assembly_id IS NULL) GROUP BY category");
$stmt->execute([$uid, $weekStart, $weekEnd, $aid]);
$sums = [];
foreach ($stmt->fetchAll() as $r) $sums[$r['category']] = (float)$r['total'];

$g = db()->prepare("SELECT * FROM goals WHERE user_id=? AND week_start=? AND (assembly_id=? OR assembly_id IS NULL) ORDER BY id DESC");
$g->execute([$uid, $weekStart, $aid]);
$goals = $g->fetchAll();

$asm = db()->prepare("SELECT a.*, am.role FROM assembly_members am
  JOIN assemblies a ON a.id=am.assembly_id
  WHERE am.user_id=? AND am.active=1 AND am.status='active' ORDER BY am.id DESC LIMIT 1");
$asm->execute([$uid]);
$myAssembly = $asm->fetch();

$monthLabels = [];
$monthKeys = [];
for ($i = 5; $i >= 0; $i--) {
  $dt = (new DateTimeImmutable('first day of this month'))->modify("-{$i} months");
  $monthKeys[] = $dt->format('Y-m');
  $monthLabels[] = $dt->format('M');
}

$series = [];
$hourCats = ['gebet','prayer_minutes'];
// Map categories to goal labels for display
$goalLabels = [];
$goalMapStmt = db()->prepare("SELECT category, label FROM goals WHERE user_id=? AND week_start=? AND (assembly_id=? OR assembly_id IS NULL)");
$goalMapStmt->execute([$uid, $weekStart, $aid]);
foreach ($goalMapStmt->fetchAll() as $r) {
  $goalLabels[$r['category']] = $r['label'];
}
$stmt = db()->prepare("SELECT DATE_FORMAT(day, '%Y-%m') AS ym, category, SUM(value) AS total
  FROM daily_progress WHERE user_id=? AND day >= ? AND (assembly_id=? OR assembly_id IS NULL) GROUP BY ym, category ORDER BY ym ASC");
$from = (new DateTimeImmutable('first day of this month'))->modify('-5 months')->format('Y-m-01');
$stmt->execute([$uid, $from, $aid]);
foreach ($stmt->fetchAll() as $r) {
  $ym = $r['ym'];
  if (!in_array($ym, $monthKeys, true)) continue;
  $cat = $r['category'];
  if (!isset($series[$cat])) $series[$cat] = array_fill(0, count($monthKeys), 0);
  $idx = array_search($ym, $monthKeys, true);
  $val = (float)$r['total'];
  if (in_array($cat, $hourCats, true)) {
    $val = $val / 60.0;
  }
  $series[$cat][$idx] = $val;
}

$unread = db()->prepare("SELECT COUNT(*) AS c FROM messages WHERE to_user_id=? AND is_read=0 AND (assembly_id=? OR assembly_id IS NULL)");
$unread->execute([$uid, $aid]);
$unreadCount = (int)($unread->fetch()['c'] ?? 0);

$groupsStmt = db()->prepare("SELECT a.id FROM assemblies a
  JOIN assembly_members am ON am.assembly_id=a.id
  WHERE am.user_id=? AND am.status='active'");
$groupsStmt->execute([$uid]);
$groupIds = array_map(fn($r)=> (int)$r['id'], $groupsStmt->fetchAll());
$in = $groupIds ? implode(',', array_fill(0, count($groupIds), '?')) : '';
$eventsSql = "SELECT id, title, date, time, type FROM calendar_events
  WHERE (user_id=? OR (group_id IS NOT NULL AND group_id IN ($in)))
  AND (assembly_id=? OR assembly_id IS NULL)
  AND date >= ? ORDER BY date ASC, time ASC LIMIT 5";
$eventsStmt = db()->prepare($groupIds ? $eventsSql : "SELECT id, title, date, time, type FROM calendar_events
  WHERE user_id=? AND (assembly_id=? OR assembly_id IS NULL) AND date >= ? ORDER BY date ASC, time ASC LIMIT 5");
$eventsStmt->execute($groupIds ? array_merge([$uid], $groupIds, [$aid, $today]) : [$uid, $aid, $today]);
$upcomingEvents = $eventsStmt->fetchAll();

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('dashboard_week', ['start' => $weekStart, 'end' => $weekEnd])) ?></div>
    <h2 class="h4 mb-1"><?= e(t('dashboard_welcome', ['name' => auth_user()['name']])) ?></h2>
    <div class="text-muted"><?= e(t('dashboard_sub')) ?></div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-primary" href="<?= e(base_url('goals')) ?>"><?= e(t('nav_goals')) ?></a>
    <a class="btn btn-primary" href="<?= e(base_url('progress')) ?>"><?= e(t('nav_progress')) ?></a>
  </div>
</div>

<?php if ($unreadCount > 0): ?>
  <div class="alert alert-info d-flex align-items-center justify-content-between">
    <div><?= e(t('dashboard_unread', ['count' => (int)$unreadCount])) ?></div>
    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('chat')) ?>"><?= e(t('dashboard_to_chat')) ?></a>
  </div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small"><?= e(t('dashboard_verse')) ?></div>
            <div class="h5 mb-2">"<?= e(t('dashboard_verse_text')) ?>"</div>
            <div class="text-muted small"><?= e(t('dashboard_verse_ref')) ?></div>
          </div>
          <span class="badge-soft"><?= e(t('dashboard_today')) ?></span>
        </div>
        <div class="mt-3 d-flex flex-wrap gap-2">
          <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('bible')) ?>"><?= e(t('nav_bible')) ?></a>
          <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('goals')) ?>"><?= e(t('dashboard_plan_goals')) ?></a>
          <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('assemblies')) ?>"><?= e(t('nav_groups')) ?></a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <?php if ($myAssembly): ?>
      <div class="card h-100">
        <div class="card-body d-flex flex-column justify-content-between gap-2">
          <div>
            <div class="text-muted small"><?= e(t('dashboard_assembly')) ?></div>
            <div class="h5 mb-1"><?= e($myAssembly['name']) ?></div>
            <div class="text-muted small">Rolle: <span class="fw-semibold"><?= e($myAssembly['role']) ?></span></div>
          </div>
          <div>
            <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('assemblies/show?id='.(int)$myAssembly['id'])) ?>">Oeffnen</a>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="card h-100">
        <div class="card-body d-flex flex-column justify-content-between gap-2">
          <div>
            <div class="text-muted small"><?= e(t('nav_groups')) ?></div>
            <div class="h5 mb-1"><?= e(t('dashboard_no_group')) ?></div>
            <div class="text-muted small"><?= e(t('dashboard_no_group_sub')) ?></div>
          </div>
          <div>
            <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('assemblies')) ?>"><?= e(t('dashboard_join_create')) ?></a>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-2">
  <div class="fw-semibold"><?= e(t('dashboard_goals')) ?></div>
  <a class="small text-decoration-none" href="<?= e(base_url('goals')) ?>"><?= e(t('dashboard_all_goals')) ?></a>
</div>

<div class="row g-3">
<?php if (!$goals): ?>
  <div class="col-12">
    <div class="alert alert-info"><?= e(t('dashboard_no_goals')) ?> <a href="<?= e(base_url('goals')) ?>"><?= e(t('dashboard_create_goals')) ?></a></div>
  </div>
<?php else: ?>
  <?php foreach ($goals as $goal):
    $cat = $goal['category'];
    $actual = (float)($sums[$cat] ?? 0);
    $target = (float)$goal['target'];
    $progress = $target > 0 ? min(100, (int)round(($actual/$target)*100)) : 0;
  ?>
    <div class="col-md-6 col-lg-3">
      <div class="card h-100" data-goal-card data-category="<?= e($cat) ?>" data-target="<?= e($target) ?>">
        <div class="card-body">
          <div class="text-muted small" data-goal-label><?= e($goal['label']) ?></div>
          <div class="h5 mb-2">
            <span data-goal-actual><?= e(rtrim(rtrim(number_format($actual,2,'.',''), '0'), '.')) ?></span>
            <span data-goal-unit><?= e(compact_goal_unit((string)$goal['unit'])) ?></span>
          </div>
          <div class="progress" role="progressbar" aria-valuenow="<?= (int)$progress ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" data-goal-bar style="width: <?= (int)$progress ?>%"></div>
          </div>
          <div class="text-muted small mt-2"><span data-goal-target><?= e($goal['target']) ?></span> <?= e(compact_goal_unit((string)$goal['unit'])) ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<div class="card mt-4">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="fw-semibold"><?= e(t('nav_calendar')) ?></div>
      <a class="small text-decoration-none" href="<?= e(base_url('calendar')) ?>"><?= e(t('calendar_title')) ?></a>
    </div>
    <?php if (!$upcomingEvents): ?>
      <div class="text-muted"><?= e(t('calendar_no_events')) ?></div>
    <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($upcomingEvents as $e): ?>
          <div class="list-group-item d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-semibold"><?= e($e['title']) ?></div>
              <div class="small text-muted"><?= e($e['date']) ?> <?= e($e['time'] ?? '') ?></div>
            </div>
            <div class="badge bg-light text-dark text-uppercase"><?= e($e['type']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card mt-4">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="fw-semibold"><?= e(t('dashboard_chart')) ?></div>
      <div class="text-muted small"><?= e(t('dashboard_chart_sub')) ?></div>
    </div>
    <?php if (!$series): ?>
      <div class="text-muted"><?= e(t('dashboard_no_chart')) ?></div>
    <?php else: ?>
      <svg viewBox="0 0 600 220" width="100%" height="220" role="img" aria-label="<?= e(t('dashboard_chart_aria')) ?>">
        <defs>
          <linearGradient id="gridFade" x1="0" x2="0" y1="0" y2="1">
            <stop offset="0" stop-color="rgba(30,58,95,.15)"/>
            <stop offset="1" stop-color="rgba(30,58,95,0)"/>
          </linearGradient>
        </defs>
        <rect x="0" y="0" width="600" height="220" fill="url(#gridFade)"/>
        <?php
          $max = 0;
          foreach ($series as $vals) foreach ($vals as $v) if ($v > $max) $max = $v;
          $max = $max > 0 ? $max : 1;
          $colors = ['#1E3A5F', '#D4A574', '#274a78', '#b68457'];
          $i = 0;
          $ticks = 4;
        ?>
        <?php for ($t=0; $t<=$ticks; $t++): ?>
          <?php
            $val = ($max / $ticks) * $t;
            $y = 190 - ($val / $max) * 140;
          ?>
          <line x1="40" y1="<?= $y ?>" x2="560" y2="<?= $y ?>" stroke="rgba(30,58,95,.08)"/>
          <text x="10" y="<?= $y+4 ?>" font-size="11" fill="#5b6b7a"><?= e(number_format($val,1)) ?>h</text>
        <?php endfor; ?>
        <?php foreach ($series as $cat => $vals): ?>
          <?php
            $pts = [];
            foreach ($vals as $idx => $v) {
              $x = 40 + ($idx * (520 / max(1, count($monthKeys) - 1)));
              $y = 190 - ($v / $max) * 140;
              $pts[] = $x . ',' . $y;
            }
            $color = $colors[$i % count($colors)];
            $lastX = 40 + ((count($monthKeys) - 1) * (520 / max(1, count($monthKeys) - 1)));
            $lastY = 190 - ((float)$vals[count($vals) - 1] / $max) * 140;
            $label = $goalLabels[$cat] ?? $cat;
            $i++;
          ?>
          <polyline fill="none" stroke="<?= $color ?>" stroke-width="3" points="<?= e(implode(' ', $pts)) ?>"/>
          <text x="<?= $lastX + 6 ?>" y="<?= $lastY + 4 ?>" font-size="11" fill="<?= $color ?>"><?= e($label) ?></text>
        <?php endforeach; ?>
        <?php foreach ($monthLabels as $idx => $label): ?>
          <?php $x = 40 + ($idx * (520 / max(1, count($monthLabels) - 1))); ?>
          <text x="<?= $x ?>" y="210" text-anchor="middle" font-size="11" fill="#5b6b7a"><?= e($label) ?></text>
        <?php endforeach; ?>
      </svg>
    <?php endif; ?>
  </div>
</div>

<?php if (is_main_admin()): ?>
  <div class="card mt-4">
    <div class="card-body">
      <div class="fw-semibold"><?= e(t('admin_block_title')) ?></div>
      <div class="text-muted small mb-2"><?= e(t('admin_block_sub')) ?></div>
      <a class="btn btn-sm btn-dark" href="<?= e(base_url('admin/users')) ?>"><?= e(t('admin_manage_users')) ?></a>
    </div>
  </div>
<?php endif; ?>

<script>
  (function(){
    var lastSync = 0;
    function fmtNum(v){
      var n = Math.round((Number(v) || 0) * 100) / 100;
      var s = n.toString();
      return s.replace(/\.0+$/,'').replace(/(\.\d)0$/,'$1');
    }
    function updateCards(data){
      if (!data || !data.sums) return;
      var sums = data.sums || {};
      document.querySelectorAll('[data-goal-card]').forEach(function(card){
        var cat = card.getAttribute('data-category');
        var target = parseFloat(card.getAttribute('data-target') || '0');
        var actual = parseFloat(sums[cat] || 0);
        var actualEl = card.querySelector('[data-goal-actual]');
        var bar = card.querySelector('[data-goal-bar]');
        if (actualEl) actualEl.textContent = fmtNum(actual);
        if (bar && target > 0) {
          var pct = Math.min(100, Math.round((actual / target) * 100));
          bar.style.width = pct + '%';
        }
      });
    }
    function fetchProgress(){
      fetch('<?= e(base_url('action/fetch-goal-progress')) ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r){ return r.json(); })
        .then(function(data){ updateCards(data); lastSync = Date.now(); })
        .catch(function(){});
    }
    fetchProgress();
    setInterval(fetchProgress, 15000);
  })();
</script>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
