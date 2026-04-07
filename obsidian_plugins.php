<?php
// ============================================================
// OBSIDIAN PLUGINS MANAGER — Single-File PHP App
// DB: b10_39913602_obsidian_plugins | Host: sql105.byethost10.com
// ============================================================

define('DB_HOST', 'sql105.byethost10.com');
define('DB_USER', 'b10_39913602');
define('DB_PASS', 'Cr0ssfire');
define('DB_NAME', 'b10_39913602_obsidian_plugins');
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// ── Database Connection ──────────────────────────────────────
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'DB Connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── Auto-create table if not exists ─────────────────────────
function ensureTable() {
    $pdo = getDB();
    $pdo->exec("CREATE TABLE IF NOT EXISTS plugins (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        plugin_name  VARCHAR(255) NOT NULL,
        description  LONGTEXT,
        type         ENUM('Alpha','Beta','Community') DEFAULT 'Community',
        category     VARCHAR(255),
        version      VARCHAR(50),
        release_date VARCHAR(50),
        author       VARCHAR(255),
        readme_file  VARCHAR(500),
        manifest_file VARCHAR(500),
        mainjs_file  VARCHAR(500),
        styles_file  VARCHAR(500),
        needs_update TINYINT(1) DEFAULT 0,
        dev_notes    LONGTEXT,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ── Ensure upload directory exists ──────────────────────────
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

ensureTable();

// ── Handle file upload helper ────────────────────────────────
function handleFileUpload($field, $pluginName, $existingFile = '') {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingFile;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return $existingFile;
    }
    $allowed = ['readme_file' => ['md','txt','html'],
                'manifest_file' => ['json'],
                'mainjs_file'   => ['js'],
                'styles_file'   => ['css']];
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (isset($allowed[$field]) && !in_array($ext, $allowed[$field])) {
        return $existingFile;
    }
    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $pluginName);
    $filename = $safeName . '_' . $field . '_' . time() . '.' . $ext;
    $dest = UPLOAD_DIR . $filename;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
        // Delete old file
        if ($existingFile && file_exists(UPLOAD_DIR . $existingFile)) {
            unlink(UPLOAD_DIR . $existingFile);
        }
        return $filename;
    }
    return $existingFile;
}

// ── AJAX / Action Handler ────────────────────────────────────
$action = $_REQUEST['action'] ?? '';

if ($action === 'list') {
    $pdo   = getDB();
    $where = [];
    $params = [];

    if (!empty($_GET['search'])) {
        $where[] = "(plugin_name LIKE :s OR author LIKE :s2 OR category LIKE :s3 OR description LIKE :s4)";
        $params[':s']  = '%' . $_GET['search'] . '%';
        $params[':s2'] = '%' . $_GET['search'] . '%';
        $params[':s3'] = '%' . $_GET['search'] . '%';
        $params[':s4'] = '%' . $_GET['search'] . '%';
    }
    if (!empty($_GET['type_filter'])) {
        $where[] = "type = :type";
        $params[':type'] = $_GET['type_filter'];
    }
    if (isset($_GET['needs_update']) && $_GET['needs_update'] !== '') {
        $where[] = "needs_update = :nu";
        $params[':nu'] = (int)$_GET['needs_update'];
    }

    $sortCols = ['plugin_name','type','category','version','author','release_date','needs_update','created_at'];
    $sort = in_array($_GET['sort'] ?? '', $sortCols) ? $_GET['sort'] : 'plugin_name';
    $dir  = ($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    $sql = "SELECT * FROM plugins";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY $sort $dir";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Stats
    $total = count($rows);
    $needsUpdate = count(array_filter($rows, fn($r) => $r['needs_update']));
    $types = array_count_values(array_column($rows, 'type'));

    header('Content-Type: application/json');
    echo json_encode(['plugins' => $rows, 'stats' => [
        'total' => $total, 'needsUpdate' => $needsUpdate, 'types' => $types
    ]]);
    exit;
}

if ($action === 'get') {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM plugins WHERE id = :id");
    $stmt->execute([':id' => (int)$_GET['id']]);
    $row  = $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode($row ?: ['error' => 'Not found']);
    exit;
}

if ($action === 'save') {
    $pdo = getDB();
    $id  = (int)($_POST['id'] ?? 0);

    $existing = [];
    if ($id) {
        $s = $pdo->prepare("SELECT * FROM plugins WHERE id=:id");
        $s->execute([':id' => $id]);
        $existing = $s->fetch() ?: [];
    }

    $pluginName = trim($_POST['plugin_name'] ?? '');
    if (!$pluginName) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Plugin name is required']);
        exit;
    }

    $data = [
        'plugin_name'  => $pluginName,
        'description'  => $_POST['description'] ?? '',
        'type'         => in_array($_POST['type'] ?? '', ['Alpha','Beta','Community']) ? $_POST['type'] : 'Community',
        'category'     => $_POST['category'] ?? '',
        'version'      => $_POST['version'] ?? '',
        'release_date' => $_POST['release_date'] ?? '',
        'author'       => $_POST['author'] ?? '',
        'needs_update' => isset($_POST['needs_update']) ? 1 : 0,
        'dev_notes'    => $_POST['dev_notes'] ?? '',
        'readme_file'  => handleFileUpload('readme_file',  $pluginName, $existing['readme_file']  ?? ''),
        'manifest_file'=> handleFileUpload('manifest_file',$pluginName, $existing['manifest_file'] ?? ''),
        'mainjs_file'  => handleFileUpload('mainjs_file',  $pluginName, $existing['mainjs_file']   ?? ''),
        'styles_file'  => handleFileUpload('styles_file',  $pluginName, $existing['styles_file']   ?? ''),
    ];

    if ($id) {
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $stmt = $pdo->prepare("UPDATE plugins SET $sets WHERE id = :id");
        $data['id'] = $id;
    } else {
        $cols = implode(', ', array_keys($data));
        $vals = ':' . implode(', :', array_keys($data));
        $stmt = $pdo->prepare("INSERT INTO plugins ($cols) VALUES ($vals)");
    }

    $stmt->execute($data);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'id' => $id ?: $pdo->lastInsertId()]);
    exit;
}

