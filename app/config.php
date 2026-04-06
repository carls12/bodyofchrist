<?php
// Configuration for both local (XAMPP) and shared hosting.
//
// You can override any value by setting environment variables, e.g.
//   APP_BASE_URL=/bodyofchrist2
//   DB_HOST=localhost
//   DB_NAME=bodyofchrist2
//   DB_USER=root
//   DB_PASS=secret

$baseUrl = getenv('APP_BASE_URL');
if ($baseUrl === false || $baseUrl === '') {
  // Auto-detect base URL from the current script path.
  // Examples:
  //   /bodyofchrist2/index.php        -> /bodyofchrist2
  //   /public/index.php               -> /public
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  $dir = str_replace('\\', '/', dirname($script));
  $dir = rtrim($dir, '/');
  $baseUrl = ($dir === '' || $dir === '/') ? '' : $dir;
}

return [
  'app_name' => 'BodyOfChrist',
  'base_path' => dirname(__DIR__),
  'base_url' => $baseUrl,
  'timezone' => getenv('APP_TIMEZONE') ?: 'Europe/Berlin',
  // WebRTC signaling (WebSocket URL)
  'rtc_signaling_url' => (function() {
    $env = getenv('RTC_SIGNALING_URL');
    if ($env) return $env;
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (str_contains($host, ':')) {
      $host = explode(':', $host)[0];
    }
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'wss' : 'ws';
    return $scheme . '://' . $host . ':3001';
  })(),
  // ICE servers (STUN only by default)
  'rtc_ice_servers' => [
    ['urls' => 'stun:stun.l.google.com:19302'],
    ['urls' => 'stun:stun1.l.google.com:19302'],
  ],
  'db' => [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'name' => getenv('DB_NAME') ?: 'bodyofchrist2',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
  ],
  'super_admin_email' => getenv('SUPER_ADMIN_EMAIL') ?: 'carlsontantoh25@gmail.com',
  // Optional: comma-separated list of additional super admins
  'super_admin_emails' => getenv('SUPER_ADMIN_EMAILS') ?: '',
];
