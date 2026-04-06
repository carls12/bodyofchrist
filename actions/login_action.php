<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$email = strtolower(trim($_POST['email'] ?? ''));
$pass = (string)($_POST['password'] ?? '');

if ($email === '' || $pass === '') { flash_set('error', t('flash_login_fields')); redirect(base_url('login')); }

$stmt = db()->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($pass, $user['password_hash'])) { flash_set('error', t('flash_login_failed')); redirect(base_url('login')); }

login_user($user);
$_SESSION['locale'] = $user['locale'] ?: ($_SESSION['locale'] ?? 'de');
flash_set('success', t('flash_login_ok'));
redirect(base_url('dashboard'));
