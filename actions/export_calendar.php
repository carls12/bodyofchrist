<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$viewMode = $_GET['view'] ?? 'week';
$month = $_GET['month'] ?? (new DateTimeImmutable('first day of this month'))->format('Y-m');
$weekDateStr = $_GET['week_date'] ?? 'now';

if ($viewMode === 'week') {
    $weekDate = $weekDateStr === 'now'
        ? new DateTimeImmutable()
        : DateTimeImmutable::createFromFormat('Y-m-d', $weekDateStr) ?: new DateTimeImmutable();
    $weekday = (int)$weekDate->format('N');
    $start = $weekDate->modify('-' . ($weekday - 1) . ' days');
    $end = $start->modify('+6 days');
} else {
    $monthDate = DateTimeImmutable::createFromFormat('Y-m', $month) ?: new DateTimeImmutable('first day of this month');
    $start = $monthDate->modify('first day of this month');
    $end = $monthDate->modify('last day of this month');
}

$uid = auth_user()['id'];
$aid = active_assembly_id();

$groupsStmt = db()->prepare("SELECT a.id FROM assemblies a
  JOIN assembly_members am ON am.assembly_id=a.id
  WHERE am.user_id=? AND am.status='active'");
$groupsStmt->execute([$uid]);
$groups = $groupsStmt->fetchAll();
$groupIds = array_map(fn($r) => (int)$r['id'], $groups);

if ($groupIds) {
    $in = implode(',', array_fill(0, count($groupIds), '?'));
    $sql = "SELECT * FROM calendar_events WHERE (user_id=? OR (group_id IS NOT NULL AND group_id IN ($in)))
      AND (assembly_id=? OR assembly_id IS NULL)
      AND date BETWEEN ? AND ? ORDER BY date ASC";
    $params = array_merge([$uid], $groupIds, [$aid, $start->format('Y-m-d'), $end->format('Y-m-d')]);
} else {
    $sql = "SELECT * FROM calendar_events WHERE user_id=? AND (assembly_id=? OR assembly_id IS NULL) AND date BETWEEN ? AND ? ORDER BY date ASC";
    $params = [$uid, $aid, $start->format('Y-m-d'), $end->format('Y-m-d')];
}

$stmt = db()->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

function ical_escape(string $value): string {
    $value = str_replace(["\r\n", "\r", "\n"], ['\\n', '\\n', '\\n'], $value);
    return preg_replace('/([\\;,])/u', '\\$1', $value);
}

function ical_date(DateTimeImmutable $dt, bool $allDay = false): string {
    return $allDay ? $dt->format('Ymd') : $dt->format('Ymd\\THis');
}

function build_ics_event(array $event): string {
    $title = ical_escape($event['title'] ?? '');
    $description = ical_escape($event['description'] ?? '');
    $uid = 'event-' . $event['id'] . '@bodyofchrist';
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $created = $event['created_at'] ? new DateTimeImmutable($event['created_at']) : $now;

    if ($event['time']) {
        $start = new DateTimeImmutable($event['date'] . ' ' . $event['time']);
        if ($event['end_time']) {
            $end = new DateTimeImmutable($event['date'] . ' ' . $event['end_time']);
        } else {
            $end = $start->modify('+1 hour');
        }
        $dtStart = ical_date($start);
        $dtEnd = ical_date($end);
        $dateLineStart = "DTSTART:$dtStart";
        $dateLineEnd = "DTEND:$dtEnd";
    } else {
        $start = new DateTimeImmutable($event['date']);
        $end = $start->modify('+1 day');
        $dateLineStart = 'DTSTART;VALUE=DATE:' . ical_date($start, true);
        $dateLineEnd = 'DTEND;VALUE=DATE:' . ical_date($end, true);
    }

    return implode("\r\n", [
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . ical_date($now),
        'CREATED:' . ical_date($created),
        $dateLineStart,
        $dateLineEnd,
        'SUMMARY:' . $title,
        'DESCRIPTION:' . $description,
        'END:VEVENT',
    ]);
}

$filename = 'schedule-' . $start->format('Ymd') . '-to-' . $end->format('Ymd') . '.ics';
header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//BodyOfChrist//Schedule//DE',
    'CALSCALE:GREGORIAN',
];
foreach ($events as $event) {
    $lines[] = build_ics_event($event);
}
$lines[] = 'END:VCALENDAR';

echo implode("\r\n", $lines);
exit;
