<?php
session_start();

// --- 1. MULTI-USER AUTHENTICATION / STORAGE ---
// All application logic remains in this single PHP file. User accounts are stored
// as password-hashed records in a small JSON file created automatically under /data.
function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$dataRoot = __DIR__ . '/data';
if (!is_dir($dataRoot)) {
    mkdir($dataRoot, 0755, true);
}
$userFile = $dataRoot . '/users.json';

function loadUsers(): array {
    global $userFile;
    if (!file_exists($userFile)) return [];
    $data = json_decode((string)file_get_contents($userFile), true);
    return is_array($data) ? $data : [];
}

function saveUsers(array $users): void {
    global $userFile;
    $tmp = $userFile . '.tmp';
    if (file_put_contents($tmp, json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        throw new RuntimeException('Cannot write the user database. Ensure the data folder is writable.');
    }
    if (!rename($tmp, $userFile)) {
        @unlink($tmp);
        throw new RuntimeException('Cannot update the user database. Ensure the data folder is writable.');
    }
}

if (!file_exists($userFile)) {
    saveUsers([[
        'id' => 1,
        'username' => 'admin',
        'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
        'display_name' => 'Administrator',
        'subject' => 'All Subjects',
        'role' => 'admin',
        'folder_key' => 'admin',
        'active' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ]]);
}

function currentUser(): ?array {
    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id <= 0) return null;
    foreach (loadUsers() as $user) {
        if ((int)($user['id'] ?? 0) === $id && !empty($user['active'])) return $user;
    }
    return null;
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function safeFolderKey(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '_', $value);
    $value = trim($value, '_-');
    return $value !== '' ? $value : 'teacher';
}

function teacherUsers(): array {
    $users = array_filter(loadUsers(), fn($u) => ($u['role'] ?? '') === 'teacher');
    usort($users, fn($a, $b) => strcasecmp((string)$a['display_name'], (string)$b['display_name']));
    return array_values($users);
}

function userById(int $id): ?array {
    foreach (loadUsers() as $u) {
        if ((int)($u['id'] ?? 0) === $id && !empty($u['active'])) return $u;
    }
    return null;
}

function findUserByUsername(string $username): ?array {
    foreach (loadUsers() as $u) {
        if (strcasecmp((string)($u['username'] ?? ''), $username) === 0 && !empty($u['active'])) return $u;
    }
    return null;
}

// --- 2. LOGOUT LOGIC ---
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 3. LOGIN PROCESSING ---
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_login'])) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $user = findUserByUsername($username);

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['is_authenticated'] = true;
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['subject'] = $user['subject'];
        $_SESSION['teacher_id'] = $user['folder_key'];
        $_SESSION['active_owner_id'] = (int)$user['id'];
        // Never carry another account's selected bank, basket or paper configuration.
        unset($_SESSION['selected_bank'], $_SESSION['basket'], $_SESSION['doc_config'], $_SESSION['section_order'], $_SESSION['section_heading']);
        $_SESSION['basket'] = [];
        header("Location: " . basename($_SERVER['PHP_SELF']));
        exit;
    } else {
        $login_error = 'Invalid credentials. Please try again.';
    }
}

// --- 4. RENDER LOGIN SCREEN IF NOT AUTHENTICATED ---
if (empty($_SESSION['is_authenticated']) || !currentUser()) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Question Paper Generator</title>
        <style>
            body { font-family: Cambria, "Cambria Math", serif; background: #f6f7f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login-box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 350px; text-align: center; }
            .login-box h2 { margin-top: 0; color: #333; margin-bottom: 20px; }
            .login-box input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 15px; font-family: Cambria, serif; }
            .login-box button { width: 100%; padding: 10px; background: #1976d2; color: #fff; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; font-weight: bold; margin-top: 10px; font-family: Cambria, serif; }
            .login-box button:hover { background: #1565c0; }
            .error { color: #c62828; font-size: 14px; margin-bottom: 10px; background: #f8d7da; padding: 8px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>St. Paul's QP Generator</h2>
            <?php if ($login_error): ?><div class="error"><?= h($login_error) ?></div><?php endif; ?>
            <form method="post">
                <input type="text" name="username" placeholder="Username" required autofocus>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="do_login">Teacher Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit; 
}

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\ComplexType\TblWidth;

$loggedInUser = currentUser();
if (!$loggedInUser) {
    $_SESSION = [];
    header('Location: ' . basename($_SERVER['PHP_SELF']));
    exit;
}

$allQuestionBanksRoot = __DIR__ . '/question_banks';
$allSavedPapersRoot = __DIR__ . '/saved_papers';
if (!is_dir($allQuestionBanksRoot)) mkdir($allQuestionBanksRoot, 0755, true);
if (!is_dir($allSavedPapersRoot)) mkdir($allSavedPapersRoot, 0755, true);

// Prevent direct browser downloads of user accounts, Excel banks and saved JSON papers
// when the app is hosted inside XAMPP/Apache. (The PHP app reads these files server-side.)
foreach ([$dataRoot, $allQuestionBanksRoot, $allSavedPapersRoot] as $protectedDir) {
    $denyFile = $protectedDir . '/.htaccess';
    if (!file_exists($denyFile)) {
        @file_put_contents($denyFile, "Require all denied\n");
    }
}

// Teachers are permanently restricted to their own folder. Administrators may select
// a teacher to work with, but the selected owner is always resolved server-side.
$previousOwnerId = isAdmin()
    ? (int)($_SESSION['active_owner_id'] ?? $loggedInUser['id'])
    : (int)$loggedInUser['id'];
$activeOwnerId = $previousOwnerId;

if (isAdmin() && (isset($_GET['owner']) || isset($_POST['owner']))) {
    $requestedOwnerId = (int)($_GET['owner'] ?? $_POST['owner']);
    if ($requestedOwnerId === (int)$loggedInUser['id']) {
        // Administrator / Own workspace
        $activeOwnerId = (int)$loggedInUser['id'];
    } else {
        $requestedOwner = userById($requestedOwnerId);
        if ($requestedOwner && $requestedOwner['role'] === 'teacher') {
            $activeOwnerId = (int)$requestedOwner['id'];
        }
    }
}

if (isAdmin()) {
    // When the administrator switches to a different teacher workspace, clear any
    // previously selected bank, basket questions, and paper configuration.
    if ($activeOwnerId !== $previousOwnerId) {
        unset(
            $_SESSION['selected_bank'],
            $_SESSION['basket'],
            $_SESSION['doc_config'],
            $_SESSION['section_order'],
            $_SESSION['section_heading']
        );
        $_SESSION['basket'] = [];
        $_SESSION['active_owner_id'] = $activeOwnerId;

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            header('Location: ' . basename($_SERVER['PHP_SELF']));
            exit;
        }
    }
    $_SESSION['active_owner_id'] = $activeOwnerId;
}

$activeOwner = userById($activeOwnerId) ?: $loggedInUser;
$bankFolder = $allQuestionBanksRoot . '/' . safeFolderKey($activeOwner['folder_key']);
$savedPapersFolder = $allSavedPapersRoot . '/' . safeFolderKey($activeOwner['folder_key']);
if (!is_dir($bankFolder)) mkdir($bankFolder, 0755, true);
if (!is_dir($savedPapersFolder)) mkdir($savedPapersFolder, 0755, true);

// App configuration constants
define('APP_VERSION', 'v2.5.0');
define('SCHOOL_NAME', "St. Paul's English School");
define('ADDRESS', "NSD Compound, Savalanga Road");
define('CITY_NAME', "Shivamogga");

/*
|--------------------------------------------------------------------------
| FINE-TUNED QUESTION PAPER GENERATOR (DYNAMIC COLUMNS)
|--------------------------------------------------------------------------
*/

/**
 * Robustly finds the "Questions" sheet regardless of case.
 */
function getQuestionsSheet($spreadsheet) {
    $sheet = $spreadsheet->getSheetByName('Questions');
    if ($sheet !== null) {
        return $sheet;
    }
    foreach ($spreadsheet->getSheetNames() as $sheetName) {
        if (strtolower(trim($sheetName)) === 'questions') {
            return $spreadsheet->getSheetByName($sheetName);
        }
    }
    return $spreadsheet->getActiveSheet();
}

/**
 * Dynamically maps column names to standard keys.
 */
function detectColumnMap(array $data): array {
    $map = [];
    $headerRow = 1;
    for ($i = 1; $i <= 5; $i++) {
        if (!isset($data[$i])) continue;
        
        $tempMap = [];
        foreach ($data[$i] as $colLetter => $val) {
            $cleanVal = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$val));
            if ($cleanVal === '') continue;
            
            if (in_array($cleanVal, ['chaptername', 'chapter', 'chapterid'])) $tempMap['Chapter'] = $colLetter;
            elseif (in_array($cleanVal, ['qno', 'questionno'])) $tempMap['QNo'] = $colLetter;
            elseif (in_array($cleanVal, ['questiontype', 'category', 'type'])) $tempMap['Category'] = $colLetter;
            elseif (in_array($cleanVal, ['marks', 'mark', 'weightage'])) $tempMap['Marks'] = $colLetter;
            elseif (in_array($cleanVal, ['difficulty', 'difficultylevel'])) $tempMap['Difficulty'] = $colLetter;
            elseif (in_array($cleanVal, ['questiontext', 'question', 'qtext'])) $tempMap['Question'] = $colLetter;
            elseif ($cleanVal === 'optiona') $tempMap['OptionA'] = $colLetter;
            elseif ($cleanVal === 'optionb') $tempMap['OptionB'] = $colLetter;
            elseif ($cleanVal === 'optionc') $tempMap['OptionC'] = $colLetter;
            elseif ($cleanVal === 'optiond') $tempMap['OptionD'] = $colLetter;
            elseif (in_array($cleanVal, ['correctoption', 'answer', 'answertext'])) $tempMap['Answer'] = $colLetter;
        }
        
        if (isset($tempMap['Question']) && (isset($tempMap['Chapter']) || isset($tempMap['QNo']))) {
            $map = $tempMap;
            $headerRow = $i;
            break;
        }
    }
    return ['map' => $map, 'row' => $headerRow];
}

function getQuestionBanks(string $folder): array {
    $files = glob($folder . '/*.xlsx') ?: [];
    $banks = array_map('basename', $files);
    sort($banks, SORT_NATURAL | SORT_FLAG_CASE);
    return $banks;
}

function loadQuestionBank(string $path): array {
    $questions = [];
    $spreadsheet = IOFactory::load($path);
    $sheet = getQuestionsSheet($spreadsheet);
    $data = $sheet->toArray(null, true, true, true);
    
    $detect = detectColumnMap($data);
    $colMap = $detect['map'];
    $headerRow = $detect['row'];

    if (empty($colMap)) {
        $colMap = [
            'Chapter' => 'F', 'QNo' => 'G', 'Category' => 'H', 
            'Marks' => 'I', 'Difficulty' => 'J', 'Question' => 'K',
            'OptionA' => 'L', 'OptionB' => 'M', 'OptionC' => 'N', 
            'OptionD' => 'O', 'Answer' => 'P'
        ];
    }
    
    for ($i = 1; $i <= $headerRow; $i++) {
        unset($data[$i]);
    }

    foreach ($data as $rowNumber => $row) {
        $qText = trim((string)($row[$colMap['Question'] ?? 'K'] ?? ''));
        if ($qText === '') continue;

        $questions[] = [
            'Chapter'     => trim((string)($row[$colMap['Chapter'] ?? 'F'] ?? '')),
            'QNo'         => trim((string)($row[$colMap['QNo'] ?? 'G'] ?? '')),
            'Category'    => trim((string)($row[$colMap['Category'] ?? 'H'] ?? '')),
            'Marks'       => trim((string)($row[$colMap['Marks'] ?? 'I'] ?? '')),
            'Difficulty'  => trim((string)($row[$colMap['Difficulty'] ?? 'J'] ?? '')),
            'Question'    => $qText,
            'OptionA'     => trim((string)($row[$colMap['OptionA'] ?? 'L'] ?? '')),
            'OptionB'     => trim((string)($row[$colMap['OptionB'] ?? 'M'] ?? '')),
            'OptionC'     => trim((string)($row[$colMap['OptionC'] ?? 'N'] ?? '')),
            'OptionD'     => trim((string)($row[$colMap['OptionD'] ?? 'O'] ?? '')),
            'Answer'      => trim((string)($row[$colMap['Answer'] ?? 'P'] ?? '')),
            '_ExcelRow'   => $rowNumber,
            '_ColMap'     => $colMap
        ];
    }
    return $questions;
}

function csrfToken(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(): void {
    if (
        !isset($_POST['csrf']) ||
        !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])
    ) {
        http_response_code(403);
        exit('Invalid request token.');
    }
}

function matchesFilters(array $q, string $category, string $marks, string $chapter, string $difficulty, string $search): bool {
    $matchFilters = 
        ($category === 'All' || $q['Category'] === $category) &&
        ($marks === 'All' || (string)$q['Marks'] === (string)$marks) &&
        ($chapter === 'All' || $q['Chapter'] === $chapter) &&
        ($difficulty === 'All' || $q['Difficulty'] === $difficulty);

    if (!$matchFilters) {
        return false;
    }

    if ($search !== '') {
        $searchLower = strtolower($search);
        $contentToSearch = strtolower($q['QNo'] . ' ' . $q['Question'] . ' ' . $q['Chapter'] . ' ' . $q['Category'] . ' ' . $q['OptionA'] . ' ' . $q['OptionB'] . ' ' . $q['OptionC'] . ' ' . $q['OptionD']);
        
        if (strpos($contentToSearch, $searchLower) === false) {
            return false;
        }
    }

    return true;
}

function cleanWordText(string $text): string {
    $text = str_replace("\r", "", $text);
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? '';
}

function addQuestionToWord(PhpWord $word, array $q, int $number): void {
    $section = $word->getSections()[0];

    $question = trim(cleanWordText((string)($q['Question'] ?? '')));

    // Approximately six character-spaces of hanging indentation.
    // Word uses twips for paragraph indentation; 720 twips = 0.5 inch.
    $hangingIndent = 720;

    // Question number + question text only.
    // Per-question marks are intentionally NOT printed in the DOCX.
    $qRun = $section->addTextRun([
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
        'indentation' => [
            'left' => $hangingIndent,
            'hanging' => $hangingIndent
        ]
    ]);

    $qRun->addText($number . '. ', ['bold' => true, 'size' => 10]);

    $lines = preg_split('/\R/u', $question) ?: [''];
    foreach ($lines as $i => $line) {
        if ($i > 0) {
            $qRun->addTextBreak(1);
        }
        $qRun->addText(trim($line), ['size' => 10]);
    }

    $options = [
        trim(cleanWordText((string)($q['OptionA'] ?? ''))),
        trim(cleanWordText((string)($q['OptionB'] ?? ''))),
        trim(cleanWordText((string)($q['OptionC'] ?? ''))),
        trim(cleanWordText((string)($q['OptionD'] ?? ''))),
    ];
    $hasOptions = implode('', $options) !== '';

    if ($hasOptions) {
        $maxLen = max(array_map(function($v) {
            return function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
        }, $options));

        $optionTable = $section->addTable([
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'cellMargin' => 0,
            'cellSpacing' => 0,
            'width' => 100 * 50,
            'unit' => 'pct',
            'indent' => new TblWidth($hangingIndent, 'dxa')
        ]);

        $cellStyle = [
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'valign' => 'top'
        ];
        $cellPara = [
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0
        ];
        $font = ['size' => 9];

        if ($maxLen <= 22) {
            $optionTable->addRow(null, ['cantSplit' => true]);
            foreach ($options as $idx => $opt) {
                $label = chr(65 + $idx);
                $optionTable->addCell(2350, $cellStyle)->addText(
                    '(' . $label . ') ' . $opt,
                    $font,
                    $cellPara
                );
            }
        } else {
            for ($row = 0; $row < 2; $row++) {
                $optionTable->addRow(null, ['cantSplit' => true]);
                for ($col = 0; $col < 2; $col++) {
                    $idx = ($row * 2) + $col;
                    $label = chr(65 + $idx);
                    $optionTable->addCell(4700, $cellStyle)->addText(
                        '(' . $label . ') ' . $options[$idx],
                        $font,
                        $cellPara
                    );
                }
            }
        }
    }
}

function getSectionKey(array $q): string {
    $cat = trim($q['Category']);
    if ($cat === '') $cat = 'Uncategorized';
    $mks = (int)$q['Marks'];
    
    if (stripos($cat, 'long') !== false || preg_match('/\bla\b/i', $cat)) {
        return $cat . ' (' . $mks . ' Marks)';
    }
    return $cat;
}

function getCategorySortWeight(string $cat): int {
    $c = strtolower(trim($cat));
    if (strpos($c, 'mcq') !== false || strpos($c, 'multiple choice') !== false) return 1;
    if (strpos($c, 'very short') !== false || preg_match('/\bvsa\b/', $c)) return 2;
    if (strpos($c, 'short') !== false || preg_match('/\bsa\b/', $c)) return 3;
    if (strpos($c, 'long') !== false && strpos($c, '3') !== false) return 4;
    if (strpos($c, 'long') !== false && strpos($c, '4') !== false) return 5;
    if (strpos($c, 'long') !== false && strpos($c, '5') !== false) return 6;
    if (strpos($c, 'long') !== false || preg_match('/\bla\b/', $c)) return 7;
    return 99;
}