if ($action === 'delete') {
    $pdo  = getDB();
    $id   = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM plugins WHERE id=:id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch();
    if ($row) {
        foreach (['readme_file','manifest_file','mainjs_file','styles_file'] as $f) {
            if ($row[$f] && file_exists(UPLOAD_DIR . $row[$f])) unlink(UPLOAD_DIR . $row[$f]);
        }
        $pdo->prepare("DELETE FROM plugins WHERE id=:id")->execute([':id' => $id]);
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'download') {
    $file = basename($_GET['file'] ?? '');
    $path = UPLOAD_DIR . $file;
    if ($file && file_exists($path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    http_response_code(404);
    exit('File not found');
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Obsidian Plugins Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── RESET & BASE ─────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:        #0d0f14;
    --bg2:       #13161e;
    --bg3:       #1a1e28;
    --border:    #252a38;
    --border2:   #2e3448;
    --text:      #e8eaf0;
    --text2:     #8891aa;
    --text3:     #5a6280;
    --accent:    #7c6af7;
    --accent2:   #a78bfa;
    --accent-glow: rgba(124,106,247,0.18);
    --green:     #34d399;
    --yellow:    #fbbf24;
    --red:       #f87171;
    --alpha:     #f87171;
    --beta:      #fbbf24;
    --community: #34d399;
    --radius:    10px;
    --radius-lg: 16px;
    --shadow:    0 4px 24px rgba(0,0,0,0.4);
    --shadow-lg: 0 8px 48px rgba(0,0,0,0.6);
    --mono:      'Space Mono', monospace;
    --sans:      'Syne', sans-serif;
    --transition: 0.18s cubic-bezier(0.4,0,0.2,1);
}

html { scroll-behavior: smooth; }
body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--sans);
    min-height: 100vh;
    overflow-x: hidden;
}

/* ── NOISE OVERLAY ───────────────────────────────────────── */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
    pointer-events: none;
    z-index: 0;
    opacity: 0.5;
}

/* ── LAYOUT ──────────────────────────────────────────────── */
.app { display: flex; min-height: 100vh; position: relative; z-index: 1; }

/* ── SIDEBAR ─────────────────────────────────────────────── */
.sidebar {
    width: 240px;
    flex-shrink: 0;
    background: var(--bg2);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 100;
    transition: transform var(--transition);
}

.sidebar-logo {
    padding: 28px 24px 20px;
    border-bottom: 1px solid var(--border);
}
.sidebar-logo .logo-icon {
    width: 40px; height: 40px;
    background: linear-gradient(135deg, var(--accent), #c084fc);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; margin-bottom: 10px;
    box-shadow: 0 0 20px var(--accent-glow);
}
.sidebar-logo h1 {
    font-size: 14px; font-weight: 700;
    color: var(--text); letter-spacing: 0.02em;
    line-height: 1.3;
}
.sidebar-logo span { color: var(--text3); font-size: 11px; font-family: var(--mono); }

.sidebar-nav { padding: 16px 12px; flex: 1; }
.nav-section-label {
    font-size: 10px; font-weight: 700; letter-spacing: 0.1em;
    color: var(--text3); text-transform: uppercase;
    padding: 0 12px 8px; margin-top: 8px;
}
.nav-btn {
    display: flex; align-items: center; gap: 10px;
    width: 100%; padding: 10px 12px;
    border-radius: 8px; border: none;
    background: transparent; color: var(--text2);
    font-family: var(--sans); font-size: 13px; font-weight: 600;
    cursor: pointer; text-align: left;
    transition: all var(--transition);
    position: relative;
}
.nav-btn .icon { font-size: 16px; width: 20px; text-align: center; }
.nav-btn:hover { background: var(--bg3); color: var(--text); }
.nav-btn.active {
    background: var(--accent-glow);
    color: var(--accent2);
    border: 1px solid rgba(124,106,247,0.2);
}
.nav-btn.active::before {
    content: '';
    position: absolute; left: 0; top: 50%; transform: translateY(-50%);
    width: 3px; height: 60%; background: var(--accent);
    border-radius: 0 2px 2px 0;
}
.nav-badge {
    margin-left: auto;
    background: var(--red);
    color: #fff; font-size: 10px; font-weight: 700;
    font-family: var(--mono);
    padding: 2px 6px; border-radius: 10px;
}

.sidebar-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    font-size: 11px; color: var(--text3);
    font-family: var(--mono);
}

