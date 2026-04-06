<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

$uid = auth_user()['id'];
$code = strtoupper(trim($_POST['code'] ?? ''));

db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE assembly_members MODIFY COLUMN status ENUM('pending','invited','active','inactive') NOT NULL DEFAULT 'active'");

if ($code === '') { flash_set('error', t('flash_code_missing')); redirect(base_url('assemblies')); }

$stmt = db()->prepare("SELECT * FROM assemblies WHERE join_code=? LIMIT 1");
$stmt->execute([$code]);
$assembly = $stmt->fetch();
if (!$assembly) { flash_set('error', t('flash_code_invalid2')); redirect(base_url('assemblies')); }

// Do not deactivate other groups; users can be in multiple groups.

$check = db()->prepare("SELECT id FROM assembly_members WHERE assembly_id=? AND user_id=? LIMIT 1");
$check->execute([(int)$assembly['id'], $uid]);
if ($row = $check->fetch()) {
  db()->prepare("UPDATE assembly_members SET active=0, status='pending', role='member', updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
} else {
  db()->prepare("INSERT INTO assembly_members(assembly_id,user_id,role,status,active,created_at,updated_at)
    VALUES(?,?, 'member', 'pending', 0, NOW(), NOW())")->execute([(int)$assembly['id'], $uid]);
}

flash_set('success', t('flash_joined'));
redirect(base_url('assemblies'));
