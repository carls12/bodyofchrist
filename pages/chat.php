<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';
$cfg = require __DIR__ . '/../app/config.php';
$rtcUrl = $cfg['rtc_signaling_url'] ?? '';
$iceServers = $cfg['rtc_ice_servers'] ?? [];

db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS chat_enabled TINYINT(1) NOT NULL DEFAULT 1");
db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS type ENUM('discipleship','assembly') NOT NULL DEFAULT 'assembly'");
db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");

$me = auth_user()['id'];

$users = db()->prepare("SELECT u.id,u.name,u.email,u.is_leader, MAX(m.id) AS last_id
  FROM users u
  JOIN messages m ON ((m.from_user_id=u.id AND m.to_user_id=?) OR (m.from_user_id=? AND m.to_user_id=u.id))
  WHERE u.id<>?
  GROUP BY u.id
  ORDER BY last_id DESC
  LIMIT 50");
$users->execute([$me, $me, $me]);
$userList = $users->fetchAll();

$hasAssemblyStmt = db()->prepare("SELECT a.id FROM assemblies a
  JOIN assembly_members am ON am.assembly_id=a.id
  WHERE am.user_id=? AND am.active=1 AND am.status='active' AND a.type='assembly' LIMIT 1");
$hasAssemblyStmt->execute([$me]);
$hasAssembly = (bool)$hasAssemblyStmt->fetch();

if ($hasAssembly) {
  $groups = db()->prepare("SELECT a.id,a.name,a.type FROM assemblies a
    JOIN assembly_members am ON am.assembly_id=a.id
    WHERE am.user_id=? AND am.active=1 AND am.status='active' AND a.chat_enabled=1 ORDER BY a.name ASC");
  $groups->execute([$me]);
  $groupList = $groups->fetchAll();
} else {
  $groupList = [];
}

$activeId = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$activeGroupId = isset($_GET['gid']) ? (int)$_GET['gid'] : 0;

$activeUser = null;
foreach ($userList as $u) if ((int)$u['id'] === $activeId) $activeUser = $u;
if ($activeId > 0 && !$activeUser) {
  $stmt = db()->prepare("SELECT id,name,email,is_leader FROM users WHERE id=? LIMIT 1");
  $stmt->execute([$activeId]);
  $activeUser = $stmt->fetch();
}

$activeGroup = null;
foreach ($groupList as $g) if ((int)$g['id'] === $activeGroupId) $activeGroup = $g;

$messages = [];
$aid = active_assembly_id();
if ($activeId > 0) {
  $stmt = db()->prepare("SELECT * FROM messages
    WHERE (from_user_id=? AND to_user_id=?) OR (from_user_id=? AND to_user_id=?)
    AND (assembly_id=? OR assembly_id IS NULL)
    ORDER BY id ASC LIMIT 200");
  $stmt->execute([$me, $activeId, $activeId, $me, $aid]);
  $messages = $stmt->fetchAll();

  db()->prepare("UPDATE messages SET is_read=1, read_at=NOW()
    WHERE to_user_id=? AND from_user_id=? AND is_read=0 AND (assembly_id=? OR assembly_id IS NULL)")
    ->execute([$me, $activeId, $aid]);
}

$groupMessages = [];
if ($activeGroupId > 0) {
  $stmt = db()->prepare("SELECT gm.*, u.name FROM group_messages gm
    JOIN users u ON u.id=gm.user_id
    WHERE gm.assembly_id=? ORDER BY gm.id ASC LIMIT 300");
  $stmt->execute([$activeGroupId]);
  $groupMessages = $stmt->fetchAll();
}

function initials(string $name): string {
  $parts = preg_split('/\s+/', trim($name));
  $first = strtoupper(substr($parts[0] ?? '', 0, 1));
  $last = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
  return trim($first . $last);
}

function human_day(string $dt): string {
  $d = new DateTimeImmutable($dt);
  $today = new DateTimeImmutable('today');
  $diffDays = (int)$today->diff($d->setTime(0,0))->format('%r%a');
  $loc = $_SESSION['locale'] ?? 'de';
  $todayLabel = ['de' => 'Heute', 'en' => 'Today', 'fr' => 'Aujourd hui'][$loc] ?? 'Heute';
  $yLabel = ['de' => 'Gestern', 'en' => 'Yesterday', 'fr' => 'Hier'][$loc] ?? 'Gestern';
  $days = [
    'de' => ['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'],
    'en' => ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],
    'fr' => ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'],
  ];
  if ($diffDays === 0) return $todayLabel;
  if ($diffDays === -1) return $yLabel;
  if ($diffDays >= -6 && $diffDays <= -2) {
    $idx = (int)$d->format('N') - 1;
    return $days[$loc][$idx] ?? $days['de'][$idx];
  }
  return $d->format('d.m.Y');
}

function human_time(string $dt): string {
  $d = new DateTimeImmutable($dt);
  return $d->format('H:i');
}

function linkify_text(string $text): string {
  $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
  return preg_replace_callback('~(https?://[^\s]+)~', function($m){
    $url = $m[1];
    $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    return '<a href="' . $safe . '" target="_blank" rel="noopener">' . $safe . '</a>';
  }, $escaped);
}

$hasActive = $activeGroup || $activeUser;
include __DIR__ . '/_layout_top.php';
?>
<style>
  .chat-list .list-group-item{
    border:0;
    border-radius:12px;
    margin-bottom:6px;
    background:rgba(255,255,255,.7);
  }
  .chat-list .list-group-item.active{
    background:linear-gradient(135deg, rgba(212,165,116,.25), rgba(30,58,95,.12));
    color:#1E3A5F;
  }
  .chat-panel{
    background:
      radial-gradient(600px 320px at 5% -10%, rgba(212,165,116,.2), transparent 60%),
      radial-gradient(600px 320px at 95% -10%, rgba(30,58,95,.15), transparent 60%),
      #fff;
  }
  .chat-bubble{ max-width:75%; }
  .chat-text{ white-space:pre-wrap; }
  .chat-bubble.mine{
    background:linear-gradient(135deg, #1E3A5F, #274a78);
    color:#fff;
  }
  .chat-bubble.other{ background:#f3f5f8; color:#0f1a2b; }
  .chat-meta{ font-size:.78rem; opacity:.75; }
  .chat-name{ font-weight:600; font-size:.8rem; margin-bottom:2px; }
  .search-hint{ font-size:.75rem; color:#6b7c8f; }
  .admin-btn{ margin-left:auto; }
  .call-overlay{
    position:fixed; inset:0; background:rgba(9,16,28,.65);
    display:none; align-items:center; justify-content:center; z-index:1060;
  }
  .call-overlay.active{ display:flex; }
  .call-card{
    width:min(980px, 96vw); background:#0f172a; color:#f8fafc;
    border-radius:16px; padding:16px; box-shadow:0 20px 50px rgba(0,0,0,.35);
  }
  .call-header{ display:flex; align-items:center; justify-content:space-between; gap:12px; }
  .call-title{ font-weight:600; }
  .call-body{ margin-top:10px; display:grid; grid-template-columns:1fr; gap:10px; }
  .remote-grid{
    display:grid; gap:10px;
    grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
  }
  .call-video{
    background:#111827; width:100%; aspect-ratio:16/9; border-radius:12px;
    object-fit:cover;
  }
  .call-local{
    width:180px; aspect-ratio:16/9; border-radius:10px; object-fit:cover;
    border:2px solid rgba(255,255,255,.2);
  }
  .call-controls{ display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
  .incoming-call{
    position:fixed; right:16px; bottom:16px; z-index:1070; display:none;
  }
  .incoming-call.active{ display:block; }
  .chat-back-btn{ display:none; }
  @media (max-width: 991.98px){
    .chat-page{ display:flex; flex-direction:column; gap:12px; }
    .chat-list-col, .chat-panel-col{ width:100%; }
    .chat-page.has-active .chat-list-col{ display:none; }
    .chat-page.has-active .chat-panel-col{ display:block; }
    .chat-page.no-active .chat-panel-col{ display:none; }
    .chat-back-btn{ display:inline-flex; }
    .chat-panel-card{ height:calc(100vh - 210px); }
    .chat-panel-card .card-body{ height:100%; }
    .chat-panel-wrap{ min-height:0; }
  }
</style>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('nav_chat')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('chat_title')) ?></h2>
    <div class="text-muted"><?= e(t('chat_sub')) ?></div>
  </div>
</div>

<div class="row g-3 chat-page <?= $hasActive ? 'has-active' : 'no-active' ?>">
  <div class="col-lg-4 chat-list-col">
    <div class="card h-100">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('chat_conversations')) ?></div>
        <input class="form-control form-control-sm mb-2" id="chatSearch" placeholder="<?= e(t('chat_search')) ?>">
        <div class="list-group list-group-flush chat-list" id="chatSearchResults" style="display:none;"></div>
        <div class="search-hint mb-2"><?= e(t('chat_search_hint')) ?></div>

        <div class="text-muted small mb-1"><?= e(t('chat_direct')) ?></div>
        <?php if (!$userList): ?>
          <div class="text-muted"><?= e(t('chat_no_users')) ?></div>
        <?php else: ?>
          <div class="list-group list-group-flush chat-list" id="userList">
            <?php foreach ($userList as $u): ?>
              <?php $ini = initials($u['name']); ?>
              <div class="list-group-item d-flex align-items-center gap-2 chat-user-item"
                   data-name="<?= e(strtolower($u['name'])) ?>">
                <a class="flex-grow-1 text-decoration-none <?= (int)$u['id']===$activeId ? 'active' : '' ?>"
                   href="<?= e(base_url('chat?uid='.(int)$u['id'])) ?>">
                <div class="fw-semibold"><?= e($u['name']) ?></div>
                <div class="small text-muted"><?= e($u['email']) ?></div>
                </a>
                <?php if (is_main_admin()): ?>
                  <form method="post" action="<?= e(base_url('action/toggle-leader')) ?>" class="m-0 admin-btn">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-sm <?= (int)$u['is_leader'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                      <?= (int)$u['is_leader'] ? e(t('leader_remove')) : e(t('leader_make')) ?>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="text-muted small mt-3 mb-1"><?= e(t('chat_groups')) ?></div>
        <?php if (!$groupList): ?>
          <div class="text-muted"><?= e($hasAssembly ? t('chat_no_groups') : t('chat_need_assembly')) ?></div>
        <?php else: ?>
          <div class="list-group list-group-flush chat-list">
            <?php foreach ($groupList as $g): ?>
              <a class="list-group-item list-group-item-action <?= (int)$g['id']===$activeGroupId ? 'active' : '' ?>"
                 href="<?= e(base_url('chat?gid='.(int)$g['id'])) ?>">
                <div class="fw-semibold"><?= e($g['name']) ?></div>
                <div class="small text-muted"><?= e($g['type']==='discipleship' ? t('groups_type_discipleship') : t('groups_type_assembly')) ?> • <?= e(t('chat_group')) ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8 chat-panel-col">
    <div class="card h-100 chat-panel-card">
      <div class="card-body d-flex flex-column chat-panel-wrap" style="min-height:420px;">
        <?php if ($activeGroup): ?>
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <div class="d-flex align-items-center gap-2">
              <a class="btn btn-sm btn-outline-secondary chat-back-btn" href="<?= e(base_url('chat')) ?>"><?= e(t('btn_back')) ?></a>
              <div class="fw-semibold"><?= e($activeGroup['name']) ?> (<?= e(t('chat_group')) ?>)</div>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-outline-secondary btn-sm" id="callGroupVideo"><?= e(t('chat_video_call')) ?></button>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="callGroupAudio"><?= e(t('chat_voice_call')) ?></button>
            </div>
          </div>
          <div class="flex-grow-1 border rounded p-3 mb-3 chat-panel" style="overflow:auto;" data-last-id="<?= (int)($groupMessages ? end($groupMessages)['id'] : 0) ?>">
            <?php if (!$groupMessages): ?>
              <div class="text-muted"><?= e(t('chat_none')) ?></div>
            <?php else: ?>
              <?php foreach ($groupMessages as $m): ?>
                <?php $mine = (int)$m['user_id'] === $me; ?>
                <div class="d-flex mb-2 <?= $mine ? 'justify-content-end' : 'justify-content-start' ?>">
                  <div class="chat-bubble px-3 py-2 rounded <?= $mine ? 'mine' : 'other' ?>">
                    <div class="chat-name"><?= $mine ? e(t('chat_you')) : e($m['name']) ?></div>
                    <div class="chat-text"><?= linkify_text($m['content']) ?></div>
                    <div class="chat-meta mt-1"><?= e(human_day($m['created_at'])) ?> • <?= e(human_time($m['created_at'])) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <form method="post" action="<?= e(base_url('action/send-group-message')) ?>" class="d-flex gap-2" id="groupSendForm">
            <input type="hidden" name="assembly_id" value="<?= (int)$activeGroupId ?>">
            <textarea class="form-control" name="content" rows="2" placeholder="<?= e(t('chat_placeholder')) ?>" required></textarea>
            <button class="btn btn-primary" type="submit"><?= e(t('chat_send')) ?></button>
          </form>

        <?php elseif ($activeUser): ?>
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <div class="d-flex align-items-center gap-2">
              <a class="btn btn-sm btn-outline-secondary chat-back-btn" href="<?= e(base_url('chat')) ?>"><?= e(t('btn_back')) ?></a>
              <div class="fw-semibold"><?= e($activeUser['name']) ?></div>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-outline-secondary btn-sm" id="callDirectVideo"><?= e(t('chat_video_call')) ?></button>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="callDirectAudio"><?= e(t('chat_voice_call')) ?></button>
            </div>
          </div>
          <div class="flex-grow-1 border rounded p-3 mb-3 chat-panel" style="overflow:auto;" data-last-id="<?= (int)($messages ? end($messages)['id'] : 0) ?>">
            <?php if (!$messages): ?>
              <div class="text-muted"><?= e(t('chat_none')) ?></div>
            <?php else: ?>
              <?php foreach ($messages as $m): ?>
                <?php $mine = (int)$m['from_user_id'] === $me; ?>
                <div class="d-flex mb-2 <?= $mine ? 'justify-content-end' : 'justify-content-start' ?>">
                  <div class="chat-bubble px-3 py-2 rounded <?= $mine ? 'mine' : 'other' ?>">
                    <div class="chat-name"><?= $mine ? e(t('chat_you')) : e($activeUser['name']) ?></div>
                    <div class="chat-text"><?= linkify_text($m['content']) ?></div>
                    <div class="chat-meta mt-1"><?= e(human_day($m['created_at'])) ?> • <?= e(human_time($m['created_at'])) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <form method="post" action="<?= e(base_url('action/send-message')) ?>" class="d-flex gap-2" id="directSendForm">
            <input type="hidden" name="to_user_id" value="<?= (int)$activeId ?>">
            <textarea class="form-control" name="content" rows="2" placeholder="<?= e(t('chat_placeholder')) ?>" required></textarea>
            <button class="btn btn-primary" type="submit"><?= e(t('chat_send')) ?></button>
          </form>
        <?php else: ?>
          <div class="text-muted"><?= e(t('chat_pick')) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="call-overlay" id="callOverlay">
  <div class="call-card">
    <div class="call-header">
      <div class="call-title" id="callTitle"><?= e(t('call_connecting')) ?></div>
      <button class="btn btn-sm btn-outline-light" id="callEndBtn"><?= e(t('call_end')) ?></button>
    </div>
    <div class="call-body">
      <div class="remote-grid" id="remoteGrid"></div>
      <div class="d-flex align-items-center gap-2">
        <video class="call-local" id="localVideo" autoplay muted playsinline></video>
        <div class="text-muted small" id="localLabel"><?= e(t('call_you')) ?></div>
      </div>
    </div>
    <div class="call-controls">
      <button class="btn btn-sm btn-outline-light" id="callMuteBtn"><?= e(t('call_mute')) ?></button>
      <button class="btn btn-sm btn-outline-light" id="callCamBtn"><?= e(t('call_cam_off')) ?></button>
    </div>
  </div>
</div>

<div class="incoming-call" id="incomingCall">
  <div class="card">
    <div class="card-body">
      <div class="fw-semibold mb-1" id="incomingText"><?= e(t('call_incoming')) ?></div>
      <div class="d-flex gap-2 mt-2">
        <button class="btn btn-sm btn-success" id="incomingAccept"><?= e(t('call_accept')) ?></button>
        <button class="btn btn-sm btn-outline-danger" id="incomingDecline"><?= e(t('call_decline')) ?></button>
      </div>
    </div>
  </div>
</div>

<script>
  var input = document.getElementById('chatSearch');
  var results = document.getElementById('chatSearchResults');
  if (input) {
    input.addEventListener('input', function(){
      var q = input.value.trim().toLowerCase();
      if (q.length < 2) {
        if (results) results.style.display = 'none';
        return;
      }
      fetch('<?= e(base_url('action/search-users')) ?>?q=' + encodeURIComponent(q), { headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(function(res){
          if (!results) return;
          results.innerHTML = '';
          if (!res || !res.items || res.items.length === 0) {
            results.style.display = 'none';
            return;
          }
          res.items.forEach(function(u){
            var a = document.createElement('a');
            a.className = 'list-group-item list-group-item-action';
            a.href = '<?= e(base_url('chat?uid=')) ?>' + u.id;
            a.innerHTML = '<div class="fw-semibold">' + u.name + '</div><div class="small text-muted">' + (u.email || '') + '</div>';
            results.appendChild(a);
          });
          results.style.display = '';
        });
    });
  }

  function appendMessage(container, msg) {
    var wrap = document.createElement('div');
    wrap.className = 'd-flex mb-2 ' + (msg.mine ? 'justify-content-end' : 'justify-content-start');
    var bubble = document.createElement('div');
    bubble.className = 'chat-bubble px-3 py-2 rounded ' + (msg.mine ? 'mine' : 'other');
    var name = document.createElement('div');
    name.className = 'chat-name';
    name.textContent = msg.name;
    var body = document.createElement('div');
    body.className = 'chat-text';
    body.innerHTML = (msg.content || '').replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
    var meta = document.createElement('div');
    meta.className = 'chat-meta mt-1';
    meta.textContent = msg.day + ' • ' + msg.time;
    bubble.appendChild(name);
    bubble.appendChild(body);
    bubble.appendChild(meta);
    wrap.appendChild(bubble);
    container.appendChild(wrap);
    container.scrollTop = container.scrollHeight;
  }

  function setupForm(formId, url, extra) {
    var form = document.getElementById(formId);
    if (!form) return;
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var sendBtn = form.querySelector('button[type="submit"]');
      if (sendBtn && sendBtn.disabled) return;
      if (sendBtn) sendBtn.disabled = true;
      var fd = new FormData(form);
      if (extra) Object.keys(extra).forEach(function(k){ fd.set(k, extra[k]); });
      fetch(url, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(function(res){
          if (!res || !res.ok) return;
          var panel = document.querySelector('.chat-panel');
          if (panel && res.message) {
            appendMessage(panel, res.message);
            if (res.message.id && res.message.id > lastId) lastId = res.message.id;
          }
          var ta = form.querySelector('textarea');
          if (ta) ta.value = '';
        })
        .catch(function(){})
        .finally(function(){
          if (sendBtn) sendBtn.disabled = false;
        });
    });
  }

  var panel = document.querySelector('.chat-panel');
  var lastId = panel ? parseInt(panel.getAttribute('data-last-id') || '0', 10) : 0;

  function poll(url, extraKey, extraVal) {
    if (!panel) return;
    var params = new URLSearchParams();
    if (extraVal) params.set(extraKey, extraVal);
    params.set('since_id', lastId || 0);
    fetch(url + '?' + params.toString(), { headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(r => r.json())
      .then(function(res){
        if (!res || !res.items) return;
        res.items.forEach(function(m){
          appendMessage(panel, m);
          if (m.id > lastId) lastId = m.id;
        });
      });
  }

  setInterval(function(){
    var uid = <?= (int)$activeId ?>;
    var gid = <?= (int)$activeGroupId ?>;
    if (gid > 0) {
      poll('<?= e(base_url('action/fetch-group-messages')) ?>', 'gid', gid);
    } else if (uid > 0) {
      poll('<?= e(base_url('action/fetch-messages')) ?>', 'uid', uid);
    }
  }, 5000);

  var rtcSignalingUrl = <?= json_encode($rtcUrl) ?>;
  var rtcIceServers = <?= json_encode($iceServers) ?>;
  var myUserId = <?= (int)$me ?>;
  var myUserName = <?= json_encode(auth_user()['name'] ?? 'User') ?>;
  var activeUserId = <?= (int)$activeId ?>;
  var activeGroupId = <?= (int)$activeGroupId ?>;

  var ws = null;
  var wsReady = false;
  var currentRoom = null;
  var currentKind = 'video';
  var localStream = null;
  var peerConnections = {};
  var pendingInvite = null;

  function ensureWs() {
    if (!rtcSignalingUrl) return;
    if (ws && (ws.readyState === 0 || ws.readyState === 1)) return;
    ws = new WebSocket(rtcSignalingUrl);
    ws.addEventListener('open', function(){
      wsReady = true;
      wsSend({ type:'hello', userId: myUserId, name: myUserName });
      if (activeGroupId) wsSend({ type:'join', room: 'presence:grp-' + activeGroupId });
    });
    ws.addEventListener('close', function(){ wsReady = false; });
    ws.addEventListener('message', function(ev){
      var msg = {};
      try { msg = JSON.parse(ev.data); } catch(e) { return; }
      handleWs(msg);
    });
  }

  function wsSend(obj) {
    if (!ws || ws.readyState !== 1) return;
    ws.send(JSON.stringify(obj));
  }

  function handleWs(msg) {
    if (msg.type === 'call_invite') {
      if (currentRoom) return;
      pendingInvite = msg;
      showIncoming(msg.fromName, msg.kind);
      return;
    }
    if (msg.type === 'call_cancel') {
      hideIncoming();
      return;
    }
    if (msg.type === 'call_end') {
      if (msg.room && msg.room === currentRoom) endCall(false);
      return;
    }
    if (msg.type === 'peers' && msg.room === currentRoom) {
      (msg.peers || []).forEach(function(p){
        if (p.userId === myUserId) return;
        ensurePeer(p.userId, p.name);
        if (shouldOffer(p.userId)) makeOffer(p.userId);
      });
      return;
    }
    if (msg.type === 'peer_joined' && msg.room === currentRoom) {
      if (msg.userId === myUserId) return;
      ensurePeer(msg.userId, msg.name);
      if (shouldOffer(msg.userId)) makeOffer(msg.userId);
      return;
    }
    if (msg.type === 'peer_left' && msg.room === currentRoom) {
      removePeer(msg.userId);
      return;
    }
    if (msg.type === 'signal' && msg.room === currentRoom) {
      handleSignal(msg.from, msg.data);
    }
  }

  function shouldOffer(peerId) {
    return myUserId < peerId;
  }

  function ensurePeer(peerId, peerName) {
    if (peerConnections[peerId]) return;
    var pc = new RTCPeerConnection({ iceServers: rtcIceServers || [] });
    peerConnections[peerId] = { pc: pc, name: peerName || '' };
    if (localStream) {
      localStream.getTracks().forEach(function(track){ pc.addTrack(track, localStream); });
    }
    pc.onicecandidate = function(ev){
      if (!ev.candidate) return;
      wsSend({ type:'signal', room: currentRoom, to: peerId, data: { type:'candidate', candidate: ev.candidate } });
    };
    pc.ontrack = function(ev){
      attachRemote(peerId, ev.streams[0]);
    };
    pc.onconnectionstatechange = function(){
      if (pc.connectionState === 'failed' || pc.connectionState === 'closed' || pc.connectionState === 'disconnected') {
        removePeer(peerId);
      }
    };
  }

  function makeOffer(peerId) {
    var pc = peerConnections[peerId].pc;
    pc.createOffer().then(function(offer){
      return pc.setLocalDescription(offer).then(function(){
        wsSend({ type:'signal', room: currentRoom, to: peerId, data: offer });
      });
    });
  }

  function handleSignal(fromId, data) {
    ensurePeer(fromId, data.name || '');
    var pc = peerConnections[fromId].pc;
    if (data.type === 'offer') {
      pc.setRemoteDescription(new RTCSessionDescription(data)).then(function(){
        return pc.createAnswer();
      }).then(function(answer){
        return pc.setLocalDescription(answer).then(function(){
          wsSend({ type:'signal', room: currentRoom, to: fromId, data: answer });
        });
      });
    } else if (data.type === 'answer') {
      pc.setRemoteDescription(new RTCSessionDescription(data));
    } else if (data.type === 'candidate') {
      try { pc.addIceCandidate(new RTCIceCandidate(data.candidate)); } catch(e) {}
    }
  }

  function attachRemote(peerId, stream) {
    var grid = document.getElementById('remoteGrid');
    if (!grid) return;
    var existing = document.getElementById('remote-' + peerId);
    if (!existing) {
      existing = document.createElement('video');
      existing.id = 'remote-' + peerId;
      existing.autoplay = true;
      existing.playsInline = true;
      existing.className = 'call-video';
      grid.appendChild(existing);
    }
    existing.srcObject = stream;
  }

  function removePeer(peerId) {
    var entry = peerConnections[peerId];
    if (!entry) return;
    try { entry.pc.close(); } catch(e) {}
    delete peerConnections[peerId];
    var el = document.getElementById('remote-' + peerId);
    if (el && el.parentNode) el.parentNode.removeChild(el);
  }

  function startLocal(kind) {
    var constraints = { audio: true, video: kind === 'video' };
    return navigator.mediaDevices.getUserMedia(constraints).then(function(stream){
      localStream = stream;
      var lv = document.getElementById('localVideo');
      if (lv) lv.srcObject = stream;
      return stream;
    });
  }

  function stopLocal() {
    if (!localStream) return;
    localStream.getTracks().forEach(function(t){ t.stop(); });
    localStream = null;
    var lv = document.getElementById('localVideo');
    if (lv) lv.srcObject = null;
  }

  function openCallUi(title) {
    var overlay = document.getElementById('callOverlay');
    if (overlay) overlay.classList.add('active');
    var t = document.getElementById('callTitle');
    if (t) t.textContent = title || '...';
  }

  function closeCallUi() {
    var overlay = document.getElementById('callOverlay');
    if (overlay) overlay.classList.remove('active');
    var grid = document.getElementById('remoteGrid');
    if (grid) grid.innerHTML = '';
  }

  function showIncoming(fromName, kind) {
    var box = document.getElementById('incomingCall');
    var txt = document.getElementById('incomingText');
    if (txt) txt.textContent = (fromName || '') + ' ' + (kind === 'audio' ? '<?= e(t('call_incoming_audio')) ?>' : '<?= e(t('call_incoming_video')) ?>');
    if (box) box.classList.add('active');
  }

  function hideIncoming() {
    var box = document.getElementById('incomingCall');
    if (box) box.classList.remove('active');
    pendingInvite = null;
  }

  function joinRoom(room) {
    currentRoom = room;
    wsSend({ type:'join', room: room });
  }

  function startCall(scope, kind) {
    if (!rtcSignalingUrl) return;
    ensureWs();
    currentKind = kind;
    var room = scope === 'direct'
      ? ('dm-' + Math.min(myUserId, activeUserId) + '-' + Math.max(myUserId, activeUserId))
      : ('grp-' + activeGroupId);
    openCallUi('<?= e(t('call_connecting')) ?>');
    startLocal(kind).then(function(){
      joinRoom(room);
      if (scope === 'direct') {
        wsSend({ type:'call_invite', to: activeUserId, room: room, kind: kind, fromName: myUserName });
      } else {
        wsSend({ type:'call_invite', room: room, kind: kind, groupId: activeGroupId, fromName: myUserName });
      }
    }).catch(function(){
      endCall(false);
    });
  }

  function acceptCall() {
    if (!pendingInvite) return;
    var invite = pendingInvite;
    hideIncoming();
    currentKind = invite.kind || 'video';
    openCallUi('<?= e(t('call_connecting')) ?>');
    ensureWs();
    startLocal(currentKind).then(function(){
      joinRoom(invite.room);
    }).catch(function(){
      wsSend({ type:'call_cancel', room: invite.room, to: invite.from });
      endCall(false);
    });
  }

  function endCall(notify) {
    if (notify && currentRoom) wsSend({ type:'call_end', room: currentRoom });
    Object.keys(peerConnections).forEach(function(pid){ removePeer(parseInt(pid,10)); });
    peerConnections = {};
    stopLocal();
    if (currentRoom) wsSend({ type:'leave', room: currentRoom });
    currentRoom = null;
    closeCallUi();
  }

  var callEndBtn = document.getElementById('callEndBtn');
  if (callEndBtn) callEndBtn.addEventListener('click', function(){ endCall(true); });

  var muteBtn = document.getElementById('callMuteBtn');
  if (muteBtn) muteBtn.addEventListener('click', function(){
    if (!localStream) return;
    var track = localStream.getAudioTracks()[0];
    if (!track) return;
    track.enabled = !track.enabled;
    muteBtn.textContent = track.enabled ? '<?= e(t('call_mute')) ?>' : '<?= e(t('call_unmute')) ?>';
  });

  var camBtn = document.getElementById('callCamBtn');
  if (camBtn) camBtn.addEventListener('click', function(){
    if (!localStream) return;
    var track = localStream.getVideoTracks()[0];
    if (!track) return;
    track.enabled = !track.enabled;
    camBtn.textContent = track.enabled ? '<?= e(t('call_cam_off')) ?>' : '<?= e(t('call_cam_on')) ?>';
  });

  var incAccept = document.getElementById('incomingAccept');
  if (incAccept) incAccept.addEventListener('click', acceptCall);
  var incDecline = document.getElementById('incomingDecline');
  if (incDecline) incDecline.addEventListener('click', function(){
    if (pendingInvite && pendingInvite.room) {
      wsSend({ type:'call_cancel', room: pendingInvite.room, to: pendingInvite.from });
    }
    hideIncoming();
  });

  var callDirectVideo = document.getElementById('callDirectVideo');
  if (callDirectVideo) callDirectVideo.addEventListener('click', function(){ startCall('direct', 'video'); });
  var callDirectAudio = document.getElementById('callDirectAudio');
  if (callDirectAudio) callDirectAudio.addEventListener('click', function(){ startCall('direct', 'audio'); });
  var callGroupVideo = document.getElementById('callGroupVideo');
  if (callGroupVideo) callGroupVideo.addEventListener('click', function(){ startCall('group', 'video'); });
  var callGroupAudio = document.getElementById('callGroupAudio');
  if (callGroupAudio) callGroupAudio.addEventListener('click', function(){ startCall('group', 'audio'); });

  window.addEventListener('beforeunload', function(){
    if (activeGroupId) wsSend({ type:'leave', room: 'presence:grp-' + activeGroupId });
    if (currentRoom) wsSend({ type:'leave', room: currentRoom });
  });

  ensureWs();
  (function(){
    var params = new URLSearchParams(window.location.search);
    if (params.get('call') !== '1') return;
    var stored = sessionStorage.getItem('pending_call');
    if (!stored) return;
    var data = null;
    try { data = JSON.parse(stored); } catch(e) { return; }
    if (data.groupId) {
      if (activeGroupId !== data.groupId) {
        window.location.href = '<?= e(base_url('chat?gid=')) ?>' + data.groupId + '&call=1';
        return;
      }
    } else if (data.from) {
      if (activeUserId !== data.from) {
        window.location.href = '<?= e(base_url('chat?uid=')) ?>' + data.from + '&call=1';
        return;
      }
    }
    pendingInvite = data;
    sessionStorage.removeItem('pending_call');
    setTimeout(function(){ acceptCall(); }, 300);
  })();

  setupForm('directSendForm', '<?= e(base_url('action/send-message')) ?>');
  setupForm('groupSendForm', '<?= e(base_url('action/send-group-message')) ?>');
</script>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
