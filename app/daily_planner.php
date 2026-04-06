<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function daily_planner_ensure_tables(): void {
  db()->exec("CREATE TABLE IF NOT EXISTS daily_planner_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    planned_time TIME NOT NULL,
    planned_end_time TIME NULL,
    notes TEXT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX (user_id, weekday, sort_order),
    CONSTRAINT fk_daily_planner_template_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  db()->exec("CREATE TABLE IF NOT EXISTS daily_planner_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    template_id INT UNSIGNED NULL,
    plan_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    planned_time TIME NOT NULL,
    planned_end_time TIME NULL,
    notes TEXT NULL,
    completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX (user_id, plan_date, planned_time),
    INDEX (template_id),
    CONSTRAINT fk_daily_planner_entry_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_daily_planner_entry_template FOREIGN KEY (template_id) REFERENCES daily_planner_templates(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  // Add the planned_end_time column if it doesn't exist
  db()->exec("ALTER TABLE daily_planner_templates ADD COLUMN IF NOT EXISTS planned_end_time TIME NULL");
  db()->exec("ALTER TABLE daily_planner_entries ADD COLUMN IF NOT EXISTS planned_end_time TIME NULL");
}

function daily_planner_selected_date(?string $date): string {
  if ($date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    return $date;
  }
  return now_ymd();
}

function daily_planner_weekday(string $date): int {
  return (int)(new DateTimeImmutable($date))->format('N');
}

function daily_planner_weekday_name(int $weekday): string {
  $loc = $_SESSION['locale'] ?? 'de';
  $map = [
    'de' => [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'],
    'en' => [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'],
    'fr' => [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'],
  ];
  return $map[$loc][$weekday] ?? $map['en'][$weekday] ?? (string)$weekday;
}

function daily_planner_sync_entries_for_date(int $userId, string $date): void {
  $weekday = daily_planner_weekday($date);
  $templatesStmt = db()->prepare("SELECT id, title, planned_time, planned_end_time, notes, sort_order
    FROM daily_planner_templates
    WHERE user_id=? AND weekday=?
    ORDER BY planned_time ASC, sort_order ASC, id ASC");
  $templatesStmt->execute([$userId, $weekday]);
  $templates = $templatesStmt->fetchAll();
  if (!$templates) {
    return;
  }

  $existingStmt = db()->prepare("SELECT template_id FROM daily_planner_entries WHERE user_id=? AND plan_date=? AND template_id IS NOT NULL");
  $existingStmt->execute([$userId, $date]);
  $existingTemplateIds = array_map(static fn($row) => (int)$row['template_id'], $existingStmt->fetchAll());
  $existingMap = array_fill_keys($existingTemplateIds, true);

  $insertStmt = db()->prepare("INSERT INTO daily_planner_entries
    (user_id, template_id, plan_date, title, planned_time, planned_end_time, notes, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
  foreach ($templates as $template) {
    $templateId = (int)$template['id'];
    if (isset($existingMap[$templateId])) {
      continue;
    }
    $insertStmt->execute([
      $userId,
      $templateId,
      $date,
      $template['title'],
      $template['planned_time'],
      $template['planned_end_time'],
      $template['notes'],
    ]);
  }
}

function daily_planner_format_time(?string $time): string {
  if ($time === null || $time === '') {
    return '';
  }
  return substr($time, 0, 5);
}

function daily_planner_format_time_range(?string $startTime, ?string $endTime): string {
  $start = daily_planner_format_time($startTime);
  if ($endTime === null || $endTime === '') {
    return $start;
  }
  $end = daily_planner_format_time($endTime);
  return $start . ' - ' . $end;
}
