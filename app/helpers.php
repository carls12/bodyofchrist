<?php
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $url) {
  header("Location: $url");
  exit;
}

function base_url(string $path = ''): string {
  $cfg = require __DIR__ . '/config.php';
  $base = rtrim($cfg['base_url'], '/');
  $path = '/' . ltrim($path, '/');
  return $base . $path;
}

function flash_set(string $key, string $value) {
  $_SESSION['_flash'][$key] = $value;
}
function flash_get(string $key): ?string {
  if (!isset($_SESSION['_flash'][$key])) return null;
  $v = $_SESSION['_flash'][$key];
  unset($_SESSION['_flash'][$key]);
  return $v;
}

function t(string $key, array $vars = []): string {
  static $dict = null;
  if ($dict === null) {
    $dict = require __DIR__ . '/i18n.php';
  }
  $loc = $_SESSION['locale'] ?? 'de';
  $text = $dict[$loc][$key] ?? $dict['de'][$key] ?? $key;
  foreach ($vars as $k => $v) {
    $text = str_replace('{' . $k . '}', (string)$v, $text);
  }
  return $text;
}

function country_list(): array {
  return [
    'Afghanistan','Albania','Algeria','Andorra','Angola','Antigua and Barbuda','Argentina','Armenia','Australia','Austria','Azerbaijan',
    'Bahamas','Bahrain','Bangladesh','Barbados','Belarus','Belgium','Belize','Benin','Bhutan','Bolivia','Bosnia and Herzegovina','Botswana','Brazil','Brunei','Bulgaria','Burkina Faso','Burundi',
    'Cabo Verde','Cambodia','Cameroon','Canada','Central African Republic','Chad','Chile','China','Colombia','Comoros','Congo','Congo (Democratic Republic)','Costa Rica','Cote d\'Ivoire','Croatia','Cuba','Cyprus','Czechia',
    'Denmark','Djibouti','Dominica','Dominican Republic',
    'Ecuador','Egypt','El Salvador','Equatorial Guinea','Eritrea','Estonia','Eswatini','Ethiopia',
    'Fiji','Finland','France',
    'Gabon','Gambia','Georgia','Germany','Ghana','Greece','Grenada','Guatemala','Guinea','Guinea-Bissau','Guyana',
    'Haiti','Honduras','Hungary',
    'Iceland','India','Indonesia','Iran','Iraq','Ireland','Israel','Italy',
    'Jamaica','Japan','Jordan',
    'Kazakhstan','Kenya','Kiribati','Korea (North)','Korea (South)','Kosovo','Kuwait','Kyrgyzstan',
    'Laos','Latvia','Lebanon','Lesotho','Liberia','Libya','Liechtenstein','Lithuania','Luxembourg',
    'Madagascar','Malawi','Malaysia','Maldives','Mali','Malta','Marshall Islands','Mauritania','Mauritius','Mexico','Micronesia','Moldova','Monaco','Mongolia','Montenegro','Morocco','Mozambique','Myanmar',
    'Namibia','Nauru','Nepal','Netherlands','New Zealand','Nicaragua','Niger','Nigeria','North Macedonia','Norway',
    'Oman',
    'Pakistan','Palau','Palestine','Panama','Papua New Guinea','Paraguay','Peru','Philippines','Poland','Portugal',
    'Qatar',
    'Romania','Russia','Rwanda',
    'Saint Kitts and Nevis','Saint Lucia','Saint Vincent and the Grenadines','Samoa','San Marino','Sao Tome and Principe','Saudi Arabia','Senegal','Serbia','Seychelles','Sierra Leone','Singapore','Slovakia','Slovenia','Solomon Islands','Somalia','South Africa','South Sudan','Spain','Sri Lanka','Sudan','Suriname','Sweden','Switzerland','Syria',
    'Taiwan','Tajikistan','Tanzania','Thailand','Timor-Leste','Togo','Tonga','Trinidad and Tobago','Tunisia','Turkey','Turkmenistan','Tuvalu',
    'Uganda','Ukraine','United Arab Emirates','United Kingdom','United States','Uruguay','Uzbekistan',
    'Vanuatu','Vatican City','Venezuela','Vietnam',
    'Yemen',
    'Zambia','Zimbabwe'
  ];
}

