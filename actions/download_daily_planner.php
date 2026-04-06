<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/daily_planner.php';

daily_planner_ensure_tables();

if (!class_exists('Dompdf\\Dompdf')) {
  http_response_code(400);
  echo 'PDF export unavailable (DOMPDF missing).';
  exit;
}

$uid = auth_user()['id'];
$period = $_GET['period'] ?? 'week';
$selectedDate = daily_planner_selected_date($_GET['date'] ?? null);

if ($period === 'month') {
  $start = (new DateTimeImmutable($selectedDate))->modify('first day of this month');
  $end = (new DateTimeImmutable($selectedDate))->modify('last day of this month');
} else {
  $start = new DateTimeImmutable(monday_of($selectedDate));
  $end = $start->modify('+6 days');
  $period = 'week';
}

for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
  daily_planner_sync_entries_for_date($uid, $cursor->format('Y-m-d'));
}

$entriesStmt = db()->prepare("SELECT * FROM daily_planner_entries
  WHERE user_id=? AND plan_date BETWEEN ? AND ?
  ORDER BY plan_date ASC, planned_time ASC, id ASC");
$entriesStmt->execute([$uid, $start->format('Y-m-d'), $end->format('Y-m-d')]);
$entries = $entriesStmt->fetchAll();

$byDate = [];
foreach ($entries as $entry) {
  $byDate[$entry['plan_date']][] = $entry;
}

$html = '<html><head><meta charset="utf-8"><style>
  body{ font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#0f172a; }
  h1{ font-size:20px; margin-bottom:4px; color:#1E3A5F; }
  .muted{ color:#64748b; margin-bottom:14px; }
  .day{ border:1px solid #dbe4f0; border-radius:12px; padding:12px; margin-bottom:12px; }
  table{ width:100%; border-collapse:collapse; margin-top:8px; }
  th, td{ text-align:left; padding:7px; border-bottom:1px solid #e2e8f0; vertical-align:top; }
  th{ background:#f8fafc; }
  .done{ color:#166534; font-weight:bold; }
  .open{ color:#9a3412; font-weight:bold; }
</style></head><body>';
$html .= '<h1>' . htmlspecialchars(t('daily_planner_print_title')) . '</h1>';
$html .= '<div class="muted">' . htmlspecialchars((string)auth_user()['name']) . ' | ' . htmlspecialchars($period) . ' | ' . $start->format('Y-m-d') . ' - ' . $end->format('Y-m-d') . '</div>';

for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
  $date = $cursor->format('Y-m-d');
  $dayEntries = $byDate[$date] ?? [];
  $doneCount = 0;
  foreach ($dayEntries as $entry) {
    if (!empty($entry['completed'])) {
      $doneCount++;
    }
  }

  $html .= '<div class="day">';
  $html .= '<strong>' . htmlspecialchars(daily_planner_weekday_name((int)$cursor->format('N')) . ' ' . $date) . '</strong>';
  $html .= '<div class="muted">' . htmlspecialchars(t('daily_planner_summary', ['done' => $doneCount, 'total' => count($dayEntries)])) . '</div>';

  if (!$dayEntries) {
    $html .= '<div>' . htmlspecialchars(t('daily_planner_no_entries')) . '</div>';
  } else {
    $html .= '<table><thead><tr><th>' . htmlspecialchars(t('daily_planner_time')) . '</th><th>' . htmlspecialchars(t('daily_planner_activity')) . '</th><th>' . htmlspecialchars(t('daily_planner_notes')) . '</th><th>' . htmlspecialchars(t('daily_planner_completion_label')) . '</th></tr></thead><tbody>';
    foreach ($dayEntries as $entry) {
      $html .= '<tr>';
      $html .= '<td>' . htmlspecialchars(daily_planner_format_time($entry['planned_time'])) . '</td>';
      $html .= '<td>' . htmlspecialchars($entry['title']) . '</td>';
      $html .= '<td>' . nl2br(htmlspecialchars((string)($entry['notes'] ?? ''))) . '</td>';
      $html .= '<td class="' . (!empty($entry['completed']) ? 'done' : 'open') . '">' . htmlspecialchars(!empty($entry['completed']) ? t('daily_planner_status_done') : t('daily_planner_status_open')) . '</td>';
      $html .= '</tr>';
    }
    $html .= '</tbody></table>';
  }
  $html .= '</div>';
}

$html .= '</body></html>';

$dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="daily-planner-' . $period . '-' . $start->format('Y-m-d') . '.pdf"');
echo $dompdf->output();
exit;
