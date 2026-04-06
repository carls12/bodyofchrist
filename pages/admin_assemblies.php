<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

if (!is_regional_leader() && !is_main_admin()) { http_response_code(403); die('Forbidden'); }

db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE assembly_members MODIFY COLUMN status ENUM('pending','invited','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL");
db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS country VARCHAR(120) NULL");
db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");
db()->exec("ALTER TABLE bible_readings ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE bible_readings ADD INDEX IF NOT EXISTS idx_bible_assembly (assembly_id, read_date)");
$countries = country_list();

$weekStart = monday_of(now_ymd());
$weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');

$q = trim($_GET['q'] ?? '');
$params = [];
$where = '';
if ($q !== '' && is_main_admin()) {
  $where = "WHERE a.name LIKE ? OR a.region LIKE ? OR a.country LIKE ?";
  $params = [$q.'%', $q.'%', $q.'%'];
}
if (is_regional_leader() && !is_main_admin()) {
  $where = $where ? ($where . " AND a.region=?") : "WHERE a.region=?";
  $params[] = user_region();
}
$stmt = db()->prepare("SELECT a.*, u.name AS leader_name
  FROM assemblies a
  LEFT JOIN users u ON u.id=a.leader_id
  $where
  ORDER BY a.name ASC");
$stmt->execute($params);
$groups = $stmt->fetchAll();

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
  <div>
    <div class="text-muted small"><?= e(t('nav_admin')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('admin_assemblies')) ?></h2>
    <div class="text-muted">
      <?= e(t('admin_assemblies_sub')) ?>
      <?php if (is_regional_leader() && user_region()): ?> - <?= e(t('admin_region')) ?>: <?= e(user_region()) ?><?php endif; ?>
      - <?= e($weekStart) ?> - <?= e($weekEnd) ?>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (is_main_admin()): ?>
      <form method="get" action="<?= e(base_url('admin/assemblies')) ?>" class="m-0">
        <input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('admin_search_assembly')) ?>">
      </form>
    <?php endif; ?>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('action/admin-download-totals?pdf=1')) ?>"><?= e(t('admin_download_pdf')) ?></a>
    <?php if (is_main_admin()): ?>
      <form method="post" action="<?= e(base_url('action/set-global-goal')) ?>" class="m-0">
        <input type="hidden" name="category" value="tracktate">
        <input type="hidden" name="label" value="Tracktate">
        <input type="hidden" name="target" value="35">
        <input type="hidden" name="unit" value="pro Woche">
        <button class="btn btn-primary btn-sm"><?= e(t('admin_global_goal_btn')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!$groups): ?>
  <div class="text-muted"><?= e(t('groups_none')) ?></div>
