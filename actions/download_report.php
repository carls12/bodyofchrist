<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

if (!auth_user()['is_leader']) { http_response_code(403); die('Forbidden'); }
ensure_goal_group_schema();

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
$requestedWeek = (string)($_GET['week_start'] ?? '');
$weekStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedWeek) ? monday_of($requestedWeek) : monday_of($today);
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

  $goalStmt = db()->prepare("SELECT user_id, category, label, group_title, target, unit
    FROM goals WHERE user_id IN ($in) AND week_start=? AND (assembly_id=? OR assembly_id IS NULL)
    ORDER BY user_id ASC, group_title ASC, id ASC");
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
ensure_report_notes_schema();

// Generate multi-member report with the same visual language as the reference PDF.
$css = '<style>
  @page { margin: 8mm 10mm; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #ffffff; color: #2c3e50; font-family: "Segoe UI", Tahoma, Geneva, Verdana, "DejaVu Sans", sans-serif; font-size: 8.8pt; line-height: 1.2; }
  .report-header { background: #2c3e50; color: #ffffff; padding: 15px 20px 14px; text-align: center; }
  .report-header h1 { margin: 0 0 5px; font-size: 16pt; line-height: 1.15; font-weight: 700; letter-spacing: .7px; text-transform: uppercase; }
  .report-meta { font-size: 9pt; font-weight: 400; color: #f8f9fa; }
  .member-report { page-break-before: always; margin-bottom: 8px; }
  .member-report.first-member { page-break-before: auto; }
  .section-bar { margin: 8px 0 5px; background: #ffffff; color: #2c3e50; padding: 0 0 3px; border-bottom: 1.2px solid #2c3e50; font-size: 11.5pt; line-height: 1.15; font-weight: 700; letter-spacing: 0; text-transform: none; }
  .metric-grid { width: 100%; border-collapse: collapse; margin: 0 0 8px; table-layout: fixed; }
  .metric-grid td { background: #f8f9fa; border: 1px solid #dddddd; padding: 5px 7px; vertical-align: top; }
  .metric-label { display: block; color: #6c757d; font-size: 8.5pt; font-weight: 700; letter-spacing: 0; text-transform: none; }
  .metric-value { display: block; margin-top: 2px; color: #2c3e50; font-size: 8.8pt; font-weight: 400; }
  .section { margin-bottom: 8px; page-break-inside: auto; }
  table.report-table { width: 100%; border-collapse: collapse; margin-bottom: 7px; table-layout: fixed; }
  .report-table th { background: #f8f9fa; border: 1px solid #dddddd; color: #2c3e50; padding: 4px 5px; font-size: 8.7pt; line-height: 1.2; font-weight: 700; text-align: center; text-transform: none; }
  .report-table th:first-child, .report-table td:first-child { text-align: left; }
  .report-table td { border: 1px solid #dddddd; padding: 4px 5px; font-size: 8.7pt; line-height: 1.2; font-weight: 400; text-align: center; vertical-align: middle; }
  .report-table tbody tr:nth-child(even) td { background: #fbfcfd; }
  .name-col { width: 30%; }
  .status { font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
  .status-exceeded, .status-met { color: #1f7a3a; }
  .status-partial { color: #b86400; }
  .status-missed { color: #b42318; }
  .empty { border: 1px solid #dddddd; background: #f8f9fa; padding: 8px; color: #6c757d; font-size: 9pt; line-height: 1.2; }
  .planning-note { border-left: 4px solid #2c3e50; background: #f8f9fa; padding: 7px 9px; margin: 0 0 8px; font-size: 9pt; line-height: 1.2; white-space: pre-line; }
  .report-footer { margin-top: 8px; padding-top: 5px; border-top: 1px solid #dddddd; color: #6c757d; font-size: 8pt; line-height: 1.2; text-align: center; }
</style>';

$html = '<html><head><meta charset="utf-8">' . $css . '</head><body>';

// Group header
$html .= '<div class="report-header">';
$html .= '<h1>' . htmlspecialchars(t('report_group_title')) . '</h1>';
$html .= '<div class="report-meta">';
$html .= htmlspecialchars(t('report_group')) . ': ' . htmlspecialchars($group['name']) . ' | ' . htmlspecialchars(t('report_period')) . ': ' . htmlspecialchars(report_date_display(new DateTimeImmutable($weekStart))) . ' - ' . htmlspecialchars(report_date_display(new DateTimeImmutable($weekEnd)));
$html .= '</div></div>';

$html .= '<div class="section-bar">' . htmlspecialchars(t('report_overview')) . '</div>';
$html .= '<table class="metric-grid"><tr>';
$html .= '<td><span class="metric-label">' . htmlspecialchars(t('report_group')) . '</span><span class="metric-value">' . htmlspecialchars($group['name']) . '</span></td>';
$html .= '<td><span class="metric-label">' . htmlspecialchars(t('report_disciples')) . '</span><span class="metric-value">' . count($members) . '</span></td>';
$html .= '<td><span class="metric-label">' . htmlspecialchars(t('report_start')) . '</span><span class="metric-value">' . htmlspecialchars(report_date_display(new DateTimeImmutable($weekStart))) . '</span></td>';
$html .= '<td><span class="metric-label">' . htmlspecialchars(t('report_end')) . '</span><span class="metric-value">' . htmlspecialchars(report_date_display(new DateTimeImmutable($weekEnd))) . '</span></td>';
$html .= '</tr></table>';

// Generate report for each member
$firstMember = true;
foreach ($members as $member) {
  $uid = (int)$member['id'];
  $memberGoals = $goalsByUser[$uid] ?? [];
  $memberDaily = $goalDaily[$uid] ?? [];
  
  $html .= '<div class="member-report' . ($firstMember ? ' first-member' : '') . '">';
  $firstMember = false;
  $html .= '<div class="section-bar">' . htmlspecialchars(t('report_disciple')) . ': ' . htmlspecialchars($member['name']) . '</div>';
  $html .= '<table class="metric-grid"><tr>';
  $html .= '<td><span class="metric-label">' . htmlspecialchars(t('report_disciple')) . '</span><span class="metric-value">' . htmlspecialchars($member['name']) . '</span></td>';
  $html .= '<td><span class="metric-label">' . htmlspecialchars(t('report_week_start')) . '</span><span class="metric-value">' . htmlspecialchars(report_date_display(new DateTimeImmutable($weekStart))) . '</span></td>';
  $html .= '<td><span class="metric-label">' . htmlspecialchars(t('report_week_end')) . '</span><span class="metric-value">' . htmlspecialchars(report_date_display(new DateTimeImmutable($weekEnd))) . '</span></td>';
  $html .= '<td><span class="metric-label">' . htmlspecialchars(t('report_generated')) . '</span><span class="metric-value">' . htmlspecialchars(report_date_display(new DateTimeImmutable('now'))) . '</span></td>';
  $html .= '</tr></table>';
  
  if ($memberGoals) {
    // Group goals by category
    $goalsByCategory = [];
    foreach ($memberGoals as $goal) {
      $cat = default_goal_group_title($goal);
      if (!isset($goalsByCategory[$cat])) $goalsByCategory[$cat] = [];
      $goalsByCategory[$cat][] = $goal;
    }
    
    // Generate section for each category
    foreach ($goalsByCategory as $category => $catGoals) {
      $html .= '<div class="section">';
      $html .= '<div class="section-bar">' . htmlspecialchars($category) . '</div>';
      
      $html .= '<table class="report-table">';
      $html .= '<thead><tr><th class="name-col">' . htmlspecialchars(t('report_metric')) . '</th><th>' . htmlspecialchars(t('report_actual')) . '</th><th>' . htmlspecialchars(t('report_goal')) . '</th><th>' . htmlspecialchars(t('report_status')) . '</th></tr></thead>';
      $html .= '<tbody>';
      
      foreach ($catGoals as $goal) {
        $goalCat = (string)$goal['category'];
        $total = (float)($goalTotals[$uid][$goalCat] ?? 0);
        $target = (float)$goal['target'];
        $percent = $target > 0 ? ($total / $target * 100) : 0;
        
        if ($percent >= 100) {
          $status = t('report_status_completed');
          $statusClass = 'status-exceeded';
        } elseif ($percent >= 80) {
          $status = t('report_status_on_track');
          $statusClass = 'status-met';
        } elseif ($percent >= 50) {
          $status = t('report_status_partial');
          $statusClass = 'status-partial';
        } else {
          $status = t('report_status_missed');
          $statusClass = 'status-missed';
        }
        
        $totalStr = rtrim(rtrim(number_format($total, 1, '.', ''), '0'), '.');
        $targetStr = rtrim(rtrim(number_format($target, 1, '.', ''), '0'), '.');
        $unit = compact_goal_unit((string)$goal['unit']);
        
        $html .= '<tr>';
        $html .= '<td class="name">' . htmlspecialchars($goal['label']) . '</td>';
        $html .= '<td>' . $totalStr . ' ' . htmlspecialchars($unit) . '</td>';
        $html .= '<td>' . $targetStr . ' ' . htmlspecialchars($unit) . '</td>';
        $html .= '<td class="status ' . $statusClass . '">' . htmlspecialchars($status) . '</td>';
        $html .= '</tr>';
      }
      
      $html .= '</tbody></table>';
      
      $html .= '</div>';
    }
  } else {
    $html .= '<div class="empty">' . htmlspecialchars(t('report_no_member_goals')) . '</div>';
  }

  $nextWeekNotes = report_next_week_note($uid, $weekStart, $groupId);
  if (trim($nextWeekNotes) !== '') {
    $html .= '<div class="section">';
    $html .= '<div class="section-bar">' . htmlspecialchars(t('report_next_week_title')) . '</div>';
    $html .= '<div class="planning-note">' . nl2br(htmlspecialchars(trim($nextWeekNotes))) . '</div>';
    $html .= '</div>';
  }
  
  $html .= '</div>';
}

$html .= '<div class="report-footer">' . htmlspecialchars(t('report_footer')) . '</div>';
$html .= '</body></html>';

$dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="report-'.$groupId.'-'.$weekStart.'.pdf"');
echo $dompdf->output();
exit;
