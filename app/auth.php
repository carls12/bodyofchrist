<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function auth_user(): ?array {
  return $_SESSION['user'] ?? null;
}
function auth_check(): bool { return auth_user() !== null; }

function require_auth() {
  if (!auth_check()) redirect(base_url('login'));
  require_two_factor_if_needed();
}

function is_main_admin(): bool {
  $cfg = require __DIR__ . '/config.php';
  $u = auth_user();
  if (!$u) return false;
  $email = strtolower($u['email']);
  if ($email === strtolower($cfg['super_admin_email'])) return true;
  $list = array_filter(array_map('trim', explode(',', $cfg['super_admin_emails'] ?? '')));
  foreach ($list as $e) {
    if ($email === strtolower($e)) return true;
  }
  return false;
}

function is_regional_leader(): bool {
  $u = auth_user();
  return $u && !empty($u['is_regional_leader']);
}

function is_national_leader(): bool {
  $u = auth_user();
  return $u && !empty($u['is_national_leader']);
}

function active_assembly_id(): ?int {
  if (!auth_check()) return null;
  db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
  db()->exec("ALTER TABLE assembly_members MODIFY COLUMN status ENUM('pending','invited','active','inactive') NOT NULL DEFAULT 'active'");
  $u = auth_user();
  $stmt = db()->prepare("SELECT assembly_id FROM assembly_members WHERE user_id=? AND status='active' AND active=1 ORDER BY updated_at DESC, id DESC LIMIT 1");
  $stmt->execute([$u['id']]);
  $row = $stmt->fetch();
  return $row ? (int)$row['assembly_id'] : null;
}

function user_region(): ?string {
  $u = auth_user();
  $r = $u['region'] ?? null;
  return $r !== '' ? $r : null;
}

function user_country(): ?string {
  $u = auth_user();
  $c = $u['country'] ?? null;
  return $c !== '' ? $c : null;
}

function refresh_auth_user() {
  if (!auth_check()) return;
  db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_regional_leader TINYINT(1) NOT NULL DEFAULT 0");
  db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_national_leader TINYINT(1) NOT NULL DEFAULT 0");
  db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL");
  db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS country VARCHAR(120) NULL");
  db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL");
  db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0");
  db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_2fa_at DATETIME NULL");
  $u = auth_user();
  $stmt = db()->prepare("SELECT id,name,email,locale,is_leader,is_regional_leader,is_national_leader,region,country,totp_enabled,last_2fa_at,avatar_path FROM users WHERE id=?");
  $stmt->execute([$u['id']]);
  $fresh = $stmt->fetch();
  if ($fresh) $_SESSION['user'] = $fresh;
}

function login_user(array $userRow) {
  $_SESSION['user'] = [
    'id' => (int)$userRow['id'],
    'name' => $userRow['name'],
    'email' => $userRow['email'],
    'locale' => $userRow['locale'] ?? 'de',
    'is_leader' => (int)($userRow['is_leader'] ?? 0),
    'is_regional_leader' => (int)($userRow['is_regional_leader'] ?? 0),
    'is_national_leader' => (int)($userRow['is_national_leader'] ?? 0),
    'region' => $userRow['region'] ?? null,
    'country' => $userRow['country'] ?? null,
    'totp_enabled' => (int)($userRow['totp_enabled'] ?? 0),
    'last_2fa_at' => $userRow['last_2fa_at'] ?? null,
    'avatar_path' => $userRow['avatar_path'] ?? null,
  ];
}

function requires_two_factor(): bool {
  $u = auth_user();
  if (!$u) return false;
  return !empty($u['is_leader']) || !empty($u['is_regional_leader']) || !empty($u['is_national_leader']);
}

function two_factor_ok_for_today(): bool {
  $u = auth_user();
  if (!$u) return false;
  $last = $u['last_2fa_at'] ?? null;
  if (!$last) return false;
  $lastDate = (new DateTimeImmutable($last))->format('Y-m-d');
  $today = (new DateTimeImmutable('today'))->format('Y-m-d');
  return $lastDate === $today;
}

function require_two_factor_if_needed() {
  if (!requires_two_factor()) return;
  $u = auth_user();
  $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
  if (str_contains($path, '/two-factor')) return;
  if (str_contains($path, '/action/setup-two-factor')) return;
  if (str_contains($path, '/action/verify-two-factor')) return;
  if (!empty($u['totp_enabled'])) {
    if (!two_factor_ok_for_today()) redirect(base_url('two-factor'));
  } else {
    redirect(base_url('two-factor-setup'));
  }
}

function logout_user() { unset($_SESSION['user']); }
