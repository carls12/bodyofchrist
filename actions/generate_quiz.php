<?php
require_once __DIR__ . '/../app/middleware.php'; bootstrap_app();
require_once __DIR__ . '/../app/auth.php'; require_auth();
require_once __DIR__ . '/../app/helpers.php';

header('Content-Type: application/json');

$ref = trim($_POST['reference'] ?? '');
$count = (int)($_POST['count'] ?? 6);
$quizType = trim($_POST['quiz_type'] ?? 'mcq');
if (!in_array($quizType, ['mcq','tf','free'], true)) $quizType = 'mcq';
if ($count <= 0) $count = 6;
if ($count > 10) $count = 10;

if ($ref === '') {
  echo json_encode(['ok' => false, 'error' => 'missing_reference']);
  exit;
}

function normalize_book(string $s): string {
  if (function_exists('mb_strtolower')) $s = mb_strtolower($s, 'UTF-8');
  else $s = strtolower($s);
  $s = str_replace(['.', ',', ';', ':', '-'], ' ', $s);
  $s = preg_replace('/\s+/', ' ', $s);
  $map = [
    'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
    'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
    'á' => 'a', 'à' => 'a', 'â' => 'a', 'å' => 'a',
    'í' => 'i', 'ì' => 'i', 'î' => 'i',
    'ó' => 'o', 'ò' => 'o', 'ô' => 'o',
    'ú' => 'u', 'ù' => 'u', 'û' => 'u',
    'ç' => 'c',
  ];
  $s = strtr($s, $map);
  return trim($s);
}

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

// Split reference into book + chapter
$parts = preg_split('/\s+/', $ref);
$chapter = 0;
if ($parts) {
  $last = $parts[count($parts) - 1];
  if (preg_match('/^\d+$/', $last)) {
    $chapter = (int)$last;
    array_pop($parts);
  }
}
$bookRef = trim(implode(' ', $parts));
if ($bookRef === '' || $chapter <= 0) {
  echo json_encode(['ok' => false, 'error' => 'invalid_reference']);
  exit;
}

$loc = $_SESSION['locale'] ?? 'de';
$dataPath = __DIR__ . '/../data/bible_' . $loc . '.json';
$fallbackPath = __DIR__ . '/../data/bible.json';
$dataPathPublic = __DIR__ . '/../public/data/bible.json';
$jsonPath = file_exists($dataPath) ? $dataPath : (file_exists($fallbackPath) ? $fallbackPath : (file_exists($dataPathPublic) ? $dataPathPublic : null));
if (!$jsonPath) {
  echo json_encode(['ok' => false, 'error' => 'bible_missing']);
  exit;
}

$raw = json_decode(file_get_contents($jsonPath), true);
if (!is_array($raw)) {
  echo json_encode(['ok' => false, 'error' => 'bible_read_error']);
  exit;
}

$list = $raw['books'] ?? $raw['data'] ?? $raw;
if (!is_array($list)) {
  echo json_encode(['ok' => false, 'error' => 'bible_format_error']);
  exit;
}

$bookKey = null;
$bookNorm = normalize_book($bookRef);
$keys = array_keys($list);
$isAssoc = $keys !== range(0, count($keys) - 1);
if ($isAssoc) {
  foreach ($list as $k => $_) {
    if (normalize_book((string)$k) === $bookNorm) { $bookKey = (string)$k; break; }
  }
} else {
  foreach ($list as $b) {
    if (!is_array($b)) continue;
    $name = $b['name'] ?? $b['book'] ?? $b['title'] ?? null;
    if ($name && normalize_book((string)$name) === $bookNorm) { $bookKey = (string)$name; break; }
  }
}

if ($bookKey === null) {
  $best = null;
  $bestScore = 999;
  $candidates = [];
  if ($isAssoc) {
    foreach ($list as $k => $_) $candidates[] = (string)$k;
  } else {
    foreach ($list as $b) {
      if (!is_array($b)) continue;
      $name = $b['name'] ?? $b['book'] ?? $b['title'] ?? null;
      if ($name) $candidates[] = (string)$name;
    }
  }
  foreach ($candidates as $cand) {
    $cn = normalize_book($cand);
    if ($cn === $bookNorm) { $best = $cand; break; }
    if (function_exists('levenshtein')) {
      $d = levenshtein($bookNorm, $cn);
      if ($d < $bestScore) { $bestScore = $d; $best = $cand; }
    }
  }
  if ($best !== null && $bestScore <= 3) {
    $bookKey = $best;
  } else {
    echo json_encode(['ok' => false, 'error' => 'book_not_found']);
    exit;
  }
}

