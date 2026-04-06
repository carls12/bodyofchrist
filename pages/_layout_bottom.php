      </div>
    </main>
  </div>
</div>

<nav class="app-bottom-nav">
  <?php if (auth_check()): ?>
    <a class="<?= $reqPath==='/' || $reqPath==='/dashboard' ? 'active' : '' ?>" href="<?= e(base_url('dashboard')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9"/><path d="M9 21V9h6v12"/></svg>
      <?= e(t('nav_home')) ?>
    </a>
    <a class="<?= $reqPath==='/goals' ? 'active' : '' ?>" href="<?= e(base_url('goals')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
      <?= e(t('nav_goals')) ?>
    </a>
    <a class="<?= $reqPath==='/daily-planner' ? 'active' : '' ?>" href="<?= e(base_url('daily-planner')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M5 6h.01"/><path d="M5 12h.01"/><path d="M5 18h.01"/></svg>
      <?= e(t('nav_daily_planner')) ?>
    </a>
    <a class="<?= $reqPath==='/prayer' ? 'active' : '' ?>" href="<?= e(base_url('prayer')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19h16"/><path d="M6 16l4-4 3 3 5-6"/></svg>
      <?= e(t('nav_timer')) ?>
    </a>
    <a class="<?= $reqPath==='/assemblies' || str_starts_with($reqPath, '/assemblies') ? 'active' : '' ?>" href="<?= e(base_url('assemblies')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-6 9 6"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>
      <?= e(t('nav_assemblies')) ?>
    </a>
    <a class="<?= $reqPath==='/chat' ? 'active' : '' ?>" href="<?= e(base_url('chat')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
      <?= e(t('nav_chat')) ?>
    </a>
    <a class="<?= $reqPath==='/my-reports' ? 'active' : '' ?>" href="<?= e(base_url('my-reports')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19h16"/><path d="M7 16V8"/><path d="M12 16V4"/><path d="M17 16v-6"/></svg>
      <?= e(t('nav_my_reports')) ?>
    </a>
    <?php if (auth_user()['is_leader']): ?>
      <a class="<?= $reqPath==='/reports' ? 'active' : '' ?>" href="<?= e(base_url('reports')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19h16"/><path d="M7 16V8"/><path d="M12 16V4"/><path d="M17 16v-6"/></svg>
        <?= e(t('nav_reports')) ?>
      </a>
    <?php endif; ?>
    <a class="<?= $reqPath==='/profile' ? 'active' : '' ?>" href="<?= e(base_url('profile')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <?= e(t('nav_profile')) ?>
    </a>
    <?php if (is_main_admin()): ?>
      <a class="<?= $reqPath==='/admin/users' ? 'active' : '' ?>" href="<?= e(base_url('admin/users')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11h-6"/><path d="M19 8v6"/></svg>
        <?= e(t('nav_admin')) ?>
      </a>
    <?php endif; ?>
    <?php if (is_regional_leader() || is_main_admin()): ?>
      <a class="<?= $reqPath==='/admin/assemblies' ? 'active' : '' ?>" href="<?= e(base_url('admin/assemblies')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-6 9 6"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>
        <?= e(t('admin_assemblies')) ?>
      </a>
    <?php endif; ?>
    <?php if (is_national_leader() || is_main_admin()): ?>
      <a class="<?= $reqPath==='/admin/national' ? 'active' : '' ?>" href="<?= e(base_url('admin/national')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/><path d="M6 3v18"/><path d="M12 3v18"/><path d="M18 3v18"/></svg>
        <?= e(t('admin_national_reports')) ?>
      </a>
    <?php endif; ?>
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
