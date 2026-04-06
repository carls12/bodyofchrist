<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

if (!is_main_admin()) { http_response_code(403); die('Forbidden'); }

db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_regional_leader TINYINT(1) NOT NULL DEFAULT 0");
db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_national_leader TINYINT(1) NOT NULL DEFAULT 0");
db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL");
db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS country VARCHAR(120) NULL");
$users = db()->query("SELECT id,name,email,is_leader,is_regional_leader,is_national_leader,region,country,created_at FROM users ORDER BY id DESC LIMIT 200")->fetchAll();
$countries = country_list();

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <div class="text-muted small"><?= e(t('nav_admin')) ?></div>
    <h2 class="h4 mb-0"><?= e(t('admin_users')) ?></h2>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
      <input class="form-control form-control-sm w-auto" id="userSearch" placeholder="<?= e(t('admin_search')) ?>" style="min-width:240px;">
      <span class="text-muted small" id="userSearchCount"></span>
    </div>
    <div class="table-responsive">
      <style>
        .admin-users-table th,
        .admin-users-table td { vertical-align: top; }
        .admin-user-actions { min-width: 420px; }
        .admin-user-actions .action-stack { display: flex; flex-direction: column; gap: 6px; }
        .admin-user-actions .action-row { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; justify-content: flex-end; }
        .admin-user-actions .form-control { height: 30px; padding: .2rem .45rem; font-size: .78rem; }
        .admin-user-actions .btn { padding: .2rem .5rem; font-size: .78rem; }
        @media (max-width: 1200px){ .admin-user-actions { min-width: 320px; } }
        @media (max-width: 768px){ .admin-user-actions { min-width: 260px; } }
      </style>
      <table class="table align-middle admin-users-table" id="usersTable">
        <thead><tr><th>ID</th><th><?= e(t('name')) ?></th><th><?= e(t('email')) ?></th><th><?= e(t('admin_leader')) ?></th><th><?= e(t('admin_regional_leader')) ?></th><th><?= e(t('admin_region')) ?></th><th><?= e(t('admin_national_leader')) ?></th><th><?= e(t('admin_country')) ?></th><th></th></tr></thead>
        <tbody>
          <?php foreach($users as $u): ?>
            <tr>
              <td><?= (int)$u['id'] ?></td>
              <td><?= e($u['name']) ?></td>
              <td><?= e($u['email']) ?></td>
              <td><?= (int)$u['is_leader'] ? e(t('admin_yes')) : e(t('admin_no')) ?></td>
              <td><?= (int)$u['is_regional_leader'] ? e(t('admin_yes')) : e(t('admin_no')) ?></td>
              <td><?= e($u['region'] ?? '') ?></td>
              <td><?= (int)$u['is_national_leader'] ? e(t('admin_yes')) : e(t('admin_no')) ?></td>
              <td><?= e($u['country'] ?? '') ?></td>
              <td class="text-end admin-user-actions">
                <div class="action-stack">
                  <form method="post" action="<?= e(base_url('action/toggle-leader')) ?>" class="m-0 action-row">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-sm <?= (int)$u['is_leader'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                      <?= (int)$u['is_leader'] ? e(t('admin_remove_leader')) : e(t('admin_set_leader')) ?>
                    </button>
                  </form>
                  <form method="post" action="<?= e(base_url('action/set-regional-leader')) ?>" class="m-0 action-row">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <input class="form-control form-control-sm w-auto" name="region" placeholder="<?= e(t('admin_region')) ?>" value="<?= e($u['region'] ?? '') ?>">
                    <button class="btn btn-sm btn-outline-primary" name="action" value="save"><?= e(t('admin_save')) ?></button>
                    <?php if ((int)$u['is_regional_leader']): ?>
                      <button class="btn btn-sm btn-outline-danger" name="action" value="remove"><?= e(t('admin_remove_regional_leader')) ?></button>
                    <?php else: ?>
                      <button class="btn btn-sm btn-outline-success" name="action" value="set"><?= e(t('admin_set_regional_leader')) ?></button>
                    <?php endif; ?>
                  </form>
                  <form method="post" action="<?= e(base_url('action/set-national-leader')) ?>" class="m-0 action-row">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <select class="form-select form-select-sm w-auto" name="country">
                    <option value=""><?= e(t('admin_country')) ?></option>
                    <?php foreach ($countries as $c): ?>
                      <option value="<?= e($c) ?>" <?= ($u['country'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                  </select>
                    <button class="btn btn-sm btn-outline-primary" name="action" value="save"><?= e(t('admin_save')) ?></button>
                    <?php if ((int)$u['is_national_leader']): ?>
                      <button class="btn btn-sm btn-outline-danger" name="action" value="remove"><?= e(t('admin_remove_national_leader')) ?></button>
                    <?php else: ?>
                      <button class="btn btn-sm btn-outline-success" name="action" value="set"><?= e(t('admin_set_national_leader')) ?></button>
                    <?php endif; ?>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  (function(){
    var input = document.getElementById('userSearch');
    var table = document.getElementById('usersTable');
    var counter = document.getElementById('userSearchCount');
    if (!input || !table) return;
    var rows = Array.prototype.slice.call(table.tBodies[0].rows);
    function norm(s){ return (s || '').toString().toLowerCase().trim(); }
    function apply(){
      var q = norm(input.value);
      var visible = 0;
      rows.forEach(function(row){
        var text = norm(row.textContent);
        var match = q === '' || text.indexOf(q) !== -1;
        row.style.display = match ? '' : 'none';
        if (match) visible++;
      });
      if (counter) counter.textContent = q ? (visible + ' / ' + rows.length) : '';
    }
    input.addEventListener('input', apply);
  })();
</script>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
