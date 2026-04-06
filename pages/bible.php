<?php require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth(); refresh_auth_user();
require_once __DIR__ . '/../app/db.php'; require_once __DIR__ . '/../app/helpers.php';

$loc = $_SESSION['locale'] ?? 'de';
$prefKey = 'all';
$aid = active_assembly_id();

db()->exec("ALTER TABLE bible_readings ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE bible_readings ADD INDEX IF NOT EXISTS idx_bible_assembly (assembly_id, read_date)");

db()->exec("CREATE TABLE IF NOT EXISTS bible_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lang VARCHAR(5) NOT NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  UNIQUE KEY uniq_lang_slug (lang, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

db()->exec("CREATE TABLE IF NOT EXISTS user_bible_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  lang VARCHAR(5) NOT NULL,
  version_id INT UNSIGNED NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_user_lang (user_id, lang),
  INDEX (version_id),
  CONSTRAINT fk_ubv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ubv_version FOREIGN KEY (version_id) REFERENCES bible_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$dataPath = __DIR__ . '/../data/bible_' . $loc . '.json';
$fallbackPath = __DIR__ . '/../data/bible.json';
$dataPathPublic = __DIR__ . '/../public/data/bible.json';

// Auto-register default file if versions are missing
$vcount = db()->prepare("SELECT COUNT(*) AS c FROM bible_versions WHERE lang=?");
$vcount->execute([$loc]);
$hasVersion = (int)($vcount->fetch()['c'] ?? 0) > 0;
if (!$hasVersion) {
  $defaultPath = file_exists($dataPath) ? $dataPath : (file_exists($fallbackPath) ? $fallbackPath : null);
  if ($defaultPath) {
    db()->prepare("INSERT INTO bible_versions(lang,name,slug,file_path,active,created_at)
      VALUES(?,?,?,?,1,NOW())")
      ->execute([$loc, 'Default', 'default', $defaultPath]);
  }
}

$versionsStmt = db()->prepare("SELECT * FROM bible_versions WHERE active=1 ORDER BY id DESC");
$versionsStmt->execute();
$versions = $versionsStmt->fetchAll();

$prefStmt = db()->prepare("SELECT v.* FROM user_bible_versions u
  JOIN bible_versions v ON v.id=u.version_id
  WHERE u.user_id=? AND u.lang=? LIMIT 1");
$prefStmt->execute([auth_user()['id'], $prefKey]);
$pref = $prefStmt->fetch();

$selectedVersionId = isset($_GET['version_id']) ? (int)$_GET['version_id'] : (int)($pref['id'] ?? 0);
$selectedVersion = null;
foreach ($versions as $v) {
  if ((int)$v['id'] === $selectedVersionId) { $selectedVersion = $v; break; }
}
if (!$selectedVersion && $versions) $selectedVersion = $versions[0];

$jsonPath = $selectedVersion['file_path'] ?? (file_exists($dataPath) ? $dataPath : (file_exists($fallbackPath) ? $fallbackPath : (file_exists($dataPathPublic) ? $dataPathPublic : null)));
$books = [];
$dataError = null;
$verses = [];

function fix_mojibake(string $s): string {
  if (!preg_match('/Ã.|Â.|â€™|â€“|â€”|â€œ|â€|â€˜|â€¢/', $s)) return $s;
  if (function_exists('mb_convert_encoding')) {
    return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
  }
  if (function_exists('iconv')) {
    $out = iconv('ISO-8859-1', 'UTF-8//IGNORE', $s);
    if ($out !== false) return $out;
  }
  return $s;
}

if ($jsonPath) {
  $raw = json_decode(file_get_contents($jsonPath), true);
  if (!is_array($raw)) {
    $dataError = t('bible_error_read');
  } else {
    $list = $raw['books'] ?? $raw['data'] ?? $raw;
    if (is_array($list)) {
      $keys = array_keys($list);
      $isAssoc = $keys !== range(0, count($keys) - 1);
      if ($isAssoc) {
        foreach ($list as $bookName => $chapters) {
          if (!is_array($chapters)) continue;
          $chapCount = count($chapters);
          if ($chapCount <= 0) continue;
          $books[] = ['name' => (string)$bookName, 'label' => fix_mojibake((string)$bookName), 'chapters' => $chapCount];
        }
      } else {
        foreach ($list as $b) {
          if (!is_array($b)) continue;
          $name = $b['name'] ?? $b['book'] ?? $b['title'] ?? null;
          if (!$name) continue;
          $chapters = $b['chapters'] ?? $b['chapterCount'] ?? $b['chaptersCount'] ?? null;
          if (is_array($chapters)) $chapCount = count($chapters);
          else $chapCount = (int)$chapters;
          if ($chapCount <= 0 && isset($b['chapter'])) $chapCount = (int)$b['chapter'];
          if ($chapCount <= 0) continue;
          $books[] = ['name' => (string)$name, 'label' => fix_mojibake((string)$name), 'chapters' => $chapCount];
        }
      }
    }
  }
} else {
  $dataError = t('bible_error_missing');
}

$selectedBook = $_GET['book'] ?? ($books[0]['name'] ?? '');
$selectedBook = (string)$selectedBook;
$selectedChapter = isset($_GET['chapter']) ? (int)$_GET['chapter'] : 1;

if ($jsonPath && is_array($raw ?? null) && $selectedBook !== '' && $selectedChapter > 0) {
  $list = $raw['books'] ?? $raw['data'] ?? $raw;
  if (is_array($list)) {
    $keys = array_keys($list);
    $isAssoc = $keys !== range(0, count($keys) - 1);
    $chapterData = null;
    if ($isAssoc && isset($list[$selectedBook]) && is_array($list[$selectedBook])) {
      $chapters = $list[$selectedBook];
      $chapterData = $chapters[(string)$selectedChapter] ?? $chapters[$selectedChapter] ?? null;
      if ($chapterData === null && isset($chapters[$selectedChapter - 1])) $chapterData = $chapters[$selectedChapter - 1];
    } elseif (!$isAssoc) {
      foreach ($list as $b) {
        if (!is_array($b)) continue;
        $name = $b['name'] ?? $b['book'] ?? $b['title'] ?? null;
        if ($name !== $selectedBook) continue;
        if (isset($b['chapters']) && is_array($b['chapters'])) {
          $chapterData = $b['chapters'][$selectedChapter] ?? $b['chapters'][(string)$selectedChapter] ?? null;
          if ($chapterData === null && isset($b['chapters'][$selectedChapter - 1])) $chapterData = $b['chapters'][$selectedChapter - 1];
          if (is_array($chapterData) && isset($chapterData['verses']) && is_array($chapterData['verses'])) {
            $chapterData = $chapterData['verses'];
          }
        }
        break;
      }
    }

    if (is_array($chapterData)) {
      $cKeys = array_keys($chapterData);
      $cAssoc = $cKeys !== range(0, count($cKeys) - 1);
      if ($cAssoc) {
        foreach ($chapterData as $vn => $text) {
          $verses[(int)$vn] = fix_mojibake((string)$text);
        }
      } else {
        foreach ($chapterData as $idx => $text) {
          $verses[$idx + 1] = fix_mojibake((string)$text);
        }
      }
    }
  }
}

$readChapters = [];
if ($selectedBook !== '') {
  $stmt = db()->prepare("SELECT chapter FROM bible_readings WHERE user_id=? AND book=? AND (assembly_id=? OR assembly_id IS NULL)");
  $stmt->execute([auth_user()['id'], $selectedBook, $aid]);
  $readChapters = array_map(fn($r) => (int)$r['chapter'], $stmt->fetchAll());
}

$recent = db()->prepare("SELECT book, chapter, read_date, notes FROM bible_readings
  WHERE user_id=? AND (assembly_id=? OR assembly_id IS NULL) ORDER BY read_date DESC, id DESC LIMIT 12");
$recent->execute([auth_user()['id'], $aid]);
$recentReads = $recent->fetchAll();

include __DIR__ . '/_layout_top.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small"><?= e(t('bible_title')) ?></div>
    <h2 class="h4 mb-1"><?= e(t('bible_sub')) ?></h2>
    <div class="text-muted"><?= e(t('bible_help')) ?></div>
  </div>
</div>

<?php if ($dataError): ?>
  <div class="alert alert-warning"><?= e($dataError) ?></div>
<?php endif; ?>

<?php if (is_main_admin()): ?>
  <div class="card mb-3">
    <div class="card-body">
      <div class="fw-semibold mb-2"><?= e(t('bible_upload')) ?></div>
      <form method="post" action="<?= e(base_url('action/upload-bible')) ?>" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
        <select class="form-select" name="lang" style="max-width:160px">
          <option value="de">Deutsch</option>
          <option value="en">English</option>
          <option value="fr">Francais</option>
        </select>
        <input class="form-control" name="version_name" placeholder="<?= e(t('bible_version_name')) ?>" style="max-width:220px" required>
        <input class="form-control" type="file" name="bible" accept="application/json" required>
        <button class="btn btn-outline-primary"><?= e(t('bible_upload_btn')) ?></button>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('bible_book_select')) ?></div>
        <?php if ($versions): ?>
          <form method="post" action="<?= e(base_url('action/set-bible-version')) ?>" class="d-grid gap-2 mb-2">
            <input type="hidden" name="lang" value="<?= e($prefKey) ?>">
            <select class="form-select" name="version_id" onchange="this.form.submit()">
              <?php foreach ($versions as $v): ?>
                <option value="<?= (int)$v['id'] ?>" <?= $selectedVersion && (int)$selectedVersion['id'] === (int)$v['id'] ? 'selected' : '' ?>>
                  <?= e($v['name']) ?> (<?= e($v['lang']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </form>
        <?php endif; ?>
        <form method="get" class="d-grid gap-2">
          <?php if ($selectedVersion): ?>
            <input type="hidden" name="version_id" value="<?= (int)$selectedVersion['id'] ?>">
          <?php endif; ?>
          <select class="form-select" name="book">
            <?php foreach ($books as $b): ?>
              <option value="<?= e($b['name']) ?>" <?= $b['name']===$selectedBook ? 'selected' : '' ?>>
                <?= e($b['label'] ?? $b['name']) ?> (<?= (int)$b['chapters'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-outline-primary"><?= e(t('bible_show')) ?></button>
        </form>
        <div class="text-muted small mt-3"><?= e(t('bible_file')) ?>:
          <?= e($selectedVersion['name'] ?? ($jsonPath ? basename($jsonPath) : t('bible_not_found'))) ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-body">
        <div class="fw-semibold mb-2"><?= e(t('bible_mark')) ?></div>
        <?php
          $chapCount = 0;
          foreach ($books as $b) if ($b['name'] === $selectedBook) $chapCount = (int)$b['chapters'];
        ?>
        <?php if ($chapCount === 0): ?>
          <div class="text-muted"><?= e(t('bible_none')) ?></div>
        <?php else: ?>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <?php for ($i=1; $i<=$chapCount; $i++): ?>
              <?php $done = in_array($i, $readChapters, true); ?>
              <a class="btn btn-sm <?= $done ? 'btn-success' : 'btn-outline-secondary' ?>" href="<?= e(base_url('bible?version_id=' . (int)($selectedVersion['id'] ?? 0) . '&book=' . urlencode($selectedBook) . '&chapter=' . $i)) ?>">
                <?= $i ?>
              </a>
            <?php endfor; ?>
          </div>

          <form method="post" action="<?= e(base_url('action/save-bible-reading')) ?>" class="d-grid gap-2">
            <input type="hidden" name="book" value="<?= e($selectedBook) ?>">
            <input type="hidden" name="chapter" id="chapterInput" value="<?= (int)$selectedChapter ?>">
            <div class="text-muted small"><?= e(t('bible_selected', ['num' => (int)$selectedChapter])) ?></div>
            <textarea class="form-control" name="notes" rows="3" placeholder="<?= e(t('bible_notes')) ?>"></textarea>
            <button class="btn btn-primary"><?= e(t('bible_mark_read')) ?></button>
          </form>

          <div class="mt-3">
            <div class="fw-semibold mb-2"><?= e(t('bible_verses_title', ['num' => (int)$selectedChapter])) ?></div>
            <?php if (!$verses): ?>
              <div class="text-muted"><?= e(t('bible_no_verses')) ?></div>
            <?php else: ?>
              <div class="list-group list-group-flush">
                <?php foreach ($verses as $vn => $text): ?>
                  <div class="list-group-item">
                    <div class="fw-semibold small"><?= (int)$vn ?></div>
                    <div><?= e($text) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if (is_main_admin() && $versions): ?>
  <div class="card mt-3">
    <div class="card-body">
      <div class="fw-semibold mb-2"><?= e(t('bible_versions_manage')) ?></div>
      <div class="list-group list-group-flush">
        <?php foreach ($versions as $v): ?>
          <div class="list-group-item d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
              <div class="fw-semibold"><?= e($v['name']) ?></div>
              <div class="text-muted small"><?= e($v['lang']) ?> • <?= e(basename($v['file_path'])) ?></div>
            </div>
            <div class="d-flex gap-2 align-items-center">
              <form method="post" action="<?= e(base_url('action/update-bible-version')) ?>" class="d-flex gap-2 align-items-center m-0">
                <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                <input class="form-control form-control-sm" name="name" value="<?= e($v['name']) ?>" style="max-width:200px">
                <button class="btn btn-sm btn-outline-secondary"><?= e(t('btn_save')) ?></button>
              </form>
              <form method="post" action="<?= e(base_url('action/delete-bible-version')) ?>" class="m-0">
                <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= e(t('bible_version_delete_confirm')) ?>')">
                  <?= e(t('bible_version_delete')) ?>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="card mt-3">
  <div class="card-body">
    <div class="fw-semibold mb-2"><?= e(t('bible_recent')) ?></div>
    <?php if (!$recentReads): ?>
      <div class="text-muted"><?= e(t('bible_no_entries')) ?></div>
    <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($recentReads as $r): ?>
          <div class="list-group-item d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-semibold"><?= e(fix_mojibake($r['book'])) ?> <?= (int)$r['chapter'] ?></div>
              <?php if ($r['notes']): ?><div class="text-muted small"><?= e($r['notes']) ?></div><?php endif; ?>
            </div>
            <div class="text-muted small"><?= e($r['read_date']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/_layout_bottom.php'; ?>
