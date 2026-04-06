<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$ddl = "CREATE TABLE IF NOT EXISTS bible_readings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  assembly_id INT UNSIGNED NULL,
  book VARCHAR(120) NOT NULL,
  chapter INT UNSIGNED NOT NULL,
  read_date DATE NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_read (user_id, book, chapter, read_date),
  INDEX (user_id, book),
  INDEX (assembly_id, read_date),
  CONSTRAINT fk_bible_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
db()->exec($ddl);
db()->exec("ALTER TABLE bible_readings ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE bible_readings ADD INDEX IF NOT EXISTS idx_bible_assembly (assembly_id, read_date)");

$ddlProgress = "CREATE TABLE IF NOT EXISTS daily_progress (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  assembly_id INT UNSIGNED NULL,
  day DATE NOT NULL,
  category VARCHAR(120) NOT NULL,
  value DECIMAL(10,2) NOT NULL DEFAULT 0,
  note TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  INDEX (user_id, day),
  INDEX (assembly_id, day),
  INDEX (category),
  CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
db()->exec($ddlProgress);
db()->exec("ALTER TABLE daily_progress ADD COLUMN IF NOT EXISTS assembly_id INT UNSIGNED NULL");
db()->exec("ALTER TABLE daily_progress ADD INDEX IF NOT EXISTS idx_daily_progress_assembly (assembly_id, day)");

$book = trim($_POST['book'] ?? '');
$chapter = (int)($_POST['chapter'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if ($book === '' || $chapter <= 0) {
  flash_set('error', t('flash_bible_required'));
  redirect(base_url('bible'));
}

$today = now_ymd();
$aid = active_assembly_id();

$exists = db()->prepare("SELECT id FROM bible_readings WHERE user_id=? AND book=? AND chapter=? AND read_date=? AND (assembly_id=? OR assembly_id IS NULL) LIMIT 1");
$exists->execute([auth_user()['id'], $book, $chapter, $today, $aid]);
$found = $exists->fetch();

if ($found) {
  $upd = db()->prepare("UPDATE bible_readings SET notes=?, updated_at=NOW() WHERE id=? AND user_id=?");
  $upd->execute([$notes === '' ? null : $notes, (int)$found['id'], auth_user()['id']]);
} else {
  $stmt = db()->prepare("INSERT INTO bible_readings (user_id, assembly_id, book, chapter, read_date, notes, created_at, updated_at)
    VALUES (?,?,?,?,?,?,NOW(),NOW())");
  $stmt->execute([auth_user()['id'], $aid, $book, $chapter, $today, $notes === '' ? null : $notes]);

  $note = $book . ' ' . (int)$chapter;
  $prog = db()->prepare("INSERT INTO daily_progress(user_id,assembly_id,day,category,value,note,created_at,updated_at)
    VALUES(?,?,?,?,?,?,NOW(),NOW())");
  $prog->execute([auth_user()['id'], $aid, $today, 'bible_chapters', 1, $note]);
}

flash_set('success', t('flash_bible_saved'));
redirect(base_url('bible?book=' . urlencode($book) . '&chapter=' . (int)$chapter));
