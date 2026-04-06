<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/daily_planner.php';

daily_planner_ensure_tables();

$uid = auth_user()['id'];
$selectedDate = daily_planner_selected_date($_GET['date'] ?? null);
$selectedWeekday = daily_planner_weekday($selectedDate);
$selectedDayName = daily_planner_weekday_name($selectedWeekday);

daily_planner_sync_entries_for_date($uid, $selectedDate);

$templateEditId = (int)($_GET['edit_template'] ?? 0);
$templateEdit = null;
if ($templateEditId > 0) {
  $templateStmt = db()->prepare("SELECT * FROM daily_planner_templates WHERE id=? AND user_id=? LIMIT 1");
  $templateStmt->execute([$templateEditId, $uid]);
  $templateEdit = $templateStmt->fetch();
  if ($templateEdit && (int)$templateEdit['weekday'] !== $selectedWeekday) {
    $templateEdit = null;
  }
}

$templatesStmt = db()->prepare("SELECT * FROM daily_planner_templates
  WHERE user_id=? AND weekday=?
  ORDER BY planned_time ASC, sort_order ASC, id ASC");
$templatesStmt->execute([$uid, $selectedWeekday]);
$templates = $templatesStmt->fetchAll();

$entriesStmt = db()->prepare("SELECT * FROM daily_planner_entries
  WHERE user_id=? AND plan_date=?
  ORDER BY planned_time ASC, id ASC");
$entriesStmt->execute([$uid, $selectedDate]);
$entries = $entriesStmt->fetchAll();

$selectedCompleted = 0;
foreach ($entries as $entry) {
  if (!empty($entry['completed'])) {
    $selectedCompleted++;
  }
}

$historyStart = (new DateTimeImmutable($selectedDate))->modify('-13 days')->format('Y-m-d');
$historyStmt = db()->prepare("SELECT plan_date,
    COUNT(*) AS total_items,
    SUM(CASE WHEN completed=1 THEN 1 ELSE 0 END) AS completed_items
  FROM daily_planner_entries
  WHERE user_id=? AND plan_date BETWEEN ? AND ?
  GROUP BY plan_date
  ORDER BY plan_date DESC");
$historyStmt->execute([$uid, $historyStart, $selectedDate]);
$history = $historyStmt->fetchAll();

$groupsStmt = db()->prepare("SELECT a.id,a.name FROM assemblies a
  JOIN assembly_members am ON am.assembly_id=a.id
  WHERE am.user_id=? AND am.status='active' ORDER BY a.name ASC");
$groupsStmt->execute([$uid]);
$groups = $groupsStmt->fetchAll();

// Get calendar events for the selected date
$aid = active_assembly_id();
$groupIds = array_map(fn($r)=> (int)$r['id'], $groups);
$in = $groupIds ? implode(',', array_fill(0, count($groupIds), '?')) : '';

$params = [$uid];
$sql = "SELECT * FROM calendar_events WHERE (user_id=? OR (group_id IS NOT NULL AND group_id IN ($in)))
  AND (assembly_id=? OR assembly_id IS NULL)
  AND date = ? ORDER BY time ASC";
$calendarStmt = db()->prepare($groupIds ? $sql : "SELECT * FROM calendar_events WHERE user_id=? AND (assembly_id=? OR assembly_id IS NULL) AND date = ? ORDER BY time ASC");
$calendarStmt->execute($groupIds ? array_merge($params, $groupIds, [$aid, $selectedDate]) : [$uid, $aid, $selectedDate]);
$calendarEvents = $calendarStmt->fetchAll();

$weekStart = monday_of($selectedDate);
$weekEnd = (new DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
$monthStart = (new DateTimeImmutable($selectedDate))->modify('first day of this month')->format('Y-m-d');
$monthEnd = (new DateTimeImmutable($selectedDate))->modify('last day of this month')->format('Y-m-d');

function daily_planner_day_label(string $date): string {
  return daily_planner_weekday_name(daily_planner_weekday($date)) . ' ' . (new DateTimeImmutable($date))->format('d.m.Y');
}

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('nav_daily_planner')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('daily_planner_title')) ?></h2>
    <div class="text-muted"><?= e(t('daily_planner_sub')) ?></div>
  </div>
  <form method="get" action="<?= e(base_url('daily-planner')) ?>" class="d-flex gap-2 align-items-end">
    <div>
      <label class="form-label mb-1"><?= e(t('daily_planner_pick_date')) ?></label>
      <input class="form-control" type="date" name="date" value="<?= e($selectedDate) ?>">
    </div>
    <button class="btn btn-primary"><?= e(t('btn_show')) ?></button>
  </form>
</div>

<style>
  .planner-stat {
    border: 1px solid rgba(15,26,43,.08);
    border-radius: 16px;
    padding: 14px;
    background: rgba(255,255,255,.88);
  }
  .planner-entry {
    border: 1px solid rgba(15,26,43,.08);
    border-radius: 16px;
    padding: 14px;
    background: #fff;
  }
  .planner-entry.done {
    background: rgba(212,165,116,.12);
    border-color: rgba(212,165,116,.35);
  }
  .planner-time {
    font-weight: 700;
    color: var(--deep-blue);
    min-width: 72px;
  }
  .planner-history-link {
    text-decoration: none;
    color: inherit;
    display: block;
    border: 1px solid rgba(15,26,43,.08);
    border-radius: 14px;
    padding: 12px 14px;
    background: #fff;
  }
  .modal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    z-index: 2000 !important;
    pointer-events: auto !important;
  }
  .modal-dialog {
    z-index: 2100 !important;
    pointer-events: auto !important;
  }
  .modal-backdrop {
    z-index: 1900 !important;
  }
  .modal-content {
    pointer-events: auto !important;
  }
</style>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="planner-stat h-100">
      <div class="text-muted small"><?= e(t('daily_planner_for_day', ['date' => $selectedDate])) ?></div>
      <div class="fw-semibold mt-1"><?= e($selectedDayName) ?></div>
      <div class="mt-2"><?= e(t('daily_planner_summary', ['done' => $selectedCompleted, 'total' => count($entries)])) ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="planner-stat h-100">
      <div class="text-muted small"><?= e(t('daily_planner_week_block')) ?></div>
      <div class="fw-semibold mt-1"><?= e($weekStart) ?> - <?= e($weekEnd) ?></div>
      <a class="btn btn-sm btn-outline-primary mt-2" href="<?= e(base_url('action/download-daily-planner?period=week&date=' . $selectedDate)) ?>"><?= e(t('daily_planner_print_week')) ?></a>
    </div>
  </div>
  <div class="col-md-4">
    <div class="planner-stat h-100">
      <div class="text-muted small"><?= e(t('daily_planner_month_block')) ?></div>
      <div class="fw-semibold mt-1"><?= e(substr($monthStart, 0, 7)) ?></div>
      <a class="btn btn-sm btn-outline-primary mt-2" href="<?= e(base_url('action/download-daily-planner?period=month&date=' . $selectedDate)) ?>"><?= e(t('daily_planner_print_month')) ?></a>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-12">
    <!-- Day Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h5 class="mb-0"><?= e(daily_planner_day_label($selectedDate)) ?></h5>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('daily-planner?date='.(new DateTimeImmutable($selectedDate))->modify('-1 day')->format('Y-m-d'))) ?>">← Previous Day</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('daily-planner')) ?>">Today</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('daily-planner?date='.(new DateTimeImmutable($selectedDate))->modify('+1 day')->format('Y-m-d'))) ?>">Next Day →</a>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <div class="fw-semibold"><?= e(t('daily_planner_today_list')) ?></div>
            <div class="text-muted small"><?= e(daily_planner_day_label($selectedDate)) ?></div>
          </div>
          <div class="badge-soft"><?= e(t('daily_planner_completion_label')) ?>: <?= e(t('daily_planner_summary', ['done' => $selectedCompleted, 'total' => count($entries)])) ?></div>
        </div>

        <!-- 24-Hour Timeline View -->
        <div style="position: relative; margin: -14px -14px 0 -14px;">
          <div style="display: grid; grid-template-columns: 60px 1fr; gap: 0;">
            <?php
              // Helper function to convert HH:MM to minutes since midnight
              $timeToMinutes = function($time) {
                if (!$time) return 0;
                list($h, $m) = explode(':', substr($time, 0, 5));
                return (int)$h * 60 + (int)$m;
              };

              // Group entries by time
              $entriesByTime = [];
              foreach ($entries as $entry) {
                $time = $entry['planned_time'] ?: '00:00';
                if (!isset($entriesByTime[$time])) {
                  $entriesByTime[$time] = ['planner' => [], 'calendar' => []];
                }
                $entriesByTime[$time]['planner'][] = $entry;
              }

              // Collect all individual calendar events (don't group by time)
              $allCalendarEvents = $calendarEvents;

              // Also group for display at start time
              foreach ($calendarEvents as $event) {
                $time = $event['time'] ? substr($event['time'], 0, 5) : '00:00';
                if (!isset($entriesByTime[$time])) {
                  $entriesByTime[$time] = ['planner' => [], 'calendar' => []];
                }
                $entriesByTime[$time]['calendar'][] = $event;
              }

              // Show all 24 hours
              for ($hour = 0; $hour < 24; $hour++) {
                $timeStr = sprintf('%02d:00', $hour);
                $hasItems = isset($entriesByTime[$timeStr]) && (!empty($entriesByTime[$timeStr]['planner']) || !empty($entriesByTime[$timeStr]['calendar']));
            ?>
              <!-- Time Header -->
              <div style="padding: 8px 12px; text-align: right; border-right: 1px solid #e5e7eb; background: #f9fafb; font-weight: 700; color: var(--deep-blue); min-height: 60px; display: flex; align-items: center; justify-content: flex-end;">
                <?= e($timeStr) ?>
              </div>
              <!-- Time Content -->
              <div class="hour-slot" style="border-bottom: 1px solid #e5e7eb; padding: 8px 12px; min-height: 60px; background: <?= $hasItems ? '#fff' : '#fafbfc' ?>; cursor: pointer; transition: background-color 0.2s; position: relative;" onclick="openEventModal('<?= e($selectedDate) ?>', '<?= e($timeStr) ?>')" onmouseover="this.style.backgroundColor='#f0f8ff'" onmouseout="this.style.backgroundColor='<?= $hasItems ? '#fff' : '#fafbfc' ?>'">
                <?php if (isset($entriesByTime[$timeStr])): ?>
                  <div class="d-flex flex-column gap-2">
                    <!-- Calendar Events (small display) -->
                    <?php foreach ($entriesByTime[$timeStr]['calendar'] as $event): ?>
                      <div class="calendar-event" style="background: #4a90e2; color: white; padding: 4px 6px; border-radius: 4px; font-size: 0.75rem; margin: -2px -3px;">
                        <div class="fw-semibold" style="margin: 0;"><?= e(substr($event['title'], 0, 15)) ?></div>
                      </div>
                    <?php endforeach; ?>

                    <!-- Daily Planner Entries -->
                    <?php foreach ($entriesByTime[$timeStr]['planner'] as $entry): ?>
                      <div class="planner-entry <?= !empty($entry['completed']) ? 'done' : '' ?>" style="margin: 0; padding: 8px; font-size: 0.85rem;">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                          <div>
                            <div class="fw-semibold"><?= e(substr($entry['title'], 0, 20)) ?></div>
                          </div>
                          <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="entry-<?= (int)$entry['id'] ?>" <?= !empty($entry['completed']) ? 'checked' : '' ?> onchange="updateEntry(<?= (int)$entry['id'] ?>, this.checked)">
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php } ?>
          </div>

          <!-- Overlaid Calendar Events (spanning their duration) -->
          <div style="position: absolute; top: 0; left: 60px; right: 0; height: <?= 24 * 60 ?>px; pointer-events: none;">
            <?php foreach ($allCalendarEvents as $event): ?>
              <?php
                $startMin = $timeToMinutes($event['time']);
                $endMin = $timeToMinutes($event['end_time']) ?: $startMin + 60;
                $duration = $endMin - $startMin;
                if ($duration <= 0) $duration = 60;
                $topPx = $startMin;
              ?>
              <div style="position: absolute; top: <?= $topPx ?>px; left: 12px; right: 12px; height: <?= $duration ?>px; background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%); color: white; padding: 8px; border-radius: 6px; border-left: 4px solid #2563eb; font-size: 0.875rem; pointer-events: auto; cursor: pointer; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;" onclick='editEventModal(<?= (int)$event['id'] ?>, "<?= e($selectedDate) ?>", "<?= e(substr($event['time'], 0, 5)) ?>", <?= json_encode($event['title']) ?>, <?= json_encode($event['description'] ?? '') ?>, "<?= e(substr($event['end_time'], 0, 5)) ?>", "<?= e($event['type']) ?>")'>
                <div>
                  <div class="fw-semibold" style="margin: 0; font-size: 0.9rem;"><?= e($event['title']) ?></div>
                  <?php if ($duration > 60): ?>
                    <div style="font-size: 0.8rem; opacity: 0.9; margin-top: 2px;"><?= e(substr($event['time'], 0, 5)) ?> - <?= e(substr($event['end_time'], 0, 5)) ?></div>
                  <?php endif; ?>
                  <?php if ($event['description'] && $duration > 100): ?>
                    <div style="font-size: 0.75rem; opacity: 0.8; margin-top: 4px;"><?= e(substr($event['description'], 0, 50)) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Event Creation/Edit Modal -->
        <div id="eventModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
          <div style="background: white; border-radius: 8px; padding: 20px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
              <h5 id="modalTitle" style="margin: 0;">Create Event</h5>
              <button onclick="closeEventModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <form id="eventForm" method="post" action="<?= e(base_url('action/save-event')) ?>" style="display: grid; gap: 15px;">
              <input type="hidden" name="id" id="eventId" value="">
              <input type="hidden" name="redirect_view" value="daily_planner">
              <input type="hidden" name="redirect_date" value="<?= e($selectedDate) ?>">
              <input type="hidden" name="hide_after_save" value="1">
              <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Title</label>
                <input class="form-control" name="title" id="eventTitle" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
              </div>
              <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Description</label>
                <input class="form-control" name="description" id="eventDescription" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
              </div>
              <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Date</label>
                <input class="form-control" type="date" name="date" id="eventDate" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
              </div>
              <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Start Time</label>
                <input class="form-control" type="time" name="time" id="eventTime" step="900" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
              </div>
              <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">End Time (optional)</label>
                <input class="form-control" type="time" name="end_time" id="eventEndTime" step="900" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
              </div>
              <div>
                <input class="form-check-input" type="checkbox" id="eventAllDay" name="all_day" value="1" style="margin-right: 8px;">
                <label class="form-check-label" for="eventAllDay">All day event</label>
              </div>
              <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Type</label>
                <select class="form-select" name="type" id="eventType" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                  <option value="personal">Personal</option>
                  <option value="group">Group</option>
                  <option value="assembly">Assembly</option>
                </select>
              </div>
              <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Group</label>
                <select class="form-select" name="group_id" id="eventGroup" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                  <option value="">-</option>
                  <?php foreach ($groups as $g): ?>
                    <option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div style="display: flex; gap: 10px; justify-content: space-between;">
                <div style="flex: 1;">
                  <button id="submitBtn" class="btn btn-primary" type="submit" style="width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Create Event</button>
                </div>
                <button id="deleteBtn" type="button" onclick="deleteEvent()" style="display: none; padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">Delete</button>
              </div>
            </form>
          </div>
        </div>

        <script>
          function openEventModal(date, time) {
            console.log('Opening event modal for date:', date, 'time:', time);
            const eventId = document.getElementById('eventId');
            const eventDate = document.getElementById('eventDate');
            const eventTime = document.getElementById('eventTime');
            const eventEndTime = document.getElementById('eventEndTime');
            const eventTitle = document.getElementById('eventTitle');
            const eventDescription = document.getElementById('eventDescription');
            const eventAllDay = document.getElementById('eventAllDay');
            const eventType = document.getElementById('eventType');
            const eventGroup = document.getElementById('eventGroup');
            const modalTitle = document.getElementById('modalTitle');
            const submitBtn = document.getElementById('submitBtn');
            const deleteBtn = document.getElementById('deleteBtn');

            eventId.value = '';
            eventDate.value = date;
            eventTime.value = time;
            eventEndTime.value = '';
            eventTitle.value = '';
            eventDescription.value = '';
            eventAllDay.checked = false;
            eventTime.disabled = false;
            eventEndTime.disabled = false;
            eventType.value = 'personal';
            eventGroup.value = '';

            // Calculate default end time (1 hour later)
            const [hours, minutes] = time.split(':');
            const endHour = (parseInt(hours, 10) + 1) % 24;
            eventEndTime.value = sprintf('%02d:%s', endHour, minutes);

            modalTitle.textContent = 'Create Event';
            submitBtn.textContent = 'Create Event';
            deleteBtn.style.display = 'none';

            const modalElement = document.getElementById('eventModal');
            modalElement.style.display = 'flex';
            eventTitle.focus();
            console.log('Modal should be visible now');
          }

          function editEventModal(id, date, time, title, description, endTime, type) {
            console.log('editEventModal called with:', {id, date, time, title, description, endTime, type});
            const eventId = document.getElementById('eventId');
            const eventDate = document.getElementById('eventDate');
            const eventTime = document.getElementById('eventTime');
            const eventEndTime = document.getElementById('eventEndTime');
            const eventTitle = document.getElementById('eventTitle');
            const eventDescription = document.getElementById('eventDescription');
            const eventType = document.getElementById('eventType');
            const modalTitle = document.getElementById('modalTitle');
            const submitBtn = document.getElementById('submitBtn');
            const deleteBtn = document.getElementById('deleteBtn');

            eventId.value = id;
            eventDate.value = date;
            eventTime.value = time;
            eventEndTime.value = endTime || '';
            eventTitle.value = title;
            eventDescription.value = description || '';
            eventType.value = type;

            modalTitle.textContent = 'Edit Event';
            submitBtn.textContent = 'Update Event';
            deleteBtn.style.display = 'block';

            const modalElement = document.getElementById('eventModal');
            modalElement.style.display = 'flex';
            eventTitle.focus();
          }

          function deleteEvent() {
            const eventId = document.getElementById('eventId').value;
            if (!eventId || eventId === '') {
              alert('Cannot delete: Event ID is missing');
              return;
            }
            if (confirm('Are you sure you want to delete this event?')) {
              const form = document.getElementById('eventForm');
              form.action = '<?= e(base_url('action/delete-event')) ?>';
              const deleteInput = document.createElement('input');
              deleteInput.type = 'hidden';
              deleteInput.name = 'id';
              deleteInput.value = eventId;
              form.appendChild(deleteInput);
              form.submit();
            }
          }

          function closeEventModal() {
            document.getElementById('eventModal').style.display = 'none';
          }

          function sprintf(format, ...args) {
            return format.replace(/%(\d+)\$s/g, (match, num) => args[num - 1] || match);
          }

          // Handle all day checkbox
          const eventAllDayCheckbox = document.getElementById('eventAllDay');
          eventAllDayCheckbox.addEventListener('change', function() {
            const eventTime = document.getElementById('eventTime');
            const eventEndTime = document.getElementById('eventEndTime');
            eventTime.disabled = this.checked;
            eventEndTime.disabled = this.checked;
            if (this.checked) {
              eventTime.value = '';
              eventEndTime.value = '';
            }
          });

          // Close modal when clicking outside
          document.getElementById('eventModal').addEventListener('click', function(event) {
            if (event.target === this) {
              closeEventModal();
            }
          });
        </script>

        <!-- Quick update via AJAX -->
        <script>
          function updateEntry(entryId, isCompleted) {
            const formData = new FormData();
            formData.append('form_type', 'quick_update');
            formData.append('entry_id', entryId);
            formData.append('completed', isCompleted ? '1' : '0');

            fetch('<?= e(base_url('action/save-daily-planner')) ?>', {
              method: 'POST',
              body: formData
            }).then(response => {
              if (!response.ok) console.error('Update failed');
            }).catch(error => console.error('Error:', error));
          }
        </script>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="fw-semibold mb-3"><?= e(t('daily_planner_history')) ?></div>
        <?php if (!$history): ?>
          <div class="text-muted"><?= e(t('daily_planner_past_empty')) ?></div>
        <?php else: ?>
          <div class="d-grid gap-2">
            <?php foreach ($history as $day): ?>
              <a class="planner-history-link" href="<?= e(base_url('daily-planner?date=' . $day['plan_date'])) ?>">
                <div class="d-flex justify-content-between gap-3 flex-wrap">
                  <div>
                    <div class="fw-semibold"><?= e(daily_planner_day_label($day['plan_date'])) ?></div>
                    <div class="text-muted small"><?= e(t('daily_planner_summary', ['done' => (int)$day['completed_items'], 'total' => (int)$day['total_items']])) ?></div>
                  </div>
                  <div class="badge-soft"><?= e((int)$day['completed_items'] === (int)$day['total_items'] && (int)$day['total_items'] > 0 ? t('daily_planner_status_done') : t('daily_planner_status_open')) ?></div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
