<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/helpers.php';

$room = trim($_GET['room'] ?? '');
if ($room === '') {
  $room = 'room-' . bin2hex(random_bytes(4));
}
$audioOnly = isset($_GET['audio']) && $_GET['audio'] === '1';
$me = auth_user();

include __DIR__ . '/_layout_top.php';
?>
<style>
  .meet-wrap{ min-height:70vh; }
  #meet{ width:100%; min-height:70vh; }
</style>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
  <div>
    <div class="text-muted small"><?= e(t('nav_chat')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('meet_title')) ?></h2>
    <div class="text-muted"><?= e(t('meet_sub')) ?></div>
  </div>
  <a class="btn btn-outline-secondary" href="<?= e(base_url('chat')) ?>"><?= e(t('meet_back')) ?></a>
</div>

<div class="card meet-wrap">
  <div class="card-body">
    <div class="mb-2 text-muted small"><?= e(t('meet_room_label')) ?>: <span class="fw-semibold"><?= e($room) ?></span></div>
    <div id="meet"></div>
  </div>
</div>

<script src="https://meet.jit.si/external_api.js"></script>
<script>
  (function(){
    var domain = 'meet.jit.si';
    var options = {
      roomName: <?= json_encode($room) ?>,
      parentNode: document.querySelector('#meet'),
      userInfo: { displayName: <?= json_encode($me['name'] ?? 'User') ?> },
      configOverwrite: {
        prejoinPageEnabled: false,
        startWithVideoMuted: <?= $audioOnly ? 'true' : 'false' ?>,
        startAudioOnly: <?= $audioOnly ? 'true' : 'false' ?>
      }
    };
    new JitsiMeetExternalAPI(domain, options);
  })();
</script>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