function getSectionOrder(string $secKey): int {
    $hash = md5($secKey);
    if (isset($_SESSION['section_order'][$hash]) && $_SESSION['section_order'][$hash] !== '') {
        return (int)$_SESSION['section_order'][$hash];
    }
    return getCategorySortWeight($secKey);
}

function getSectionHeading(string $secKey): string {
    $hash = md5($secKey);
    if (isset($_SESSION['section_heading'][$hash]) && trim($_SESSION['section_heading'][$hash]) !== '') {
        return trim($_SESSION['section_heading'][$hash]);
    }
    return strtoupper($secKey);
}

/**
 * Build the marks summary shown beside each DOCX section heading.
 *
 * Uniform marks:
 *   (1M x 10 = 10)
 *
 * Mixed marks within one section:
 *   (1M x 5 = 5; 2M x 3 = 6; Total = 11)
 */
function getSectionMarksSummary(array $groupQuestions): string {
    $byMarks = [];
    $totalMarks = 0;

    foreach ($groupQuestions as $q) {
        $marks = (int)($q['Marks'] ?? 0);
        if ($marks < 0) $marks = 0;

        if (!isset($byMarks[$marks])) {
            $byMarks[$marks] = 0;
        }
        $byMarks[$marks]++;
        $totalMarks += $marks;
    }

    if (empty($groupQuestions)) {
        return '(0M x 0 = 0)';
    }

    ksort($byMarks, SORT_NUMERIC);

    // The normal case: every question in the section carries the same marks.
    if (count($byMarks) === 1) {
        $marks = (int)array_key_first($byMarks);
        $count = $byMarks[$marks];
        return '(' . $marks . 'M x ' . $count . ' = ' . $totalMarks . ')';
    }

    // Mixed-mark sections: show each mark group and the section total.
    $parts = [];
    foreach ($byMarks as $marks => $count) {
        $subtotal = (int)$marks * (int)$count;
        $parts[] = $marks . 'M x ' . $count . ' = ' . $subtotal;
    }

    return '(' . implode('; ', $parts) . '; Total = ' . $totalMarks . ')';
}

$csrf = csrfToken();
$message = '';
$messageType = '';
$justAddedId = null;
$editingBasketId = null;
$editId = null;
$editBankId = null;
$justEditedBankId = null;
$scrollToBasket = false;
$scrollToPreview = false;
$resetFiltersOnBankSwitch = false;

$questionBanks = getQuestionBanks($bankFolder);

// Default to blank ('') so "None" is selected initially. The value is checked
// against the active owner's actual folder, so a teacher cannot request another
// teacher's workbook by changing the URL.
$previousBank = $_SESSION['selected_bank'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['bank']) && !isset($_GET['keep_basket'])) {
    $selectedBank = (string)$_GET['bank'];
} else {
    $selectedBank = $_GET['bank'] ?? $previousBank;
}

if ($selectedBank !== '' && !in_array($selectedBank, $questionBanks, true)) {
    $selectedBank = '';
}

// When switching to a different Question Bank via GET, clear basket and paper settings
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['bank']) && !isset($_GET['keep_basket']) && $selectedBank !== $previousBank) {
    $_SESSION['basket'] = [];
    $_SESSION['doc_config'] = [];
    $_SESSION['section_order'] = [];
    $_SESSION['section_heading'] = [];
}
$_SESSION['selected_bank'] = $selectedBank;

$questions = [];
if ($selectedBank !== '') {
    try {
        $questions = loadQuestionBank($bankFolder . '/' . basename($selectedBank));
    } catch (Throwable $e) {
        $message = 'Could not load the selected question bank.';
        $messageType = 'error';
    }
}

if (!isset($_SESSION['basket'])) $_SESSION['basket'] = [];

/* Fetch Saved JSON Papers */
$savedPapersList = [];
$existingFileNames = [];
if (is_dir($savedPapersFolder)) {
    $files = glob($savedPapersFolder . '/*/*.json') ?: [];
    usort($files, function($a, $b) { return filemtime($b) <=> filemtime($a); });
    foreach ($files as $file) {
        $relPath = 'saved_papers/' . safeFolderKey($activeOwner['folder_key']) . '/' . basename(dirname($file)) . '/' . basename($file);
        $dateLabel = date('Y-m-d H:i', filemtime($file));
        $savedPapersList[] = [
            'path' => $relPath,
            'label' => basename($file) . ' (' . $dateLabel . ')'
        ];
        $folderName = basename(dirname($file));
        $fileNameOnly = basename($file, '.json');
        $existingFileNames[$folderName][] = strtolower($fileNameOnly);
    }
}
$existingFileNamesJson = json_encode($existingFileNames);

/* Change own password */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verifyCsrf();

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $message = 'Please fill in all password fields.';
        $messageType = 'error';
    } elseif (!password_verify($currentPassword, (string)($loggedInUser['password_hash'] ?? ''))) {
        $message = 'The current password is incorrect.';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 6) {
        $message = 'The new password must contain at least 6 characters.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'The new password and confirmation do not match.';
        $messageType = 'error';
    } elseif (password_verify($newPassword, (string)($loggedInUser['password_hash'] ?? ''))) {
        $message = 'The new password must be different from the current password.';
        $messageType = 'error';
    } else {
        try {
            $users = loadUsers();
            $updated = false;

            foreach ($users as &$account) {
                if ((int)($account['id'] ?? 0) === (int)$loggedInUser['id']) {
                    $account['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    $account['password_changed_at'] = date('Y-m-d H:i:s');
                    $updated = true;
                    break;
                }
            }
            unset($account);

            if (!$updated) {
                throw new RuntimeException('User account could not be found.');
            }

            saveUsers($users);

            // Refresh the authenticated session and user record after a password change.
            session_regenerate_id(true);
            $freshUser = currentUser();
            if ($freshUser) {
                $_SESSION['is_authenticated'] = true;
                $_SESSION['user_id'] = (int)$freshUser['id'];
                $_SESSION['username'] = $freshUser['username'];
                $_SESSION['display_name'] = $freshUser['display_name'];
                $_SESSION['role'] = $freshUser['role'];
                $_SESSION['subject'] = $freshUser['subject'];
                $_SESSION['teacher_id'] = $freshUser['folder_key'];
            }

            $message = 'Your password has been changed successfully.';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = 'Could not change the password. Please ensure the data folder is writable.';
            $messageType = 'error';
        }
    }
}

/* Import XLSX */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_bank'])) {
    verifyCsrf();

    if (!isset($_FILES['question_bank']) || $_FILES['question_bank']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Please select an XLSX file.';
        $messageType = 'error';
    } else {
        $originalName = $_FILES['question_bank']['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'xlsx') {
            $message = 'Only .xlsx files are allowed.';
            $messageType = 'error';
        } else {
            $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $originalName);
            $target = $bankFolder . '/' . $safeName;

            try {
                $spreadsheet = IOFactory::load($_FILES['question_bank']['tmp_name']);
                $sheet = getQuestionsSheet($spreadsheet);
                $data = $sheet->toArray(null, true, true, true);
                
                $detect = detectColumnMap($data);
                
                if (empty($detect['map']) || !isset($detect['map']['Question'])) {
                    $message = 'Invalid Excel format. We could not automatically detect a "Question" or "Question_Text" column in the first 5 rows.';
                    $messageType = 'error';
                } elseif (file_exists($target)) {
                    $message = 'That question bank already exists. Rename the XLSX and upload again.';
                    $messageType = 'error';
                } elseif (move_uploaded_file($_FILES['question_bank']['tmp_name'], $target)) {
                    $_SESSION['selected_bank'] = $safeName;
                    $_SESSION['basket'] = [];
                    $_SESSION['doc_config'] = [];
                    $_SESSION['section_order'] = [];
                    $_SESSION['section_heading'] = [];
                    $message = 'Question bank imported successfully. Columns auto-detected!';
                    $messageType = 'success';
                    header('Location: ' . basename($_SERVER['PHP_SELF']) . '?bank=' . urlencode($safeName));
                    exit;
                }
            } catch (Throwable $e) {
                $message = 'Could not read the Excel file. Please check that it is a valid XLSX.';
                $messageType = 'error';
            }
        }
    }
}

