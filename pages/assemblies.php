<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS type ENUM('discipleship','assembly') NOT NULL DEFAULT 'assembly'");
db()->exec("ALTER TABLE assemblies ADD COLUMN IF NOT EXISTS chat_enabled TINYINT(1) NOT NULL DEFAULT 1");
db()->exec("ALTER TABLE assembly_members ADD COLUMN IF NOT EXISTS status ENUM('pending','active','inactive') NOT NULL DEFAULT 'active'");
db()->exec("ALTER TABLE assembly_members MODIFY COLUMN status ENUM('pending','invited','active','inactive') NOT NULL DEFAULT 'active'");

$uid = auth_user()['id'];
$asm = db()->prepare("SELECT a.*, am.role FROM assembly_members am
  JOIN assemblies a ON a.id=am.assembly_id
  WHERE am.user_id=? AND am.active=1 AND am.status='active' ORDER BY am.id DESC LIMIT 1");
$asm->execute([$uid]);
$myAssembly = $asm->fetch();

$all = db()->prepare("SELECT a.id,a.name,a.description,a.type,am.role,am.active,am.status
  FROM assembly_members am
  JOIN assemblies a ON a.id=am.assembly_id
  WHERE am.user_id=? ORDER BY am.active DESC, a.name ASC");
$all->execute([$uid]);
$memberships = $all->fetchAll();

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <div class="text-muted small"><?= e(t('nav_groups')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('groups_title')) ?></h2>
    <div class="text-muted"><?= e(t('groups_sub')) ?></div>
  </div>
</div>

<?php if ($myAssembly): ?>
  <div class="alert alert-success">
    <?= e(t('groups_active')) ?>: <strong><?= e($myAssembly['name']) ?></strong> • <?= e(t('assembly_role')) ?>: <strong><?= e($myAssembly['role']) ?></strong>
    <a class="btn btn-sm btn-outline-dark ms-2" href="<?= e(base_url('assemblies/show?id='.(int)$myAssembly['id'])) ?>"><?= e(t('open')) ?></a>
  </div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('groups_join')) ?></div>
        <form method="post" action="<?= e(base_url('action/join-assembly')) ?>" class="d-grid gap-2">
          <div><label class="form-label"><?= e(t('code')) ?></label><input class="form-control" name="code" required></div>
          <button class="btn btn-primary"><?= e(t('join')) ?></button>
          <div class="text-muted small"><?= e(t('groups_waiting')) ?></div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('groups_create')) ?></div>
        <?php if (!is_main_admin()): ?>
          <div class="text-muted"><?= e(t('groups_not_admin_help')) ?></div>
        <?php else: ?>
          <form method="post" action="<?= e(base_url('action/create-assembly')) ?>" class="d-grid gap-2">
            <div><label class="form-label"><?= e(t('name')) ?></label><input class="form-control" name="name" required></div>
            <div>
              <label class="form-label"><?= e(t('groups_type_label')) ?></label>
              <select class="form-select" name="type">
                <option value="discipleship"><?= e(t('groups_type_discipleship')) ?></option>
                <option value="assembly"><?= e(t('groups_type_assembly')) ?></option>
              </select>
            </div>
            <div><label class="form-label"><?= e(t('groups_desc_label')) ?></label><input class="form-control" name="description"></div>
            <button class="btn btn-success"><?= e(t('create')) ?></button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="fw-semibold mb-2"><?= e(t('groups_my')) ?></div>
    <?php if (!$memberships): ?>
      <div class="text-muted"><?= e(t('groups_none')) ?></div>
    <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($memberships as $m): ?>
          <div class="list-group-item d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
              <div class="fw-semibold"><?= e($m['name']) ?></div>
              <div class="text-muted small"><?= e($m['description'] ?? '') ?></div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge-soft"><?= e($m['type']==='discipleship' ? t('groups_type_discipleship') : t('groups_type_assembly')) ?></span>
              <span class="badge-soft">
                <?php
                  if ($m['status']==='invited') echo e(t('groups_invited'));
                  elseif ($m['status']==='pending') echo e(t('groups_pending'));
                  elseif ($m['status']==='inactive') echo e(t('groups_inactive'));
                  else echo e(t('groups_active'));
                ?>
              </span>
              <span class="badge-soft"><?= e($m['role']) ?></span>
              <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('assemblies/show?id='.(int)$m['id'])) ?>"><?= e(t('view')) ?></a>
              <?php if ($m['status']==='invited'): ?>
                <form method="post" action="<?= e(base_url('action/accept-invite')) ?>" class="m-0">
                  <input type="hidden" name="assembly_id" value="<?= (int)$m['id'] ?>">
                  <button class="btn btn-sm btn-outline-success"><?= e(t('groups_accept_invite')) ?></button>
                </form>
              <?php endif; ?>
              <?php if (!$m['active'] && $m['status']==='active'): ?>
                <form method="post" action="<?= e(base_url('action/activate-assembly')) ?>" class="m-0">
                  <input type="hidden" name="assembly_id" value="<?= (int)$m['id'] ?>">
                  <button class="btn btn-sm btn-outline-success"><?= e(t('activate')) ?></button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
