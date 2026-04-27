<?php
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/auth.php';
$cfg = require __DIR__ . '/../app/config.php';
$appName = $cfg['app_name'];
$presenceGroups = [];
if (auth_check()) {
  require_once __DIR__ . '/../app/db.php';
  db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS chat_enabled TINYINT(1) NOT NULL DEFAULT 1");
  db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS type ENUM('discipleship','assembly') NOT NULL DEFAULT 'assembly'");
  db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
  $stmt = db()->prepare("SELECT a.id FROM assemblies a
    JOIN assembly_members am ON am.assembly_id=a.id
    WHERE am.user_id=? AND am.active=1 AND am.status='active' AND a.chat_enabled=1");
  $stmt->execute([auth_user()['id']]);
  $presenceGroups = array_map(fn($r) => (int)$r['id'], $stmt->fetchAll());
}

$flashSuccess = flash_get('success');
$flashError = flash_get('error');
$reqPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$base = rtrim($cfg['base_url'], '/');
if ($base !== '' && str_starts_with($reqPath, $base)) {
  $reqPath = substr($reqPath, strlen($base));
}
$reqPath = '/' . ltrim($reqPath, '/');
$mobilePrimaryNav = [];
$mobileMoreNav = [];
if (auth_check()) {
  $mobilePrimaryNav = [
    [
      'href' => base_url('dashboard'),
      'active' => ($reqPath==='/' || $reqPath==='/dashboard'),
      'label' => t('nav_home'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9"/><path d="M9 21V9h6v12"/></svg>',
    ],
    [
      'href' => base_url('goals'),
      'active' => ($reqPath==='/goals'),
      'label' => t('nav_goals'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>',
    ],
    [
      'href' => base_url('prayer'),
      'active' => ($reqPath==='/prayer'),
      'label' => t('nav_timer'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2s4 4 4 8a4 4 0 0 1-8 0c0-4 4-8 4-8z"/><path d="M5 22h14"/></svg>',
    ],
  ];

  $mobileMoreNav = [
    [
      'href' => base_url('chat'),
      'active' => ($reqPath==='/chat'),
      'label' => t('nav_chat'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>',
    ],
    [
      'href' => base_url('bible'),
      'active' => ($reqPath==='/bible'),
      'label' => t('nav_bible'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h12a3 3 0 0 1 3 3v13"/><path d="M4 4v15a2 2 0 0 0 2 2h13"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/></svg>',
    ],
    [
      'href' => base_url('progress'),
      'active' => ($reqPath==='/progress'),
      'label' => t('nav_progress'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19h16"/><path d="M6 16l4-4 3 3 5-6"/></svg>',
    ],
    [
      'href' => base_url('goal-history'),
      'active' => ($reqPath==='/goal-history'),
      'label' => t('nav_goal_history'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 6.3L3 8"/><path d="M12 7v5l3 2"/></svg>',
    ],
    [
      'href' => base_url('calendar'),
      'active' => ($reqPath==='/calendar'),
      'label' => t('nav_calendar'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>',
    ],
    [
      'href' => base_url('daily-planner'),
      'active' => ($reqPath==='/daily-planner'),
      'label' => t('nav_daily_planner'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M5 6h.01"/><path d="M5 12h.01"/><path d="M5 18h.01"/></svg>',
    ],
    [
      'href' => base_url('assemblies'),
      'active' => ($reqPath==='/assemblies' || str_starts_with($reqPath, '/assemblies')),
      'label' => t('nav_assemblies'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-7a4 4 0 0 0-8 0v7"/><path d="M7 21h10"/><path d="M12 3a4 4 0 1 1-4 4 4 4 0 0 1 4-4z"/></svg>',
    ],
    [
      'href' => base_url('quizzes'),
      'active' => ($reqPath==='/quizzes' || str_starts_with($reqPath, '/quiz')),
      'label' => t('nav_quiz'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
    ],
    [
      'href' => base_url('my-reports'),
      'active' => ($reqPath==='/my-reports'),
      'label' => t('nav_my_reports'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19h16"/><path d="M7 16V8"/><path d="M12 16V4"/><path d="M17 16v-6"/></svg>',
    ],
    [
      'href' => base_url('profile'),
      'active' => ($reqPath==='/profile'),
      'label' => t('nav_profile'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    ],
  ];

  if (auth_user()['is_leader']) {
    $mobileMoreNav[] = [
      'href' => base_url('reports'),
      'active' => ($reqPath==='/reports'),
      'label' => t('nav_reports'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19h16"/><path d="M7 16V8"/><path d="M12 16V4"/><path d="M17 16v-6"/></svg>',
    ];
  }
  if (is_main_admin()) {
    $mobileMoreNav[] = [
      'href' => base_url('admin/users'),
      'active' => ($reqPath==='/admin/users'),
      'label' => t('nav_admin'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11h-6"/><path d="M19 8v6"/></svg>',
    ];
  }
  if (is_regional_leader() || is_main_admin()) {
    $mobileMoreNav[] = [
      'href' => base_url('admin/assemblies'),
      'active' => ($reqPath==='/admin/assemblies'),
      'label' => t('admin_assemblies'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-6 9 6"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>',
    ];
  }
  if (is_national_leader() || is_main_admin()) {
    $mobileMoreNav[] = [
      'href' => base_url('admin/national'),
      'active' => ($reqPath==='/admin/national'),
      'label' => t('admin_national_reports'),
      'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/><path d="M6 3v18"/><path d="M12 3v18"/><path d="M18 3v18"/></svg>',
    ];
  }
}
?>
<!doctype html>
<html lang="<?= e($_SESSION['locale'] ?? 'de') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('app')) ?></title>
  <link rel="manifest" href="<?= e(base_url('manifest.json')) ?>">
  <meta name="theme-color" content="#1E3A5F">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    :root{
      --gold:#D4A574;
      --deep-blue:#1E3A5F;
      --cream:#FFF8F0;
      --warm-white:#FEFEFE;
      --ink:#0f1a2b;
      --muted:#5b6b7a;
      --border:rgba(15,26,43,.08);
      --shadow:0 12px 30px rgba(15,26,43,.08);
      --radius:18px;
    }
    body{
      min-height:100vh;
      color:var(--ink);
      background:
        radial-gradient(1000px 600px at 10% -10%, rgba(212,165,116,.35), transparent 60%),
        radial-gradient(900px 500px at 90% -20%, rgba(30,58,95,.25), transparent 55%),
        linear-gradient(135deg, #fffbf4 0%, #ffffff 45%, #f1f6ff 100%);
    }
    .app-shell{
      display:flex;
      min-height:100vh;
      gap:24px;
      padding:24px;
    }
    .app-sidebar{
      width:260px;
      background:rgba(255,255,255,.8);
      border:1px solid var(--border);
      border-radius:24px;
      padding:20px;
      box-shadow:var(--shadow);
      backdrop-filter: blur(6px);
      max-height:calc(100vh - 48px);
      overflow-y:auto;
      overscroll-behavior:contain;
      -webkit-overflow-scrolling:touch;
      display:flex;
      flex-direction:column;
      gap:18px;
    }
    .app-brand{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:700;
      color:var(--deep-blue);
      text-decoration:none;
      font-size:1.1rem;
      letter-spacing:.2px;
    }
    .app-brand-badge{
      width:38px;
      height:38px;
      border-radius:12px;
      background:linear-gradient(135deg, var(--gold), #f1c28c);
      display:grid;
      place-items:center;
      color:#fff;
      font-weight:700;
      box-shadow:0 8px 18px rgba(212,165,116,.35);
    }
    .app-nav{
      display:flex;
      flex-direction:column;
      gap:6px;
    }
    .app-nav-link{
      display:flex;
      align-items:center;
      gap:10px;
      padding:10px 12px;
      border-radius:12px;
      color:var(--ink);
      text-decoration:none;
      font-weight:600;
      transition:all .2s ease;
    }
    .app-nav-link:hover{
      background:#fff;
      box-shadow:0 6px 16px rgba(15,26,43,.08);
      transform:translateY(-1px);
      color:var(--deep-blue);
    }
    .app-nav-link.active{
      background:linear-gradient(135deg, rgba(212,165,116,.2), rgba(30,58,95,.08));
      border:1px solid rgba(212,165,116,.35);
      color:var(--deep-blue);
    }
    .app-nav-icon{
      width:18px;
      height:18px;
      color:var(--deep-blue);
    }
    .app-content{
      flex:1;
      display:flex;
      flex-direction:column;
      gap:18px;
      min-width:0;
    }
    .app-topbar{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
      background:rgba(255,255,255,.85);
      border:1px solid var(--border);
      border-radius:20px;
      padding:12px 16px;
      box-shadow:var(--shadow);
      backdrop-filter: blur(6px);
    }
    .app-topbar .btn{ border-radius:10px; }
    .app-main{
      background:rgba(255,255,255,.8);
      border:1px solid var(--border);
      border-radius:24px;
      padding:22px;
      box-shadow:var(--shadow);
      backdrop-filter: blur(6px);
      min-height:60vh;
    }
    .app-inner{
      max-width:1200px;
      margin:0 auto;
    }
    .card{
      border-radius:var(--radius);
      border:1px solid var(--border);
      box-shadow:var(--shadow);
      background:var(--warm-white);
    }
    .btn-primary{
      background:linear-gradient(135deg, var(--deep-blue), #274a78);
      border:none;
    }
    .btn-outline-primary{
      border-color:var(--deep-blue);
      color:var(--deep-blue);
    }
    .btn-outline-primary:hover{
      background:var(--deep-blue);
      color:#fff;
    }
    .badge-soft{
      background:rgba(212,165,116,.2);
      color:var(--deep-blue);
      border-radius:999px;
      padding:.25rem .6rem;
      font-weight:600;
      font-size:.75rem;
    }
    .alert{
      border-radius:16px;
      border:1px solid var(--border);
      box-shadow:var(--shadow);
    }
    .progress{
      height:10px;
      border-radius:999px;
      background:#edf1f6;
    }
    .progress-bar{
      background:linear-gradient(135deg, var(--gold), #f1c28c);
    }
    .app-bottom-nav{
      display:none;
      position:fixed;
      bottom:0;
      left:0;
      right:0;
      background:rgba(255,255,255,.95);
      border-top:1px solid var(--border);
      padding:8px 10px calc(8px + env(safe-area-inset-bottom));
      box-shadow:0 -8px 24px rgba(15,26,43,.08);
      z-index:1040;
      backdrop-filter: blur(6px);
      justify-content:space-between;
      align-items:stretch;
      gap:8px;
    }
    .app-bottom-nav a{
      flex:1 1 0;
      display:flex;
      flex-direction:column;
      align-items:center;
      gap:4px;
      font-size:.7rem;
      text-decoration:none;
      color:var(--muted);
      font-weight:600;
      min-width:0;
      padding:6px 4px;
      border-radius:12px;
    }
    .app-bottom-nav button{
      flex:1 1 0;
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      gap:4px;
      font-size:.7rem;
      color:var(--muted);
      font-weight:600;
      min-width:0;
      padding:6px 4px;
      border:0;
      background:transparent;
      border-radius:12px;
    }
    .app-bottom-nav a.active,
    .app-bottom-nav button.active{
      color:var(--deep-blue);
      background:rgba(30,58,95,.06);
    }
    .app-bottom-nav svg{
      width:18px;
      height:18px;
    }
    .app-mobile-sheet.offcanvas{
      height:75vh;
      border-top-left-radius:22px;
      border-top-right-radius:22px;
    }
    .app-mobile-sheet .offcanvas-header{
      border-bottom:1px solid var(--border);
    }
    .app-mobile-sheet-list{
      display:grid;
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:10px;
    }
    .app-mobile-sheet-link{
      display:flex;
      align-items:center;
      gap:10px;
      padding:12px;
      border:1px solid var(--border);
      border-radius:12px;
      text-decoration:none;
      color:var(--ink);
      background:#fff;
      font-weight:600;
    }
    .app-mobile-sheet-link.active{
      border-color:rgba(212,165,116,.5);
      background:rgba(212,165,116,.12);
      color:var(--deep-blue);
    }
    .app-mobile-sheet-link svg{
      width:18px;
      height:18px;
      flex:0 0 auto;
    }
    .global-call-toast{
      position:fixed;
      right:16px;
      bottom:16px;
      z-index:1080;
      display:none;
    }
    .global-call-toast.active{ display:block; }
    @media (max-width: 991.98px){
      .app-shell{ padding:14px; }
      .app-sidebar{ display:none; }
      .app-topbar{ position:sticky; top:14px; z-index:5; }
      .app-main{ padding:16px; }
      .app-bottom-nav{ display:flex; }
      .app-content{ padding-bottom:92px; }
    }
    @media (max-width: 575.98px){
      .app-shell{ padding:10px; }
      .app-topbar{ gap:8px; padding:8px 10px; }
      .app-main{ padding:12px; }
      .card{ border-radius:14px; }
      .btn{ padding:.35rem .6rem; font-size:.85rem; }
      .btn-sm{ padding:.25rem .5rem; font-size:.8rem; }
      .h4{ font-size:1.05rem; }
      .h5{ font-size:1rem; }
      .display-6{ font-size:1.6rem; }
      .mb-4{ margin-bottom:1rem !important; }
      .mb-3{ margin-bottom:.75rem !important; }
      .row.g-3{ --bs-gutter-y:.75rem; --bs-gutter-x:.75rem; }
      .app-mobile-sheet-list{ grid-template-columns:1fr; }
    }
  </style>
</head>
<body>
<div class="app-shell">
  <aside class="app-sidebar">
    <a class="app-brand" href="<?= e(base_url('dashboard')) ?>">
      <span class="app-brand-badge">B</span>
      <span><?= e(t('app')) ?></span>
    </a>
    <?php if (auth_check()): ?>
      <nav class="app-nav">
        <a class="app-nav-link <?= $reqPath==='/' || $reqPath==='/dashboard' ? 'active' : '' ?>" href="<?= e(base_url('dashboard')) ?>">
          <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9"/><path d="M9 21V9h6v12"/></svg>
          <?= e(t('nav_dashboard')) ?>
        </a>
        <a class="app-nav-link <?= $reqPath==='/goals' ? 'active' : '' ?>" href="<?= e(base_url('goals')) ?>">
          <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
          <?= e(t('nav_goals')) ?>
        </a>
        <a class="app-nav-link <?= $reqPath==='/goal-history' ? 'active' : '' ?>" href="<?= e(base_url('goal-history')) ?>">
          <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 6.3L3 8"/><path d="M12 7v5l3 2"/></svg>
          <?= e(t('nav_goal_history')) ?>
        </a>
        <a class="app-nav-link <?= $reqPath==='/bible' ? 'active' : '' ?>" href="<?= e(base_url('bible')) ?>">
          <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h12a3 3 0 0 1 3 3v13"/><path d="M4 4v15a2 2 0 0 0 2 2h13"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/></svg>
          <?= e(t('nav_bible')) ?>
        </a>
        <a class="app-nav-link <?= $reqPath==='/prayer' ? 'active' : '' ?>" href="<?= e(base_url('prayer')) ?>">
          <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2s4 4 4 8a4 4 0 0 1-8 0c0-4 4-8 4-8z"/><path d="M5 22h14"/></svg>
          <?= e(t('nav_prayer')) ?>
        </a>
        <a class="app-nav-link <?= $reqPath==='/progress' ? 'active' : '' ?>" href="<?= e(base_url('progress')) ?>">
          <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19h16"/><path d="M6 16l4-4 3 3 5-6"/></svg>
          <?= e(t('nav_progress')) ?>
        </a>
        <a class="app-nav-link <?= $reqPath==='/calendar' ? 'active' : '' ?>" href="<?= e(base_url('calendar')) ?>">
          <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
          <?= e(t('nav_calendar')) ?>
        </a>
        <a class="app-nav-link <?= $reqPath==='/daily-planner' ? 'active' : '' ?>" href="<?= e(base_url('daily-planner')) ?>">
          <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M5 6h.01"/><path d="M5 12h.01"/><path d="M5 18h.01"/></svg>
          <?= e(t('nav_daily_planner')) ?>
        </a>
        <a class="app-nav-link <?= $reqPath==='/assemblies' || str_starts_with($reqPath, '/assemblies') ? 'active' : '' ?>" href="<?= e(base_url('assemblies')) ?>">
          <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-7a4 4 0 0 0-8 0v7"/><path d="M7 21h10"/><path d="M12 3a4 4 0 1 1-4 4 4 4 0 0 1 4-4z"/></svg>
          <?= e(t('nav_assemblies')) ?>
        </a>
        <a class="app-nav-link <?= $reqPath==='/chat' ? 'active' : '' ?>" href="<?= e(base_url('chat')) ?>">
          <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
          <?= e(t('nav_chat')) ?>
        </a>
        <a class="app-nav-link <?= $reqPath==='/quizzes' || str_starts_with($reqPath, '/quiz') ? 'active' : '' ?>" href="<?= e(base_url('quizzes')) ?>">
          <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          <?= e(t('nav_quiz')) ?>
        </a>
        <a class="app-nav-link <?= $reqPath==='/my-reports' ? 'active' : '' ?>" href="<?= e(base_url('my-reports')) ?>">
          <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19h16"/><path d="M7 16V8"/><path d="M12 16V4"/><path d="M17 16v-6"/></svg>
          <?= e(t('nav_my_reports')) ?>
        </a>
        <?php if (auth_user()['is_leader']): ?>
          <a class="app-nav-link <?= $reqPath==='/reports' ? 'active' : '' ?>" href="<?= e(base_url('reports')) ?>">
            <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19h16"/><path d="M7 16V8"/><path d="M12 16V4"/><path d="M17 16v-6"/></svg>
            <?= e(t('nav_reports')) ?>
          </a>
        <?php endif; ?>
        <a class="app-nav-link <?= $reqPath==='/profile' ? 'active' : '' ?>" href="<?= e(base_url('profile')) ?>">
          <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <?= e(t('nav_profile')) ?>
        </a>
        <?php if (is_main_admin()): ?>
          <a class="app-nav-link <?= $reqPath==='/admin/users' ? 'active' : '' ?>" href="<?= e(base_url('admin/users')) ?>">
            <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11h-6"/><path d="M19 8v6"/></svg>
            <?= e(t('nav_admin')) ?>
          </a>
        <?php endif; ?>
        <?php if (is_regional_leader() || is_main_admin()): ?>
          <a class="app-nav-link <?= $reqPath==='/admin/assemblies' ? 'active' : '' ?>" href="<?= e(base_url('admin/assemblies')) ?>">
            <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-6 9 6"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>
            <?= e(t('admin_assemblies')) ?>
          </a>
        <?php endif; ?>
        <?php if (is_national_leader() || is_main_admin()): ?>
          <a class="app-nav-link <?= $reqPath==='/admin/national' ? 'active' : '' ?>" href="<?= e(base_url('admin/national')) ?>">
            <svg class="app-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/><path d="M6 3v18"/><path d="M12 3v18"/><path d="M18 3v18"/></svg>
            <?= e(t('admin_national_reports')) ?>
          </a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </aside>

  <div class="app-content">
    <header class="app-topbar">
      <div class="fw-semibold text-truncate"><?= e(t('app')) ?></div>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <?php if (auth_check()): ?>
          <a class="badge-soft text-decoration-none" href="<?= e(base_url('profile')) ?>"><?= e(auth_user()['name']) ?></a>
          <form method="post" action="<?= e(base_url('action/logout')) ?>" class="m-0">
            <button class="btn btn-sm btn-outline-danger"><?= e(t('btn_logout')) ?></button>
          </form>
        <?php else: ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('login')) ?>"><?= e(t('nav_login')) ?></a>
        <?php endif; ?>
      </div>
    </header>

    <main class="app-main">
      <div class="app-inner">
<?php if ($flashSuccess): ?><div class="alert alert-success border-0 shadow-sm"><?= e($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-danger border-0 shadow-sm"><?= e($flashError) ?></div><?php endif; ?>