/* Administrator: teacher account management */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_user_action'])) {
    verifyCsrf();
    if (!isAdmin()) {
        http_response_code(403);
        exit('Access denied.');
    }

    $adminAction = $_POST['admin_user_action'];
    try {
        $users = loadUsers();
        if ($adminAction === 'create_teacher') {
            $username = trim((string)($_POST['new_username'] ?? ''));
            $displayName = trim((string)($_POST['new_display_name'] ?? ''));
            $subject = trim((string)($_POST['new_subject'] ?? ''));
            $password = (string)($_POST['new_password'] ?? '');
            if ($username === '' || $displayName === '' || $subject === '' || $password === '') {
                throw new RuntimeException('Username, display name, subject and password are required.');
            }
            if (strlen($password) < 6) {
                throw new RuntimeException('Password must contain at least 6 characters.');
            }
            foreach ($users as $u) {
                if (strcasecmp((string)$u['username'], $username) === 0) {
                    throw new RuntimeException('That username already exists.');
                }
            }
            $baseFolder = safeFolderKey($username);
            $folderKey = $baseFolder;
            $n = 2;
            $usedFolders = array_map(fn($u) => (string)$u['folder_key'], $users);
            while (in_array($folderKey, $usedFolders, true)) $folderKey = $baseFolder . '_' . $n++;
            $nextId = empty($users) ? 1 : (max(array_map(fn($u) => (int)$u['id'], $users)) + 1);
            $users[] = [
                'id' => $nextId,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'display_name' => $displayName,
                'subject' => $subject,
                'role' => 'teacher',
                'folder_key' => $folderKey,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            saveUsers($users);
            mkdir($allQuestionBanksRoot . '/' . $folderKey, 0755, true);
            mkdir($allSavedPapersRoot . '/' . $folderKey, 0755, true);
            $message = 'Teacher account created successfully.';
            $messageType = 'success';
        } elseif ($adminAction === 'edit_teacher') {
            $teacherId = (int)($_POST['teacher_id'] ?? 0);
            $username = trim((string)($_POST['edit_username'] ?? ''));
            $displayName = trim((string)($_POST['edit_display_name'] ?? ''));
            $subject = trim((string)($_POST['edit_subject'] ?? ''));
            $newPassword = (string)($_POST['edit_password'] ?? '');

            if ($teacherId <= 0 || $username === '' || $displayName === '' || $subject === '') {
                throw new RuntimeException('Teacher name, username and subject are required.');
            }
            if (strlen($username) < 3) {
                throw new RuntimeException('Username must contain at least 3 characters.');
            }
            if ($newPassword !== '' && strlen($newPassword) < 6) {
                throw new RuntimeException('Password must contain at least 6 characters.');
            }

            $found = false;
            foreach ($users as &$u) {
                if ((int)$u['id'] === $teacherId && ($u['role'] ?? '') === 'teacher') {
                    foreach ($users as $other) {
                        if ((int)($other['id'] ?? 0) !== $teacherId && strcasecmp((string)($other['username'] ?? ''), $username) === 0) {
                            throw new RuntimeException('That username is already in use.');
                        }
                    }
                    $u['username'] = $username;
                    $u['display_name'] = $displayName;
                    $u['subject'] = $subject;
                    if ($newPassword !== '') {
                        $u['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                        $u['password_changed_at'] = date('Y-m-d H:i:s');
                    }
                    $u['updated_at'] = date('Y-m-d H:i:s');
                    $found = true;
                    break;
                }
            }
            unset($u);
            if (!$found) throw new RuntimeException('Teacher account not found.');
            saveUsers($users);
            $message = 'Teacher account details updated successfully.';
            $messageType = 'success';
        } elseif ($adminAction === 'toggle_teacher') {
            $teacherId = (int)($_POST['teacher_id'] ?? 0);
            $found = false;
            foreach ($users as &$u) {
                if ((int)$u['id'] === $teacherId && ($u['role'] ?? '') === 'teacher') {
                    $u['active'] = empty($u['active']) ? 1 : 0;
                    $found = true;
                    break;
                }
            }
            unset($u);
            if (!$found) throw new RuntimeException('Teacher account not found.');
            saveUsers($users);
            $message = 'Teacher account status updated.';
            $messageType = 'success';
        } elseif ($adminAction === 'reset_password') {
            $teacherId = (int)($_POST['teacher_id'] ?? 0);
            $newPassword = (string)($_POST['reset_password'] ?? '');
            if (strlen($newPassword) < 6) throw new RuntimeException('New password must contain at least 6 characters.');
            $found = false;
            foreach ($users as &$u) {
                if ((int)$u['id'] === $teacherId && ($u['role'] ?? '') === 'teacher') {
                    $u['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    $found = true;
                    break;
                }
            }
            unset($u);
            if (!$found) throw new RuntimeException('Teacher account not found.');
            saveUsers($users);
            $message = 'Teacher password reset successfully.';
            $messageType = 'success';
        }
    } catch (Throwable $e) {
        $message = 'Could not update teacher account: ' . $e->getMessage();
        $messageType = 'error';
    }
}

/* Actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['import_bank']) && !isset($_POST['admin_user_action']) && !isset($_POST['change_password'])) {
    verifyCsrf();

    if (isset($_POST['section_order']) && is_array($_POST['section_order'])) {
        if (!isset($_SESSION['section_order']) || !is_array($_SESSION['section_order'])) {
            $_SESSION['section_order'] = [];
        }
        foreach ($_POST['section_order'] as $k => $v) {
            $_SESSION['section_order'][(string)$k] = $v;
        }
    }
    if (isset($_POST['section_heading']) && is_array($_POST['section_heading'])) {
        if (!isset($_SESSION['section_heading']) || !is_array($_SESSION['section_heading'])) {
            $_SESSION['section_heading'] = [];
        }
        foreach ($_POST['section_heading'] as $k => $v) {
            $_SESSION['section_heading'][(string)$k] = $v;
        }
    }

    // -- Background Auto-Save for Section Heading & Sort Order --
    if (isset($_POST['ajax_save_section_config'])) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['status' => 'ok']);
        exit;
    }

    if (isset($_POST['update_order'])) {
        $message = 'Section configurations updated. Blueprint and Preview refreshed.';
        $messageType = 'success';
        $scrollToBasket = true;
    }

    $chainedSaveMessage = '';

    // -- JSON Save to Server (Processed before load_paper so "Save & Load QP" chains seamlessly) --
    if (isset($_POST['save_json'])) {
        $basket = $_SESSION['basket'] ?? [];

        if (!$basket) {
            $message = 'Select or generate questions before saving.';
            $messageType = 'error';
        } else {
            $title = trim($_POST['paper_title'] ?? 'QUESTION PAPER');
            $school = trim($_POST['school_name'] ?? strtoupper(SCHOOL_NAME));
            $subtitle = trim($_POST['paper_subtitle'] ?? '');
            $fontName = trim($_POST['doc_font'] ?? ($_SESSION['doc_config']['doc_font'] ?? 'Cambria'));
            $watermarkText = trim($_POST['watermark_text'] ?? ($_SESSION['doc_config']['watermark_text'] ?? ''));

            $_SESSION['doc_config']['paper_title'] = $title;
            $_SESSION['doc_config']['school_name'] = $school;
            $_SESSION['doc_config']['paper_subtitle'] = $subtitle;
            $_SESSION['doc_config']['doc_font'] = $fontName;
            $_SESSION['doc_config']['watermark_text'] = $watermarkText;

            $totalMarks = 0;
            foreach ($basket as $q) $totalMarks += (int)$q['Marks'];
            $totalQuestions = count($basket);

            $groupedBasket = [];
            foreach ($basket as $id => $q) {
                $sec = getSectionKey($q);
                $q['_BankId'] = $id; // Preserve question's index in the Question Bank
                $groupedBasket[$sec][] = $q;
            }
            
            uksort($groupedBasket, function($a, $b) {
                $orderA = getSectionOrder($a);
                $orderB = getSectionOrder($b);
                if ($orderA === $orderB) {
                    return strnatcmp($a, $b); 
                }
                return $orderA <=> $orderB;
            });

            $paperData = [
                'metadata' => [
                    'school_name' => $school,
                    'paper_title' => $title,
                    'paper_subtitle' => $subtitle,
                    'doc_font' => $fontName,
                    'watermark_text' => $watermarkText,
                    'question_bank' => $selectedBank, // Link QP to the active Question Bank
                    'total_marks' => $totalMarks,
                    'total_questions' => $totalQuestions,
                    'generated_at' => date('Y-m-d H:i:s')
                ],
                'sections' => []
            ];

            foreach ($groupedBasket as $sectionKey => $groupQuestions) {
                $heading = getSectionHeading($sectionKey);
                $sectionData = [
                    'section_key' => $sectionKey,
                    'section_heading' => $heading,
                    'questions' => []
                ];
                
                foreach ($groupQuestions as $q) {
                    unset($q['_ExcelRow'], $q['_ColMap']);
                    $sectionData['questions'][] = $q;
                }
                $paperData['sections'][] = $sectionData;
            }

            $saveFolder = $savedPapersFolder;
            if (!is_dir($saveFolder)) {
                mkdir($saveFolder, 0755, true);
            }
            
            $dateFolder = date('Y-m-d');
            $subFolder = $saveFolder . '/' . $dateFolder;
            if (!is_dir($subFolder)) {
                mkdir($subFolder, 0755, true);
            }
            
            $customFileName = trim($_POST['custom_filename'] ?? '');
            
            if ($customFileName !== '') {
                $safeCustomName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $customFileName);
                $fileName = $safeCustomName;
                if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'json') {
                    $fileName .= '.json';
                }
            } else {
                $fileName = 'QuestionPaper_' . date('Ymd_His') . '.json';
            }
            
            $filePath = $subFolder . '/' . $fileName;
            
            file_put_contents($filePath, json_encode($paperData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $message = 'Question paper successfully saved to your private folder: ' . h($activeOwner['display_name']) . '/' . $dateFolder . '/' . $fileName;
            $messageType = 'success';
            
            // Refresh saved papers list for UI
            $savedPapersList = [];
            $existingFileNames = [];
            $files = glob($saveFolder . '/*/*.json') ?: [];
            if ($files) {
                usort($files, function($a, $b) { return filemtime($b) <=> filemtime($a); });
                foreach ($files as $file) {
                    $relPath = 'saved_papers/' . safeFolderKey($activeOwner['folder_key']) . '/' . basename(dirname($file)) . '/' . basename($file);
                    $dateLabel = date('Y-m-d H:i', filemtime($file));
                    $savedPapersList[] = ['path' => $relPath, 'label' => basename($file) . ' (' . $dateLabel . ')'];
                    
                    $folderName = basename(dirname($file));
                    $fileNameOnly = basename($file, '.json');
                    $existingFileNames[$folderName][] = strtolower($fileNameOnly);
                }
            }
            $existingFileNamesJson = json_encode($existingFileNames);
            $scrollToBasket = true;

            // Handle chained action if triggered via the Save/Discard prompt modal
            $afterSaveAction = trim((string)($_POST['after_save_action'] ?? ''));
            $afterSaveTarget = trim((string)($_POST['after_save_target'] ?? ''));

            if ($afterSaveAction === 'switch_bank') {
                if ($afterSaveTarget === '' || in_array($afterSaveTarget, $questionBanks, true)) {
                    $_SESSION['basket'] = [];
                    $_SESSION['doc_config'] = [];
                    $_SESSION['section_order'] = [];
                    $_SESSION['section_heading'] = [];
                    $_SESSION['selected_bank'] = $afterSaveTarget;
                    $selectedBank = $afterSaveTarget;
                    $questions = [];
                    if ($selectedBank !== '') {
                        try {
                            $questions = loadQuestionBank($bankFolder . '/' . basename($selectedBank));
                        } catch (Throwable $e) {
                            $questions = [];
                        }
                    }
                    $resetFiltersOnBankSwitch = true;
                    $scrollToBasket = false;
                    $message .= ' | Basket cleared and loaded Question Bank: ' . ($selectedBank ?: 'None');
                }
            } elseif ($afterSaveAction === 'load_paper' && $afterSaveTarget !== '') {
                $chainedSaveMessage = $message;
                $_POST['load_paper'] = '1';
                $_POST['saved_paper_path'] = $afterSaveTarget;
            }
        }
    }

    // -- Load Saved JSON Paper (Also loads the linked Question Bank) --
    if (isset($_POST['load_paper']) && !empty($_POST['saved_paper_path'])) {
        $relativePaper = (string)$_POST['saved_paper_path'];
        $allowedPrefix = 'saved_papers/' . safeFolderKey($activeOwner['folder_key']) . '/';
        $path = __DIR__ . '/' . ltrim($relativePaper, '/\\');
        $realRoot = realpath($savedPapersFolder);
        $realPath = file_exists($path) ? realpath($path) : false;
        if ($realRoot && $realPath && str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR) && str_starts_with($relativePaper, $allowedPrefix)) {
            $json = json_decode(file_get_contents($path), true);
            if ($json && isset($json['sections'])) {
                $_SESSION['basket'] = [];
                $_SESSION['section_order'] = [];
                $_SESSION['section_heading'] = [];
                $_SESSION['doc_config'] = $json['metadata'] ?? [];

                $hasBankMeta = isset($json['metadata']) && array_key_exists('question_bank', $json['metadata']);
                $linkedBank = trim((string)($json['metadata']['question_bank'] ?? ''));
                $bankWarning = '';

                // If older saved QP doesn't have question_bank metadata, try to auto-detect its QB
                if (!$hasBankMeta && $linkedBank === '' && !empty($questionBanks)) {
                    $sampleQ = null;
                    foreach ($json['sections'] as $sec) {
                        if (!empty($sec['questions'][0])) {
                            $sampleQ = $sec['questions'][0];
                            break;
                        }
                    }
                    if ($sampleQ !== null) {
                        foreach ($questionBanks as $candidateBank) {
                            try {
                                $candidateQs = ($candidateBank === $selectedBank && !empty($questions))
                                    ? $questions
                                    : loadQuestionBank($bankFolder . '/' . basename($candidateBank));
                                foreach ($candidateQs as $cq) {
                                    if ($cq['Chapter'] === ($sampleQ['Chapter'] ?? '') && $cq['Question'] === ($sampleQ['Question'] ?? '')) {
                                        $linkedBank = $candidateBank;
                                        break 2;
                                    }
                                }
                            } catch (Throwable $e) {
                                continue;
                            }
                        }
                    }
                }

                // Load the linked Question Bank
                if ($linkedBank !== '') {
                    if (in_array($linkedBank, $questionBanks, true)) {
                        $selectedBank = $linkedBank;
                        $_SESSION['selected_bank'] = $selectedBank;
                        try {
                            $questions = loadQuestionBank($bankFolder . '/' . basename($selectedBank));
                        } catch (Throwable $e) {
                            $questions = [];
                            $bankWarning = ' (Warning: Could not read linked Question Bank "' . $linkedBank . '".)';
                        }
                    } else {
                        $selectedBank = '';
                        $_SESSION['selected_bank'] = '';
                        $questions = [];
                        $bankWarning = ' (Note: Linked Question Bank "' . $linkedBank . '" was not found in your folder.)';
                    }
                    $resetFiltersOnBankSwitch = true;
                } elseif ($hasBankMeta && $linkedBank === '') {
                    $selectedBank = '';
                    $_SESSION['selected_bank'] = '';
                    $questions = [];
                    $resetFiltersOnBankSwitch = true;
                }

                $orderCounter = 1;
                $usedMatchedKeys = [];
                foreach ($json['sections'] as $sec) {
                    $secKey = $sec['section_key'] ?? 'Uncategorized';
                    $hash = md5($secKey);
                    
                    if (isset($sec['section_heading'])) {
                        $_SESSION['section_heading'][$hash] = $sec['section_heading'];
                    }
                    $_SESSION['section_order'][$hash] = $orderCounter++;

                    if (isset($sec['questions']) && is_array($sec['questions'])) {
                        foreach ($sec['questions'] as $q) {
                            // Match saved question back to its index in the loaded Question Bank
                            $matchedKey = null;
                            if (isset($q['_BankId']) && is_numeric($q['_BankId'])) {
                                $candidateId = (int)$q['_BankId'];
                                if (isset($questions[$candidateId]) && !isset($usedMatchedKeys[$candidateId])) {
                                    $bankQ = $questions[$candidateId];
                                    if ($bankQ['Chapter'] === ($q['Chapter'] ?? '') && $bankQ['QNo'] === ($q['QNo'] ?? '')) {
                                        $matchedKey = $candidateId;
                                    }
                                }
                            }
                            if ($matchedKey === null && !empty($questions)) {
                                foreach ($questions as $qIdx => $bankQ) {
                                    if (isset($usedMatchedKeys[$qIdx])) continue;
                                    if ($bankQ['Chapter'] === ($q['Chapter'] ?? '') && $bankQ['Question'] === ($q['Question'] ?? '')) {
                                        $matchedKey = $qIdx;
                                        break;
                                    }
                                }
                            }
                            if ($matchedKey === null && !empty($questions)) {
                                foreach ($questions as $qIdx => $bankQ) {
                                    if (isset($usedMatchedKeys[$qIdx])) continue;
                                    if (($q['QNo'] ?? '') !== '' && $bankQ['Chapter'] === ($q['Chapter'] ?? '') && $bankQ['QNo'] === ($q['QNo'] ?? '')) {
                                        $matchedKey = $qIdx;
                                        break;
                                    }
                                }
                            }

                            unset($q['_BankId'], $q['_ExcelRow'], $q['_ColMap']);
                            if ($matchedKey !== null) {
                                $usedMatchedKeys[$matchedKey] = true;
                                $loadId = $matchedKey;
                            } else {
                                $loadId = 'loaded_' . uniqid() . mt_rand(100, 999);
                            }
                            $_SESSION['basket'][$loadId] = $q;
                        }
                    }
                }

                $loadedMsg = 'Saved paper' . ($selectedBank !== '' && $bankWarning === '' ? ' and linked Question Bank (' . $selectedBank . ')' : '') . ' loaded successfully!' . $bankWarning;
                $message = $chainedSaveMessage !== '' ? ($chainedSaveMessage . ' | ' . $loadedMsg) : $loadedMsg;
                $messageType = $bankWarning === '' ? 'success' : 'error';
                $scrollToBasket = true;
            } else {
                $message = 'Invalid JSON paper format.';
                $messageType = 'error';
            }
        } else {
            $message = 'File not found on server.';
            $messageType = 'error';
        }
    }

    // -- Delete Saved JSON Paper --
    if (isset($_POST['delete_paper']) && !empty($_POST['saved_paper_path'])) {
        $pathToDelete = (string)$_POST['saved_paper_path'];
        $allowedPrefix = 'saved_papers/' . safeFolderKey($activeOwner['folder_key']) . '/';
        $fullPath = __DIR__ . '/' . ltrim($pathToDelete, '/\\');
        $realRoot = realpath($savedPapersFolder);
        $realPath = file_exists($fullPath) ? realpath($fullPath) : false;
        if ($realRoot && $realPath && str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR) && str_starts_with($pathToDelete, $allowedPrefix)) {
            if (file_exists($fullPath)) {
                $dir = dirname($fullPath);
                unlink($fullPath); 
                
                $filesInDir = array_diff(scandir($dir), array('.', '..'));
                if (empty($filesInDir)) {
                    rmdir($dir);
                }
                
                $message = 'Saved paper deleted successfully.';
                $messageType = 'success';

                // Refresh saved papers list after deletion
                $savedPapersList = [];
                $existingFileNames = [];
                $files = glob($savedPapersFolder . '/*/*.json') ?: [];
                if ($files) {
                    usort($files, function($a, $b) { return filemtime($b) <=> filemtime($a); });
                    foreach ($files as $file) {
                        $relPath = 'saved_papers/' . safeFolderKey($activeOwner['folder_key']) . '/' . basename(dirname($file)) . '/' . basename($file);
                        $dateLabel = date('Y-m-d H:i', filemtime($file));
                        $savedPapersList[] = ['path' => $relPath, 'label' => basename($file) . ' (' . $dateLabel . ')'];
                        $folderName = basename(dirname($file));
                        $fileNameOnly = basename($file, '.json');
                        $existingFileNames[$folderName][] = strtolower($fileNameOnly);
                    }
                }
                $existingFileNamesJson = json_encode($existingFileNames);
            } else {
                $message = 'File not found on server.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid file path.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['add'])) {
        $id = (int)$_POST['add'];
        if (isset($questions[$id])) {
            $_SESSION['basket'][$id] = $questions[$id];
            $justAddedId = (string)$id;
            $scrollToPreview = true;
        }
    }
    
    if (isset($_POST['bulk_add']) && !empty($_POST['bulk_ids']) && is_array($_POST['bulk_ids'])) {
        $addedCount = 0;
        foreach ($_POST['bulk_ids'] as $bid) {
            $id = (int)$bid;
            if (isset($questions[$id])) {
                $_SESSION['basket'][$id] = $questions[$id];
                $justAddedId = (string)$id;
                $addedCount++;
            }
        }
        if ($addedCount > 0) {
            $message = $addedCount . ' question(s) successfully added to the basket.';
            $messageType = 'success';
            $scrollToPreview = true;
        }
    }

    if (isset($_POST['remove'])) {
        $id = (string)$_POST['remove'];
        unset($_SESSION['basket'][$id]);
        $scrollToBasket = true;
    }
    
    // -- Bulk Remove from Basket --
    if (isset($_POST['bulk_remove']) && !empty($_POST['basket_ids']) && is_array($_POST['basket_ids'])) {
        $removedCount = 0;
        foreach ($_POST['basket_ids'] as $bid) {
            unset($_SESSION['basket'][(string)$bid]);
            $removedCount++;
        }
        if ($removedCount > 0) {
            $message = $removedCount . ' question(s) successfully removed from the basket.';
            $messageType = 'success';
        }
        $scrollToBasket = true;
    }

    if (isset($_POST['edit_id'])) {
        $editId = (string)$_POST['edit_id'];
        $editingBasketId = $editId;
        $scrollToBasket = false;
    }

    if (isset($_POST['save_edit'])) {
        $idToSave = (string)$_POST['save_edit'];
        if (isset($_SESSION['basket'][$idToSave])) {
            $_SESSION['basket'][$idToSave]['Question'] = trim($_POST['edited_question'] ?? '');
            $_SESSION['basket'][$idToSave]['OptionA'] = trim($_POST['edited_option_a'] ?? '');
            $_SESSION['basket'][$idToSave]['OptionB'] = trim($_POST['edited_option_b'] ?? '');
            $_SESSION['basket'][$idToSave]['OptionC'] = trim($_POST['edited_option_c'] ?? '');
            $_SESSION['basket'][$idToSave]['OptionD'] = trim($_POST['edited_option_d'] ?? '');
            $justAddedId = $idToSave; 
        }
        $scrollToBasket = true;
    }

    if (isset($_POST['clear_basket'])) {
        $_SESSION['basket'] = [];
        $_SESSION['doc_config'] = [];
        $_SESSION['section_order'] = [];
        $_SESSION['section_heading'] = [];
    }

    if (isset($_POST['edit_bank_id'])) {
        $editBankId = (string)$_POST['edit_bank_id'];
        $justEditedBankId = $editBankId;
        $scrollToBasket = false;
    }

    if (isset($_POST['save_bank_edit'])) {
        $idToSave = (int)$_POST['save_bank_edit'];
        if (isset($questions[$idToSave])) {
            $newText = trim($_POST['edited_bank_question'] ?? '');
            $newOptA = trim($_POST['edited_bank_option_a'] ?? '');
            $newOptB = trim($_POST['edited_bank_option_b'] ?? '');
            $newOptC = trim($_POST['edited_bank_option_c'] ?? '');
            $newOptD = trim($_POST['edited_bank_option_d'] ?? '');
            
            // New editable fields
            $newCat = trim($_POST['edited_bank_category'] ?? '');
            $newMarks = trim($_POST['edited_bank_marks'] ?? '');
            $newDiff = trim($_POST['edited_bank_difficulty'] ?? '');
            
            $rowNum = $questions[$idToSave]['_ExcelRow'];
            $qCol = $questions[$idToSave]['_ColMap']['Question'] ?? 'K';
            $optACol = $questions[$idToSave]['_ColMap']['OptionA'] ?? 'L';
            $optBCol = $questions[$idToSave]['_ColMap']['OptionB'] ?? 'M';
            $optCCol = $questions[$idToSave]['_ColMap']['OptionC'] ?? 'N';
            $optDCol = $questions[$idToSave]['_ColMap']['OptionD'] ?? 'O';
            
            // Map columns for the new fields
            $catCol = $questions[$idToSave]['_ColMap']['Category'] ?? 'H';
            $marksCol = $questions[$idToSave]['_ColMap']['Marks'] ?? 'I';
            $diffCol = $questions[$idToSave]['_ColMap']['Difficulty'] ?? 'J';
            
            $path = $bankFolder . '/' . basename($selectedBank);
            
            try {
                $spreadsheet = IOFactory::load($path);
                $sheet = getQuestionsSheet($spreadsheet);
                $sheet->setCellValue($qCol . $rowNum, $newText);
                if ($optACol) $sheet->setCellValue($optACol . $rowNum, $newOptA);
                if ($optBCol) $sheet->setCellValue($optBCol . $rowNum, $newOptB);
                if ($optCCol) $sheet->setCellValue($optCCol . $rowNum, $newOptC);
                if ($optDCol) $sheet->setCellValue($optDCol . $rowNum, $newOptD);
                
                // Write new fields to Excel
                if ($catCol) $sheet->setCellValue($catCol . $rowNum, $newCat);
                if ($marksCol) $sheet->setCellValue($marksCol . $rowNum, $newMarks);
                if ($diffCol) $sheet->setCellValue($diffCol . $rowNum, $newDiff);
                
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save($path);
                
                $questions[$idToSave]['Question'] = $newText;
                $questions[$idToSave]['OptionA'] = $newOptA;
                $questions[$idToSave]['OptionB'] = $newOptB;
                $questions[$idToSave]['OptionC'] = $newOptC;
                $questions[$idToSave]['OptionD'] = $newOptD;
                
                // Update new fields in memory
                $questions[$idToSave]['Category'] = $newCat;
                $questions[$idToSave]['Marks'] = $newMarks;
                $questions[$idToSave]['Difficulty'] = $newDiff;
                
                if (isset($_SESSION['basket'][$idToSave])) {
                    $_SESSION['basket'][$idToSave]['Question'] = $newText;
                    $_SESSION['basket'][$idToSave]['OptionA'] = $newOptA;
                    $_SESSION['basket'][$idToSave]['OptionB'] = $newOptB;
                    $_SESSION['basket'][$idToSave]['OptionC'] = $newOptC;
                    $_SESSION['basket'][$idToSave]['OptionD'] = $newOptD;
                    // Update new fields in basket
                    $_SESSION['basket'][$idToSave]['Category'] = $newCat;
                    $_SESSION['basket'][$idToSave]['Marks'] = $newMarks;
                    $_SESSION['basket'][$idToSave]['Difficulty'] = $newDiff;
                }
                
                $editBankId = null;
                $justEditedBankId = $idToSave;
                $message = 'Question bank updated successfully.';
                $messageType = 'success';
            } catch (Throwable $e) {
                $message = 'Could not save the edited question. Ensure the Excel file is writable.';
                $messageType = 'error';
            }
        }
    }

    // -- Add New Question to Bank --
    if (isset($_POST['add_new_question_to_bank']) && $selectedBank !== '') {
        $path = $bankFolder . '/' . basename($selectedBank);
        
        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = getQuestionsSheet($spreadsheet);
            
            // Find the next empty row
            $highestRow = $sheet->getHighestDataRow();
            $nextRow = $highestRow + 1;
            
            // Detect column maps
            $data = $sheet->toArray(null, true, true, true);
            $detect = detectColumnMap($data);
            $colMap = $detect['map'];
            
            if (empty($colMap)) {
                $colMap = [
                    'Chapter' => 'F', 'QNo' => 'G', 'Category' => 'H', 
                    'Marks' => 'I', 'Difficulty' => 'J', 'Question' => 'K',
                    'OptionA' => 'L', 'OptionB' => 'M', 'OptionC' => 'N', 
                    'OptionD' => 'O', 'Answer' => 'P'
                ];
            }
            
            // Write the new values to the Excel sheet
            if (isset($colMap['QNo'])) $sheet->setCellValue($colMap['QNo'] . $nextRow, trim($_POST['new_qno'] ?? ''));
            if (isset($colMap['Chapter'])) $sheet->setCellValue($colMap['Chapter'] . $nextRow, trim($_POST['new_chapter'] ?? ''));
            if (isset($colMap['Category'])) $sheet->setCellValue($colMap['Category'] . $nextRow, trim($_POST['new_category'] ?? ''));
            if (isset($colMap['Marks'])) $sheet->setCellValue($colMap['Marks'] . $nextRow, trim($_POST['new_marks'] ?? ''));
            if (isset($colMap['Difficulty'])) $sheet->setCellValue($colMap['Difficulty'] . $nextRow, trim($_POST['new_difficulty'] ?? ''));
            if (isset($colMap['Question'])) $sheet->setCellValue($colMap['Question'] . $nextRow, trim($_POST['new_question'] ?? ''));
            if (isset($colMap['OptionA'])) $sheet->setCellValue($colMap['OptionA'] . $nextRow, trim($_POST['new_opt_a'] ?? ''));
            if (isset($colMap['OptionB'])) $sheet->setCellValue($colMap['OptionB'] . $nextRow, trim($_POST['new_opt_b'] ?? ''));
            if (isset($colMap['OptionC'])) $sheet->setCellValue($colMap['OptionC'] . $nextRow, trim($_POST['new_opt_c'] ?? ''));
            if (isset($colMap['OptionD'])) $sheet->setCellValue($colMap['OptionD'] . $nextRow, trim($_POST['new_opt_d'] ?? ''));
            
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($path);
            
            $message = 'New question successfully added to the bank!';
            $messageType = 'success';
            
            // Reload the questions array so the UI updates immediately
            $questions = loadQuestionBank($path);
            
        } catch (Throwable $e) {
            $message = 'Could not add the question. Ensure the Excel file is writable and not open elsewhere.';
            $messageType = 'error';
        }
    }

    // -- DOCX Export (Feature 1, 2, 6 Integrated) --
    if (isset($_POST['export']) || isset($_POST['export_answers'])) {
        $basket = $_SESSION['basket'] ?? [];
        $isAnswerKey = isset($_POST['export_answers']);

        if (!$basket) {
            $message = 'Select or generate questions before exporting.';
            $messageType = 'error';
        } else {
            $title = trim($_POST['paper_title'] ?? 'QUESTION PAPER');
            $school = trim($_POST['school_name'] ?? strtoupper(SCHOOL_NAME));
            $subtitle = trim($_POST['paper_subtitle'] ?? '');
            $fontName = trim($_POST['doc_font'] ?? 'Cambria');
            $watermarkText = trim($_POST['watermark_text'] ?? '');

            $_SESSION['doc_config']['paper_title'] = $title;
            $_SESSION['doc_config']['school_name'] = $school;
            $_SESSION['doc_config']['paper_subtitle'] = $subtitle;
            $_SESSION['doc_config']['doc_font'] = $fontName;
            $_SESSION['doc_config']['watermark_text'] = $watermarkText;

            $phpWord = new PhpWord();
            $phpWord->setDefaultFontName($fontName);
            $phpWord->setDefaultFontSize(10);

            $section = $phpWord->addSection([
                'pageSizeW' => 11906,
                'pageSizeH' => 16838,
                'marginTop' => 720,
                'marginBottom' => 720,
                'marginLeft' => 850,
                'marginRight' => 850,
                'headerHeight' => 350,
                'footerHeight' => 350
            ]);

            if ($watermarkText !== '') {
                $header = $section->addHeader();
                $header->addText(
                    cleanWordText($watermarkText),
                    ['size' => 28, 'color' => 'E6E6E6', 'bold' => true],
                    ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
                );
            }

            $footer = $section->addFooter();
            $footer->addPreserveText(
                'Page {PAGE} of {NUMPAGES}',
                ['size' => 8],
                ['alignment' => Jc::CENTER, 'spaceBefore' => 0, 'spaceAfter' => 0]
            );

            $section->addText(
                cleanWordText($school),
                ['bold' => true, 'size' => 15],
                ['alignment' => Jc::CENTER, 'spaceBefore' => 0, 'spaceAfter' => 45, 'keepNext' => true]
            );

            $displayTitle = $isAnswerKey ? ($title . ' - ANSWER KEY') : $title;
            $section->addText(
                cleanWordText($displayTitle),
                ['bold' => true, 'size' => 13.5],
                ['alignment' => Jc::CENTER, 'spaceBefore' => 0, 'spaceAfter' => 35, 'keepNext' => true]
            );

            if ($subtitle !== '') {
                $section->addText(
                    cleanWordText($subtitle),
                    ['italic' => true, 'size' => 10.5],
                    ['alignment' => Jc::CENTER, 'spaceBefore' => 0, 'spaceAfter' => 55, 'keepNext' => true]
                );
            }

            $totalMarks = 0;
            foreach ($basket as $q) $totalMarks += (int)$q['Marks'];

            $meta = $section->addTable([
                'borderSize' => 0,
                'borderColor' => 'FFFFFF',
                'cellMargin' => 0,
                'cellSpacing' => 0,
                'width' => 100 * 50,
                'unit' => 'pct'
            ]);
            $meta->addRow(null, ['cantSplit' => true]);
            $meta->addCell(4500, ['borderSize' => 0])->addText(
                'Questions: ' . count($basket),
                ['bold' => true, 'size' => 10],
                ['alignment' => Jc::START, 'spaceAfter' => 0]
            );
            $meta->addCell(4500, ['borderSize' => 0])->addText(
                'Maximum Marks: ' . $totalMarks,
                ['bold' => true, 'size' => 10],
                ['alignment' => Jc::END, 'spaceAfter' => 0]
            );
            $section->addText('', ['size' => 2], [
                'spaceBefore' => 0,
                'spaceAfter' => 55,
                'borderBottomSize' => 6,
                'borderBottomColor' => 'B7B7B7'
            ]);

            $groupedBasket = [];
            foreach ($basket as $q) {
                $sec = getSectionKey($q);
                $groupedBasket[$sec][] = $q;
            }
            
            uksort($groupedBasket, function($a, $b) {
                $orderA = getSectionOrder($a);
                $orderB = getSectionOrder($b);
                if ($orderA === $orderB) {
                    return strnatcmp($a, $b); 
                }
                return $orderA <=> $orderB;
            });

            $qIndex = 1;
            $sectionCount = count($groupedBasket);
            $currentSectionNumber = 0;

            foreach ($groupedBasket as $sectionKey => $groupQuestions) {
                $currentSectionNumber++;
                $heading = getSectionHeading($sectionKey);
                $marksSummary = getSectionMarksSummary($groupQuestions);

                $section->addText(
                    $heading . ' ' . $marksSummary,
                    ['bold' => true, 'size' => 11],
                    [
                        'spaceBefore' => $currentSectionNumber === 1 ? 40 : 80,
                        'spaceAfter' => 35,
                        'keepNext' => true,
                        'borderBottomSize' => 4,
                        'borderBottomColor' => 'D9D9D9'
                    ]
                );

                foreach ($groupQuestions as $q) {
                    if ($isAnswerKey) {
                        $ans = trim((string)$q['Answer']);
                        if ($ans === '') {
                            $ans = '________________________________________';
                        }
                        $section->addText(
                            $qIndex++ . '. ' . cleanWordText($ans),
                            ['size' => 10],
                            [
                                'spaceBefore' => 0,
                                'spaceAfter' => 0,
                                'lineHeight' => 1.0,
                                'indentation' => ['left' => 720, 'hanging' => 720]
                            ]
                        );
                    } else {
                        addQuestionToWord($phpWord, $q, $qIndex++);
                    }
                }

                // One blank line after each main section, and no blank line between questions.
                if ($currentSectionNumber < $sectionCount) {
                    $section->addText(
                        ' ',
                        ['size' => 10],
                        ['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.0]
                    );
                }
            }

            $fileName = ($isAnswerKey ? 'AnswerKey_' : 'QuestionPaper_') . date('Ymd_His') . '.docx';
            $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($fileName);

            header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Length: ' . filesize($fileName));
            readfile($fileName);
            unlink($fileName);
            exit;
        }
    }
}

