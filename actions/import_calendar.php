<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

function unfold_ics_lines(string $content): array {
    $raw = preg_split('/\r\n|\r|\n/', $content);
    $lines = [];
    foreach ($raw as $line) {
        if ($line === '') {
            $lines[] = $line;
            continue;
        }
        if (!empty($lines) && preg_match('/^[ \t]/', $line)) {
            $lines[count($lines) - 1] .= substr($line, 1);
        } else {
            $lines[] = $line;
        }
    }
    return $lines;
}

function parse_ics_datetime(string $value): ?array {
    $value = trim($value);
    $value = preg_replace('/\\s+/', '', $value);
    $value = rtrim($value, 'Z');
    if (preg_match('/^(\d{8})T(\d{6})$/', $value, $matches)) {
        return [
            'date' => substr($matches[1], 0, 4) . '-' . substr($matches[1], 4, 2) . '-' . substr($matches[1], 6, 2),
            'time' => substr($matches[2], 0, 2) . ':' . substr($matches[2], 2, 2) . ':' . substr($matches[2], 4, 2),
        ];
    }
    if (preg_match('/^(\d{8})$/', $value, $matches)) {
        return [
            'date' => substr($matches[1], 0, 4) . '-' . substr($matches[1], 4, 2) . '-' . substr($matches[1], 6, 2),
            'time' => null,
        ];
    }
    return null;
}

$file = $_FILES['ics_file'] ?? null;
if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
    flash_set('error', t('flash_upload_failed'));
    redirect(base_url('calendar'));
}
$pathInfo = pathinfo($file['name']);
$extension = strtolower($pathInfo['extension'] ?? '');
if ($extension !== 'ics') {
    flash_set('error', t('flash_invalid_ics'));
    redirect(base_url('calendar'));
}

$content = file_get_contents($file['tmp_name']);
if ($content === false) {
    flash_set('error', t('flash_upload_failed'));
    redirect(base_url('calendar'));
}

$lines = unfold_ics_lines($content);
$events = [];
$current = null;
foreach ($lines as $line) {
    if (preg_match('/^BEGIN:VEVENT/i', $line)) {
        $current = [];
        continue;
    }
    if (preg_match('/^END:VEVENT/i', $line)) {
        if ($current !== null) {
            $events[] = $current;
            $current = null;
        }
        continue;
    }
    if ($current === null) {
        continue;
    }
    if (preg_match('/^([^:;]+)(?:;[^:]+)*:(.*)$/', $line, $matches)) {
        $name = strtoupper($matches[1]);
        $value = $matches[2];
        $current[$name][] = $value;
    }
}

$inserted = 0;
$assemblyId = active_assembly_id();
foreach ($events as $event) {
    $summary = trim($event['SUMMARY'][0] ?? '');
    if ($summary === '') {
        continue;
    }
    $startLine = $event['DTSTART'][0] ?? null;
    if (!$startLine) {
        continue;
    }
    $start = parse_ics_datetime($startLine);
    if (!$start) {
        continue;
    }

    $description = trim($event['DESCRIPTION'][0] ?? '');

    $endLine = $event['DTEND'][0] ?? null;
    $end = $endLine ? parse_ics_datetime($endLine) : null;
    $time = $start['time'];
    $endTime = null;

    if ($end && $end['time']) {
        $endTime = $end['time'];
    } elseif ($time) {
        try {
            $dt = new DateTimeImmutable($start['date'] . ' ' . $time);
            $dt = $dt->modify('+1 hour');
            $endTime = $dt->format('H:i:s');
        } catch (Exception $e) {
            $endTime = null;
        }
    }

    db()->prepare("INSERT INTO calendar_events (user_id, assembly_id, group_id, title, description, date, time, end_time, type, created_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
      ->execute([auth_user()['id'], $assemblyId, null, $summary, $description ?: null, $start['date'], $time ?: null, $endTime ?: null, 'personal']);
    $inserted++;
}

if ($inserted > 0) {
    flash_set('success', t('flash_ics_imported', ['count' => $inserted]));
} else {
    flash_set('error', t('flash_ics_no_events'));
}
redirect(base_url('calendar'));
