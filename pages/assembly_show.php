<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS type ENUM('discipleship','assembly') NOT NULL DEFAULT 'assembly'");
db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS chat_enabled TINYINT(1) NOT NULL DEFAULT 1");
db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE assembly_members MODIFY COLUMN status ENUM('pending','invited','active','inactive') NOT NULL DEFAULT 'active'");

$uid = auth_user()['id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { flash_set('error', t('assembly_invalid')); redirect(base_url('assemblies')); }

$g = db()->prepare("SELECT * FROM assemblies WHERE id=? LIMIT 1");
$g->execute([$id]);
$group = $g->fetch();
if (!$group) { flash_set('error', t('assembly_invalid')); redirect(base_url('assemblies')); }

$memStmt = db()->prepare("SELECT * FROM assembly_members WHERE assembly_id=? AND user_id=? LIMIT 1");
$memStmt->execute([$id, $uid]);
$mem = $memStmt->fetch();

$isLeader = ((int)$group['leader_id'] === (int)$uid);
$isRegional = is_regional_leader() && user_region() && user_region() === ($group['region'] ?? null);
$canManage = $isLeader || $isRegional || is_main_admin();
if (!$canManage && (!$mem || $mem['status'] !== 'active')) {
  flash_set('error', t('assembly_not_member'));
  redirect(base_url('assemblies'));
}

$members = db()->prepare("SELECT u.id,u.name,u.email,am.role
  FROM assembly_members am
  JOIN users u ON u.id=am.user_id
  WHERE am.assembly_id=? AND am.status='active' ORDER BY am.role DESC, u.name ASC");
$members->execute([$id]);
$members = $members->fetchAll();

$pending = [];
$invited = [];
if ($canManage) {
  $p = db()->prepare("SELECT am.id,u.name,u.email
    FROM assembly_members am
    JOIN users u ON u.id=am.user_id
    WHERE am.assembly_id=? AND am.status='pending' ORDER BY u.name ASC");
  $p->execute([$id]);
  $pending = $p->fetchAll();

  $i = db()->prepare("SELECT am.id,u.name,u.email
    FROM assembly_members am
    JOIN users u ON u.id=am.user_id
    WHERE am.assembly_id=? AND am.status='invited' ORDER BY u.name ASC");
  $i->execute([$id]);
  $invited = $i->fetchAll();
}

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h2 class="h4 mb-1"><?= e($group['name']) ?></h2>
    <div class="text-muted"><?= e((string)($group['description'] ?? '')) ?></div>
    <div class="mt-2">
      <span class="badge-soft"><?= e($group['type']==='discipleship' ? t('groups_type_discipleship') : t('groups_type_assembly')) ?></span>
      <?php if ($isLeader): ?><span class="badge-soft"><?= e(t('admin_leader')) ?></span><?php endif; ?>
      <?php if ($isRegional): ?><span class="badge-soft"><?= e(t('admin_regional_leader')) ?></span><?php endif; ?>
    </div>
  </div>
  <a class="btn btn-outline-secondary" href="<?= e(base_url('assemblies')) ?>"><?= e(t('assembly_back')) ?></a>
</div>

<?php if ($canManage): ?>
  <div class="row g-3 mb-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <div class="text-muted small"><?= e(t('groups_member_count')) ?></div>
          <div class="h4 mb-0"><?= count($members) ?></div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <div class="text-muted small"><?= e(t('chat_group')) ?></div>
          <form method="post" action="<?= e(base_url('action/toggle-chat')) ?>" class="m-0">
            <input type="hidden" name="assembly_id" value="<?= (int)$id ?>">
            <button class="btn btn-sm <?= (int)$group['chat_enabled'] ? 'btn-outline-success' : 'btn-outline-danger' ?>">
              <?= (int)$group['chat_enabled'] ? e(t('groups_chat_on')) : e(t('groups_chat_off')) ?>
            </button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <div class="text-muted small"><?= e(t('assembly_join_code')) ?></div>
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <code class="fs-5" id="inviteCode"><?= e($group['join_code']) ?></code>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="copyCode"><?= e(t('groups_invite_copy')) ?></button>
            <form method="post" action="<?= e(base_url('action/regenerate-code')) ?>" class="m-0">
              <input type="hidden" name="assembly_id" value="<?= (int)$id ?>">
              <button class="btn btn-sm btn-outline-secondary"><?= e(t('assembly_regen')) ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('groups_active_members')) ?></div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>#</th><th><?= e(t('name')) ?></th><th><?= e(t('assembly_role')) ?></th></tr></thead>
            <tbody>
              <?php $i=1; foreach ($members as $mm): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><?= e($mm['name']) ?></td>
                  <td><span class="badge text-bg-secondary"><?= e($mm['role']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('groups_pending_members')) ?></div>
        <?php if (!$canManage): ?>
          <div class="text-muted"><?= e(t('assembly_leader_only')) ?></div>
        <?php elseif (!$pending): ?>
          <div class="text-muted"><?= e(t('groups_none')) ?></div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th><?= e(t('name')) ?></th><th><?= e(t('email')) ?></th><th></th></tr></thead>
              <tbody>
                <?php foreach ($pending as $p): ?>
                  <tr>
                    <td><?= e($p['name']) ?></td>
                    <td><?= e($p['email']) ?></td>
                    <td class="text-end">
                      <form method="post" action="<?= e(base_url('action/approve-member')) ?>" class="m-0">
                        <input type="hidden" name="member_id" value="<?= (int)$p['id'] ?>">
                        <button class="btn btn-sm btn-outline-success"><?= e(t('groups_activate_member')) ?></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($canManage): ?>
  <div class="card mt-3">
    <div class="card-body">
      <div class="fw-semibold mb-2"><?= e(t('groups_invite_member')) ?></div>
      <input class="form-control form-control-sm mb-2" id="inviteSearch" placeholder="<?= e(t('groups_invite_search')) ?>">
      <div class="list-group list-group-flush" id="inviteResults" style="display:none;"></div>
      <?php if ($invited): ?>
        <div class="mt-3 fw-semibold"><?= e(t('groups_invited')) ?></div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th><?= e(t('name')) ?></th><th><?= e(t('email')) ?></th></tr></thead>
            <tbody>
              <?php foreach ($invited as $inv): ?>
                <tr><td><?= e($inv['name']) ?></td><td><?= e($inv['email']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-body">
      <div class="fw-semibold mb-2"><?= e(t('group_add_progress')) ?></div>
      <form method="post" action="<?= e(base_url('action/add-member-progress')) ?>" class="row g-2 align-items-end">
        <input type="hidden" name="assembly_id" value="<?= (int)$id ?>">
        <div class="col-md-4">
          <label class="form-label"><?= e(t('name')) ?></label>
          <select class="form-select form-select-sm" name="user_id" required>
            <?php foreach ($members as $mm): ?>
              <option value="<?= (int)$mm['id'] ?>"><?= e($mm['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label"><?= e(t('group_add_progress_day')) ?></label>
          <input class="form-control form-control-sm" type="date" name="day" value="<?= e(now_ymd()) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label"><?= e(t('group_add_progress_type')) ?></label>
          <select class="form-select form-select-sm" name="type" required>
            <option value="prayer_minutes"><?= e(t('progress_default_prayer')) ?></option>
            <option value="tracktate"><?= e(t('progress_default_tracktate')) ?></option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label"><?= e(t('group_add_progress_value')) ?></label>
          <input class="form-control form-control-sm" type="number" step="0.01" name="value" required>
        </div>
        <div class="col-12">
          <button class="btn btn-sm btn-outline-primary"><?= e(t('group_add_progress_btn')) ?></button>
        </div>
      </form>
    </div>
  </div>

  <?php $dompdfInstalled = class_exists('Dompdf\\Dompdf'); ?>
  <div class="card mt-3">
    <div class="card-body">
      <div class="fw-semibold mb-2"><?= e(t('assembly_leader_tools')) ?></div>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary btn-sm" href="<?= e(base_url('assemblies/summary?id='.(int)$id)) ?>"><?= e(t('assembly_summary')) ?></a>
        <?php if ($dompdfInstalled): ?>
          <a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('assemblies/summary?id='.(int)$id.'&pdf=1')) ?>"><?= e(t('assembly_pdf')) ?></a>
        <?php else: ?>
          <span class="text-muted small"><?= e(t('assembly_pdf')) ?>: <?= e(t('assembly_pdf_missing')) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
  var btn = document.getElementById('copyCode');
  if (btn) {
    btn.addEventListener('click', function(){
      var code = document.getElementById('inviteCode').textContent;
      navigator.clipboard.writeText(code);
      btn.textContent = 'OK';
      setTimeout(function(){ btn.textContent = '<?= e(t('groups_invite_copy')) ?>'; }, 1200);
    });
  }

  var inviteInput = document.getElementById('inviteSearch');
  var inviteResults = document.getElementById('inviteResults');
  if (inviteInput && inviteResults) {
    inviteInput.addEventListener('input', function(){
      var q = inviteInput.value.trim();
      if (q.length < 2) {
        inviteResults.style.display = 'none';
        inviteResults.innerHTML = '';
        return;
      }
      fetch('<?= e(base_url('action/search-group-users')) ?>?assembly_id=<?= (int)$id ?>&q=' + encodeURIComponent(q), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(r => r.json()).then(function(res){
        inviteResults.innerHTML = '';
        if (!res || !res.items || res.items.length === 0) {
          inviteResults.style.display = 'none';
          return;
        }
        res.items.forEach(function(u){
          var row = document.createElement('div');
          row.className = 'list-group-item d-flex align-items-center justify-content-between gap-2';
          row.innerHTML = '<div><div class="fw-semibold">'+u.name+'</div><div class="text-muted small">'+(u.email || '')+'</div></div>';
          var btn = document.createElement('button');
          btn.className = 'btn btn-sm btn-outline-primary';
          btn.textContent = '<?= e(t('groups_invite_add')) ?>';
          btn.addEventListener('click', function(){
            var fd = new FormData();
            fd.set('assembly_id', '<?= (int)$id ?>');
            fd.set('user_id', u.id);
            fetch('<?= e(base_url('action/invite-member')) ?>', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
              .then(r => r.json())
              .then(function(resp){
                if (resp && resp.ok) {
                  btn.textContent = 'OK';
                  btn.disabled = true;
                }
              });
          });
          row.appendChild(btn);
          inviteResults.appendChild(row);
        });
        inviteResults.style.display = '';
      });
    });
  }
</script>
<?php include __DIR__ . '/_layout_bottom.php'; ?>