<?php else: ?>
  <div class="accordion" id="adminAssemblies">
    <?php foreach ($groups as $idx => $g): ?>
      <?php
        $membersStmt = db()->prepare("SELECT u.id,u.name,u.email
          FROM assembly_members am
          JOIN users u ON u.id=am.user_id
          WHERE am.assembly_id=? AND am.status='active' ORDER BY u.name ASC");
        $membersStmt->execute([(int)$g['id']]);
        $members = $membersStmt->fetchAll();
        $memberIds = array_map(fn($m) => (int)$m['id'], $members);
        $bibleMap = [];
        $trackMap = [];
        if ($memberIds) {
          $in = implode(',', array_fill(0, count($memberIds), '?'));
          $b = db()->prepare("SELECT user_id, COUNT(*) AS chapters
            FROM bible_readings WHERE user_id IN ($in) AND read_date BETWEEN ? AND ?
            AND (assembly_id=? OR assembly_id IS NULL)
            GROUP BY user_id");
          $b->execute(array_merge($memberIds, [$weekStart, $weekEnd, (int)$g['id']]));
          foreach ($b->fetchAll() as $row) $bibleMap[(int)$row['user_id']] = (int)$row['chapters'];

          $t = db()->prepare("SELECT user_id, SUM(value) AS total
            FROM daily_progress WHERE user_id IN ($in) AND category='tracktate' AND day BETWEEN ? AND ?
            AND (assembly_id=? OR assembly_id IS NULL)
            GROUP BY user_id");
          $t->execute(array_merge($memberIds, [$weekStart, $weekEnd, (int)$g['id']]));
          foreach ($t->fetchAll() as $row) $trackMap[(int)$row['user_id']] = (float)$row['total'];
        }
      ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="heading<?= (int)$g['id'] ?>">
          <button class="accordion-button <?= $idx===0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= (int)$g['id'] ?>">
            <?= e($g['name']) ?> - <?= e($g['type']) ?> - <?= e($g['leader_name'] ?? '-') ?><?php if ($g['region'] || $g['country']): ?> - <?= e(trim(($g['region'] ?? '') . ' / ' . ($g['country'] ?? ''), ' /')) ?><?php endif; ?>
          </button>
        </h2>
        <div id="collapse<?= (int)$g['id'] ?>" class="accordion-collapse collapse <?= $idx===0 ? 'show' : '' ?>" data-bs-parent="#adminAssemblies">
          <div class="accordion-body">
            <?php if (is_main_admin()): ?>
              <form method="post" action="<?= e(base_url('action/update-assembly-admin')) ?>" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="assembly_id" value="<?= (int)$g['id'] ?>">
                <input type="hidden" name="leader_id" value="<?= (int)$g['leader_id'] ?>" id="leaderId<?= (int)$g['id'] ?>">
                <div class="col-md-4">
                  <label class="form-label"><?= e(t('admin_region')) ?></label>
                  <input class="form-control form-control-sm" name="region" value="<?= e($g['region'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label"><?= e(t('admin_country')) ?></label>
                  <select class="form-select form-select-sm" name="country">
                    <option value=""><?= e(t('admin_country')) ?></option>
                    <?php foreach ($countries as $c): ?>
                      <option value="<?= e($c) ?>" <?= ($g['country'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label"><?= e(t('admin_assign_leader')) ?></label>
                  <input class="form-control form-control-sm leader-search" data-target="<?= (int)$g['id'] ?>" placeholder="<?= e(t('admin_search_user')) ?>">
                  <div class="list-group list-group-flush leader-results" id="leaderResults<?= (int)$g['id'] ?>" style="display:none;"></div>
                </div>
                <div class="col-md-3">
                  <button class="btn btn-sm btn-outline-primary"><?= e(t('admin_save')) ?></button>
                </div>
              </form>
            <?php endif; ?>
            <?php if (!$members): ?>
              <div class="text-muted"><?= e(t('groups_none')) ?></div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th><?= e(t('name')) ?></th>
                      <th><?= e(t('email')) ?></th>
                      <th class="text-end"><?= e(t('my_reports_bible')) ?></th>
                      <th class="text-end"><?= e(t('progress_default_tracktate')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($members as $m): ?>
                      <tr>
                        <td><?= e($m['name']) ?></td>
                        <td><?= e($m['email']) ?></td>
                        <td class="text-end"><?= e($bibleMap[(int)$m['id']] ?? 0) ?></td>
                        <td class="text-end"><?= e($trackMap[(int)$m['id']] ?? 0) ?></td>
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

<?php include __DIR__ . '/_layout_bottom.php'; ?>
<script>
  document.querySelectorAll('.leader-search').forEach(function(input){
    input.addEventListener('input', function(){
      var q = input.value.trim();
      var targetId = input.getAttribute('data-target');
      var results = document.getElementById('leaderResults' + targetId);
      if (!results) return;
      if (q.length < 2) {
        results.style.display = 'none';
        results.innerHTML = '';
        return;
      }
      fetch('<?= e(base_url('action/search-users-admin')) ?>?q=' + encodeURIComponent(q), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(r => r.json()).then(function(res){
        results.innerHTML = '';
        if (!res || !res.items || res.items.length === 0) {
          results.style.display = 'none';
          return;
        }
        res.items.forEach(function(u){
          var row = document.createElement('div');
          row.className = 'list-group-item d-flex align-items-center justify-content-between gap-2';
          row.innerHTML = '<div><div class="fw-semibold">'+u.name+'</div><div class="text-muted small">'+(u.email || '')+'</div></div>';
          var btn = document.createElement('button');
          btn.className = 'btn btn-sm btn-outline-primary';
          btn.textContent = '<?= e(t('admin_set_leader')) ?>';
          btn.addEventListener('click', function(){
            var hidden = document.getElementById('leaderId' + targetId);
            if (hidden) hidden.value = u.id;
            input.value = u.name + ' (' + (u.email || '') + ')';
            results.style.display = 'none';
          });
          row.appendChild(btn);
          results.appendChild(row);
        });
        results.style.display = '';
      });
    });
  });
</script>
