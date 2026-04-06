<?php
require_once __DIR__ . '/helpers.php';

function bootstrap_app() {
  $cfg = require __DIR__ . '/config.php';
  date_default_timezone_set($cfg['timezone']);

  $autoload = __DIR__ . '/../vendor/autoload.php';
  if (file_exists($autoload)) {
    require_once $autoload;
  }

  if (session_status() !== PHP_SESSION_ACTIVE) session_start();

  if (isset($_SESSION['user']['locale']) && $_SESSION['user']['locale'] !== '') {
    $_SESSION['locale'] = $_SESSION['user']['locale'];
  }

  if (!isset($_SESSION['locale'])) {
    $browser = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $lang = strtolower(substr($browser, 0, 2));
    $_SESSION['locale'] = in_array($lang, ['de','en','fr'], true) ? $lang : 'de';
  }
}

function set_locale(string $locale) {
  if (!in_array($locale, ['de','en','fr'], true)) $locale = 'de';
  $_SESSION['locale'] = $locale;
}