/* ── MAIN ─────────────────────────────────────────────────── */
.main {
    margin-left: 240px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.topbar {
    background: var(--bg2);
    border-bottom: 1px solid var(--border);
    padding: 16px 32px;
    display: flex; align-items: center; gap: 16px;
    position: sticky; top: 0; z-index: 50;
}
.topbar h2 {
    font-size: 18px; font-weight: 800; color: var(--text);
    letter-spacing: -0.02em;
}
.topbar .breadcrumb { font-size: 12px; color: var(--text3); font-family: var(--mono); }
.topbar-actions { margin-left: auto; display: flex; gap: 10px; }

.content { padding: 32px; flex: 1; }

/* ── STAT CARDS ──────────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}
.stat-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 20px;
    position: relative;
    overflow: hidden;
    transition: border-color var(--transition), transform var(--transition);
}
.stat-card:hover { border-color: var(--border2); transform: translateY(-2px); }
.stat-card::after {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 2px;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}
.stat-card.total::after   { background: linear-gradient(90deg, var(--accent), #c084fc); }
.stat-card.update::after  { background: var(--red); }
.stat-card.alpha::after   { background: var(--alpha); }
.stat-card.beta::after    { background: var(--beta); }
.stat-card.community::after { background: var(--community); }
.stat-label { font-size: 11px; color: var(--text3); font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 8px; }
.stat-value { font-size: 32px; font-weight: 800; color: var(--text); line-height: 1; }
.stat-sub { font-size: 11px; color: var(--text3); margin-top: 4px; }

/* ── BUTTONS ─────────────────────────────────────────────── */
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; border-radius: 8px;
    font-family: var(--sans); font-size: 13px; font-weight: 700;
    border: none; cursor: pointer;
    transition: all var(--transition);
    white-space: nowrap;
}
.btn-primary {
    background: var(--accent);
    color: #fff;
    box-shadow: 0 0 20px var(--accent-glow);
}
.btn-primary:hover { background: var(--accent2); box-shadow: 0 0 30px rgba(124,106,247,0.35); transform: translateY(-1px); }
.btn-ghost {
    background: transparent;
    color: var(--text2);
    border: 1px solid var(--border2);
}
.btn-ghost:hover { background: var(--bg3); color: var(--text); }
.btn-danger { background: rgba(248,113,113,0.12); color: var(--red); border: 1px solid rgba(248,113,113,0.2); }
.btn-danger:hover { background: rgba(248,113,113,0.2); }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-icon { padding: 8px; border-radius: 8px; }

/* ── TOOLBAR ─────────────────────────────────────────────── */
.toolbar {
    display: flex; align-items: center; gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 14px 16px;
}
.search-wrap { position: relative; flex: 1; min-width: 200px; }
.search-wrap .search-icon {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: var(--text3); font-size: 14px; pointer-events: none;
}
.search-input {
    width: 100%; padding: 9px 12px 9px 36px;
    background: var(--bg3); border: 1px solid var(--border2);
    border-radius: 8px; color: var(--text);
    font-family: var(--sans); font-size: 13px;
    outline: none; transition: border-color var(--transition);
}
.search-input:focus { border-color: var(--accent); }
.search-input::placeholder { color: var(--text3); }

select.filter-select, select.sort-select {
    padding: 9px 32px 9px 12px;
    background: var(--bg3) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238891aa' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center;
    border: 1px solid var(--border2);
    border-radius: 8px; color: var(--text);
    font-family: var(--sans); font-size: 13px;
    outline: none; cursor: pointer;
    appearance: none;
    transition: border-color var(--transition);
}
select.filter-select:focus, select.sort-select:focus { border-color: var(--accent); }
select.filter-select option, select.sort-select option { background: var(--bg2); }

/* ── TABLE ───────────────────────────────────────────────── */
.table-wrap {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}
table { width: 100%; border-collapse: collapse; }
thead th {
    padding: 12px 16px;
    background: var(--bg3);
    font-size: 11px; font-weight: 700;
    color: var(--text3); text-transform: uppercase;
    letter-spacing: 0.08em;
    text-align: left;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
    cursor: pointer;
    user-select: none;
    transition: color var(--transition);
}
thead th:hover { color: var(--text); }
thead th.sort-active { color: var(--accent2); }
thead th .sort-arrow { margin-left: 4px; opacity: 0.5; }
thead th.sort-active .sort-arrow { opacity: 1; }

tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background var(--transition);
    cursor: pointer;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: var(--bg3); }
tbody td { padding: 14px 16px; font-size: 13px; color: var(--text2); vertical-align: middle; }
tbody td:first-child { color: var(--text); font-weight: 600; }

.type-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 700;
    font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.05em;
}
.type-badge.Alpha   { background: rgba(248,113,113,0.12); color: var(--alpha); border: 1px solid rgba(248,113,113,0.2); }
.type-badge.Beta    { background: rgba(251,191,36,0.12); color: var(--beta); border: 1px solid rgba(251,191,36,0.2); }
.type-badge.Community { background: rgba(52,211,153,0.12); color: var(--community); border: 1px solid rgba(52,211,153,0.2); }

.update-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; font-family: var(--mono);
}
.update-badge.yes { color: var(--red); }
.update-badge.no  { color: var(--text3); }

.file-link {
    color: var(--accent2); text-decoration: none;
    font-size: 11px; font-family: var(--mono);
    display: inline-flex; align-items: center; gap: 4px;
}
.file-link:hover { text-decoration: underline; }

.row-actions { display: flex; gap: 6px; }

.empty-state {
    text-align: center; padding: 60px 20px;
    color: var(--text3);
}
.empty-state .icon { font-size: 48px; margin-bottom: 16px; opacity: 0.4; }
.empty-state p { font-size: 14px; }

