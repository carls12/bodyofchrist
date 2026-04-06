<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$pass = (string)($_POST['password'] ?? '');

if ($name === '' || $email === '' || strlen($pass) < 6) { flash_set('error', t('flash_register_invalid')); redirect(base_url('register')); }

$check = db()->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
$check->execute([$email]);
if ($check->fetch()) { flash_set('error', t('flash_register_exists')); redirect(base_url('register')); }

$hash = password_hash($pass, PASSWORD_DEFAULT);
$locale = $_SESSION['locale'] ?? 'de';

$stmt = db()->prepare("INSERT INTO users(name,email,password_hash,locale,is_leader,created_at,updated_at)
  VALUES(?,?,?,?,0,NOW(),NOW())");
$stmt->execute([$name, $email, $hash, $locale]);

$id = (int)db()->lastInsertId();
login_user(['id'=>$id,'name'=>$name,'email'=>$email,'locale'=>$locale,'is_leader'=>0]);

flash_set('success', t('flash_register_ok'));
redirect(base_url('dashboard'));
