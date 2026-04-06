<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

if (!is_national_leader() && !is_main_admin()) { http_response_code(403); die('Forbidden'); }

db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL");
db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS country VARCHAR(120) NULL");
db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE assembly_members MODIFY COLUMN status ENUM('pending','invited','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");
db()->exec("ALTER TABLE bible_readings ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE bible_readings ADD INDEX IF NOT EXISTS idx_bible_assembly (assembly_id, read_date)");

$weekStart = monday_of(now_ymd());
$weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');

$q = trim($_GET['q'] ?? '');
$params = [];
$where = '';
if ($q !== '' && is_main_admin()) {
  $where = "WHERE a.name LIKE ? OR a.region LIKE ? OR a.country LIKE ?";
  $params = [$q.'%', $q.'%', $q.'%'];
}
if (is_national_leader() && !is_main_admin()) {
  $country = user_country();
  $where = $where ? ($where . " AND a.country=?") : "WHERE a.country=?";
  $params[] = $country;
}

$stmt = db()->prepare("SELECT a.*, u.name AS leader_name
  FROM assemblies a
  LEFT JOIN users u ON u.id=a.leader_id
  $where
  ORDER BY a.country ASC, a.region ASC, a.name ASC");
$stmt->execute($params);
$groups = $stmt->fetchAll();

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
  <div>
    <div class="text-muted small"><?= e(t('nav_admin')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('admin_national_reports')) ?></h2>
    <div class="text-muted">
      <?= e(t('admin_national_sub')) ?>
      <?php if (is_national_leader() && user_country()): ?> - <?= e(t('admin_country')) ?>: <?= e(user_country()) ?><?php endif; ?>
      - <?= e($weekStart) ?> - <?= e($weekEnd) ?>
    </div>
  </div>
  <?php if (is_main_admin()): ?>
    <form method="get" action="<?= e(base_url('admin/national')) ?>" class="m-0">
      <input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('admin_search_assembly')) ?>">
    </form>
  <?php endif; ?>
</div>

<?php if (is_national_leader() && !is_main_admin() && !user_country()): ?>
  <div class="text-muted"><?= e(t('admin_country_missing')) ?></div>
<?php elseif (!$groups): ?>
  <div class="text-muted"><?= e(t('groups_none')) ?></div>
<?php else: ?>
  <div class="accordion" id="adminNationalAssemblies">
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
        <div id="collapse<?= (int)$g['id'] ?>" class="accordion-collapse collapse <?= $idx===0 ? 'show' : '' ?>" data-bs-parent="#adminNationalAssemblies">
          <div class="accordion-body">
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
