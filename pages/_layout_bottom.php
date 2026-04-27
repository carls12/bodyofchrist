      </div>
    </main>
  </div>
</div>

<nav class="app-bottom-nav">
  <?php if (auth_check()): ?>
    <?php foreach ($mobilePrimaryNav as $item): ?>
      <a class="<?= $item['active'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
        <?= $item['icon'] ?>
        <?= e($item['label']) ?>
      </a>
    <?php endforeach; ?>
    <button type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMoreSheet" aria-controls="mobileMoreSheet" class="<?= array_filter($mobileMoreNav, fn($item) => !empty($item['active'])) ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg>
      Mehr
    </button>
  <?php else: ?>
    <a class="<?= $reqPath==='/login' ? 'active' : '' ?>" href="<?= e(base_url('login')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
      <?= e(t('nav_login')) ?>
    </a>
    <a class="<?= $reqPath==='/register' ? 'active' : '' ?>" href="<?= e(base_url('register')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4z"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>
      <?= e(t('nav_register')) ?>
    </a>
  <?php endif; ?>
</nav>

<?php if (auth_check()): ?>
  <div class="offcanvas offcanvas-bottom app-mobile-sheet" tabindex="-1" id="mobileMoreSheet" aria-labelledby="mobileMoreSheetLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="mobileMoreSheetLabel"><?= e(t('app')) ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      <div class="app-mobile-sheet-list">
        <?php foreach ($mobileMoreNav as $item): ?>
          <a class="app-mobile-sheet-link <?= $item['active'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
            <?= $item['icon'] ?>
            <span><?= e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('<?= e(base_url('service-worker.js')) ?>');
    });
  }
</script>
<?php if (auth_check() && $reqPath !== '/chat'): ?>
  <div class="global-call-toast" id="globalCallToast">
    <div class="card">
      <div class="card-body">
        <div class="fw-semibold mb-1" id="globalCallText"><?= e(t('call_incoming')) ?></div>
        <div class="d-flex gap-2 mt-2">
          <button class="btn btn-sm btn-success" id="globalCallAccept"><?= e(t('call_accept')) ?></button>
          <button class="btn btn-sm btn-outline-danger" id="globalCallDecline"><?= e(t('call_decline')) ?></button>
        </div>
      </div>
    </div>
  </div>
  <script>
    (function(){
      var rtcUrl = <?= json_encode($cfg['rtc_signaling_url'] ?? '') ?>;
      if (!rtcUrl) return;
      var myUserId = <?= (int)auth_user()['id'] ?>;
      var myUserName = <?= json_encode(auth_user()['name'] ?? 'User') ?>;
      var presenceGroups = <?= json_encode($presenceGroups ?? []) ?>;

      var ws = null;
      var pendingInvite = null;
      var ringTimer = null;
      var audioCtx = null;

      function startRinging() {
        if (ringTimer) return;
        function beep() {
          try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            var o = audioCtx.createOscillator();
            var g = audioCtx.createGain();
            o.type = 'sine';
            o.frequency.value = 880;
            g.gain.value = 0.05;
            o.connect(g);
            g.connect(audioCtx.destination);
            o.start();
            setTimeout(function(){ o.stop(); }, 400);
          } catch (e) {}
        }
        beep();
        ringTimer = setInterval(beep, 1600);
      }

      function stopRinging() {
        if (ringTimer) clearInterval(ringTimer);
        ringTimer = null;
      }

      function showToast(text) {
        var t = document.getElementById('globalCallText');
        if (t) t.textContent = text;
        var box = document.getElementById('globalCallToast');
        if (box) box.classList.add('active');
        startRinging();
      }

      function hideToast() {
        var box = document.getElementById('globalCallToast');
        if (box) box.classList.remove('active');
        stopRinging();
        pendingInvite = null;
      }

      function wsSend(obj) {
        if (!ws || ws.readyState !== 1) return;
        ws.send(JSON.stringify(obj));
      }

      function connect() {
        if (ws && (ws.readyState === 0 || ws.readyState === 1)) return;
        ws = new WebSocket(rtcUrl);
        ws.addEventListener('open', function(){
          wsSend({ type:'hello', userId: myUserId, name: myUserName });
          presenceGroups.forEach(function(gid){
            wsSend({ type:'join', room: 'presence:grp-' + gid });
          });
        });
        ws.addEventListener('message', function(ev){
          var msg = {};
          try { msg = JSON.parse(ev.data); } catch(e) { return; }
          if (msg.type === 'call_invite') {
            pendingInvite = msg;
            var label = (msg.kind === 'audio' ? '<?= e(t('call_incoming_audio')) ?>' : '<?= e(t('call_incoming_video')) ?>');
            showToast((msg.fromName || '') + ' ' + label);
          }
          if (msg.type === 'call_cancel') {
            hideToast();
          }
        });
        ws.addEventListener('close', function(){
          setTimeout(connect, 2000);
        });
      }

      var acceptBtn = document.getElementById('globalCallAccept');
      if (acceptBtn) acceptBtn.addEventListener('click', function(){
        if (!pendingInvite) return;
        sessionStorage.setItem('pending_call', JSON.stringify(pendingInvite));
        if (pendingInvite.groupId) {
          window.location.href = '<?= e(base_url('chat?gid=')) ?>' + pendingInvite.groupId + '&call=1';
        } else if (pendingInvite.from) {
          window.location.href = '<?= e(base_url('chat?uid=')) ?>' + pendingInvite.from + '&call=1';
        } else {
          hideToast();
        }
      });

      var declineBtn = document.getElementById('globalCallDecline');
      if (declineBtn) declineBtn.addEventListener('click', function(){
        if (pendingInvite && pendingInvite.room) {
          wsSend({ type:'call_cancel', room: pendingInvite.room, to: pendingInvite.from });
        }
        hideToast();
      });

      connect();
    })();
  </script>
<?php endif; ?>
</body>
</html>
