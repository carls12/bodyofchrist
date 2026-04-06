<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL");
db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");

$uid = auth_user()['id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die('Invalid');

$stmt = db()->prepare("SELECT a.* FROM assemblies a WHERE a.id=?");
$stmt->execute([$id]);
$assembly = $stmt->fetch();
$isLeader = $assembly && (int)$assembly['leader_id'] === (int)$uid;
$isRegional = $assembly && is_regional_leader() && user_region() && user_region() === ($assembly['region'] ?? null);
if (!$assembly || (!$isLeader && !$isRegional && !is_main_admin())) { http_response_code(403); die('Forbidden'); }

$today = now_ymd();
$weekStart = monday_of($today);
$weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
$days = [];
for ($i=0;$i<7;$i++) $days[] = (new DateTimeImmutable($weekStart))->modify("+$i days")->format('Y-m-d');

$membersStmt = db()->prepare("SELECT u.id,u.name FROM assembly_members am
  JOIN users u ON u.id=am.user_id
  WHERE am.assembly_id=? AND am.active=1 AND am.status='active' ORDER BY u.name ASC");
$membersStmt->execute([$id]);
$members = $membersStmt->fetchAll();

$map = [];
$totalsPerDay = array_fill_keys($days, 0.0);

if ($members) {
  $userIds = array_map(fn($r)=> (int)$r['id'], $members);
  $in = implode(',', array_fill(0, count($userIds), '?'));

  $q = db()->prepare("SELECT user_id, day, SUM(value) as minutes
    FROM daily_progress
    WHERE category IN ('prayer_minutes','gebet') AND user_id IN ($in) AND day BETWEEN ? AND ?
    AND (assembly_id=? OR assembly_id IS NULL)
    GROUP BY user_id, day");
  $params = array_merge($userIds, [$weekStart, $weekEnd, $id]);
  $q->execute($params);

  foreach ($q->fetchAll() as $r) {
    $u = (int)$r['user_id'];
    $d = $r['day'];
    $mins = (float)$r['minutes'];
    $map[$u][$d] = $mins;
    $totalsPerDay[$d] += $mins;
  }
}

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

ob_start();
?>
<!doctype html>
<html><head><meta charset="utf-8"><title><?= e(t('summary_title')) ?></title>
<style>
body{ font-family: DejaVu Sans, Arial, sans-serif; font-size:12px; }
h1{ font-size:18px; margin:0 0 8px 0; }
.muted{ color:#666; margin-bottom:12px; }
table{ width:100%; border-collapse:collapse; }
th,td{ border:1px solid #ddd; padding:6px; text-align:center; }
th{ background:#f2f2f2; }
td.name{ text-align:left; }
.ok{ background:#d1f7c4; }
.bad{ background:#ffd6d6; }
.sumrow td{ font-weight:bold; background:#fafafa; }
@media print { .no-print{ display:none; } }
</style></head>
<body>
<h1><?= e($assembly['name']) ?> — <?= e(t('summary_title')) ?></h1>
<div class="muted"><?= e(t('summary_sub', ['start' => $weekStart, 'end' => $weekEnd])) ?></div>

<table>
  <thead>
    <tr>
      <th>#</th><th style="text-align:left"><?= e(t('name')) ?></th>
      <?php foreach($days as $d): ?><th><?= e(label_day_i18n($d)) ?></th><?php endforeach; ?>
      <th><?= e(t('summary_week_hours')) ?></th>
    </tr>
  </thead>
  <tbody>
    <?php $i=1; foreach($members as $m): $uid2=(int)$m['id']; $weekTotal=0.0; ?>
      <tr>
        <td><?= $i++ ?></td>
        <td class="name"><?= e($m['name']) ?></td>
        <?php foreach($days as $d): $mins=(float)($map[$uid2][$d] ?? 0.0); $weekTotal += $mins; $cls=($mins>=60.0)?'ok':'bad'; ?>
          <td class="<?= $cls ?>"><?= e(rtrim(rtrim(number_format($mins/60,2,'.',''),'0'),'.')) ?></td>
        <?php endforeach; ?>
        <td><?= e(rtrim(rtrim(number_format($weekTotal/60,2,'.',''),'0'),'.')) ?></td>
      </tr>
    <?php endforeach; ?>

    <tr class="sumrow">
      <td colspan="2" style="text-align:left"><?= e(t('summary_sum_day')) ?></td>
      <?php $grand=0.0; foreach($days as $d): $grand += $totalsPerDay[$d]; ?>
        <td><?= e(rtrim(rtrim(number_format($totalsPerDay[$d]/60,2,'.',''),'0'),'.')) ?></td>
      <?php endforeach; ?>
      <td><?= e(rtrim(rtrim(number_format($grand/60,2,'.',''),'0'),'.')) ?></td>
    </tr>
  </tbody>
</table>

<div class="no-print" style="margin-top:14px;">
  <button onclick="window.print()">PDF</button>
</div>
</body></html>
<?php
$html = ob_get_clean();

$wantPdf = isset($_GET['pdf']) && $_GET['pdf'] === '1';
$dompdfInstalled = class_exists('Dompdf\\Dompdf');

if ($wantPdf && $dompdfInstalled) {
  $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4', 'landscape');
  $dompdf->render();
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="church-summary-'.$weekStart.'.pdf"');
  echo $dompdf->output();
  exit;
}

echo $html;
