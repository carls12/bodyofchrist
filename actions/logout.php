<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php';
logout_user();
flash_set('success', t('flash_logged_out'));
redirect(base_url('login'));
