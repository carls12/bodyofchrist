<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("CREATE TABLE IF NOT EXISTS calendar_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  assembly_id INT UNSIGNED NULL,
  group_id INT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  date DATE NOT NULL,
  time TIME NULL,
  end_time TIME NULL,
  type ENUM('personal','group','assembly') NOT NULL DEFAULT 'personal',
  created_at DATETIME NULL,
  INDEX (user_id, date),
  INDEX (assembly_id, date),
  INDEX (group_id, date),
  CONSTRAINT fk_event_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_event_group FOREIGN KEY (group_id) REFERENCES assemblies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
db()->exec("ALTER TABLE calendar_events ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE calendar_events ADD INDEX IF NOT EXISTS idx_calendar_events_assembly (assembly_id, date)");
db()->exec("ALTER TABLE calendar_events ADD COLUMN IF NOT EXISTS end_time TIME NULL");

db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");

$uid = auth_user()['id'];
$aid = active_assembly_id();
// Support both month and week view
$viewMode = $_GET['view'] ?? 'week'; // 'week' or 'month'
$month = $_GET['month'] ?? (new DateTimeImmutable('first day of this month'))->format('Y-m');
$monthDate = DateTimeImmutable::createFromFormat('Y-m', $month) ?: new DateTimeImmutable('first day of this month');

if ($viewMode === 'week') {
  // Get the current week or specified date
  $dateStr = $_GET['week_date'] ?? 'now';
  if ($dateStr === 'now') {
    $weekDate = new DateTimeImmutable();
  } else {
    $weekDate = DateTimeImmutable::createFromFormat('Y-m-d', $dateStr) ?: new DateTimeImmutable();
  }
  $weekday = (int)$weekDate->format('N');
  $start = $weekDate->modify('-' . ($weekday - 1) . ' days'); // Monday
  $end = $start->modify('+6 days'); // Sunday
} else {
  // Month view
  $start = $monthDate->modify('first day of this month');
  $end = $monthDate->modify('last day of this month');
}

$groupsStmt = db()->prepare("SELECT a.id,a.name FROM assemblies a
  JOIN assembly_members am ON am.assembly_id=a.id
  WHERE am.user_id=? AND am.status='active' ORDER BY a.name ASC");
$groupsStmt->execute([$uid]);
$groups = $groupsStmt->fetchAll();

$groupIds = array_map(fn($r)=> (int)$r['id'], $groups);
$in = $groupIds ? implode(',', array_fill(0, count($groupIds), '?')) : '';

$params = [$uid];
$sql = "SELECT * FROM calendar_events WHERE (user_id=? OR (group_id IS NOT NULL AND group_id IN ($in)))
  AND (assembly_id=? OR assembly_id IS NULL)
  AND date BETWEEN ? AND ? ORDER BY date ASC";
$stmt = db()->prepare($groupIds ? $sql : "SELECT * FROM calendar_events WHERE user_id=? AND (assembly_id=? OR assembly_id IS NULL) AND date BETWEEN ? AND ? ORDER BY date ASC");
$stmt->execute($groupIds ? array_merge($params, $groupIds, [$aid, $start->format('Y-m-d'), $end->format('Y-m-d')]) : [$uid, $aid, $start->format('Y-m-d'), $end->format('Y-m-d')]);
$events = $stmt->fetchAll();

$byDate = [];
foreach ($events as $ev) {
  $byDate[$ev['date']][] = $ev;
}

function label_day_short(string $ymd): string {
  $dt = new DateTimeImmutable($ymd);
  $w = (int)$dt->format('w');
  $loc = $_SESSION['locale'] ?? 'de';
  $map = [
    'de' => ['So','Mo','Di','Mi','Do','Fr','Sa'],
    'en' => ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
    'fr' => ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'],
  ];
  $arr = $map[$loc] ?? $map['de'];
  return $arr[$w] . ' ' . $dt->format('d.m');
}

$selectedDate = $_GET['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
  $selectedDate = '';
}
$selectedEvents = $selectedDate !== '' ? ($byDate[$selectedDate] ?? []) : [];
$editId = (int)($_GET['edit'] ?? 0);
$editEvent = null;
if ($editId > 0) {
  $editStmt = db()->prepare("SELECT * FROM calendar_events WHERE id=? AND user_id=? AND (assembly_id=? OR assembly_id IS NULL) LIMIT 1");
  $editStmt->execute([$editId, $uid, $aid]);
  $editEvent = $editStmt->fetch();
  if ($editEvent && $selectedDate === '') {
    $selectedDate = $editEvent['date'];
    $selectedEvents = $byDate[$selectedDate] ?? [];
  }
}

$upcomingSql = "SELECT * FROM calendar_events
  WHERE (user_id=? OR (group_id IS NOT NULL AND group_id IN ($in)))
  AND (assembly_id=? OR assembly_id IS NULL)
  AND date >= ? ORDER BY date ASC, time ASC LIMIT 10";
$upcomingStmt = db()->prepare($groupIds ? $upcomingSql : "SELECT * FROM calendar_events
  WHERE user_id=? AND (assembly_id=? OR assembly_id IS NULL) AND date >= ? ORDER BY date ASC, time ASC LIMIT 10");
$upcomingStmt->execute($groupIds ? array_merge([$uid], $groupIds, [$aid, now_ymd()]) : [$uid, $aid, now_ymd()]);
$upcoming = $upcomingStmt->fetchAll();

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('nav_calendar')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('calendar_title')) ?></h2>
    <div class="text-muted"><?= e(t('calendar_sub')) ?></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8 order-2 order-lg-1">
    <!-- View Mode Selector -->
    <div style="margin-bottom: 20px; display: flex; gap: 10px;">
      <button class="btn btn-sm <?= $viewMode === 'week' ? 'btn-primary' : 'btn-outline-secondary' ?>" 
        onclick="document.location='<?= e(base_url('calendar?view=week&week_date='.($start->format('Y-m-d')))) ?>'">
        Week View
      </button>
      <button class="btn btn-sm <?= $viewMode === 'month' ? 'btn-primary' : 'btn-outline-secondary' ?>" 
        onclick="document.location='<?= e(base_url('calendar?view=month&month='.($monthDate->format('Y-m')))) ?>'">
        Month View
      </button>
    </div>

    <?php if ($viewMode === 'week'): ?>
      <!-- Outlook-Style Week View: Each day independent -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="fw-semibold"><?= e($start->format('M d') . ' - ' . $end->format('M d, Y')) ?></div>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('calendar?view=week&week_date='.$start->modify('-7 days')->format('Y-m-d'))) ?>">← Prev</a>
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('calendar?view=week&week_date='.$start->modify('+7 days')->format('Y-m-d'))) ?>">Next →</a>
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('calendar?view=week')) ?>">Today</a>
        </div>
      </div>

      <div class="row g-3">
        <?php
          $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
          $cursor = $start;
          $dayIndex = 0;
          while ($cursor <= $end):
            $ymd = $cursor->format('Y-m-d');
            $evs = $byDate[$ymd] ?? [];
            $isToday = $ymd === date('Y-m-d');
        ?>
          <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 <?= $isToday ? 'border-primary border-2' : '' ?>" style="min-height: 500px;">
              <!-- Day Header -->
              <div class="card-header" style="background: <?= $isToday ? '#007bff' : '#f8f9fa' ?>; color: <?= $isToday ? 'white' : 'black' ?>;">
                <div class="fw-bold"><?= e($dayNames[$dayIndex]) ?></div>
                <div class="h4 mb-0"><?= e($cursor->format('d')) ?></div>
                <div class="small"><?= e($cursor->format('M Y')) ?></div>
              </div>

              <!-- Day Content -->
              <div class="card-body d-flex flex-column" style="overflow-y: auto; padding: 12px; flex: 1;">
                <?php if (empty($evs)): ?>
                  <div class="text-muted text-center" style="flex: 1; display: flex; align-items: center; justify-content: center;">
                    No events
                  </div>
                <?php else: ?>
                  <div class="d-flex flex-column gap-2">
                    <?php foreach ($evs as $e): ?>
                      <div class="p-2 rounded" style="background: <?= $e['type']==='personal' ? '#1E3A5F' : ($e['type']==='group' ? '#D4A574' : '#274a78') ?>; color: white; font-size: 0.875rem;">
                        <div class="fw-semibold small"><?= e($e['title']) ?></div>
                        <?php if ($e['time']): ?>
                          <div class="text-light small">
                            <?php if ($e['end_time']): ?>
                              <?= e(substr($e['time'], 0, 5) . ' - ' . substr($e['end_time'], 0, 5)) ?>
                            <?php else: ?>
                              <?= e(substr($e['time'], 0, 5)) ?>
                            <?php endif; ?>
                          </div>
                        <?php else: ?>
                          <div class="text-light small">All day</div>
                        <?php endif; ?>
                        <?php if ($e['description']): ?>
                          <div class="text-light small mt-1"><?= e(substr($e['description'], 0, 50)) ?>...</div>
                        <?php endif; ?>
                        <?php if ((int)$e['user_id'] === $uid): ?>
                          <div class="mt-1">
                            <a href="<?= e(base_url('calendar?view=week&week_date='.$ymd.'&edit='.(int)$e['id'])) ?>" class="text-light text-decoration-none small">✏️ Edit</a>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Add Event Button -->
              <div class="card-footer" style="padding: 8px;">
                <a href="<?= e(base_url('calendar?view=week&week_date='.$ymd)) ?>" class="btn btn-sm btn-outline-primary w-100" style="font-size: 0.875rem;">
                  + Add Event
                </a>
              </div>
            </div>
          </div>
        <?php $cursor = $cursor->modify('+1 day'); $dayIndex++; endwhile; ?>
      </div>
    <?php else: ?>
      <!-- Month Grid View -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="fw-semibold"><?= e($start->format('F Y')) ?></div>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('calendar?view=month&month='.$start->modify('-1 month')->format('Y-m'))) ?>">‹</a>
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('calendar?view=month')) ?>">Today</a>
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('calendar?view=month&month='.$start->modify('+1 month')->format('Y-m'))) ?>">›</a>
        </div>
      </div>

      <div class="row g-2">
        <?php
          $firstWeekday = (int)$start->format('N');
          $gridStart = $start->modify('-' . ($firstWeekday - 1) . ' days');
          for ($i=0; $i<42; $i++):
            $d = $gridStart->modify("+$i days");
            $ymd = $d->format('Y-m-d');
            $isMonth = $d->format('Y-m') === $start->format('Y-m');
            $evs = $byDate[$ymd] ?? [];
            $isSelected = $selectedDate === $ymd;
            $isToday = $ymd === date('Y-m-d');
        ?>
          <div class="col-12 col-md-3 col-lg-2">
            <a class="text-decoration-none text-reset d-block" href="<?= e(base_url('calendar?view=month&month='.$start->format('Y-m').'&date='.$ymd)) ?>">
              <div class="p-2 border rounded <?= $isSelected ? 'border-2 border-primary' : '' ?><?= $isToday ? ' border-primary' : '' ?>" style="min-height:90px; background:<?= $isMonth ? '#fff' : '#f7f7f7' ?>;">
                <div class="small text-muted"><?= (int)$d->format('j') ?></div>
                <?php foreach ($evs as $e): ?>
                  <div class="small mt-1" style="color:<?= $e['type']==='personal' ? '#1E3A5F' : ($e['type']==='group' ? '#D4A574' : '#274a78') ?>">
                    <?= e($e['title']) ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </a>
          </div>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-4 order-1 order-lg-2">
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-semibold"><?= e(t('calendar_day_events', ['date' => $selectedDate ?: 'Select a day'])) ?></div>
          <?php if ($selectedDate && $viewMode === 'week'): ?>
            <div class="d-flex gap-1">
              <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('calendar?view=week&week_date='.DateTimeImmutable::createFromFormat('Y-m-d', $selectedDate)->modify('-1 day')->format('Y-m-d'))) ?>">‹</a>
              <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('calendar?view=week&week_date='.DateTimeImmutable::createFromFormat('Y-m-d', $selectedDate)->modify('+1 day')->format('Y-m-d'))) ?>">›</a>
            </div>
          <?php elseif ($selectedDate && $viewMode === 'month'): ?>
            <div class="d-flex gap-1">
              <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('calendar?view=month&month='.DateTimeImmutable::createFromFormat('Y-m-d', $selectedDate)->modify('-1 day')->format('Y-m').'&date='.DateTimeImmutable::createFromFormat('Y-m-d', $selectedDate)->modify('-1 day')->format('Y-m-d'))) ?>">‹</a>
              <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('calendar?view=month&month='.DateTimeImmutable::createFromFormat('Y-m-d', $selectedDate)->modify('+1 day')->format('Y-m').'&date='.DateTimeImmutable::createFromFormat('Y-m-d', $selectedDate)->modify('+1 day')->format('Y-m-d'))) ?>">›</a>
            </div>
          <?php endif; ?>
        </div>
        <?php if (!$selectedDate): ?>
          <div class="text-muted"><?= e(t('calendar_select_day')) ?></div>
        <?php elseif (!$selectedEvents): ?>
          <div class="text-muted"><?= e(t('calendar_no_day_events')) ?></div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($selectedEvents as $e): ?>
              <div class="list-group-item d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-semibold"><?= e($e['title']) ?></div>
                  <div class="small text-muted">
                    <?php if ($e['time']): ?>
                      <?php if ($e['end_time']): ?>
                        <?= e(substr($e['time'], 0, 5) . ' - ' . substr($e['end_time'], 0, 5)) ?>
                      <?php else: ?>
                        <?= e(substr($e['time'], 0, 5)) ?>
                      <?php endif; ?>
                    <?php else: ?>
                      All day
                    <?php endif; ?>
                  </div>
                  <?php if ($e['description']): ?>
                    <div class="small text-muted mt-1"><?= e($e['description']) ?></div>
                  <?php endif; ?>
                </div>
                <?php if ((int)$e['user_id'] === $uid): ?>
                  <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('calendar?view='.$viewMode.'&'.($viewMode === 'week' ? 'week_date=' : 'month=').($selectedDate ? substr($selectedDate, 0, ($viewMode === 'week' ? 10 : 7)) : ($viewMode === 'week' ? $start->format('Y-m-d') : $monthDate->format('Y-m'))).'&date='.$e['date'].'&edit='.(int)$e['id'])) ?>">
                    <?= e(t('calendar_edit')) ?>
                  </a>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($selectedDate || $editEvent): ?>
      <div class="card mb-3">
        <div class="card-body">
          <div class="fw-semibold mb-2"><?= e($editEvent ? t('calendar_edit_title') : t('calendar_add')) ?></div>
          <form method="post" action="<?= e(base_url('action/save-event')) ?>" class="d-grid gap-2">
            <?php if ($editEvent): ?>
              <input type="hidden" name="id" value="<?= (int)$editEvent['id'] ?>">
            <?php endif; ?>
            <input type="hidden" name="redirect_view" value="<?= e($viewMode) ?>">
            <input type="hidden" name="redirect_month" value="<?= e($selectedDate ? substr($selectedDate, 0, 7) : ($viewMode === 'week' ? $start->format('Y-m') : $monthDate->format('Y-m'))) ?>">
            <input type="hidden" name="redirect_date" value="<?= e($selectedDate ?: '') ?>">
            <input type="hidden" name="hide_after_save" value="1">
            <div><label class="form-label"><?= e(t('calendar_title_label')) ?></label><input class="form-control" name="title" value="<?= e($editEvent['title'] ?? '') ?>" required></div>
            <div><label class="form-label"><?= e(t('calendar_desc_label')) ?></label><input class="form-control" name="description" value="<?= e($editEvent['description'] ?? '') ?>"></div>
            <div><label class="form-label"><?= e(t('calendar_date')) ?></label><input class="form-control" type="date" name="date" value="<?= e($editEvent['date'] ?? ($selectedDate ?: '')) ?>" required></div>
            <div><label class="form-label">Start Time</label><input class="form-control" type="time" name="time" id="event_time" value="<?= e($editEvent['time'] ?? '') ?>" step="900" placeholder="e.g., 09:00"></div>
            <div><label class="form-label">End Time (optional)</label><input class="form-control" type="time" name="end_time" id="event_end_time" value="<?= e($editEvent['end_time'] ?? '') ?>" step="900" placeholder="e.g., 10:30"></div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="all_day" name="all_day" value="1" <?= !$editEvent || !$editEvent['time'] ? 'checked' : '' ?>>
              <label class="form-check-label" for="all_day">All day event</label>
            </div>
            <script>
              document.getElementById('all_day').addEventListener('change', function() {
                document.getElementById('event_time').disabled = this.checked;
                document.getElementById('event_end_time').disabled = this.checked;
                if (this.checked) {
                  document.getElementById('event_time').value = '';
                  document.getElementById('event_end_time').value = '';
                }
              });
            </script>
            <div>
              <label class="form-label"><?= e(t('calendar_type')) ?></label>
              <select class="form-select" name="type">
                <option value="personal" <?= ($editEvent['type'] ?? '') === 'personal' ? 'selected' : '' ?>><?= e(t('calendar_type_personal')) ?></option>
                <option value="group" <?= ($editEvent['type'] ?? '') === 'group' ? 'selected' : '' ?>><?= e(t('calendar_type_group')) ?></option>
                <option value="assembly" <?= ($editEvent['type'] ?? '') === 'assembly' ? 'selected' : '' ?>><?= e(t('calendar_type_assembly')) ?></option>
              </select>
            </div>
            <div>
              <label class="form-label"><?= e(t('calendar_group')) ?></label>
              <select class="form-select" name="group_id">
                <option value="">-</option>
                <?php foreach ($groups as $g): ?>
                  <option value="<?= (int)$g['id'] ?>" <?= ((int)($editEvent['group_id'] ?? 0) === (int)$g['id']) ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn btn-primary"><?= e($editEvent ? t('calendar_update') : t('calendar_save')) ?></button>
            <?php if ($editEvent): ?>
              <a class="btn btn-outline-secondary" href="<?= e(base_url('calendar?view='.$viewMode.'&'.($viewMode === 'week' ? 'week_date=' : 'month=').($selectedDate ? substr($selectedDate, 0, ($viewMode === 'week' ? 10 : 7)) : ($viewMode === 'week' ? $start->format('Y-m-d') : $monthDate->format('Y-m'))).'&date='.($selectedDate ?: $editEvent['date']))) ?>">
                <?= e(t('calendar_cancel')) ?>
              </a>
            <?php endif; ?>
          </form>
        </div>
    <?php else: ?>
      <div class="card mb-3">
        <div class="card-body">
          <div class="text-muted"><?= e(t('calendar_select_day')) ?></div>
        </div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('calendar_upcoming')) ?></div>
        <?php if (!$upcoming): ?>
          <div class="text-muted"><?= e(t('calendar_no_events')) ?></div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($upcoming as $e): ?>
              <div class="list-group-item">
                <div class="fw-semibold"><?= e($e['title']) ?></div>
                <div class="small text-muted"><?= e($e['date']) ?> <?= e($e['time'] ?? '') ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