/* ── MODAL ───────────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(6px);
    z-index: 200;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
    opacity: 0; pointer-events: none;
    transition: opacity 0.2s;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }

.modal {
    background: var(--bg2);
    border: 1px solid var(--border2);
    border-radius: var(--radius-lg);
    width: 100%; max-width: 720px;
    max-height: 90vh;
    overflow: hidden;
    display: flex; flex-direction: column;
    box-shadow: var(--shadow-lg);
    transform: scale(0.95) translateY(10px);
    transition: transform 0.2s;
}
.modal-overlay.open .modal { transform: scale(1) translateY(0); }

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.modal-header h3 {
    font-size: 16px; font-weight: 800; color: var(--text);
}
.modal-close {
    background: transparent; border: none;
    color: var(--text3); font-size: 20px; cursor: pointer;
    width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
    border-radius: 6px; transition: all var(--transition);
}
.modal-close:hover { background: var(--bg3); color: var(--text); }

.modal-body { padding: 24px; overflow-y: auto; flex: 1; }
.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex; gap: 10px; justify-content: flex-end;
    flex-shrink: 0;
}

/* ── FORM ────────────────────────────────────────────────── */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-grid .full { grid-column: 1 / -1; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-label {
    font-size: 12px; font-weight: 700; color: var(--text2);
    letter-spacing: 0.04em;
}
.form-label .required { color: var(--red); margin-left: 3px; }
.form-input, .form-select, .form-textarea {
    padding: 10px 12px;
    background: var(--bg3); border: 1px solid var(--border2);
    border-radius: 8px; color: var(--text);
    font-family: var(--sans); font-size: 13px;
    outline: none; transition: border-color var(--transition);
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}
.form-textarea { resize: vertical; min-height: 80px; }
.form-select { appearance: none; cursor: pointer; }

.checkbox-group { display: flex; align-items: center; gap: 10px; padding: 10px 0; }
.checkbox-group input[type=checkbox] {
    width: 18px; height: 18px; accent-color: var(--accent); cursor: pointer;
}
.checkbox-group label { font-size: 13px; color: var(--text2); cursor: pointer; }

.file-upload-group { display: flex; flex-direction: column; gap: 6px; }
.file-upload-label { font-size: 12px; font-weight: 700; color: var(--text2); letter-spacing: 0.04em; }
.file-upload-wrap {
    border: 1px dashed var(--border2);
    border-radius: 8px;
    padding: 12px;
    background: var(--bg3);
    transition: border-color var(--transition);
}
.file-upload-wrap:hover { border-color: var(--accent); }
.file-upload-wrap input[type=file] {
    width: 100%; font-size: 12px; color: var(--text2);
    background: transparent; border: none; outline: none; cursor: pointer;
    font-family: var(--mono);
}
.file-upload-wrap input[type=file]::file-selector-button {
    background: var(--bg2); border: 1px solid var(--border2);
    color: var(--text2); border-radius: 6px;
    padding: 4px 10px; font-family: var(--sans); font-size: 12px;
    cursor: pointer; margin-right: 8px;
    transition: all var(--transition);
}
.file-upload-wrap input[type=file]::file-selector-button:hover {
    background: var(--accent); color: #fff; border-color: var(--accent);
}
.existing-file {
    margin-top: 6px; font-size: 11px; color: var(--accent2);
    font-family: var(--mono); display: flex; align-items: center; gap: 4px;
}

/* ── VIEW MODAL ──────────────────────────────────────────── */
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.detail-item { display: flex; flex-direction: column; gap: 4px; }
.detail-item.full { grid-column: 1 / -1; }
.detail-key { font-size: 11px; color: var(--text3); font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.08em; }
.detail-val { font-size: 13px; color: var(--text); line-height: 1.5; }
.detail-val.longtext {
    white-space: pre-wrap; background: var(--bg3);
    border: 1px solid var(--border); border-radius: 8px;
    padding: 10px 12px; font-size: 12px; font-family: var(--mono);
    max-height: 120px; overflow-y: auto;
}
.detail-divider { grid-column: 1/-1; height: 1px; background: var(--border); margin: 4px 0; }

/* ── TOAST ───────────────────────────────────────────────── */
.toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 999; display: flex; flex-direction: column; gap: 8px; }
.toast {
    background: var(--bg2); border: 1px solid var(--border2);
    border-radius: 10px; padding: 12px 16px;
    font-size: 13px; color: var(--text);
    box-shadow: var(--shadow);
    display: flex; align-items: center; gap: 10px;
    animation: toastIn 0.3s ease forwards;
    max-width: 300px;
}
.toast.success { border-color: rgba(52,211,153,0.3); }
.toast.error   { border-color: rgba(248,113,113,0.3); }
.toast .t-icon { font-size: 16px; }
@keyframes toastIn { from { opacity:0; transform: translateX(20px); } to { opacity:1; transform: translateX(0); } }
@keyframes toastOut { to { opacity:0; transform: translateX(20px); } }

/* ── LOADING ─────────────────────────────────────────────── */
.loading-overlay {
    position: fixed; inset: 0;
    background: var(--bg);
    z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 16px;
    transition: opacity 0.4s;
}
.loading-overlay.hidden { opacity: 0; pointer-events: none; }
.loading-spinner {
    width: 40px; height: 40px;
    border: 3px solid var(--border2);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── DELETE CONFIRM ──────────────────────────────────────── */
.confirm-modal {
    background: var(--bg2); border: 1px solid var(--border2);
    border-radius: var(--radius-lg); padding: 28px;
    max-width: 380px; width: 100%;
    box-shadow: var(--shadow-lg);
    transform: scale(0.95);
    transition: transform 0.2s;
}
.modal-overlay.open .confirm-modal { transform: scale(1); }
.confirm-modal h3 { font-size: 16px; font-weight: 800; margin-bottom: 10px; }
.confirm-modal p { font-size: 13px; color: var(--text2); line-height: 1.5; margin-bottom: 20px; }
.confirm-actions { display: flex; gap: 10px; justify-content: flex-end; }

/* ── SCROLLBAR ───────────────────────────────────────────── */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--bg2); }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--text3); }

/* ── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .main { margin-left: 0; }
    .form-grid { grid-template-columns: 1fr; }
    .detail-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
    <span style="font-family:var(--mono);font-size:12px;color:var(--text3)">Loading plugins...</span>
</div>

<!-- App Layout -->
<div class="app">

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">🔮</div>
            <h1>Obsidian Plugins</h1>
            <span>Manager v1.0</span>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section-label">Navigation</div>
            <button class="nav-btn active" id="navDashboard" onclick="showView('dashboard')">
                <span class="icon">⬡</span> Dashboard
            </button>
            <button class="nav-btn" id="navPlugins" onclick="showView('plugins')">
                <span class="icon">◈</span> All Plugins
            </button>
            <button class="nav-btn" id="navUpdates" onclick="showView('updates')">
                <span class="icon">⟳</span> Needs Update
                <span class="nav-badge" id="updateCount" style="display:none">0</span>
            </button>
            <div class="nav-section-label" style="margin-top:16px">Actions</div>
            <button class="nav-btn" onclick="openAddModal()">
                <span class="icon">+</span> Add Plugin
            </button>
        </nav>
        <div class="sidebar-footer">
            © 2025 Plugin Manager
        </div>
    </aside>

    <!-- Main -->
    <main class="main">

        <!-- Top Bar -->
        <div class="topbar">
            <div>
                <div class="breadcrumb" id="breadcrumb">Dashboard</div>
                <h2 id="pageTitle">Dashboard</h2>
            </div>
            <div class="topbar-actions">
                <button class="btn btn-primary" onclick="openAddModal()">
                    <span>+</span> Add Plugin
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="content">

            <!-- DASHBOARD VIEW -->
            <div id="viewDashboard">
                <div class="stats-grid" id="statsGrid">
                    <div class="stat-card total">
                        <div class="stat-label">Total Plugins</div>
                        <div class="stat-value" id="statTotal">—</div>
                        <div class="stat-sub">in database</div>
                    </div>
                    <div class="stat-card update">
                        <div class="stat-label">Needs Update</div>
                        <div class="stat-value" id="statUpdate">—</div>
                        <div class="stat-sub">flagged</div>
                    </div>
                    <div class="stat-card alpha">
                        <div class="stat-label">Alpha</div>
                        <div class="stat-value" id="statAlpha">—</div>
                        <div class="stat-sub">plugins</div>
                    </div>
                    <div class="stat-card beta">
                        <div class="stat-label">Beta</div>
                        <div class="stat-value" id="statBeta">—</div>
                        <div class="stat-sub">plugins</div>
                    </div>
                    <div class="stat-card community">
                        <div class="stat-label">Community</div>
                        <div class="stat-value" id="statCommunity">—</div>
                        <div class="stat-sub">plugins</div>
                    </div>
                </div>

                <!-- Recent plugins on dashboard -->
                <div style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="font-size:15px;font-weight:800;color:var(--text)">Recent Plugins</h3>
                    <button class="btn btn-ghost btn-sm" onclick="showView('plugins')">View All →</button>
                </div>
                <div class="table-wrap" id="dashboardTable">
                    <div class="empty-state"><div class="icon">🔮</div><p>Loading...</p></div>
                </div>
            </div>

            <!-- PLUGINS VIEW -->
            <div id="viewPlugins" style="display:none">
                <div class="toolbar">
                    <div class="search-wrap">
                        <span class="search-icon">⌕</span>
                        <input type="text" class="search-input" id="searchInput" placeholder="Search by name, author, category..." oninput="debounceLoad()">
                    </div>
                    <select class="filter-select" id="typeFilter" onchange="loadPlugins()">
                        <option value="">All Types</option>
                        <option value="Alpha">Alpha</option>
                        <option value="Beta">Beta</option>
                        <option value="Community">Community</option>
                    </select>
                    <select class="filter-select" id="updateFilter" onchange="loadPlugins()">
                        <option value="">All Status</option>
                        <option value="1">Needs Update</option>
                        <option value="0">Up to Date</option>
                    </select>
                    <select class="sort-select" id="sortCol" onchange="loadPlugins()">
                        <option value="plugin_name">Sort: Name</option>
                        <option value="type">Sort: Type</option>
                        <option value="category">Sort: Category</option>
                        <option value="author">Sort: Author</option>
                        <option value="version">Sort: Version</option>
                        <option value="release_date">Sort: Release Date</option>
                        <option value="needs_update">Sort: Needs Update</option>
                        <option value="created_at">Sort: Created</option>
                    </select>
                    <select class="sort-select" id="sortDir" onchange="loadPlugins()">
                        <option value="asc">↑ Asc</option>
                        <option value="desc">↓ Desc</option>
                    </select>
                </div>
                <div class="table-wrap" id="pluginsTable">
                    <div class="empty-state"><div class="icon">🔮</div><p>Loading...</p></div>
                </div>
            </div>

            <!-- UPDATES VIEW -->
            <div id="viewUpdates" style="display:none">
                <div style="margin-bottom:20px;">
                    <p style="font-size:13px;color:var(--text2)">Plugins flagged as needing an update.</p>
                </div>
                <div class="table-wrap" id="updatesTable">
                    <div class="empty-state"><div class="icon">✓</div><p>Loading...</p></div>
                </div>
            </div>

        </div><!-- /content -->
    </main>
</div>

<!-- ── ADD/EDIT MODAL ────────────────────────────────────── -->
<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="formModalTitle">Add Plugin</h3>
            <button class="modal-close" onclick="closeModal('formModal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="pluginForm" enctype="multipart/form-data">
                <input type="hidden" id="formId" name="id" value="0">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Plugin Name <span class="required">*</span></label>
                        <input type="text" class="form-input" id="f_plugin_name" name="plugin_name" placeholder="e.g. DataView Enhanced" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select class="form-select" id="f_type" name="type">
                            <option value="Alpha">Alpha</option>
                            <option value="Beta">Beta</option>
                            <option value="Community" selected>Community</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Description</label>
                        <textarea class="form-textarea" id="f_description" name="description" placeholder="Plugin description..." style="min-height:60px"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <input type="text" class="form-input" id="f_category" name="category" placeholder="e.g. Productivity">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Version</label>
                        <input type="text" class="form-input" id="f_version" name="version" placeholder="e.g. 1.2.0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Release Date</label>
                        <input type="text" class="form-input" id="f_release_date" name="release_date" placeholder="e.g. 2024-01-15">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Author</label>
                        <input type="text" class="form-input" id="f_author" name="author" placeholder="e.g. John Doe">
                    </div>

                    <div class="detail-divider full"></div>

                    <!-- File Uploads -->
                    <div class="form-group">
                        <label class="form-label">ReadMe File</label>
                        <div class="file-upload-wrap">
                            <input type="file" name="readme_file" id="f_readme_file" accept=".md,.txt,.html">
                        </div>
                        <div class="existing-file" id="ex_readme_file"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">manifest.json</label>
                        <div class="file-upload-wrap">
                            <input type="file" name="manifest_file" id="f_manifest_file" accept=".json">
                        </div>
                        <div class="existing-file" id="ex_manifest_file"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">main.js</label>
                        <div class="file-upload-wrap">
                            <input type="file" name="mainjs_file" id="f_mainjs_file" accept=".js">
                        </div>
                        <div class="existing-file" id="ex_mainjs_file"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">styles.css</label>
                        <div class="file-upload-wrap">
                            <input type="file" name="styles_file" id="f_styles_file" accept=".css">
                        </div>
                        <div class="existing-file" id="ex_styles_file"></div>
                    </div>

                    <div class="detail-divider full"></div>

                    <div class="form-group full">
                        <div class="checkbox-group">
                            <input type="checkbox" id="f_needs_update" name="needs_update">
                            <label for="f_needs_update">🔴 Needs Update — flag this plugin for a pending update</label>
                        </div>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Developer Notes</label>
                        <textarea class="form-textarea" id="f_dev_notes" name="dev_notes" placeholder="Internal notes, todos, observations..." style="min-height:80px"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('formModal')">Cancel</button>
            <button class="btn btn-primary" onclick="savePlugin()" id="saveBtn">Save Plugin</button>
        </div>
    </div>
</div>

<!-- ── VIEW MODAL ─────────────────────────────────────────── -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="viewModalTitle">Plugin Details</h3>
            <button class="modal-close" onclick="closeModal('viewModal')">✕</button>
        </div>
        <div class="modal-body" id="viewModalBody"></div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('viewModal')">Close</button>
            <button class="btn btn-primary" id="viewEditBtn" onclick="">Edit</button>
        </div>
    </div>
</div>

<!-- ── DELETE CONFIRM ─────────────────────────────────────── -->
<div class="modal-overlay" id="deleteModal">
    <div class="confirm-modal">
        <h3>Delete Plugin?</h3>
        <p>This will permanently delete <strong id="deletePluginName"></strong> and all associated files. This action cannot be undone.</p>
        <div class="confirm-actions">
            <button class="btn btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
            <button class="btn btn-danger" onclick="confirmDelete()" id="confirmDeleteBtn">Delete</button>
        </div>
    </div>
</div>

<!-- ── TOAST CONTAINER ────────────────────────────────────── -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ── State ────────────────────────────────────────────────────
let currentView = 'dashboard';
let deleteTargetId = null;
let debounceTimer = null;
let allStats = {};

// ── Init ─────────────────────────────────────────────────────
window.addEventListener('load', async () => {
    await loadStats();
    hideLoading();
});

function hideLoading() {
    const el = document.getElementById('loadingOverlay');
    el.classList.add('hidden');
    setTimeout(() => el.remove(), 400);
}

// ── View Switcher ────────────────────────────────────────────
function showView(v) {
    currentView = v;
    ['dashboard','plugins','updates'].forEach(id => {
        document.getElementById('view' + cap(id)).style.display = id === v ? '' : 'none';
        document.getElementById('nav' + cap(id)).classList.toggle('active', id === v);
    });
    const titles = { dashboard: 'Dashboard', plugins: 'All Plugins', updates: 'Needs Update' };
    const breadcrumbs = { dashboard: 'Home / Dashboard', plugins: 'Home / Plugins', updates: 'Home / Needs Update' };
    document.getElementById('pageTitle').textContent = titles[v];
    document.getElementById('breadcrumb').textContent = breadcrumbs[v];

    if (v === 'plugins') loadPlugins();
    if (v === 'updates') loadUpdates();
    if (v === 'dashboard') loadDashboard();
}

function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

// ── Load Stats ───────────────────────────────────────────────
async function loadStats() {
    const data = await apiFetch('?action=list');
    if (!data || data.error) return;
    allStats = data.stats;
    document.getElementById('statTotal').textContent = data.stats.total;
    document.getElementById('statUpdate').textContent = data.stats.needsUpdate;
    document.getElementById('statAlpha').textContent = data.stats.types['Alpha'] || 0;
    document.getElementById('statBeta').textContent = data.stats.types['Beta'] || 0;
    document.getElementById('statCommunity').textContent = data.stats.types['Community'] || 0;

    if (data.stats.needsUpdate > 0) {
        const badge = document.getElementById('updateCount');
        badge.textContent = data.stats.needsUpdate;
        badge.style.display = '';
    }

    // Recent 5 on dashboard
    const recent = data.plugins.slice(0, 5);
    document.getElementById('dashboardTable').innerHTML = renderTable(recent, true);
}

async function loadDashboard() { await loadStats(); }

// ── Load Plugins ─────────────────────────────────────────────
async function loadPlugins() {
    document.getElementById('pluginsTable').innerHTML = '<div class="empty-state"><div class="loading-spinner" style="margin:0 auto"></div></div>';
    const search = encodeURIComponent(document.getElementById('searchInput').value);
    const type   = document.getElementById('typeFilter').value;
    const upd    = document.getElementById('updateFilter').value;
    const sort   = document.getElementById('sortCol').value;
    const dir    = document.getElementById('sortDir').value;
    const data   = await apiFetch(`?action=list&search=${search}&type_filter=${type}&needs_update=${upd}&sort=${sort}&dir=${dir}`);
    if (!data || data.error) { document.getElementById('pluginsTable').innerHTML = '<div class="empty-state"><p>Error loading data.</p></div>'; return; }
    document.getElementById('pluginsTable').innerHTML = renderTable(data.plugins);
}

// ── Load Updates ─────────────────────────────────────────────
async function loadUpdates() {
    document.getElementById('updatesTable').innerHTML = '<div class="empty-state"><div class="loading-spinner" style="margin:0 auto"></div></div>';
    const data = await apiFetch('?action=list&needs_update=1&sort=plugin_name&dir=asc');
    if (!data || data.error) return;
    document.getElementById('updatesTable').innerHTML = renderTable(data.plugins);
}

// ── Render Table ─────────────────────────────────────────────
function renderTable(plugins, compact = false) {
    if (!plugins || !plugins.length) {
        return `<div class="empty-state"><div class="icon">◈</div><p>No plugins found.</p></div>`;
    }
    const files = (p) => {
        let f = [];
        if (p.readme_file)   f.push(`<a class="file-link" href="?action=download&file=${encodeURIComponent(p.readme_file)}" onclick="event.stopPropagation()">📄 README</a>`);
        if (p.manifest_file) f.push(`<a class="file-link" href="?action=download&file=${encodeURIComponent(p.manifest_file)}" onclick="event.stopPropagation()">📋 manifest</a>`);
        if (p.mainjs_file)   f.push(`<a class="file-link" href="?action=download&file=${encodeURIComponent(p.mainjs_file)}" onclick="event.stopPropagation()">⚙ main.js</a>`);
        if (p.styles_file)   f.push(`<a class="file-link" href="?action=download&file=${encodeURIComponent(p.styles_file)}" onclick="event.stopPropagation()">🎨 styles</a>`);
        return f.join(' ');
    };
    const rows = plugins.map(p => `
        <tr onclick="viewPlugin(${p.id})">
            <td>${esc(p.plugin_name)}</td>
            <td><span class="type-badge ${esc(p.type)}">${esc(p.type)}</span></td>
            <td>${esc(p.category||'—')}</td>
            ${!compact ? `<td>${esc(p.version||'—')}</td>` : ''}
            ${!compact ? `<td>${esc(p.author||'—')}</td>` : ''}
            ${!compact ? `<td>${esc(p.release_date||'—')}</td>` : ''}
            <td><span class="update-badge ${p.needs_update?'yes':'no'}">${p.needs_update?'🔴 Yes':'✓ No'}</span></td>
            ${!compact ? `<td>${files(p)||'—'}</td>` : ''}
            <td>
                <div class="row-actions" onclick="event.stopPropagation()">
                    <button class="btn btn-ghost btn-sm btn-icon" title="Edit" onclick="editPlugin(${p.id})">✎</button>
                    <button class="btn btn-danger btn-sm btn-icon" title="Delete" onclick="deletePlugin(${p.id},'${esc(p.plugin_name).replace(/'/g,"\\'")}')">🗑</button>
                </div>
            </td>
        </tr>`).join('');

    return `<table>
        <thead><tr>
            <th>Plugin Name</th><th>Type</th><th>Category</th>
            ${!compact ? '<th>Version</th><th>Author</th><th>Release Date</th>' : ''}
            <th>Update?</th>
            ${!compact ? '<th>Files</th>' : ''}
            <th>Actions</th>
        </tr></thead>
        <tbody>${rows}</tbody>
    </table>`;
}

// ── View Plugin ──────────────────────────────────────────────
async function viewPlugin(id) {
    const p = await apiFetch(`?action=get&id=${id}`);
    if (!p || p.error) return;
    document.getElementById('viewModalTitle').textContent = p.plugin_name;

    const fileLink = (f, label) => f
        ? `<a class="file-link" href="?action=download&file=${encodeURIComponent(f)}">${label} ↓</a>`
        : '<span style="color:var(--text3)">—</span>';

    document.getElementById('viewModalBody').innerHTML = `
        <div class="detail-grid">
            <div class="detail-item"><div class="detail-key">Plugin Name</div><div class="detail-val">${esc(p.plugin_name)}</div></div>
            <div class="detail-item"><div class="detail-key">Type</div><div class="detail-val"><span class="type-badge ${esc(p.type)}">${esc(p.type)}</span></div></div>
            <div class="detail-item"><div class="detail-key">Category</div><div class="detail-val">${esc(p.category||'—')}</div></div>
            <div class="detail-item"><div class="detail-key">Version</div><div class="detail-val">${esc(p.version||'—')}</div></div>
            <div class="detail-item"><div class="detail-key">Author</div><div class="detail-val">${esc(p.author||'—')}</div></div>
            <div class="detail-item"><div class="detail-key">Release Date</div><div class="detail-val">${esc(p.release_date||'—')}</div></div>
            <div class="detail-item"><div class="detail-key">Needs Update</div><div class="detail-val"><span class="update-badge ${p.needs_update?'yes':'no'}">${p.needs_update?'🔴 Yes — needs update':'✓ Up to date'}</span></div></div>
            <div class="detail-item"><div class="detail-key">Created</div><div class="detail-val">${esc(p.created_at||'—')}</div></div>
            <div class="detail-divider"></div>
            <div class="detail-item full"><div class="detail-key">Description</div><div class="detail-val longtext">${esc(p.description||'—')}</div></div>
            <div class="detail-item full"><div class="detail-key">Developer Notes</div><div class="detail-val longtext">${esc(p.dev_notes||'—')}</div></div>
            <div class="detail-divider"></div>
            <div class="detail-item"><div class="detail-key">ReadMe</div><div class="detail-val">${fileLink(p.readme_file,'📄 Download README')}</div></div>
            <div class="detail-item"><div class="detail-key">manifest.json</div><div class="detail-val">${fileLink(p.manifest_file,'📋 Download manifest')}</div></div>
            <div class="detail-item"><div class="detail-key">main.js</div><div class="detail-val">${fileLink(p.mainjs_file,'⚙ Download main.js')}</div></div>
            <div class="detail-item"><div class="detail-key">styles.css</div><div class="detail-val">${fileLink(p.styles_file,'🎨 Download styles.css')}</div></div>
        </div>`;

    document.getElementById('viewEditBtn').onclick = () => { closeModal('viewModal'); editPlugin(id); };
    openModal('viewModal');
}

// ── Add Modal ─────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('formModalTitle').textContent = 'Add Plugin';
    document.getElementById('pluginForm').reset();
    document.getElementById('formId').value = 0;
    ['readme_file','manifest_file','mainjs_file','styles_file'].forEach(f => {
        document.getElementById('ex_'+f).innerHTML = '';
    });
    openModal('formModal');
}

// ── Edit Modal ────────────────────────────────────────────────
async function editPlugin(id) {
    const p = await apiFetch(`?action=get&id=${id}`);
    if (!p || p.error) return;
    document.getElementById('formModalTitle').textContent = 'Edit Plugin';
    document.getElementById('formId').value = p.id;
    document.getElementById('f_plugin_name').value  = p.plugin_name  || '';
    document.getElementById('f_description').value  = p.description  || '';
    document.getElementById('f_type').value          = p.type         || 'Community';
    document.getElementById('f_category').value      = p.category     || '';
    document.getElementById('f_version').value       = p.version      || '';
    document.getElementById('f_release_date').value  = p.release_date || '';
    document.getElementById('f_author').value        = p.author       || '';
    document.getElementById('f_needs_update').checked = !!parseInt(p.needs_update);
    document.getElementById('f_dev_notes').value     = p.dev_notes    || '';

    const files = { readme_file: p.readme_file, manifest_file: p.manifest_file, mainjs_file: p.mainjs_file, styles_file: p.styles_file };
    for (const [k, v] of Object.entries(files)) {
        const el = document.getElementById('ex_'+k);
        el.innerHTML = v ? `📎 Current: <a href="?action=download&file=${encodeURIComponent(v)}" target="_blank">${esc(v)}</a>` : '';
    }
    openModal('formModal');
}

// ── Save Plugin ───────────────────────────────────────────────
async function savePlugin() {
    const btn = document.getElementById('saveBtn');
    const name = document.getElementById('f_plugin_name').value.trim();
    if (!name) { showToast('Plugin name is required.', 'error'); return; }

    btn.disabled = true;
    btn.textContent = 'Saving...';

    const formData = new FormData(document.getElementById('pluginForm'));
    formData.set('action', 'save');

    try {
        const res = await fetch(window.location.pathname, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            closeModal('formModal');
            showToast('Plugin saved successfully!', 'success');
            loadStats();
            if (currentView === 'plugins') loadPlugins();
            if (currentView === 'updates') loadUpdates();
        } else {
            showToast(data.error || 'Save failed.', 'error');
        }
    } catch(e) {
        showToast('Network error: ' + e.message, 'error');
    }
    btn.disabled = false;
    btn.textContent = 'Save Plugin';
}

// ── Delete Plugin ─────────────────────────────────────────────
function deletePlugin(id, name) {
    deleteTargetId = id;
    document.getElementById('deletePluginName').textContent = name;
    openModal('deleteModal');
}

async function confirmDelete() {
    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true; btn.textContent = 'Deleting...';
    const fd = new FormData();
    fd.set('action', 'delete'); fd.set('id', deleteTargetId);
    const data = await apiFetch('', { method:'POST', body: fd });
    if (data && data.success) {
        closeModal('deleteModal');
        showToast('Plugin deleted.', 'success');
        loadStats();
        if (currentView === 'plugins') loadPlugins();
        if (currentView === 'updates') loadUpdates();
    } else {
        showToast('Delete failed.', 'error');
    }
    btn.disabled = false; btn.textContent = 'Delete';
}

// ── Modals ────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) closeModal(el.id); });
});

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const icons = { success: '✓', error: '✗' };
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span class="t-icon">${icons[type]||'ℹ'}</span> ${msg}`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.animation = 'toastOut 0.3s forwards'; setTimeout(() => t.remove(), 300); }, 3000);
}

// ── Debounce ──────────────────────────────────────────────────
function debounceLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadPlugins, 300);
}

// ── Fetch Helper ──────────────────────────────────────────────
async function apiFetch(url, opts = {}) {
    try {
        const res = await fetch(window.location.pathname + url, opts);
        return await res.json();
    } catch(e) { return null; }
}

// ── Escape HTML ───────────────────────────────────────────────
function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ── Keyboard ──────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        ['formModal','viewModal','deleteModal'].forEach(closeModal);
    }
});
</script>
</body>
</html>
