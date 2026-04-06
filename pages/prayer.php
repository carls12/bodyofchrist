<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("CREATE TABLE IF NOT EXISTS prayer_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  assembly_id INT UNSIGNED NULL,
  date DATE NOT NULL,
  minutes DECIMAL(10,2) NOT NULL DEFAULT 0,
  seconds INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,
  is_fasting TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  INDEX (user_id, date),
  INDEX (assembly_id, date),
  CONSTRAINT fk_prayer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS prayer_goal_minutes INT NOT NULL DEFAULT 60");
db()->exec("ALTER TABLE prayer_logs ADD COLUMN IF NOT EXISTS seconds INT UNSIGNED NOT NULL DEFAULT 0");
db()->exec("ALTER TABLE prayer_logs ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE prayer_logs ADD INDEX IF NOT EXISTS idx_prayer_logs_assembly (assembly_id, date)");

$uid = auth_user()['id'];
$today = now_ymd();
$aid = active_assembly_id();

$sum = db()->prepare("SELECT SUM(COALESCE(seconds, minutes*60)) AS total_seconds FROM prayer_logs WHERE user_id=? AND date=? AND (assembly_id=? OR assembly_id IS NULL)");
$sum->execute([$uid, $today, $aid]);
$todayTotalSeconds = (int)($sum->fetch()['total_seconds'] ?? 0);
$todayTotal = $todayTotalSeconds / 60.0;

$goal = db()->prepare("SELECT prayer_goal_minutes FROM users WHERE id=?");
$goal->execute([$uid]);
$goalMinutes = (int)($goal->fetch()['prayer_goal_minutes'] ?? 60);
$progress = $goalMinutes > 0 ? min(100, (int)round(($todayTotal / $goalMinutes) * 100)) : 0;

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('nav_prayer')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('prayer_title')) ?></h2>
    <div class="text-muted"><?= e(t('prayer_sub')) ?></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('prayer_progress')) ?></div>
        <div class="progress mb-2">
          <div class="progress-bar" id="progressBar" style="width: <?= (int)$progress ?>%"></div>
        </div>
        <div class="text-muted small">
          <?= e(t('prayer_today')) ?>:
          <span id="todayTotal"><?= e(number_format($todayTotal,2)) ?></span> / <span id="goalMinutes"><?= (int)$goalMinutes ?></span> min
          <span class="ms-2" id="todayTotalTime"><?= e(gmdate('H:i:s', $todayTotalSeconds)) ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <div class="fw-semibold mb-2">Timer</div>
        <div class="display-6 mb-3" id="timerDisplay">00:00:00</div>
        <div class="d-flex gap-2 mb-3">
          <button class="btn btn-primary" id="startBtn"><?= e(t('prayer_start')) ?></button>
          <button class="btn btn-outline-secondary" id="stopBtn"><?= e(t('prayer_stop')) ?></button>
        </div>
        <div class="d-grid gap-2">
          <textarea class="form-control" id="notes" rows="2" placeholder="<?= e(t('prayer_notes')) ?>"></textarea>
          <label class="form-check">
            <input class="form-check-input" type="checkbox" id="fasting">
            <span class="form-check-label"><?= e(t('prayer_fast')) ?></span>
          </label>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  var startBtn = document.getElementById('startBtn');
  var stopBtn = document.getElementById('stopBtn');
  var display = document.getElementById('timerDisplay');
  var todayTotalEl = document.getElementById('todayTotal');
  var progressBar = document.getElementById('progressBar');
  var baseMinutes = <?= json_encode($todayTotal) ?>;
  var baseSeconds = <?= json_encode($todayTotalSeconds) ?>;
  var goalMinutes = <?= json_encode($goalMinutes) ?>;
  var interval = null;
  var startTime = null;
  var storageKey = 'prayer_timer_start';
  var storageDayKey = 'prayer_timer_day';
  var todayKey = <?= json_encode($today) ?>;

  function fmt(ms) {
    var s = Math.floor(ms / 1000);
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    var sec = s % 60;
    return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0');
  }

  function startTimer(fromStorage) {
    if (interval) return;
    if (!startTime) startTime = Date.now();
    interval = setInterval(function(){
      display.textContent = fmt(Date.now() - startTime);
    }, 500);
    if (!fromStorage) {
      localStorage.setItem(storageKey, String(startTime));
      localStorage.setItem(storageDayKey, todayKey);
    }
  }

  function stopTimer() {
    if (interval) clearInterval(interval);
    interval = null;
    localStorage.removeItem(storageKey);
    localStorage.removeItem(storageDayKey);
  }

  startBtn.addEventListener('click', function(){
    if (interval) return;
    startTime = Date.now();
    startTimer(false);
  });

  stopBtn.addEventListener('click', function(){
    if (!interval) return;
    stopTimer();
    var ms = Date.now() - startTime;
    var seconds = Math.max(1, Math.round(ms / 1000));
    var fd = new FormData();
    fd.set('seconds', seconds);
    fd.set('notes', document.getElementById('notes').value || '');
    if (document.getElementById('fasting').checked) fd.set('is_fasting', '1');
    fetch('<?= e(base_url('action/save-prayer')) ?>', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(r => r.json())
      .then(function(data){
        if (data && typeof data.total === 'number') {
          baseMinutes = data.total;
          if (typeof data.total_seconds === 'number') baseSeconds = data.total_seconds;
        } else {
          baseSeconds = baseSeconds + seconds;
          baseMinutes = baseSeconds / 60.0;
        }
        if (todayTotalEl) todayTotalEl.textContent = baseMinutes.toFixed(2).replace(/0+$/,'').replace(/\.$/,'');
        var timeEl = document.getElementById('todayTotalTime');
        if (timeEl) {
          var s = baseSeconds;
          var h = Math.floor(s / 3600);
          var m = Math.floor((s % 3600) / 60);
          var sec = s % 60;
          timeEl.textContent = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0');
        }
        if (progressBar && goalMinutes > 0) {
          var pct = Math.min(100, Math.round((baseMinutes / goalMinutes) * 100));
          progressBar.style.width = pct + '%';
        }
        display.textContent = '00:00:00';
        startTime = null;
      });
  });

  (function restoreTimer(){
    try {
      var savedDay = localStorage.getItem(storageDayKey);
      var savedStart = localStorage.getItem(storageKey);
      if (savedDay && savedDay === todayKey && savedStart) {
        startTime = parseInt(savedStart, 10);
        if (!isNaN(startTime)) {
          display.textContent = fmt(Date.now() - startTime);
          startTimer(true);
        }
      } else {
        localStorage.removeItem(storageKey);
        localStorage.removeItem(storageDayKey);
      }
    } catch (e) {}
  })();
</script>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
