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
