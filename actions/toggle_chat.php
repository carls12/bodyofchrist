<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS chat_enabled TINYINT(1) NOT NULL DEFAULT 1");

$assemblyId = (int)($_POST['assembly_id'] ?? 0);
if ($assemblyId <= 0) {
  flash_set('error', t('flash_group_missing'));
  redirect(base_url('assemblies'));
}

$stmt = db()->prepare("SELECT leader_id, chat_enabled FROM assemblies WHERE id=? LIMIT 1");
$stmt->execute([$assemblyId]);
$row = $stmt->fetch();
if (!$row || (int)$row['leader_id'] !== (int)auth_user()['id']) {
  flash_set('error', t('flash_forbidden'));
  redirect(base_url('assemblies'));
}

$new = (int)!((int)$row['chat_enabled']);
db()->prepare("UPDATE assemblies SET chat_enabled=?, updated_at=NOW() WHERE id=?")
  ->execute([$new, $assemblyId]);

flash_set('success', t('flash_chat_toggled'));
redirect(base_url('assemblies/show?id='.(int)$assemblyId));