function monday_of(string $dateYmd): string {
  $dt = new DateTimeImmutable($dateYmd);
  $dow = (int)$dt->format('N');
  $monday = $dt->modify('-' . ($dow - 1) . ' days');
  return $monday->format('Y-m-d');
}

function now_ymd(): string { return (new DateTimeImmutable('now'))->format('Y-m-d'); }

function compact_goal_unit(string $unit): string {
  $normalized = strtolower(trim($unit));
  $map = [
    'chapter' => 'ch.',
    'chapters' => 'ch.',
    'kapitel' => 'Kap.',
    'chapitre' => 'ch.',
    'chapitres' => 'ch.',
    'minute' => 'min',
    'minutes' => 'min',
    'minuten' => 'min',
    'hour' => 'h',
    'hours' => 'h',
    'stunde' => 'h',
    'stunden' => 'h',
    'day' => 'd',
    'days' => 'd',
    'tag' => 'd',
    'tage' => 'd',
  ];
  return $map[$normalized] ?? $unit;
}

function parse_decimal_input($value): float {
  $raw = trim((string)$value);
  if ($raw === '') return 0.0;
  $raw = str_replace(["\xc2\xa0", ' '], '', $raw);
  if (str_contains($raw, ',') && str_contains($raw, '.')) {
    $raw = str_replace('.', '', $raw);
    $raw = str_replace(',', '.', $raw);
  } else {
    $raw = str_replace(',', '.', $raw);
  }
  return is_numeric($raw) ? (float)$raw : 0.0;
}

function ensure_goal_group_schema(): void {
  db()->exec("ALTER TABLE goals ADD COLUMN IF NOT EXISTS group_title VARCHAR(255) NULL");
  db()->exec("ALTER TABLE goals ADD INDEX IF NOT EXISTS idx_goals_group_title (group_title)");
}

function report_date_display(DateTimeImmutable $date): string {
  $loc = $_SESSION['locale'] ?? 'de';
  if ($loc === 'en') {
    return $date->format('M d, Y');
  }
  return $date->format('d.m.Y');
}

function default_goal_group_map(): array {
  return [
    'goals_group_default_spiritual' => '1. Spiritual Activity',
    'goals_group_default_scripture' => '2. Scripture Engagement',
    'goals_group_default_university' => '3. University Study & Preparation',
    'goals_group_default_evangelism' => '4. Evangelism & Soul Winning',
    'goals_group_default_other' => '5. Other Important Goals',
  ];
}

function normalize_goal_group_title(?string $groupTitle): string {
  $title = trim((string)$groupTitle);
  if ($title === '') {
    return '';
  }

  $map = default_goal_group_map();
  if (isset($map[$title])) {
    return $map[$title];
  }

  return $title;
}

function default_goal_group_title(array $goal): string {
  $text = strtolower((string)($goal['group_title'] ?? '') . ' ' . (string)($goal['category'] ?? '') . ' ' . (string)($goal['label'] ?? ''));
  $explicit = normalize_goal_group_title((string)($goal['group_title'] ?? ''));
  if ($explicit !== '') return $explicit;

  if (preg_match('/prayer|pray|gebet|worship|praise|fast|retreat/', $text)) {
    return '1. Spiritual Activity';
  }
  if (preg_match('/scripture|bible|chapter|chapters|kapitel|reading|read|way of life|revival/', $text)) {
    return '2. Scripture Engagement';
  }
  if (preg_match('/university|study|preparation|exam|test|school|course|lecture/', $text)) {
    return '3. University Study & Preparation';
  }
  if (preg_match('/evangel|soul|tract|repentance|preach|message|winning/', $text)) {
    return '4. Evangelism & Soul Winning';
  }

  return '5. Other Important Goals';
}