$selectedCategory = $resetFiltersOnBankSwitch ? 'All' : ($_GET['category'] ?? 'All');
$selectedMarks = $resetFiltersOnBankSwitch ? 'All' : ($_GET['marks'] ?? 'All');
$selectedChapter = $resetFiltersOnBankSwitch ? 'All' : ($_GET['chapter'] ?? 'All');
$selectedDifficulty = $resetFiltersOnBankSwitch ? 'All' : ($_GET['difficulty'] ?? 'All');
$searchQuery = $resetFiltersOnBankSwitch ? '' : trim($_GET['search'] ?? '');

$categories = array_values(array_unique(array_column($questions, 'Category')));
$difficulties = array_values(array_filter(array_unique(array_column($questions, 'Difficulty'))));
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);
sort($difficulties, SORT_NATURAL | SORT_FLAG_CASE);

$marks = [];
foreach ($questions as $q) {
    if ($selectedCategory === 'All' || $q['Category'] === $selectedCategory) {
        if (!in_array($q['Marks'], $marks, true)) $marks[] = $q['Marks'];
    }
}
usort($marks, fn($a,$b) => (int)$a <=> (int)$b);

$chapters = [];
foreach ($questions as $q) {
    if (
        ($selectedCategory === 'All' || $q['Category'] === $selectedCategory) &&
        ($selectedMarks === 'All' || (string)$q['Marks'] === (string)$selectedMarks)
    ) {
        if (!in_array($q['Chapter'], $chapters, true)) $chapters[] = $q['Chapter'];
    }
}
sort($chapters, SORT_NATURAL | SORT_FLAG_CASE);

$filtered = [];
foreach ($questions as $id => $q) {
    if (matchesFilters($q, $selectedCategory, $selectedMarks, $selectedChapter, $selectedDifficulty, $searchQuery)) {
        $filtered[$id] = $q;
    }
}

$perPage = 10;
$page = $resetFiltersOnBankSwitch ? 1 : max(1, (int)($_GET['page'] ?? 1));
$total = count($filtered);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$paginated = array_slice($filtered, ($page - 1) * $perPage, $perPage, true);
$basket = $_SESSION['basket'];

