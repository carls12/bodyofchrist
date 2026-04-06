<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

if (!is_regional_leader() && !is_main_admin()) { http_response_code(403); die('Forbidden'); }
if (!class_exists('Dompdf\\Dompdf')) { http_response_code(400); die('PDF not available'); }

db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE assembly_members MODIFY COLUMN status ENUM('pending','invited','active','inactive') NOT NULL DEFAULT 'active'");
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
db()->exec("CREATE TABLE IF NOT EXISTS daily_progress (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  assembly_id INT UNSIGNED NULL,
  day DATE NOT NULL,
  category VARCHAR(120) NOT NULL,
  value DECIMAL(10,2) NOT NULL DEFAULT 0,
  note TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  INDEX (user_id, day),
  INDEX (assembly_id, day),
  INDEX (category),
  CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
db()->exec("ALTER TABLE prayer_logs ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE prayer_logs ADD INDEX IF NOT EXISTS idx_prayer_logs_assembly (assembly_id, date)");
db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");

$today = now_ymd();
$weekStart = monday_of($today);
$weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');

$params = [];
$where = "WHERE a.type='assembly'";
if (is_regional_leader() && !is_main_admin()) {
  $where .= " AND a.region=?";
  $params[] = user_region();
}
$stmt = db()->prepare("SELECT a.*, u.name AS leader_name
  FROM assemblies a
  LEFT JOIN users u ON u.id=a.leader_id
  $where
  ORDER BY a.name ASC");
$stmt->execute($params);
$groups = $stmt->fetchAll();

ob_start();
?>
<!doctype html>
<html><head><meta charset="utf-8"><title><?= e(t('admin_download_pdf')) ?></title>
<style>
body{ font-family: DejaVu Sans, Arial, sans-serif; font-size:12px; }
h1{ font-size:18px; margin:0 0 8px 0; }
h2{ font-size:14px; margin:16px 0 6px 0; }
.muted{ color:#666; margin-bottom:12px; }
table{ width:100%; border-collapse:collapse; margin-bottom:10px; }
th,td{ border:1px solid #ddd; padding:6px; text-align:left; }
th{ background:#f2f2f2; }
td.num{ text-align:right; }
</style></head>
<body>
<h1><?= e(t('admin_download_pdf')) ?></h1>
<div class="muted"><?= e($weekStart) ?> - <?= e($weekEnd) ?></div>

<?php
$grandPrayer = 0.0;
$grandTrack = 0.0;
?>
<table>
  <thead>
    <tr>
      <th><?= e(t('groups_title')) ?></th>
      <th><?= e(t('admin_region')) ?></th>
      <th><?= e(t('admin_leader')) ?></th>
      <th class="num"><?= e(t('admin_total_prayer')) ?></th>
      <th class="num"><?= e(t('admin_total_tracktate')) ?></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($groups as $g): ?>
    <?php
      $membersStmt = db()->prepare("SELECT u.id,u.name,u.email
        FROM assembly_members am
        JOIN users u ON u.id=am.user_id
        WHERE am.assembly_id=? AND am.status='active'");
      $membersStmt->execute([(int)$g['id']]);
      $members = $membersStmt->fetchAll();
      $memberIds = array_map(fn($m) => (int)$m['id'], $members);
      $prayerTotal = 0.0;
      $trackTotal = 0.0;
      if ($memberIds) {
        $in = implode(',', array_fill(0, count($memberIds), '?'));
        $p = db()->prepare("SELECT SUM(COALESCE(seconds, minutes*60)) AS total
          FROM prayer_logs WHERE user_id IN ($in) AND date BETWEEN ? AND ?
          AND (assembly_id=? OR assembly_id IS NULL)");
        $p->execute(array_merge($memberIds, [$weekStart, $weekEnd, (int)$g['id']]));
        $prayerTotal = ((int)($p->fetch()['total'] ?? 0)) / 60.0;

        $t = db()->prepare("SELECT SUM(value) AS total
          FROM daily_progress WHERE user_id IN ($in) AND category='tracktate' AND day BETWEEN ? AND ?
          AND (assembly_id=? OR assembly_id IS NULL)");
        $t->execute(array_merge($memberIds, [$weekStart, $weekEnd, (int)$g['id']]));
        $trackTotal = (float)($t->fetch()['total'] ?? 0);
      }
      $grandPrayer += $prayerTotal;
      $grandTrack += $trackTotal;
    ?>
    <tr>
      <td><?= e($g['name']) ?></td>
      <td><?= e($g['region'] ?? '') ?></td>
      <td><?= e($g['leader_name'] ?? '-') ?></td>
      <td class="num"><?= e(rtrim(rtrim(number_format($prayerTotal,2,'.',''),'0'),'.')) ?></td>
      <td class="num"><?= e(rtrim(rtrim(number_format($trackTotal,2,'.',''),'0'),'.')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <th colspan="3" style="text-align:right;"><?= e(t('admin_total_prayer')) ?> / <?= e(t('admin_total_tracktate')) ?></th>
      <th class="num"><?= e(rtrim(rtrim(number_format($grandPrayer,2,'.',''),'0'),'.')) ?></th>
      <th class="num"><?= e(rtrim(rtrim(number_format($grandTrack,2,'.',''),'0'),'.')) ?></th>
    </tr>
  </tfoot>
</table>

<?php foreach ($groups as $g): ?>
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
    $prayerMap = [];
    if ($memberIds) {
      $in = implode(',', array_fill(0, count($memberIds), '?'));
      $p = db()->prepare("SELECT user_id, SUM(COALESCE(seconds, minutes*60)) AS total
        FROM prayer_logs WHERE user_id IN ($in) AND date BETWEEN ? AND ?
        AND (assembly_id=? OR assembly_id IS NULL)
        GROUP BY user_id");
      $p->execute(array_merge($memberIds, [$weekStart, $weekEnd, (int)$g['id']]));
      foreach ($p->fetchAll() as $row) $prayerMap[(int)$row['user_id']] = ((int)$row['total'])/60.0;

      $t = db()->prepare("SELECT user_id, SUM(value) AS total
        FROM daily_progress WHERE user_id IN ($in) AND category='tracktate' AND day BETWEEN ? AND ?
        AND (assembly_id=? OR assembly_id IS NULL)
        GROUP BY user_id");
      $t->execute(array_merge($memberIds, [$weekStart, $weekEnd, (int)$g['id']]));
      foreach ($t->fetchAll() as $row) $trackMap[(int)$row['user_id']] = (float)$row['total'];
    }
  ?>
  <h2><?= e($g['name']) ?> — <?= e(t('admin_members_detail')) ?></h2>
  <?php if (!$members): ?>
    <div class="muted"><?= e(t('groups_none')) ?></div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th><?= e(t('name')) ?></th>
          <th><?= e(t('email')) ?></th>
          <th class="num"><?= e(t('admin_total_prayer')) ?></th>
          <th class="num"><?= e(t('admin_total_tracktate')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
          <tr>
            <td><?= e($m['name']) ?></td>
            <td><?= e($m['email']) ?></td>
            <td class="num"><?= e(rtrim(rtrim(number_format($prayerMap[(int)$m['id']] ?? 0,2,'.',''),'0'),'.')) ?></td>
            <td class="num"><?= e(rtrim(rtrim(number_format($trackMap[(int)$m['id']] ?? 0,2,'.',''),'0'),'.')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
<?php endforeach; ?>
</body></html>
<?php
$html = ob_get_clean();
$dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="admin-summary-'.$weekStart.'.pdf"');
echo $dompdf->output();
exit;