$chapterData = null;
if ($isAssoc) {
  $chapters = $list[$bookKey] ?? null;
  if (is_array($chapters)) {
    $chapterData = $chapters[(string)$chapter] ?? $chapters[$chapter] ?? null;
    if ($chapterData === null && isset($chapters[$chapter - 1])) $chapterData = $chapters[$chapter - 1];
  }
} else {
  foreach ($list as $b) {
    if (!is_array($b)) continue;
    $name = $b['name'] ?? $b['book'] ?? $b['title'] ?? null;
    if ($name !== $bookKey) continue;
    if (isset($b['chapters']) && is_array($b['chapters'])) {
      $chapterData = $b['chapters'][$chapter] ?? $b['chapters'][(string)$chapter] ?? null;
      if ($chapterData === null && isset($b['chapters'][$chapter - 1])) $chapterData = $b['chapters'][$chapter - 1];
      if (is_array($chapterData) && isset($chapterData['verses']) && is_array($chapterData['verses'])) {
        $chapterData = $chapterData['verses'];
      }
    }
    break;
  }
}

if (!is_array($chapterData)) {
  echo json_encode(['ok' => false, 'error' => 'chapter_not_found']);
  exit;
}

// Normalize verse list to 1-based array
$verses = [];
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

if (count($verses) < 4) {
  echo json_encode(['ok' => false, 'error' => 'not_enough_verses']);
  exit;
}

// Local generation (no AI)
mt_srand((int)(microtime(true) * 1000000));
$verseNums = array_keys($verses);
shuffle($verseNums);
$picked = array_slice($verseNums, 0, min($count, count($verseNums)));

$qTextMap = [
  'de' => [
    'mcq' => 'Welche Aussage passt zum Kapitel?',
    'tf' => 'Die folgende Aussage ist aus dem Kapitel:',
    'free' => 'Schreibe in eigenen Worten: {s}',
  ],
  'en' => [
    'mcq' => 'Which statement fits the chapter?',
    'tf' => 'The following statement is from the chapter:',
    'free' => 'Rewrite in your own words: {s}',
  ],
  'fr' => [
    'mcq' => 'Quelle phrase correspond au chapitre ?',
    'tf' => 'La phrase suivante vient du chapitre :',
    'free' => 'Reformule avec tes mots : {s}',
  ],
];
$tpl = $qTextMap[$loc][$quizType] ?? $qTextMap['de'][$quizType];

$questions = [];
foreach ($picked as $vnum) {
  $correct = $verses[$vnum];
  if ($quizType === 'free') {
    $questions[] = [
      'q' => str_replace('{s}', $correct, $tpl),
      'answer_text' => $correct,
    ];
    continue;
  }
  if ($quizType === 'tf') {
    $others = array_values(array_filter($verseNums, fn($n) => $n !== $vnum));
    shuffle($others);
    $useTrue = (mt_rand(0, 1) === 1);
    $statement = $useTrue ? $correct : $verses[$others[0]];
    $questions[] = [
      'q' => $tpl . ' ' . $statement,
      'options' => ($loc === 'en') ? ['True','False'] : (($loc === 'fr') ? ['Vrai','Faux'] : ['Wahr','Falsch']),
      'answer' => $useTrue ? 0 : 1,
    ];
    continue;
  }
  // MCQ
  $others = array_values(array_filter($verseNums, fn($n) => $n !== $vnum));
  shuffle($others);
  $opts = [$correct];
  foreach (array_slice($others, 0, 3) as $on) {
    $opts[] = $verses[$on];
  }
  shuffle($opts);
  $answerIndex = array_search($correct, $opts, true);
  $questions[] = [
    'q' => str_replace('{v}', (string)$vnum, $tpl),
    'options' => $opts,
    'answer' => $answerIndex === false ? 0 : $answerIndex,
  ];
}

echo json_encode(['ok' => true, 'questions' => $questions]);
exit;