$baseUrl = "?bank=" . urlencode($selectedBank) . 
           "&category=" . urlencode($selectedCategory) . 
           "&marks=" . urlencode($selectedMarks) . 
           "&chapter=" . urlencode($selectedChapter) . 
           "&difficulty=" . urlencode($selectedDifficulty) . 
           "&search=" . urlencode($searchQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Question Paper Generator</title>
<style>
body { font-family: Cambria, "Cambria Math", serif; font-size: 15px; margin: 15px; background: #f6f7f9; color: #222; }
h1 { font-size: 22px; color: #333; margin-top: 0; margin-bottom: 15px; }
h2 { font-size: 18px; color: #333; margin-top: 0; margin-bottom: 10px; }
h3 { font-size: 16px; color: #333; margin-top: 0; margin-bottom: 10px; }
.box { background: #fff; border: 1px solid #ddd; padding: 12px 15px; border-radius: 7px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }

/* --- DISTINCT LIGHT PASTEL SECTION THEMES --- */
.theme-userinfo { background: #f0f4f8; border-color: #c5d5e6; border-left: 5px solid #4a76a8; }
.theme-password { background: #f5f3ff; border-color: #d8b4fe; border-left: 5px solid #7c3aed; }
.theme-admin    { background: #fff1f2; border-color: #fecdd3; border-left: 5px solid #e11d48; }
.theme-import   { background: #eaf4ff; border-color: #b6d8ff; border-left: 5px solid #1976d2; }
.theme-select   { background: #e8fbf5; border-color: #a7f3d0; border-left: 5px solid #0d9488; }
.theme-saved    { background: #fff8e6; border-color: #fde68a; border-left: 5px solid #d97706; }
.theme-create   { background: #ecfeff; border-color: #a5f3fc; border-left: 5px solid #0891b2; }
.theme-filter   { background: #f6effc; border-color: #e2cef8; border-left: 5px solid #8e24aa; }
.theme-basket   { background: #eff8ec; border-color: #c5e1a5; border-left: 5px solid #2e7d32; }
.theme-preview  { background: #eef2ff; border-color: #c7d2fe; border-left: 5px solid #3949ab; }

table { border-collapse: collapse; width: 100%; background: #fff; margin-top: 8px; font-size: 15px; }
th, td { border: 1px solid #d5d5d5; padding: 6px; vertical-align: top; } th { background: #eef1f5; }
select, input[type=file], input[type=text], input[type=number], textarea { font-family: Cambria, "Cambria Math", serif; padding: 5px; margin: 2px 6px 2px 0; font-size: 15px; box-sizing: border-box; background: #fff; }
button { font-family: Cambria, "Cambria Math", serif; padding: 5px 10px; cursor: pointer; border: 0; border-radius: 4px; font-weight: bold; font-size: 14px; }
.primary { background: #1976d2; color: #fff; } .success { background: #28a745; color: #fff; } .danger { background: #c62828; color: #fff; } .secondary { background: #666; color: #fff; } .edit { background: #ef9b22; color: #fff; }
.notice { padding: 10px; border-radius: 4px; margin-bottom: 15px; } .notice.success { background: #dff0d8; color: #245b25; } .notice.error { background: #f8d7da; color: #7a2020; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
.stat { font-size: 15px; color: #555; margin-bottom: 8px; display: inline-block; }

/* Bulk action bars keep buttons right-aligned across desktop and smartphone */
.bulk-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin: 8px 0;
}
.bulk-action-bar-end {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin: 8px 0;
}
.bulk-action-btn {
    margin-left: auto !important;
    width: auto !important;
    flex: 0 0 auto !important;
}

/* Collapsible & Editable Basket Section Header Styles */
.basket-section-header th {
    background: #e3f0de;
    padding: 7px 10px;
    cursor: pointer;
    user-select: none;
    transition: background-color 0.15s ease;
    border-top: 2px solid #b7dca8;
}
.basket-section-header:hover th {
    background: #d6e9cf;
}
.sec-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
}
.sec-header-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1 1 420px;
    flex-wrap: wrap;
    min-width: 0;
}
.sec-toggle-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 4px;
    background: #2e7d32;
    color: #fff;
    font-size: 13px;
    font-weight: bold;
    flex-shrink: 0;
}
.sec-order-badge {
    display: inline-flex;
    align-items: center;
    background: #fff;
    border: 1px solid #9ec590;
    border-radius: 4px;
    padding: 1px 5px;
    font-size: 13px;
    color: #333;
    flex-shrink: 0;
}
.sec-order-input {
    width: 44px !important;
    border: 0 !important;
    padding: 2px !important;
    margin: 0 !important;
    text-align: center;
    font-weight: bold;
    font-size: 14px !important;
    background: transparent !important;
    outline: none;
}
.sec-heading-input {
    flex: 1 1 220px;
    min-width: 150px;
    font-weight: bold;
    font-size: 14px !important;
    padding: 4px 8px !important;
    margin: 0 !important;
    border: 1px solid #9ec590;
    border-radius: 4px;
    background: #fff;
    color: #1b4d20;
}
.sec-heading-input:focus, .sec-order-badge:focus-within {
    border-color: #1976d2;
    box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.18);
    outline: none;
}
.sec-meta-info {
    font-weight: normal;
    font-size: 13px;
    color: #38573a;
    white-space: nowrap;
}
.sec-save-status {
    font-size: 12px;
    font-weight: bold;
    color: #1b5e20;
    background: #c8e6c9;
    padding: 2px 7px;
    border-radius: 10px;
    opacity: 0;
    transition: opacity 0.25s ease;
    pointer-events: none;
}
.sec-save-status.visible {
    opacity: 1;
}
.sec-collapse-controls {
    display: inline-flex;
    gap: 6px;
    align-items: center;
}
.sec-collapse-btn {
    background: #e8eef5;
    color: #2c4a6f;
    border: 1px solid #b8c7dc;
    padding: 3px 9px;
    font-size: 13px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
}
.sec-collapse-btn:hover {
    background: #d8e3f0;
}

.pagination { margin-top: 15px; }
.pagination a, .pagination span { display: inline-block; padding: 5px 12px; border: 1px solid #ccc; background: #fff; text-decoration: none; color: #333; margin: 2px; border-radius: 3px; font-size: 14px; }
.pagination a:hover { background: #eef1f5; }
.pagination span.disabled { color: #aaa; background: #f9f9f9; border-color: #ddd; cursor: not-allowed; }
.pagination span.page-info { background: #eef1f5; font-weight: bold; border-color: #ccc; }

.small { font-size: 13px; color: #666; } .badge { display: inline-block; padding: 2px 6px; border-radius: 10px; background: #eee; font-size: 13px; font-family: system-ui, sans-serif; }

.top-row { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 15px; }
.top-row .box { margin-bottom: 0; flex: 1 1 200px; }
.top-row .box-large { flex: 2 1 350px; }

.filter-group label { display: block; font-size: 14px; font-weight: bold; color: #555; margin-bottom: 3px; }
.filter-group select { width: 100%; margin: 0; }

input[type=checkbox] { transform: scale(1.2); cursor: pointer; margin: 0; }

.highlight-row { animation: highlightFade 2.5s ease-out; }
@keyframes highlightFade { 0% { background-color: #ffeb3b; } 100% { background-color: transparent; } }

.footer { text-align: center; margin-top: 30px; padding: 15px 0; border-top: 1px solid #ddd; font-size: 13px; color: #666; }

/* Doc Preview Box Styles */
.doc-preview-container { background: #dfe5f2; padding: 20px; border-radius: 7px; border: 1px solid #b8c2db; max-height: 600px; overflow-y: auto; position: relative; }
.doc-preview-page { background: #fff; max-width: 800px; margin: 0 auto; padding: 40px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); font-family: Cambria, "Cambria Math", serif; line-height: 1.3; color: #000; position: relative; z-index: 2; text-align: left; }
.doc-preview-header { text-align: center; margin-bottom: 20px; }
.doc-preview-school { font-size: 20px; font-weight: bold; }
.doc-preview-title { font-size: 18px; font-weight: bold; margin-top: 6px; }
.doc-preview-subtitle { font-size: 14px; font-style: italic; margin-top: 4px; }
.doc-preview-meta { text-align: center; font-weight: bold; font-size: 13px; margin-bottom: 20px; }
.doc-preview-category { font-weight: bold; font-size: 14px; margin: 15px 0 8px 0; text-transform: uppercase; text-align: left; }

/* Left-justified question text and options */
.doc-preview-q { display: flex; gap: 8px; margin-bottom: 6px; text-align: left; font-size: 10pt; }
.doc-preview-qno { font-weight: bold; flex-shrink: 0; }
.doc-preview-text { flex-grow: 1; text-align: left; }
.doc-preview-options { margin-top: 4px; margin-bottom: 2px; font-size: 9pt; width: 100%; text-align: left; }
.doc-preview-option-row-1 { display: flex; gap: 10px; }
.doc-preview-option-row-1 span { flex: 1; }
.doc-preview-option-row-2 { display: flex; flex-wrap: wrap; gap: 2px 0; }
.doc-preview-option-row-2 span { flex: 0 0 50%; box-sizing: border-box; padding-right: 10px; }

/* Modal overlay styling */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
.modal-content { background: #fff; padding: 20px; border-radius: 8px; width: 400px; max-width: 90%; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }

/* FEATURE 4: Print-Ready Browser CSS */
@media print {
    body { background: #fff; margin: 0; padding: 0; }
    body * { visibility: hidden; }
    .doc-preview-container, .doc-preview-container * { visibility: visible; }
    .doc-preview-container { position: absolute; left: 0; top: 0; width: 100%; max-height: none; background: transparent; padding: 0; border: none; overflow: visible; }
    .doc-preview-page { padding: 0; box-shadow: none; max-width: 100%; width: 100%; margin: 0; text-align: left; }
    .doc-preview-q, .doc-preview-text, .doc-preview-options, .doc-preview-category { text-align: left !important; }
}

/* Keep question selection and action controls aligned to the top of each row. */
.question-list-row,
.available-question-row,
.basket-question-row {
    display: flex;
    align-items: flex-start !important;
}

.question-list-row > .question-checkbox,
.available-question-row > .question-checkbox,
.basket-question-row > .question-checkbox {
    align-self: flex-start !important;
    margin-top: 2px;
}

.question-list-row .question-actions,
.available-question-row .question-actions,
.basket-question-row .question-actions {
    align-self: flex-start !important;
    margin-top: 0;
}


/* Top-align checkbox and action cells in both question sections. */
#available-questions-table td:first-child,
#available-questions-table td:last-child,
#selected-questions-table td:first-child,
#selected-questions-table td:last-child {
    vertical-align: top !important;
}

/* Keep the actual checkbox and action button at the top edge of their cell. */
#available-questions-table td:first-child input[type="checkbox"],
#available-questions-table td:last-child button,
#selected-questions-table td:first-child input[type="checkbox"],
#selected-questions-table td:last-child button {
    vertical-align: top !important;
    margin-top: 2px;
}

#available-questions-table td:first-child form,
#available-questions-table td:last-child form,
#selected-questions-table td:first-child form,
#selected-questions-table td:last-child form {
    margin: 0;
    padding: 0;
}


/* Administrator teacher edit modal */
.teacher-modal-overlay {
    display:none; position:fixed; inset:0; background:rgba(0,0,0,.45);
    z-index:10000; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;
}
.teacher-modal-overlay.open { display:flex; }
.teacher-modal {
    background:#fff; width:100%; max-width:560px; border-radius:10px;
    box-shadow:0 12px 40px rgba(0,0,0,.28); padding:22px; box-sizing:border-box;
}
.teacher-modal-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:15px; }
.teacher-modal-header h3 { margin:0; }
.teacher-modal-close { border:0; background:transparent; font-size:26px; cursor:pointer; line-height:1; padding:2px 7px; }
.teacher-modal-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.teacher-modal-grid .full { grid-column:1 / -1; }
.teacher-modal-grid label { display:block; font-weight:bold; margin-bottom:5px; }
.teacher-modal-grid input { width:100%; box-sizing:border-box; }
.teacher-modal-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:18px; }
@media (max-width:600px) { .teacher-modal-grid { grid-template-columns:1fr; } .teacher-modal-grid .full { grid-column:auto; } }

/* --- SMARTPHONE RESPONSIVE OVERRIDES --- */
@media (max-width: 768px) {
    body { margin: 8px; font-size: 14px; overflow-x: hidden; }
    .box { padding: 10px; box-sizing: border-box; max-width: 100%; }
    body > div[style*="justify-content: space-between"] { flex-direction: column; align-items: flex-start !important; gap: 12px; }
    .top-row { flex-direction: column; }
    .top-row .box { flex: 1 1 100%; }
    .grid { grid-template-columns: 1fr !important; }
    .doc-preview-page { padding: 15px; }
    .modal-content, .teacher-modal { width: 95%; padding: 15px; }
    .teacher-modal-grid { grid-template-columns: 1fr; }
    div[style*="flex-wrap:wrap"] > button:not(.bulk-action-btn):not(.sec-collapse-btn) { width: 100%; flex: 1 1 100%; }

    /* Keep Add Selected and Remove Selected buttons right-aligned on smartphones */
    .bulk-action-bar,
    .bulk-action-bar-end {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: center !important;
        width: 100%;
    }
    .bulk-action-bar .stat {
        margin-right: auto !important;
    }
    .bulk-action-btn {
        margin-left: auto !important;
        width: auto !important;
        flex: 0 0 auto !important;
    }

    /* Prevent inputs and selects from exceeding screen width */
    select, input[type=file], input[type=text], input[type=number], input[type=password], textarea {
        max-width: 100%;
        min-width: 0;
        margin-right: 0;
        box-sizing: border-box;
    }

    .sec-heading-input {
        flex: 1 1 100% !important;
        width: 100% !important;
    }
    .sec-meta-info {
        white-space: normal;
    }

    /* Keep Selected & Available Questions tables and edit fields inside mobile screen */
    .table-responsive { display: block; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px; box-sizing: border-box; background: #fff; }
    #selected-questions-table,
    #available-questions-table {
        table-layout: fixed !important;
        width: 100% !important;
    }
    #selected-questions-table th:last-child,
    #selected-questions-table td:last-child,
    #available-questions-table th:last-child,
    #available-questions-table td:last-child {
        width: 56px !important;
    }
    #selected-questions-table td,
    #selected-questions-table th,
    #available-questions-table td,
    #available-questions-table th {
        word-wrap: break-word;
        overflow-wrap: anywhere;
        min-width: 0;
    }

    /* Stack Option A/B/C/D edit fields into 1 column on mobile */
    #selected-questions-table form div[style*="grid-template-columns"],
    #available-questions-table form div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    #selected-questions-table td input[type="text"],
    #selected-questions-table td textarea,
    #available-questions-table td input[type="text"],
    #available-questions-table td textarea {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        margin-right: 0 !important;
    }
}
</style>
<script>
    const existingFiles = <?= $existingFileNamesJson ?? '{}' ?>;
    const currentDate = "<?= date('Y-m-d') ?>";

    function openChangePassword() {
        const panel = document.getElementById('change-password');
        if (!panel) return;
        panel.open = true;
        setTimeout(function() {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 0);
    }

    window.addEventListener('DOMContentLoaded', function() {
        if (window.location.hash === '#change-password') {
            openChangePassword();
        }
    });
</script>
</head>
<body>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
    <h1 style="margin-bottom: 0;">Question Paper Generator</h1>
    <div style="display:flex; gap:8px; align-items:center;">
        <a href="#change-password" onclick="openChangePassword(); return false;" style="background: #1976d2; color: #fff; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; font-size: 14px;">Change Password</a>
        <a href="?logout=1" style="background: #c62828; color: #fff; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; font-size: 14px;">Logout</a>
    </div>
</div>

<?php if ($message !== ''): ?>
<div class="notice <?= h($messageType) ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="box theme-userinfo" style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center; gap:15px; flex-wrap:wrap;">
    <div><b>Logged in:</b> <?= h($loggedInUser['display_name']) ?> &nbsp; | &nbsp; <b>Role:</b> <?= h(ucfirst($loggedInUser['role'])) ?> &nbsp; | &nbsp; <b>Subject:</b> <?= h($activeOwner['subject']) ?></div>
    <?php if (isAdmin()): ?>
        <form method="get" style="margin:0; display:flex; align-items:center; gap:8px;">
            <label><b>Work with teacher:</b></label>
            <select name="owner" onchange="this.form.submit()">
                <option value="<?= (int)$loggedInUser['id'] ?>" <?= $activeOwnerId === (int)$loggedInUser['id'] ? 'selected' : '' ?>>Administrator / Own</option>
                <?php foreach (teacherUsers() as $teacher): ?>
                    <option value="<?= (int)$teacher['id'] ?>" <?= $activeOwnerId === (int)$teacher['id'] ? 'selected' : '' ?>><?= h($teacher['display_name'] . ' — ' . $teacher['subject']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</div>

<details id="change-password" class="box theme-password" style="margin-bottom:15px;">
    <summary style="cursor:pointer; font-weight:bold;">Change My Password</summary>
    <form method="post" style="margin-top:12px; max-width:520px;">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="change_password" value="1">
        <div style="display:grid; grid-template-columns:1fr; gap:8px;">
            <label>
                <b>Current password</b>
                <input type="password" name="current_password" required autocomplete="current-password" style="width:100%; box-sizing:border-box;">
            </label>
            <label>
                <b>New password</b>
                <input type="password" name="new_password" minlength="6" required autocomplete="new-password" style="width:100%; box-sizing:border-box;">
            </label>
            <label>
                <b>Confirm new password</b>
                <input type="password" name="confirm_password" minlength="6" required autocomplete="new-password" style="width:100%; box-sizing:border-box;">
            </label>
            <div class="small">Use at least 6 characters. Your password is stored securely as a hash and cannot be viewed by the administrator.</div>
            <div>
                <button class="primary" type="submit">Change Password</button>
            </div>
        </div>
    </form>
</details>

<?php if (isAdmin()): ?>
<details class="box theme-admin" style="margin-bottom:15px;">
    <summary style="cursor:pointer; font-weight:bold;">Administrator — Manage Subject Teachers</summary>
    <div style="margin-top:12px;">
        <form method="post" class="grid" style="margin-bottom:15px;">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="admin_user_action" value="create_teacher">
            <input type="text" name="new_username" placeholder="Username" required>
            <input type="text" name="new_display_name" placeholder="Teacher name" required>
            <input type="text" name="new_subject" placeholder="Subject" required>
            <input type="password" name="new_password" placeholder="Initial password" minlength="6" required>
            <button class="primary">+ Create Teacher</button>
        </form>
        <p class="small" style="margin-bottom:10px;">Use <b>Edit</b> to change the teacher's name, username, subject or optionally set a new password. The teacher's existing question-bank and saved-paper folder is preserved when the username is changed.</p>
        <div class="table-responsive">
            <table>
                <tr><th>Teacher</th><th>Username</th><th>Subject</th><th>Status</th><th>Actions</th></tr>
                <?php foreach (teacherUsers() as $teacher): ?>
                <tr>
                    <td><?= h($teacher['display_name']) ?></td>
                    <td><?= h($teacher['username']) ?></td>
                    <td><?= h($teacher['subject']) ?></td>
                    <td><?= $teacher['active'] ? '<span style="color:#2e7d32;font-weight:bold;">Active</span>' : '<span style="color:#c62828;font-weight:bold;">Disabled</span>' ?></td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="primary teacher-edit-btn"
                            data-id="<?= (int)$teacher['id'] ?>"
                            data-name="<?= h($teacher['display_name']) ?>"
                            data-username="<?= h($teacher['username']) ?>"
                            data-subject="<?= h($teacher['subject']) ?>">Edit</button>
                        <form method="post" style="display:inline-block; margin-left:5px;">
                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                            <input type="hidden" name="admin_user_action" value="toggle_teacher">
                            <input type="hidden" name="teacher_id" value="<?= (int)$teacher['id'] ?>">
                            <button type="submit" class="<?= $teacher['active'] ? 'danger' : 'success' ?>"><?= $teacher['active'] ? 'Disable' : 'Enable' ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</details>
<?php endif; ?>

<?php if (isAdmin()): ?>
<div id="teacherEditModal" class="teacher-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="teacherEditTitle">
    <div class="teacher-modal" onclick="event.stopPropagation();">
        <div class="teacher-modal-header">
            <h3 id="teacherEditTitle">Edit Teacher</h3>
            <button type="button" class="teacher-modal-close" id="teacherEditClose" aria-label="Close">&times;</button>
        </div>
        <form method="post" id="teacherEditForm">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="admin_user_action" value="edit_teacher">
            <input type="hidden" name="teacher_id" id="edit_teacher_id" value="">
            <div class="teacher-modal-grid">
                <div>
                    <label for="edit_display_name">Teacher Name</label>
                    <input type="text" name="edit_display_name" id="edit_display_name" required>
                </div>
                <div>
                    <label for="edit_username">Username</label>
                    <input type="text" name="edit_username" id="edit_username" minlength="3" required>
                </div>
                <div class="full">
                    <label for="edit_subject">Subject</label>
                    <input type="text" name="edit_subject" id="edit_subject" required>
                </div>
                <div class="full">
                    <label for="edit_password">New Password <span class="small">(leave blank to keep current password)</span></label>
                    <input type="password" name="edit_password" id="edit_password" minlength="6" autocomplete="new-password" placeholder="Optional new password">
                </div>
            </div>
            <div class="teacher-modal-actions">
                <button type="button" id="teacherEditCancel">Cancel</button>
                <button type="submit" class="primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="top-row">
    <div class="box theme-import">
        <h2>1. Import Question Bank</h2>
        <p>Importing for: <b><?= h($activeOwner['display_name']) ?></b> — <?= h($activeOwner['subject']) ?></p>
        <p>Required: <b>QNo, Chapter, Category, Question, Marks</b></p>
        <form method="post" enctype="multipart/form-data" id="import-bank-form">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="import_bank" value="1">
            <?php if (isAdmin()): ?><input type="hidden" name="owner" value="<?= (int)$activeOwnerId ?>"><?php endif; ?>
            <input type="file" name="question_bank" id="question_bank_file" accept=".xlsx" required style="display:none;" onchange="handleImportFileSelection(this)">
            <button type="button" class="primary" id="import-bank-btn" onclick="document.getElementById('question_bank_file').click()" style="width:100%; padding:8px 12px;">📂 Import XLSX</button>
        </form>
    </div>

    <div class="box theme-select">
        <h2>2. Select Question Bank</h2>
        <form method="get" id="select-bank-form">
            <select name="bank" id="bank-select" data-current="<?= h($selectedBank) ?>" onchange="handleBankChange(this)" style="width: 100%; margin-bottom: 8px;">
                <option value="">-- None --</option>
            <?php foreach ($questionBanks as $bank): ?>
                <option value="<?= h($bank) ?>" <?= $bank === $selectedBank ? 'selected' : '' ?>><?= h($bank) ?></option>
            <?php endforeach; ?>
            </select>
        </form>
        <div class="stat">Selected: <b><?= h($selectedBank ?: 'None') ?></b><br>Total questions: <b><?= count($questions) ?></b></div>
    </div>

    <div class="box theme-saved">
        <h2>Load / Manage Saved Papers</h2>
        <p>Restore or delete previously generated QP files.</p>
        <form method="post" id="load-paper-form">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <select name="saved_paper_path" id="saved-paper-select" style="width: 100%; margin-bottom: 8px;">
                <option value="">-- Select a saved paper --</option>
                <?php foreach ($savedPapersList as $sp): ?>
                    <option value="<?= h($sp['path']) ?>"><?= h($sp['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="edit" name="load_paper" value="1" style="flex: 1;" onclick="return handleLoadPaperClick(event, this.form);">📂 Load</button>
                <button type="submit" class="danger" name="delete_paper" value="1" style="flex: 0 0 auto;" onclick="if(!this.form.saved_paper_path.value){ alert('Please select a paper first.'); return false; } return confirm('Are you sure you want to permanently delete this saved paper?');">🗑 Delete</button>
            </div>
        </form>
    </div>
</div>

<?php if ($questions): ?>
<div style="display: flex; align-items: center; justify-content: space-between;">
    <h2>Available Questions</h2>
</div>

<details class="box theme-create" style="margin-bottom: 15px; padding: 12px;">
    <summary style="cursor: pointer; font-weight: bold; color: #0e7490; font-size: 16px; outline: none;">+ Create & Add New Question</summary>
    <div style="margin-top: 15px; border-top: 1px solid #a5f3fc; padding-top: 15px;">
        <form method="post" style="margin:0;">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <div class="grid" style="margin-bottom: 10px;">
                <div><label class="small">Q.No (Optional)</label><input type="text" name="new_qno" style="width:100%;"></div>
                <div><label class="small">Chapter</label><input type="text" name="new_chapter" required style="width:100%;"></div>
                <div><label class="small">Category</label><input type="text" name="new_category" placeholder="e.g. MCQ, Short" required style="width:100%;"></div>
                <div><label class="small">Marks</label><input type="number" name="new_marks" required style="width:100%;"></div>
                <div><label class="small">Difficulty (Optional)</label><input type="text" name="new_difficulty" style="width:100%;"></div>
            </div>
            
            <label class="small">Question Text</label>
            <textarea name="new_question" rows="3" required style="width:100%; padding:5px; margin-bottom:10px;"></textarea>
            
            <div class="grid" style="margin-bottom: 15px;">
                <div><label class="small">Option A (Optional)</label><input type="text" name="new_opt_a" style="width:100%;"></div>
                <div><label class="small">Option B (Optional)</label><input type="text" name="new_opt_b" style="width:100%;"></div>
                <div><label class="small">Option C (Optional)</label><input type="text" name="new_opt_c" style="width:100%;"></div>
                <div><label class="small">Option D (Optional)</label><input type="text" name="new_opt_d" style="width:100%;"></div>
            </div>
            
            <button type="submit" name="add_new_question_to_bank" class="primary">Save Question to Excel File</button>
        </form>
    </div>
</details>

<div class="box theme-filter" style="margin-bottom: 15px;">
    <h2>3. Filter & Search Questions</h2>
    <form method="get" id="filter-form">
        <input type="hidden" name="bank" value="<?= h($selectedBank) ?>">

        <div style="display: flex; gap: 6px; margin-bottom: 10px; align-items: center;">
            <input type="text" name="search" id="search-input" value="<?= h($searchQuery) ?>" placeholder="Search Q.No, questions, categories..." style="flex:1; margin:0;" autocomplete="off">
            <a href="?bank=<?= urlencode($selectedBank) ?>&keep_basket=1" style="font-size:14px; color:#c62828; text-decoration:none; font-weight:bold; margin-left:4px; white-space:nowrap;">Clear Filters</a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 8px;">
            <div class="filter-group">
                <label>Category:</label>
                <select name="category" onchange="this.form.submit()">
                    <option>All</option>
                    <?php foreach ($categories as $v): ?><option <?= $v===$selectedCategory?'selected':'' ?>><?= h($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Marks:</label>
                <select name="marks" onchange="this.form.submit()">
                    <option>All</option>
                    <?php foreach ($marks as $v): ?><option <?= (string)$v===(string)$selectedMarks?'selected':'' ?>><?= h($v) ?></option><?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Chapter:</label>
                <select name="chapter" onchange="this.form.submit()">
                    <option>All</option>
                    <?php foreach ($chapters as $v): ?><option <?= $v===$selectedChapter?'selected':'' ?>><?= h($v) ?></option><?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Difficulty:</label>
                <select name="difficulty" onchange="this.form.submit()">
                    <option>All</option>
                    <?php foreach ($difficulties as $v): ?><option <?= $v===$selectedDifficulty?'selected':'' ?>><?= h($v) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
</div>

<form id="bulk-form" method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
</form>

<div class="bulk-action-bar">
    <div class="stat" id="available-questions-stat" style="margin:0;">Showing <?= count($paginated) ?> of <?= $total ?> filtered questions</div>
    <button form="bulk-form" name="bulk_add" class="primary bulk-action-btn">+ Add Selected to Basket</button>
</div>

<div class="table-responsive">
    <table id="available-questions-table">
    <tr>
        <th>Question Details</th>
        <th style="width: 80px; text-align: center; vertical-align: bottom;">
            <div style="margin-bottom: 4px; font-size: 13px;">Select All</div>
            <input type="checkbox" id="select-all-bank" title="Select All">
        </th>
    </tr>
    <?php foreach ($paginated as $id=>$q): ?>
    <tr id="bank-row-<?= $id ?>">
    <td>
        <?php if ((string)$editBankId === (string)$id): ?>
            <form method="post" style="margin:0;" id="edit-bank-form-<?= $id ?>">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                
                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
                    <div style="flex: 1; min-width: 100px;"><small>Category</small><input type="text" name="edited_bank_category" value="<?= h($q['Category']) ?>" style="width:100%;"></div>
                    <div style="flex: 1; min-width: 60px;"><small>Marks</small><input type="text" name="edited_bank_marks" value="<?= h($q['Marks']) ?>" style="width:100%;"></div>
                    <div style="flex: 1; min-width: 80px;"><small>Difficulty</small><input type="text" name="edited_bank_difficulty" value="<?= h($q['Difficulty']) ?>" style="width:100%;"></div>
                </div>

                <textarea name="edited_bank_question" rows="4" style="width:100%; padding:5px; margin-bottom:5px;"><?= h($q['Question']) ?></textarea>
                <?php if ($q['OptionA'] !== '' || $q['OptionB'] !== '' || $q['OptionC'] !== '' || $q['OptionD'] !== '' || stripos($q['Category'], 'mcq') !== false): ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-bottom: 5px;">
                        <div><small>Option A</small><input type="text" name="edited_bank_option_a" value="<?= h($q['OptionA']) ?>" style="width:100%;"></div>
                        <div><small>Option B</small><input type="text" name="edited_bank_option_b" value="<?= h($q['OptionB']) ?>" style="width:100%;"></div>
                        <div><small>Option C</small><input type="text" name="edited_bank_option_c" value="<?= h($q['OptionC']) ?>" style="width:100%;"></div>
                        <div><small>Option D</small><input type="text" name="edited_bank_option_d" value="<?= h($q['OptionD']) ?>" style="width:100%;"></div>
                    </div>
                <?php endif; ?>
                <div style="margin-top: 8px;">
                    <button class="success" name="save_bank_edit" value="<?= $id ?>">Save</button>
                    <button class="secondary" name="cancel_bank_edit" value="1" formnovalidate>Cancel</button>
                </div>
            </form>
        <?php else: ?>
            <div style="margin-bottom: 8px; display: flex; flex-wrap: wrap; gap: 6px;">
                <span class="badge" style="background:#eeeeee; color:#333; border:1px solid #ccc;">🔢 Q.<?= h($q['QNo']) ?></span>
                <span class="badge" style="background:#e3f2fd; color:#0d47a1; border:1px solid #bbdefb;">📖 <?= h($q['Chapter']) ?></span>
                <span class="badge" style="background:#fff3e0; color:#e65100; border:1px solid #ffe0b2;">🏷️ <?= h(getSectionKey($q)) ?></span>
                <span class="badge" style="background:#e8f5e9; color:#1b5e20; border:1px solid #c8e6c9;">⭐ <?= h($q['Marks']) ?> Marks</span>
                <?php if ($q['Difficulty'] !== ''): ?>
                    <span class="badge" style="background:#f3e5f5; color:#4a148c; border:1px solid #e1bee7;">⚡ <?= h($q['Difficulty']) ?></span>
                <?php endif; ?>
            </div>
            
            <div>
                <?= nl2br(h($q['Question'])) ?>
                <?php foreach (['A'=>'OptionA','B'=>'OptionB','C'=>'OptionC','D'=>'OptionD'] as $label=>$key): ?>
                <?php if ($q[$key]!==''): ?><br>&nbsp;&nbsp;<b>(<?= $label ?>)</b> <?= h($q[$key]) ?><?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </td>
    <td style="text-align: center; vertical-align: top; background: #fafafa;">
        <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
            <?php if (!isset($basket[$id])): ?>
                <input type="checkbox" name="bulk_ids[]" value="<?= $id ?>" form="bulk-form" class="bank-checkbox" style="transform: scale(1.3); margin: 4px 0;">
            <?php endif; ?>
            
            <form method="post" style="margin:0; width:100%;">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <?php if (isset($basket[$id])): ?>
                    <button class="danger" name="remove" value="<?= $id ?>" title="Remove" style="width:100%; padding: 6px 0;">-</button>
                <?php else: ?>
                    <button class="primary" name="add" value="<?= $id ?>" title="Add" style="width:100%; padding: 6px 0;">+</button>
                <?php endif; ?>
            </form>
            
            <?php if ((string)$editBankId !== (string)$id): ?>
                <form method="post" style="margin:0; width:100%;">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <button class="edit" name="edit_bank_id" value="<?= $id ?>" style="width:100%; padding: 4px 0; font-size: 12px;">Edit</button>
                </form>
            <?php endif; ?>
        </div>
    </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>

<div class="bulk-action-bar-end">
    <button form="bulk-form" name="bulk_add" class="primary bulk-action-btn">+ Add Selected to Basket</button>
</div>

<div class="pagination" id="available-questions-pagination">
    <?php if ($page > 1): ?>
        <a href="<?= $baseUrl ?>&page=1">&laquo; First</a>
        <a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>">&lsaquo; Prev</a>
    <?php else: ?>
        <span class="disabled">&laquo; First</span>
        <span class="disabled">&lsaquo; Prev</span>
    <?php endif; ?>

    <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>

    <?php if ($page < $totalPages): ?>
        <a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>">Next &rsaquo;</a>
        <a href="<?= $baseUrl ?>&page=<?= $totalPages ?>">Last &raquo;</a>
    <?php else: ?>
        <span class="disabled">Next &rsaquo;</span>
        <span class="disabled">Last &raquo;</span>
    <?php endif; ?>
</div>
<?php endif; ?> <!-- CLOSE Available Questions Here -->

<?php if ($selectedBank !== '' || !empty($basket)): ?>
<div id="basket-section" class="box theme-basket" style="margin-top:20px;">
<h2>4. Selected Questions</h2>

<form method="post" id="export-form">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
</form>

<form id="bulk-remove-form" method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
</form>

<?php if ($basket): ?>
    <?php
    $blueprint = [];
    $uniqueTypes = [];
    $grandTotalMarks = 0;
    $grandTotalQs = 0;
    $categoryTotalMarks = []; 

    foreach ($basket as $q) {
        $chap = trim($q['Chapter']);
        if ($chap === '') $chap = 'Uncategorized';
        
        $secKey = getSectionKey($q);
        $mks = (int)$q['Marks'];

        if (!in_array($secKey, $uniqueTypes)) {
            $uniqueTypes[] = $secKey;
            $categoryTotalMarks[$secKey] = 0;
        }

        if (!isset($blueprint[$chap])) {
            $blueprint[$chap] = ['total_marks' => 0, 'total_qs' => 0, 'types' => []];
        }
        if (!isset($blueprint[$chap]['types'][$secKey])) {
            $blueprint[$chap]['types'][$secKey] = 0;
        }

        $blueprint[$chap]['types'][$secKey]++;
        $blueprint[$chap]['total_marks'] += $mks;
        $blueprint[$chap]['total_qs']++;
        
        $categoryTotalMarks[$secKey] += $mks;
        $grandTotalMarks += $mks;
        $grandTotalQs++;
    }

    usort($uniqueTypes, function($a, $b) { 
        $wA = getSectionOrder($a);
        $wB = getSectionOrder($b);
        if ($wA === $wB) return strnatcmp($a, $b);
        return $wA <=> $wB;
    });
    ksort($blueprint);
    ?>

    <!-- 1. BLUEPRINT FIRST -->
    <div id="basket-blueprint-container">
        <h3>Basket Summary (Blueprint)</h3>
        <div style="overflow-x:auto; margin-bottom: 18px;">
            <table style="margin-top:0;">
                <tr>
                    <th>Chapter</th>
                    <?php foreach ($uniqueTypes as $type): 
                        $tHash = md5($type);
                    ?>
                        <th id="blueprint_th_<?= $tHash ?>" style="text-align:center;" title="<?= h(getSectionHeading($type)) ?>"><?= h(getSectionOrder($type)) ?></th>
                    <?php endforeach; ?>
                    <th style="text-align:center; background:#dff0d8;">Total Qs</th>
                    <th style="text-align:center; background:#dff0d8;">Weightage</th>
                </tr>
                <?php foreach ($blueprint as $chap => $data): ?>
                <tr>
                    <td><b><?= h($chap) ?></b></td>
                    <?php foreach ($uniqueTypes as $type): ?>
                        <td style="text-align:center;">
                            <?= isset($data['types'][$type]) ? $data['types'][$type] : '<span style="color:#ccc;">-</span>' ?>
                        </td>
                    <?php endforeach; ?>
                    <td style="text-align:center; background:#f9fdf9;"><b><?= $data['total_qs'] ?></b></td>
                    <td style="text-align:center; background:#f9fdf9;"><b><?= $data['total_marks'] ?></b></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background:#eef1f5;">
                    <td><b>GRAND TOTAL</b></td>
                    <?php foreach ($uniqueTypes as $type):
                        $colTotalQs = 0;
                        foreach ($blueprint as $data) {
                            $colTotalQs += $data['types'][$type] ?? 0;
                        }
                        $colTotalMks = $categoryTotalMarks[$type] ?? 0;
                    ?>
                        <td style="text-align:center;"><b><?= $colTotalQs ?> (<?= $colTotalMks ?>)</b></td>
                    <?php endforeach; ?>
                    <td style="text-align:center; background:#dff0d8; color:#245b25;"><b><?= $grandTotalQs ?></b></td>
                    <td style="text-align:center; background:#dff0d8; color:#245b25;"><b><?= $grandTotalMarks ?></b></td>
                </tr>
            </table>
        </div>
    </div>
    <hr style="border:0; border-top:1px solid #c5e1a5; margin-bottom:12px;">

<?php
$groupedBasket = [];
foreach ($basket as $id => $q) {
    $sec = getSectionKey($q);
    $groupedBasket[$sec][$id] = $q;
}

uksort($groupedBasket, function($a, $b) {
    $wA = getSectionOrder($a);
    $wB = getSectionOrder($b);
    if ($wA === $wB) return strnatcmp($a, $b);
    return $wA <=> $wB;
});
?>

<div class="bulk-action-bar">
    <div class="sec-collapse-controls">
        <button type="button" class="sec-collapse-btn" onclick="toggleAllBasketSections(true)">▾ Expand All Sections</button>
        <button type="button" class="sec-collapse-btn" onclick="toggleAllBasketSections(false)">▸ Collapse All Sections</button>
        <span class="small" style="margin-left:4px;">(Click any section bar below to expand/collapse; edit order <b>#</b> or heading inline)</span>
    </div>
    <button form="bulk-remove-form" name="bulk_remove" class="danger bulk-action-btn" onclick="return confirm('Remove selected questions from the basket?');">- Remove Selected</button>
</div>

<div class="table-responsive">
    <table id="selected-questions-table">
    <thead>
    <tr>
        <th>Question Sections &amp; Details</th>
        <th style="width: 80px; text-align: center; vertical-align: bottom;">
            <div style="margin-bottom: 4px; font-size: 13px;">Select All</div>
            <input type="checkbox" id="select-all-basket" title="Select All Questions in Basket">
        </th>
    </tr>
    </thead>
    <?php foreach ($groupedBasket as $sectionKey => $groupQuestions): 
        $hash = md5($sectionKey);
        $currentOrder = getSectionOrder($sectionKey);
        $currentHeading = getSectionHeading($sectionKey);
        // Collapsed by default; auto-expand only if actively editing a question inside this section
        $isExpanded = ($editingBasketId !== null && array_key_exists($editingBasketId, $groupQuestions));
    ?>
        <tbody class="basket-section-tbody" data-section-hash="<?= $hash ?>" data-force-expand="<?= $isExpanded ? '1' : '0' ?>">
        <tr class="basket-section-header" onclick="toggleBasketSection('<?= $hash ?>')">
            <th style="text-align:left; vertical-align:middle;">
                <div class="sec-header-bar">
                    <div class="sec-header-left">
                        <span class="sec-toggle-btn" id="sec-toggle-icon-<?= $hash ?>" title="Expand / Collapse Section"><?= $isExpanded ? '▾' : '▸' ?></span>
                        <span class="sec-order-badge" onclick="event.stopPropagation();" title="Section Sort Order (Auto-saved)">
                            #<input type="number"
                                name="section_order[<?= $hash ?>]"
                                value="<?= h($currentOrder) ?>"
                                form="export-form"
                                class="sec-order-input"
                                data-hash="<?= $hash ?>"
                                onclick="event.stopPropagation();">
                        </span>
                        <input type="text"
                            name="section_heading[<?= $hash ?>]"
                            value="<?= h($currentHeading) ?>"
                            form="export-form"
                            class="sec-heading-input"
                            data-hash="<?= $hash ?>"
                            onclick="event.stopPropagation();"
                            placeholder="Custom Section Heading"
                            title="Edit section heading (Auto-saved)">
                        <span class="sec-meta-info">
                            (<?= h($sectionKey) ?> — <?= h(getSectionMarksSummary($groupQuestions)) ?>)
                        </span>
                        <span class="sec-save-status" id="sec-save-status-<?= $hash ?>">✓ Saved</span>
                    </div>
                </div>
            </th>
            <th style="text-align:center; vertical-align:middle;" onclick="event.stopPropagation();">
                <input type="checkbox"
                    class="section-select-all"
                    data-hash="<?= $hash ?>"
                    title="Select all questions in this section"
                    style="transform: scale(1.25);">
            </th>
        </tr>
        <?php foreach ($groupQuestions as $id => $q): ?>
        <tr id="basket-row-<?= h($id) ?>" class="basket-q-row sec-rows-<?= $hash ?>" style="<?= $isExpanded ? '' : 'display:none;' ?>">
            <td>
                <?php if ((string)$editId === (string)$id): ?>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <textarea id="basket-edit-question-<?= h($id) ?>" name="edited_question" rows="4" autofocus style="width:100%; padding:5px; margin-bottom:5px;"><?= h($q['Question']) ?></textarea>
                        <?php if ($q['OptionA'] !== '' || $q['OptionB'] !== '' || $q['OptionC'] !== '' || $q['OptionD'] !== '' || stripos($q['Category'], 'mcq') !== false): ?>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-bottom: 5px;">
                                <div><small>Option A</small><input type="text" name="edited_option_a" value="<?= h($q['OptionA']) ?>" style="width:100%;"></div>
                                <div><small>Option B</small><input type="text" name="edited_option_b" value="<?= h($q['OptionB']) ?>" style="width:100%;"></div>
                                <div><small>Option C</small><input type="text" name="edited_option_c" value="<?= h($q['OptionC']) ?>" style="width:100%;"></div>
                                <div><small>Option D</small><input type="text" name="edited_option_d" value="<?= h($q['OptionD']) ?>" style="width:100%;"></div>
                            </div>
                        <?php endif; ?>
                        <div style="margin-top: 8px;">
                            <button class="success" name="save_edit" value="<?= h($id) ?>">Save</button>
                            <button class="secondary" name="cancel_edit" value="1">Cancel</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="margin-bottom: 8px; display: flex; flex-wrap: wrap; gap: 6px;">
                        <span class="badge" style="background:#eeeeee; color:#333; border:1px solid #ccc;">🔢 Q.<?= h($q['QNo']) ?></span>
                        <span class="badge" style="background:#e3f2fd; color:#0d47a1; border:1px solid #bbdefb;">📖 <?= h($q['Chapter']) ?></span>
                        <span class="badge" style="background:#fff3e0; color:#e65100; border:1px solid #ffe0b2;">🏷️ <?= h(getSectionKey($q)) ?></span>
                        <span class="badge" style="background:#e8f5e9; color:#1b5e20; border:1px solid #c8e6c9;">⭐ <?= h($q['Marks']) ?> Marks</span>
                        <?php if ($q['Difficulty'] !== ''): ?>
                            <span class="badge" style="background:#f3e5f5; color:#4a148c; border:1px solid #e1bee7;">⚡ <?= h($q['Difficulty']) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <?= nl2br(h($q['Question'])) ?>
                        <?php foreach (['A'=>'OptionA','B'=>'OptionB','C'=>'OptionC','D'=>'OptionD'] as $label=>$key): ?>
                        <?php if ($q[$key]!==''): ?><br>&nbsp;&nbsp;<b>(<?= $label ?>)</b> <?= h($q[$key]) ?><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </td>
            <td style="text-align: center; vertical-align: top; background: #fafafa;">
                <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                    <input type="checkbox" name="basket_ids[]" value="<?= h($id) ?>" form="bulk-remove-form" class="basket-checkbox sec-cb-<?= $hash ?>" data-hash="<?= $hash ?>" style="transform: scale(1.3); margin: 4px 0;">
                    
                    <form method="post" style="margin:0; width:100%;">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <button class="danger" name="remove" value="<?= h($id) ?>" title="Remove" style="width:100%; padding: 6px 0;">-</button>
                    </form>
                    
                    <?php if ((string)$editId !== (string)$id): ?>
                        <form method="post" style="margin:0; width:100%;">
                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                            <button class="edit" name="edit_id" value="<?= h($id) ?>" style="width:100%; padding: 4px 0; font-size: 12px;">Edit</button>
                        </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    <?php endforeach; ?>
    </table>
</div>

<div class="bulk-action-bar-end">
    <button form="bulk-remove-form" name="bulk_remove" class="danger bulk-action-btn" onclick="return confirm('Remove selected questions from the basket?');">- Remove Selected</button>
</div>

<?php else: ?>
<p>No questions selected. Use <b>+</b> above to add questions to the basket.</p>
<?php endif; ?>
</div>
<?php endif; ?> <!-- CLOSE BASKET -->

<?php if (!empty($basket)): 
    $docSchool = $_SESSION['doc_config']['school_name'] ?? SCHOOL_NAME;
    $docTitle = $_SESSION['doc_config']['paper_title'] ?? 'QUESTION PAPER';
    $docSubtitle = $_SESSION['doc_config']['paper_subtitle'] ?? '';
    
    // Feature 2 & 6 Context
    $docFont = $_SESSION['doc_config']['doc_font'] ?? 'Cambria';
    $docWatermark = $_SESSION['doc_config']['watermark_text'] ?? '';
?>
<div id="preview-section" class="box theme-preview" style="margin-top:20px;">
    <h2>5. Document Preview & Export</h2>
    
    <div style="margin-bottom: 20px;">
        <label style="display:block; font-weight:bold; margin-bottom: 5px;">Document Configuration:</label>
        <div class="grid">
            <input type="text" id="input_school_name" name="school_name" value="<?= h($docSchool) ?>" placeholder="School name" form="export-form">
            <input type="text" id="input_paper_title" name="paper_title" value="<?= h($docTitle) ?>" placeholder="Paper title" form="export-form">
            <input type="text" id="input_paper_subtitle" name="paper_subtitle" value="<?= h($docSubtitle) ?>" placeholder="Class / Subject / Examination" form="export-form">
            
            <!-- FEATURE 6: Watermark Text Input -->
            <input type="text" id="input_watermark" name="watermark_text" value="<?= h($docWatermark) ?>" placeholder="Watermark (e.g. MOCK EXAM)" form="export-form">
            
            <!-- FEATURE 2: Font Support Dropdown -->
            <select name="doc_font" id="input_doc_font" form="export-form">
                <option value="Cambria" <?= $docFont === 'Cambria' ? 'selected' : '' ?>>Cambria</option>
                <option value="Arial" <?= $docFont === 'Arial' ? 'selected' : '' ?>>Arial</option>
                <option value="Tunga" <?= $docFont === 'Tunga' ? 'selected' : '' ?>>Tunga (Kannada)</option>
                <option value="Nudi 01 e" <?= $docFont === 'Nudi 01 e' ? 'selected' : '' ?>>Nudi (Kannada)</option>
                <option value="Arial Unicode MS" <?= $docFont === 'Arial Unicode MS' ? 'selected' : '' ?>>Arial Unicode MS</option>
            </select>
        </div>
    </div>

    <div id="doc-preview-container" class="doc-preview-container" tabindex="-1" style="outline:none;">
        <!-- FEATURE 2: Applied inline font to dynamically match dropdown selection -->
        <div class="doc-preview-page" id="doc_preview_block" style="font-family: '<?= h($docFont) ?>', sans-serif;">
            <div class="doc-preview-header">
                <div class="doc-preview-school" id="preview_school_name"><?= strtoupper(h($docSchool)) ?></div>
                <div class="doc-preview-title" id="preview_paper_title"><?= strtoupper(h($docTitle)) ?></div>
                <div class="doc-preview-subtitle" id="preview_paper_subtitle"><?= h($docSubtitle) ?></div>
            </div>
            
            <div class="doc-preview-meta" id="preview_meta_box">
                Maximum Marks: <?= isset($grandTotalMarks) ? $grandTotalMarks : 0 ?> &nbsp;&nbsp;|&nbsp;&nbsp; Questions: <?= isset($grandTotalQs) ? $grandTotalQs : 0 ?>
            </div>

            <div id="doc_preview_questions_body">
            <?php
            $previewQIndex = 1;
            foreach ($groupedBasket as $sectionKey => $groupQuestions): 
                $hash = md5($sectionKey);
            ?>
                <div class="doc-preview-category" id="preview_heading_<?= $hash ?>">
                    <?= h(getSectionHeading($sectionKey)) ?>
                </div>
                
                <?php foreach ($groupQuestions as $q): ?>
                    <div class="doc-preview-q">
                        <div class="doc-preview-qno"><?= $previewQIndex++ ?>.</div>
                        <div class="doc-preview-text">
                            <?= nl2br(h($q['Question'])) ?>
                            
                            <?php if($q['OptionA'] !== '' || $q['OptionB'] !== '' || $q['OptionC'] !== '' || $q['OptionD'] !== ''): 
                                $optA = trim($q['OptionA']); $optB = trim($q['OptionB']); $optC = trim($q['OptionC']); $optD = trim($q['OptionD']);
                                $maxLen = max(strlen($optA), strlen($optB), strlen($optC), strlen($optD));
                            ?>
                                <div class="doc-preview-options">
                                    <?php if ($maxLen < 25): ?>
                                        <div class="doc-preview-option-row-1">
                                            <span>(A) <?= h($optA) ?></span>
                                            <span>(B) <?= h($optB) ?></span>
                                            <span>(C) <?= h($optC) ?></span>
                                            <span>(D) <?= h($optD) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="doc-preview-option-row-2">
                                            <span>(A) <?= h($optA) ?></span>
                                            <span>(B) <?= h($optB) ?></span>
                                            <span>(C) <?= h($optC) ?></span>
                                            <span>(D) <?= h($optD) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </div>
        </div>
    </div>

    <br>
    <div style="display:flex; flex-wrap:wrap; gap:10px;">
        <button class="primary" name="export" form="export-form" style="font-size: 16px; padding: 8px 16px;">⬇ Generate Professional DOCX</button>
        <!-- FEATURE 1: Answer Key Trigger -->
        <button class="primary" name="export_answers" form="export-form" style="font-size: 16px; padding: 8px 16px; background-color: #0056b3;">⬇ Generate Answer Key</button>
        <!-- FEATURE 4: Direct Print Trigger -->
        <button type="button" id="print-preview-btn" class="secondary" style="font-size: 16px; padding: 8px 16px;" onclick="window.print()">🖨️ Print Preview</button>
        <button type="button" class="success" style="font-size: 16px; padding: 8px 16px; background-color: #28a745;" onclick="document.getElementById('saveModal').style.display='flex'">💾 Save QP</button>
        <button class="secondary" name="clear_basket" form="export-form" formnovalidate style="font-size: 16px; padding: 8px 16px;" onclick="clearExpandedSectionsMemory()">Clear Basket</button>
    </div>
</div>
<?php endif; ?>

<!-- Save JSON Modal -->
<div id="saveModal" class="modal-overlay">
    <div class="modal-content">
        <h3 style="margin-top:0;">Save Question Paper</h3>
        <p class="small" style="margin-bottom:15px;">Enter a custom filename (without extension). If left blank, an automatic timestamped name will be used.</p>
        <input type="text" id="customFileNameInput" placeholder="e.g. Science_Midterm_Class10" style="width:100%; margin-bottom:15px; padding:8px; font-size:15px;">
        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" class="secondary" onclick="document.getElementById('saveModal').style.display='none'">Cancel</button>
            <button type="button" class="success" onclick="processSave()">Save Paper</button>
        </div>
    </div>
</div>
<input type="hidden" name="custom_filename" id="hiddenCustomFilename" form="export-form">
<input type="hidden" name="after_save_action" id="hiddenAfterSaveAction" value="" form="export-form">
<input type="hidden" name="after_save_target" id="hiddenAfterSaveTarget" value="" form="export-form">

<!-- Unsaved Basket Confirmation Modal (for Switching QB or Loading Saved QP) -->
<div id="unsavedBasketModal" class="modal-overlay">
    <div class="modal-content" style="width: 450px;">
        <h3 style="margin-top:0; color:#c62828;">⚠️ Unsaved Questions in Basket</h3>
        <p id="unsavedBasketMessage" style="font-size:14px; line-height:1.45;">
            You currently have <b><?= count($basket) ?> question(s)</b> in your basket. Continuing will clear your current basket. Would you like to save your current Question Paper first?
        </p>
        <label class="small" style="display:block; margin-bottom:4px; font-weight:bold;">Filename (if saving current QP):</label>
        <input type="text" id="unsavedFileNameInput" placeholder="e.g. Science_Midterm_Class10 (optional)" style="width:100%; margin-bottom:15px; padding:8px; font-size:15px;">
        <div style="display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap;">
            <button type="button" class="secondary" onclick="cancelUnsavedAction()">Cancel</button>
            <button type="button" class="danger" id="btnDiscardAndContinue" onclick="discardAndContinueAction()">🗑 Discard & Continue</button>
            <button type="button" class="success" id="btnSaveAndContinue" onclick="saveAndContinueAction()">💾 Save & Continue</button>
        </div>
    </div>
</div>

<div class="footer">
    <strong>Question Paper Generator</strong> <?= h(APP_VERSION) ?> &nbsp;|&nbsp; &copy; <?= date('Y') ?> <?= h(SCHOOL_NAME) ?>. All rights reserved.<br>
    <span style="color: #888; font-size: 12px; margin-top: 5px; display: inline-block;"><?= h(ADDRESS) ?>, <?= h(CITY_NAME) ?></span>
</div>

<script>
const basketCount = <?= count($basket) ?>;
const activeSelectedBank = <?= json_encode($selectedBank) ?>;
const csrfTokenValue = <?= json_encode($csrf) ?>;
const EXPANDED_SECTIONS_KEY = 'qpg_expanded_basket_sections';
let pendingUnsavedAction = null; // 'switch_bank' or 'load_paper'
let pendingUnsavedTarget = null;

// --- SINGLE-BUTTON XLSX IMPORT ---
function handleImportFileSelection(fileInput) {
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) return;
    const btn = document.getElementById('import-bank-btn');
    if (btn) {
        btn.innerText = '⏳ Importing XLSX...';
        btn.disabled = true;
    }
    clearExpandedSectionsMemory();
    fileInput.form.submit();
}

// --- COLLAPSIBLE BASKET SECTIONS (Collapsed by Default) ---
function getExpandedSectionsSet() {
    try {
        const raw = sessionStorage.getItem(EXPANDED_SECTIONS_KEY);
        const arr = raw ? JSON.parse(raw) : [];
        return new Set(Array.isArray(arr) ? arr : []);
    } catch (e) {
        return new Set();
    }
}

function saveExpandedSectionsSet(setObj) {
    try {
        sessionStorage.setItem(EXPANDED_SECTIONS_KEY, JSON.stringify(Array.from(setObj)));
    } catch (e) {}
}

function clearExpandedSectionsMemory() {
    try {
        sessionStorage.removeItem(EXPANDED_SECTIONS_KEY);
    } catch (e) {}
}

function setBasketSectionState(hash, expand, persist = true) {
    const rows = document.querySelectorAll('.sec-rows-' + hash);
    const icon = document.getElementById('sec-toggle-icon-' + hash);
    rows.forEach(r => {
        r.style.display = expand ? '' : 'none';
    });
    if (icon) {
        icon.innerText = expand ? '▾' : '▸';
    }
    if (persist) {
        const expandedSet = getExpandedSectionsSet();
        if (expand) {
            expandedSet.add(hash);
        } else {
            expandedSet.delete(hash);
        }
        saveExpandedSectionsSet(expandedSet);
    }
}

function toggleBasketSection(hash) {
    const icon = document.getElementById('sec-toggle-icon-' + hash);
    const isCurrentlyExpanded = icon && icon.innerText.trim() === '▾';
    setBasketSectionState(hash, !isCurrentlyExpanded, true);
}

function toggleAllBasketSections(expand) {
    const tbodies = document.querySelectorAll('.basket-section-tbody');
    tbodies.forEach(tb => {
        const hash = tb.getAttribute('data-section-hash');
        if (hash) {
            setBasketSectionState(hash, expand, true);
        }
    });
}

function applySavedCollapsibleStates() {
    const expandedSet = getExpandedSectionsSet();
    document.querySelectorAll('.basket-section-tbody').forEach(tb => {
        const hash = tb.getAttribute('data-section-hash');
        const forceExpand = tb.getAttribute('data-force-expand') === '1';
        if (!hash) return;
        if (forceExpand) {
            expandedSet.add(hash);
            saveExpandedSectionsSet(expandedSet);
            setBasketSectionState(hash, true, false);
        } else {
            // Default is collapsed unless explicitly expanded earlier in this session
            setBasketSectionState(hash, expandedSet.has(hash), false);
        }
    });
}

// --- INLINE SECTION HEADING & ORDER AUTO-SAVE ---
let sectionAutoSaveTimers = {};

function showSectionSavedBadge(hash) {
    const badge = document.getElementById('sec-save-status-' + hash);
    if (!badge) return;
    badge.classList.add('visible');
    clearTimeout(badge._hideTimer);
    badge._hideTimer = setTimeout(() => {
        badge.classList.remove('visible');
    }, 1800);
}

function autoSaveSectionConfig(hash, reloadLayoutOnOrderChange = false) {
    const formData = new FormData();
    formData.append('csrf', csrfTokenValue);
    formData.append('ajax_save_section_config', '1');

    document.querySelectorAll('.sec-order-input').forEach(inp => {
        formData.append(inp.name, inp.value);
    });
    document.querySelectorAll('.sec-heading-input').forEach(inp => {
        formData.append(inp.name, inp.value);
    });

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data && data.status === 'ok') {
            showSectionSavedBadge(hash);
            if (reloadLayoutOnOrderChange) {
                refreshBasketAndPreviewOrder();
            }
        }
    })
    .catch(() => {});
}

function refreshBasketAndPreviewOrder() {
    fetch(window.location.href)
        .then(r => r.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            const newBlueprint = doc.getElementById('basket-blueprint-container');
            const curBlueprint = document.getElementById('basket-blueprint-container');
            if (newBlueprint && curBlueprint) {
                curBlueprint.innerHTML = newBlueprint.innerHTML;
            }

            const newTable = doc.getElementById('selected-questions-table');
            const curTable = document.getElementById('selected-questions-table');
            if (newTable && curTable) {
                curTable.innerHTML = newTable.innerHTML;
                applySavedCollapsibleStates();
            }

            const newPreviewBody = doc.getElementById('doc_preview_questions_body');
            const curPreviewBody = document.getElementById('doc_preview_questions_body');
            if (newPreviewBody && curPreviewBody) {
                curPreviewBody.innerHTML = newPreviewBody.innerHTML;
            }
        })
        .catch(() => {});
}

// Keep browser URL bar in sync if a POST action (like loading a saved QP) changed the active bank
window.addEventListener('DOMContentLoaded', function() {
    if (basketCount === 0) {
        clearExpandedSectionsMemory();
    } else {
        applySavedCollapsibleStates();
    }

    const url = new URL(window.location.href);
    if (url.searchParams.has('bank') && url.searchParams.get('bank') !== activeSelectedBank) {
        if (activeSelectedBank === '') {
            url.searchParams.delete('bank');
        } else {
            url.searchParams.set('bank', activeSelectedBank);
        }
        url.searchParams.delete('category');
        url.searchParams.delete('marks');
        url.searchParams.delete('chapter');
        url.searchParams.delete('difficulty');
        url.searchParams.delete('search');
        url.searchParams.delete('page');
        url.searchParams.delete('keep_basket');
        window.history.replaceState({}, '', url);
    }
});

function handleBankChange(selectEl) {
    const currentBank = selectEl.getAttribute('data-current') || '';
    const newBank = selectEl.value;

    if (basketCount > 0 && newBank !== currentBank) {
        pendingUnsavedAction = 'switch_bank';
        pendingUnsavedTarget = newBank;
        const msgEl = document.getElementById('unsavedBasketMessage');
        if (msgEl) {
            msgEl.innerHTML = `You currently have <b>${basketCount} question(s)</b> in your basket. Loading a new Question Bank will clear the basket. Would you like to save your current Question Paper first?`;
        }
        document.getElementById('btnDiscardAndContinue').innerText = '🗑 Discard & Load QB';
        document.getElementById('btnSaveAndContinue').innerText = '💾 Save & Load QB';
        document.getElementById('unsavedBasketModal').style.display = 'flex';
    } else {
        clearExpandedSectionsMemory();
        selectEl.form.submit();
    }
}

function handleLoadPaperClick(event, formEl) {
    const paperPath = formEl.saved_paper_path ? formEl.saved_paper_path.value : '';
    if (!paperPath) {
        alert('Please select a paper first.');
        return false;
    }

    if (basketCount > 0) {
        event.preventDefault();
        pendingUnsavedAction = 'load_paper';
        pendingUnsavedTarget = paperPath;
        const msgEl = document.getElementById('unsavedBasketMessage');
        if (msgEl) {
            msgEl.innerHTML = `You currently have <b>${basketCount} question(s)</b> in your basket. Loading a saved Question Paper will replace your current basket and load its linked Question Bank. Would you like to save your current Question Paper first?`;
        }
        document.getElementById('btnDiscardAndContinue').innerText = '🗑 Discard & Load QP';
        document.getElementById('btnSaveAndContinue').innerText = '💾 Save & Load QP';
        document.getElementById('unsavedBasketModal').style.display = 'flex';
        return false;
    }

    clearExpandedSectionsMemory();
    return true;
}

function cancelUnsavedAction() {
    if (pendingUnsavedAction === 'switch_bank') {
        const selectEl = document.getElementById('bank-select');
        if (selectEl) {
            selectEl.value = selectEl.getAttribute('data-current') || '';
        }
    }
    pendingUnsavedAction = null;
    pendingUnsavedTarget = null;
    document.getElementById('unsavedBasketModal').style.display = 'none';
}

function discardAndContinueAction() {
    clearExpandedSectionsMemory();
    document.getElementById('unsavedBasketModal').style.display = 'none';
    if (pendingUnsavedAction === 'switch_bank') {
        document.getElementById('select-bank-form').submit();
    } else if (pendingUnsavedAction === 'load_paper') {
        const loadForm = document.getElementById('load-paper-form');
        const hiddenLoad = document.createElement('input');
        hiddenLoad.type = 'hidden';
        hiddenLoad.name = 'load_paper';
        hiddenLoad.value = '1';
        loadForm.appendChild(hiddenLoad);
        loadForm.submit();
    }
}

function saveAndContinueAction() {
    let rawName = document.getElementById('unsavedFileNameInput').value.trim();
    let safeName = rawName.replace(/[^A-Za-z0-9._ -]/g, '_').toLowerCase();

    if (safeName !== '') {
        if (existingFiles[currentDate] && existingFiles[currentDate].includes(safeName)) {
            if (!confirm(`A file named "${safeName}.json" already exists in today's folder. Do you want to overwrite it?`)) {
                return;
            }
        }
    }

    clearExpandedSectionsMemory();
    document.getElementById('hiddenCustomFilename').value = rawName;
    document.getElementById('hiddenAfterSaveAction').value = pendingUnsavedAction || '';
    document.getElementById('hiddenAfterSaveTarget').value = pendingUnsavedTarget ?? '';
    document.getElementById('unsavedBasketModal').style.display = 'none';

    const form = document.getElementById('export-form');
    const submitTrigger = document.createElement('input');
    submitTrigger.type = 'hidden';
    submitTrigger.name = 'save_json';
    submitTrigger.value = '1';
    form.appendChild(submitTrigger);
    form.submit();
}

function processSave() {
    let rawName = document.getElementById('customFileNameInput').value.trim();
    let safeName = rawName.replace(/[^A-Za-z0-9._ -]/g, '_').toLowerCase();
    
    if (safeName !== '') {
        if (existingFiles[currentDate] && existingFiles[currentDate].includes(safeName)) {
            if (!confirm(`A file named "${safeName}.json" already exists in today's folder. Do you want to overwrite it?`)) {
                return; 
            }
        }
    }
    
    document.getElementById('hiddenCustomFilename').value = rawName;
    document.getElementById('hiddenAfterSaveAction').value = '';
    document.getElementById('hiddenAfterSaveTarget').value = '';
    document.getElementById('saveModal').style.display = 'none';
    
    const form = document.getElementById('export-form');
    const submitTrigger = document.createElement('input');
    submitTrigger.type = 'hidden';
    submitTrigger.name = 'save_json';
    submitTrigger.value = '1';
    form.appendChild(submitTrigger);
    form.submit();
}

document.addEventListener("DOMContentLoaded", function() {

    // Administrator: teacher edit popup
    const teacherModal = document.getElementById('teacherEditModal');
    const teacherEditForm = document.getElementById('teacherEditForm');
    const teacherEditClose = document.getElementById('teacherEditClose');
    const teacherEditCancel = document.getElementById('teacherEditCancel');

    function closeTeacherEditModal() {
        if (!teacherModal) return;
        teacherModal.classList.remove('open');
        if (teacherEditForm) teacherEditForm.reset();
    }

    function openTeacherEditModal(button) {
        if (!teacherModal) return;
        document.getElementById('edit_teacher_id').value = button.dataset.id || '';
        document.getElementById('edit_display_name').value = button.dataset.name || '';
        document.getElementById('edit_username').value = button.dataset.username || '';
        document.getElementById('edit_subject').value = button.dataset.subject || '';
        document.getElementById('edit_password').value = '';
        teacherModal.classList.add('open');
        setTimeout(() => document.getElementById('edit_display_name').focus(), 50);
    }

    document.querySelectorAll('.teacher-edit-btn').forEach(btn => {
        btn.addEventListener('click', () => openTeacherEditModal(btn));
    });
    if (teacherEditClose) teacherEditClose.addEventListener('click', closeTeacherEditModal);
    if (teacherEditCancel) teacherEditCancel.addEventListener('click', closeTeacherEditModal);
    if (teacherModal) teacherModal.addEventListener('click', closeTeacherEditModal);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && teacherModal && teacherModal.classList.contains('open')) {
            closeTeacherEditModal();
        }
    });

    const inputSchool = document.getElementById('input_school_name');
    const inputTitle = document.getElementById('input_paper_title');
    const inputSubtitle = document.getElementById('input_paper_subtitle');
    
    const previewSchool = document.getElementById('preview_school_name');
    const previewTitle = document.getElementById('preview_paper_title');
    const previewSubtitle = document.getElementById('preview_paper_subtitle');

    if (inputSchool && previewSchool) {
        inputSchool.addEventListener('input', e => { previewSchool.innerText = e.target.value.toUpperCase(); });
    }
    if (inputTitle && previewTitle) {
        inputTitle.addEventListener('input', e => { previewTitle.innerText = e.target.value.toUpperCase(); });
    }
    if (inputSubtitle && previewSubtitle) {
        inputSubtitle.addEventListener('input', e => { previewSubtitle.innerText = e.target.value; });
    }
    
    // FEATURE 2: UI binding for Dynamic Font Update in Preview
    const inputFont = document.getElementById('input_doc_font');
    if (inputFont) {
        inputFont.addEventListener('change', e => {
            const previewBlock = document.getElementById('doc_preview_block');
            if (previewBlock) previewBlock.style.fontFamily = e.target.value + ', sans-serif';
        });
    }

    // Event delegation for inline Section Heading & Order editing + Auto-Save
    document.addEventListener('input', function(e) {
        if (e.target && e.target.classList.contains('sec-heading-input')) {
            const hash = e.target.getAttribute('data-hash');
            if (!hash) return;

            const previewEl = document.getElementById('preview_heading_' + hash);
            if (previewEl) {
                previewEl.innerText = e.target.value;
            }
            const bpTh = document.getElementById('blueprint_th_' + hash);
            if (bpTh) {
                bpTh.title = e.target.value;
            }

            clearTimeout(sectionAutoSaveTimers['heading_' + hash]);
            sectionAutoSaveTimers['heading_' + hash] = setTimeout(() => {
                autoSaveSectionConfig(hash, false);
            }, 350);
        }
    });

    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('sec-order-input')) {
            const hash = e.target.getAttribute('data-hash');
            if (!hash) return;
            autoSaveSectionConfig(hash, true);
        }

        if (e.target && e.target.id === "select-all-bank") {
            const checkboxes = document.querySelectorAll(".bank-checkbox");
            checkboxes.forEach(cb => cb.checked = e.target.checked);
        }
        if (e.target && e.target.id === "select-all-basket") {
            const checkboxes = document.querySelectorAll(".basket-checkbox, .section-select-all");
            checkboxes.forEach(cb => cb.checked = e.target.checked);
        }
        if (e.target && e.target.classList.contains("section-select-all")) {
            const hash = e.target.getAttribute('data-hash');
            if (hash) {
                document.querySelectorAll('.sec-cb-' + hash).forEach(cb => {
                    cb.checked = e.target.checked;
                });
            }
        }
    });

    // Prevent Enter key inside inline section inputs from accidentally triggering DOCX export
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target && (e.target.classList.contains('sec-heading-input') || e.target.classList.contains('sec-order-input'))) {
            e.preventDefault();
            e.target.blur();
        }
    });

    let searchTimeout = null;
    const searchInput = document.getElementById('search-input');
    const filterForm = document.getElementById('filter-form');

    if (searchInput && filterForm) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const url = new URL(window.location.href);
                const formData = new FormData(filterForm);
                
                for (const [key, value] of formData.entries()) {
                    url.searchParams.set(key, value);
                }
                url.searchParams.set('page', 1); 
                
                const table = document.getElementById('available-questions-table');
                if (table) table.style.opacity = '0.5'; 

                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');

                        const newStat = doc.getElementById('available-questions-stat');
                        const newTable = doc.getElementById('available-questions-table');
                        const newPagination = doc.getElementById('available-questions-pagination');

                        if (newStat && newTable && newPagination) {
                            document.getElementById('available-questions-stat').innerHTML = newStat.innerHTML;
                            document.getElementById('available-questions-table').innerHTML = newTable.innerHTML;
                            document.getElementById('available-questions-pagination').innerHTML = newPagination.innerHTML;
                        }
                        
                        if (table) table.style.opacity = '1';
                    });
            }, 350);
        });
    }

    const exportBtn = document.querySelector('button[name="export"]');
    if (exportBtn) {
        exportBtn.addEventListener('click', function(e) {
            const hasOpenEdits = document.querySelector('textarea[name="edited_question"]') || 
                                 document.querySelector('textarea[name="edited_bank_question"]');
            
            if (hasOpenEdits) {
                if (!confirm("⚠️ You have an unsaved edit currently open. If you generate the DOCX now, your recent edit will NOT be included. Do you want to proceed without saving?")) {
                    e.preventDefault(); 
                }
            }
        });
    }
});
</script>

<?php if ($editingBasketId !== null): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const targetRow = document.getElementById("basket-row-<?= h($editingBasketId) ?>");
    const textarea = document.getElementById("basket-edit-question-<?= h($editingBasketId) ?>");

    if (targetRow) {
        const tbody = targetRow.closest('.basket-section-tbody');
        if (tbody && tbody.dataset.sectionHash) {
            setBasketSectionState(tbody.dataset.sectionHash, true, true);
        }
        targetRow.classList.add("highlight-row");
        targetRow.scrollIntoView({ behavior: "auto", block: "center" });
    }

    if (textarea) {
        requestAnimationFrame(function() {
            textarea.focus({ preventScroll: true });
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        });
    }
});
</script>
<?php endif; ?>

<?php if ($scrollToPreview): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const previewSection = document.getElementById("preview-section");
    const previewContainer = document.getElementById("doc-preview-container");
    const printPreviewBtn = document.getElementById("print-preview-btn");

    if (previewSection) {
        previewSection.scrollIntoView({ behavior: "smooth", block: "start" });
    }
    if (printPreviewBtn) {
        printPreviewBtn.focus({ preventScroll: true });
    } else if (previewContainer) {
        previewContainer.focus({ preventScroll: true });
    }
});
</script>
<?php endif; ?>

<?php if ($justAddedId !== null && !$scrollToPreview): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const targetRow = document.getElementById("basket-row-<?= h($justAddedId) ?>");
    if (targetRow && targetRow.style.display !== 'none') {
        targetRow.scrollIntoView({ behavior: "smooth", block: "center" });
        targetRow.classList.add("highlight-row");

        const textarea = targetRow.querySelector('textarea');
        if (textarea) {
            textarea.focus();
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        }
    }
});
</script>
<?php endif; ?>

<?php if ($justEditedBankId !== null): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const targetBankRow = document.getElementById("bank-row-<?= h($justEditedBankId) ?>");
    if (targetBankRow) {
        targetBankRow.scrollIntoView({ behavior: "smooth", block: "center" });
        targetBankRow.classList.add("highlight-row");

        const textarea = targetBankRow.querySelector('textarea');
        if (textarea) {
            textarea.focus();
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        }
    }
});
</script>
<?php endif; ?>

<?php if ($scrollToBasket && $editingBasketId === null && !$scrollToPreview): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const basketSec = document.getElementById("basket-section");
    if (basketSec) {
        basketSec.scrollIntoView({ behavior: "smooth", block: "start" });
    }
});
</script>
<?php endif; ?>

</body>
</html>