function ensure_report_notes_schema(): void {
  db()->exec("CREATE TABLE IF NOT EXISTS report_next_week_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    assembly_id INT UNSIGNED NULL,
    week_start DATE NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_report_note (user_id, assembly_id, week_start),
    INDEX (user_id, week_start),
    INDEX (assembly_id, week_start),
    CONSTRAINT fk_report_note_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function report_next_week_note(int $userId, string $weekStart, ?int $assemblyId): string {
  ensure_report_notes_schema();
  if ($assemblyId === null) {
    $stmt = db()->prepare("SELECT notes FROM report_next_week_notes WHERE user_id=? AND week_start=? AND assembly_id IS NULL LIMIT 1");
    $stmt->execute([$userId, $weekStart]);
  } else {
    $stmt = db()->prepare("SELECT notes FROM report_next_week_notes WHERE user_id=? AND week_start=? AND assembly_id=? LIMIT 1");
    $stmt->execute([$userId, $weekStart, $assemblyId]);
  }
  return (string)($stmt->fetch()['notes'] ?? '');
}

/**
 * Generate PDF HTML for discipleship reports.
 * The visual language follows the uploaded report reference: dark slate header,
 * slim section bars, light grey metric cells, and crisp table grid lines.
 */
function generate_professional_report_html(
  string $title,
  string $name,
  string $period_text,
  array $goals,
  array $daily_data,
  DateTimeImmutable $start,
  DateTimeImmutable $end,
  string $next_week_notes = ''
): string {
  $css = '<style>
    @page { margin: 8mm 10mm; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #ffffff; color: #2c3e50; font-family: "Segoe UI", Tahoma, Geneva, Verdana, "DejaVu Sans", sans-serif; font-size: 8.8pt; line-height: 1.2; }
    .report-header { background: #2c3e50; color: #ffffff; padding: 15px 20px 14px; text-align: center; }
    .report-header h1 { margin: 0 0 5px; font-size: 16pt; line-height: 1.15; font-weight: 700; letter-spacing: .7px; text-transform: uppercase; }
    .report-meta { font-size: 9pt; font-weight: 400; color: #f8f9fa; }
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
    .name-col { width: 32%; }
    .status { font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
    .status-exceeded, .status-met { color: #1f7a3a; }
    .status-partial { color: #b86400; }
    .status-missed { color: #b42318; }
    .empty { border: 1px solid #dddddd; background: #f8f9fa; padding: 8px; color: #6c757d; font-size: 9pt; line-height: 1.2; }
    .planning-note { border-left: 4px solid #2c3e50; background: #f8f9fa; padding: 7px 9px; margin: 0 0 8px; font-size: 9pt; line-height: 1.2; white-space: pre-line; }
    .report-footer { margin-top: 8px; padding-top: 5px; border-top: 1px solid #dddddd; color: #6c757d; font-size: 8pt; line-height: 1.2; text-align: center; }
  </style>';

  $days = [];
  for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
    $days[] = $d->format('Y-m-d');
  }

  $goals_by_category = [];
  foreach ($goals as $goal) {
    $category = default_goal_group_title($goal);
    if (!isset($goals_by_category[$category])) $goals_by_category[$category] = [];
    $goals_by_category[$category][] = $goal;
  }

  $format_number = static function(float $value, int $decimals = 1): string {
    return rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');
  };
  $format_amount = static function(float $value, string $unit) use ($format_number): string {
    return $format_number($value) . ' ' . compact_goal_unit($unit);
  };
  $status_for = static function(float $actual, float $target): array {
    $percent = $target > 0 ? ($actual / $target * 100) : 0;
    if ($percent >= 100) return [t('report_status_completed'), 'status-exceeded'];
    if ($percent >= 80) return [t('report_status_on_track'), 'status-met'];
    if ($percent >= 50) return [t('report_status_partial'), 'status-partial'];
    return [t('report_status_missed'), 'status-missed'];
  };

  $html = '<html><head><meta charset="utf-8">' . $css . '</head><body>';

  $html .= '<div class="report-header">';
  $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
  $html .= '<div class="report-meta">' . htmlspecialchars(t('report_disciple')) . ': ' . htmlspecialchars($name) . ' | ' . htmlspecialchars(t('report_period')) . ': ' . htmlspecialchars($period_text) . '</div>';
  $html .= '</div>';

  $html .= '<div class="section-bar">' . htmlspecialchars(t('report_overview')) . '</div>';
  $html .= '<table class="metric-grid"><tr>';
  $html .= '<td><span class="metric-label">' . htmlspecialchars(t('report_disciple')) . '</span><span class="metric-value">' . htmlspecialchars($name) . '</span></td>';
  $html .= '<td><span class="metric-label">' . htmlspecialchars(t('report_start')) . '</span><span class="metric-value">' . htmlspecialchars(report_date_display($start)) . '</span></td>';
  $html .= '<td><span class="metric-label">' . htmlspecialchars(t('report_end')) . '</span><span class="metric-value">' . htmlspecialchars(report_date_display($end)) . '</span></td>';
  $html .= '<td><span class="metric-label">' . htmlspecialchars(t('report_generated')) . '</span><span class="metric-value">' . htmlspecialchars(report_date_display(new DateTimeImmutable('now'))) . '</span></td>';
  $html .= '</tr></table>';

  if (!$goals_by_category) {
    $html .= '<div class="empty">' . htmlspecialchars(t('report_no_goals_period')) . '</div>';
  } else {
    foreach ($goals_by_category as $category => $category_goals) {
    $section_title = (string)$category;

    $html .= '<div class="section">';
    $html .= '<div class="section-bar">' . htmlspecialchars($section_title) . '</div>';

    $html .= '<table class="report-table">';
    $html .= '<thead><tr>';
    $html .= '<th class="name-col">' . htmlspecialchars(t('report_activity')) . '</th>';
    $html .= '<th>' . htmlspecialchars(t('report_actual')) . '</th>';
    $html .= '<th>' . htmlspecialchars(t('report_goal')) . '</th>';
    $html .= '<th>' . htmlspecialchars(t('report_difference')) . '</th>';
    $html .= '<th>' . htmlspecialchars(t('report_status')) . '</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';

    foreach ($category_goals as $goal) {
      $goal_total = 0.0;
      foreach ($days as $day) {
        $goal_total += (float)($daily_data[$goal['category']][$day] ?? 0);
      }

      $target = (float)$goal['target'];
      $difference = $goal_total - $target;
      [$status, $status_class] = $status_for($goal_total, $target);

      $html .= '<tr>';
      $html .= '<td>' . htmlspecialchars($goal['label']) . '</td>';
      $html .= '<td>' . htmlspecialchars($format_amount($goal_total, (string)$goal['unit'])) . '</td>';
      $html .= '<td>' . htmlspecialchars($format_amount($target, (string)$goal['unit'])) . '</td>';
      $html .= '<td>' . ($difference >= 0 ? '+' : '') . $format_number($difference) . '</td>';
      $html .= '<td class="status ' . $status_class . '">' . htmlspecialchars($status) . '</td>';
      $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    $html .= '</div>';
    }
  }

  if (trim($next_week_notes) !== '') {
    $html .= '<div class="section">';
    $html .= '<div class="section-bar">' . htmlspecialchars(t('report_next_week_title')) . '</div>';
    $html .= '<div class="planning-note">' . nl2br(htmlspecialchars(trim($next_week_notes))) . '</div>';
    $html .= '</div>';
  }

  $html .= '<div class="report-footer">' . htmlspecialchars(t('report_footer')) . '</div>';
  $html .= '</body></html>';

  return $html;
}
