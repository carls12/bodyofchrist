<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

if (!auth_user()['is_leader']) { http_response_code(403); die('Forbidden'); }

db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS type ENUM('discipleship','assembly') NOT NULL DEFAULT 'assembly'");
db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");
db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE goals ADD INDEX IF NOT EXISTS idx_goals_assembly (assembly_id, week_start)");

$me = auth_user()['id'];
$groupId = (int)($_GET['group_id'] ?? 0);
if ($groupId <= 0) die('Invalid');

$g = db()->prepare("SELECT id,name FROM assemblies WHERE id=? AND leader_id=? LIMIT 1");
$g->execute([$groupId, $me]);
$group = $g->fetch();
if (!$group) { http_response_code(403); die('Forbidden'); }

$today = now_ymd();
$weekStart = monday_of($today);
$weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
$days = [];
for ($i=0;$i<7;$i++) $days[] = (new DateTimeImmutable($weekStart))->modify("+$i days")->format('Y-m-d');

$membersStmt = db()->prepare("SELECT u.id,u.name FROM assembly_members am
  JOIN users u ON u.id=am.user_id
  WHERE am.assembly_id=? AND am.active=1 AND am.status='active' ORDER BY u.name ASC");
$membersStmt->execute([$groupId]);
$members = $membersStmt->fetchAll();

$prayer = [];
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

  $goalStmt = db()->prepare("SELECT user_id, category, label, target, unit
    FROM goals WHERE user_id IN ($in) AND week_start=? AND (assembly_id=? OR assembly_id IS NULL)
    ORDER BY user_id ASC, id ASC");
  $goalStmt->execute(array_merge($userIds, [$weekStart, $groupId]));
  foreach ($goalStmt->fetchAll() as $row) {
    $goalsByUser[(int)$row['user_id']][] = $row;
  }

  $goalTotalsStmt = db()->prepare("SELECT user_id, category, SUM(value) as total
    FROM daily_progress WHERE user_id IN ($in) AND day BETWEEN ? AND ?
    AND (assembly_id=? OR assembly_id IS NULL)
    GROUP BY user_id, category");
  $goalTotalsStmt->execute($params);
  foreach ($goalTotalsStmt->fetchAll() as $row) {
    $goalTotals[(int)$row['user_id']][$row['category']] = (float)$row['total'];
  }

  $goalDailyStmt = db()->prepare("SELECT user_id, category, day, SUM(value) as total
    FROM daily_progress WHERE user_id IN ($in) AND day BETWEEN ? AND ?
    AND (assembly_id=? OR assembly_id IS NULL)
    GROUP BY user_id, category, day");
  $goalDailyStmt->execute($params);
  foreach ($goalDailyStmt->fetchAll() as $row) {
    $goalDaily[(int)$row['user_id']][$row['category']][$row['day']] = (float)$row['total'];
  }
}

$dompdfInstalled = class_exists('Dompdf\\Dompdf');
if (!$dompdfInstalled) { http_response_code(400); die('PDF not available'); }
$wantPdf = true;
if ($wantPdf && $dompdfInstalled) {
  $html = '<html><head><meta charset="utf-8"><style>
    body{ font-family: DejaVu Sans, Arial, sans-serif; font-size:12px; }
    table{ width:100%; border-collapse:collapse; }
    th,td{ border:1px solid #ddd; padding:6px; text-align:center; }
    th{ background:#f2f2f2; }
    td.name{ text-align:left; }
  </style></head><body>';
  $html .= '<h2>'.htmlspecialchars($group['name']).'</h2>';
  $html .= '<div>Week: '.$weekStart.' - '.$weekEnd.'</div><br>';
  $html .= '<table><thead><tr><th>#</th><th style="text-align:left">Name</th>';
  foreach ($days as $d) $html .= '<th>'.$d.'</th>';
  $html .= '<th>Total (min)</th></tr></thead><tbody>';
  $i = 1;
  foreach ($members as $m) {
    $uid = (int)$m['id'];
    $html .= '<tr><td>'.$i++.'</td><td class="name">'.htmlspecialchars($m['name']).'</td>';
    $total = 0.0;
    foreach ($days as $d) {
      $mins = (float)($prayer[$uid][$d] ?? 0);
      $html .= '<td>'.number_format($mins,1,'.','').'</td>';
      $total += $mins;
    }
    $html .= '<td>'.number_format($total,1,'.','').'</td></tr>';
  }
  $html .= '</tbody></table>';

  if ($goalsByUser) {
    $html .= '<br><h3>Weekly Goal Breakdown</h3>';
    foreach ($members as $m) {
      $uid = (int)$m['id'];
      $userGoals = $goalsByUser[$uid] ?? [];
      if (!$userGoals) continue;
      $html .= '<h4>'.htmlspecialchars($m['name']).'</h4>';
      $html .= '<table><thead><tr><th style="text-align:left">Goal</th>';
      foreach ($days as $d) $html .= '<th>'.$d.'</th>';
      $html .= '<th>Total</th><th>Target</th></tr></thead><tbody>';
      foreach ($userGoals as $goal) {
        $category = (string)$goal['category'];
        $html .= '<tr><td class="name">'.htmlspecialchars($goal['label']).'</td>';
        foreach ($days as $d) {
          $html .= '<td>'.rtrim(rtrim(number_format((float)($goalDaily[$uid][$category][$d] ?? 0), 2, '.', ''), '0'), '.').'</td>';
        }
        $html .= '<td>'.rtrim(rtrim(number_format((float)($goalTotals[$uid][$category] ?? 0), 2, '.', ''), '0'), '.').' '.htmlspecialchars($goal['unit']).'</td>';
        $html .= '<td>'.htmlspecialchars($goal['target']).' '.htmlspecialchars($goal['unit']).'</td></tr>';
      }
      $html .= '</tbody></table><br>';
    }
  }

  $html .= '</body></html>';

  $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4', 'landscape');
  $dompdf->render();
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="report-'.$groupId.'-'.$weekStart.'.pdf"');
  echo $dompdf->output();
  exit;
}
