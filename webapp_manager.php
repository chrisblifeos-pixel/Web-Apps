<?php
// ============================================================
// WEB APPS MANAGER — Single-File PHP App
// DB: b10_39913602_webapps | Host: sql105.byethost10.com
// ============================================================

define('DB_HOST', 'sql105.byethost10.com');
define('DB_USER', 'b10_39913602');
define('DB_PASS', 'Cr0ssfire');
define('DB_NAME', 'b10_39913602_webapps');
define('UPLOAD_DIR', __DIR__ . '/webapp_uploads/');

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
    $pdo->exec("CREATE TABLE IF NOT EXISTS webapps (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        app_name      VARCHAR(255) NOT NULL,
        description   LONGTEXT,
        type          ENUM('Alpha','Beta','Community') DEFAULT 'Beta',
        category      VARCHAR(255),
        version       VARCHAR(50),
        release_date  VARCHAR(50),
        author        VARCHAR(255),
        mainapp_file  VARCHAR(500),
        dbconfig_file VARCHAR(500),
        api_file      VARCHAR(500),
        readme_file   VARCHAR(500),
        needs_update  TINYINT(1) DEFAULT 0,
        dev_notes     LONGTEXT,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ── Ensure upload directory exists ──────────────────────────
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

ensureTable();

// ── Handle file upload helper (no extension restriction) ─────
function handleFileUpload($field, $existingFile = '') {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingFile;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return $existingFile;
    }
    $origName = basename($_FILES[$field]['name']);
    $safeName = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $origName);
    $filename = time() . '_' . $safeName;
    $dest = UPLOAD_DIR . $filename;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
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
    $pdo    = getDB();
    $where  = [];
    $params = [];

    if (!empty($_GET['search'])) {
        $where[] = "(app_name LIKE :s OR author LIKE :s2 OR category LIKE :s3 OR description LIKE :s4)";
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

    $sortCols = ['app_name','type','category','version','author','release_date','needs_update','created_at'];
    $sort = in_array($_GET['sort'] ?? '', $sortCols) ? $_GET['sort'] : 'app_name';
    $dir  = ($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    $sql = "SELECT * FROM webapps";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY $sort $dir";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $total       = count($rows);
    $needsUpdate = count(array_filter($rows, fn($r) => $r['needs_update']));
    $types       = array_count_values(array_column($rows, 'type'));

    header('Content-Type: application/json');
    echo json_encode(['apps' => $rows, 'stats' => [
        'total' => $total, 'needsUpdate' => $needsUpdate, 'types' => $types
    ]]);
    exit;
}

if ($action === 'get') {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM webapps WHERE id = :id");
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
        $s = $pdo->prepare("SELECT * FROM webapps WHERE id=:id");
        $s->execute([':id' => $id]);
        $existing = $s->fetch() ?: [];
    }

    $appName = trim($_POST['app_name'] ?? '');
    if (!$appName) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'App name is required']);
        exit;
    }

    $data = [
        'app_name'     => $appName,
        'description'  => $_POST['description']  ?? '',
        'type'         => in_array($_POST['type'] ?? '', ['Alpha','Beta','Community']) ? $_POST['type'] : 'Beta',
        'category'     => $_POST['category']      ?? '',
        'version'      => $_POST['version']       ?? '',
        'release_date' => $_POST['release_date']  ?? '',
        'author'       => $_POST['author']         ?? '',
        'needs_update' => isset($_POST['needs_update']) ? 1 : 0,
        'dev_notes'    => $_POST['dev_notes']      ?? '',
        'mainapp_file'  => handleFileUpload('mainapp_file',  $existing['mainapp_file']  ?? ''),
        'dbconfig_file' => handleFileUpload('dbconfig_file', $existing['dbconfig_file'] ?? ''),
        'api_file'      => handleFileUpload('api_file',      $existing['api_file']      ?? ''),
        'readme_file'   => handleFileUpload('readme_file',   $existing['readme_file']   ?? ''),
    ];

    if ($id) {
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $stmt = $pdo->prepare("UPDATE webapps SET $sets WHERE id = :id");
        $data['id'] = $id;
    } else {
        $cols = implode(', ', array_keys($data));
        $vals = ':' . implode(', :', array_keys($data));
        $stmt = $pdo->prepare("INSERT INTO webapps ($cols) VALUES ($vals)");
    }

    $stmt->execute($data);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'id' => $id ?: $pdo->lastInsertId()]);
    exit;
}

if ($action === 'delete') {
    $pdo  = getDB();
    $id   = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM webapps WHERE id=:id");
    $stmt->execute([':id' => $id]);
    $row  = $stmt->fetch();
    if ($row) {
        foreach (['mainapp_file','dbconfig_file','api_file','readme_file'] as $f) {
            if ($row[$f] && file_exists(UPLOAD_DIR . $row[$f])) unlink(UPLOAD_DIR . $row[$f]);
        }
        $pdo->prepare("DELETE FROM webapps WHERE id=:id")->execute([':id' => $id]);
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
<title>Web Apps Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:ital,wght@0,300;0,400;0,500;1,400&family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── RESET & BASE ─────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:          #f0f2f7;
    --bg2:         #ffffff;
    --bg3:         #f7f8fc;
    --border:      #e2e6f0;
    --border2:     #d0d6e8;
    --text:        #1a1e2e;
    --text2:       #4a5270;
    --text3:       #8892b0;
    --accent:      #2563eb;
    --accent-lt:   #eff4ff;
    --accent2:     #1d4ed8;
    --accent-glow: rgba(37,99,235,0.12);
    --green:       #059669;
    --green-lt:    #ecfdf5;
    --yellow:      #d97706;
    --yellow-lt:   #fffbeb;
    --red:         #dc2626;
    --red-lt:      #fef2f2;
    --alpha:       #dc2626;
    --alpha-lt:    #fef2f2;
    --beta:        #d97706;
    --beta-lt:     #fffbeb;
    --community:   #059669;
    --community-lt:#ecfdf5;
    --radius:      10px;
    --radius-lg:   14px;
    --shadow:      0 1px 8px rgba(30,40,80,0.08), 0 2px 24px rgba(30,40,80,0.05);
    --shadow-lg:   0 8px 48px rgba(30,40,80,0.14);
    --mono:        'DM Mono', monospace;
    --sans:        'DM Sans', sans-serif;
    --transition:  0.16s cubic-bezier(0.4,0,0.2,1);
}

html { scroll-behavior: smooth; }
body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--sans);
    min-height: 100vh;
    overflow-x: hidden;
}

/* ── LAYOUT ──────────────────────────────────────────────── */
.app { display: flex; min-height: 100vh; }

/* ── SIDEBAR ─────────────────────────────────────────────── */
.sidebar {
    width: 248px;
    flex-shrink: 0;
    background: var(--text);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 100;
}

.sidebar-logo {
    padding: 28px 24px 22px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.sidebar-logo .logo-row {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 4px;
}
.sidebar-logo .logo-icon {
    width: 38px; height: 38px;
    background: var(--accent);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    box-shadow: 0 0 20px rgba(37,99,235,0.35);
    flex-shrink: 0;
}
.sidebar-logo h1 {
    font-size: 15px; font-weight: 700;
    color: #fff; letter-spacing: -0.01em;
    line-height: 1.2;
}
.sidebar-logo span {
    color: rgba(255,255,255,0.35);
    font-size: 11px; font-family: var(--mono);
    padding-left: 50px;
}

.sidebar-nav { padding: 16px 12px; flex: 1; }
.nav-section-label {
    font-size: 10px; font-weight: 600; letter-spacing: 0.1em;
    color: rgba(255,255,255,0.3); text-transform: uppercase;
    padding: 0 12px 8px; margin-top: 8px;
}
.nav-btn {
    display: flex; align-items: center; gap: 10px;
    width: 100%; padding: 10px 12px;
    border-radius: 8px; border: none;
    background: transparent; color: rgba(255,255,255,0.55);
    font-family: var(--sans); font-size: 13px; font-weight: 600;
    cursor: pointer; text-align: left;
    transition: all var(--transition);
    position: relative;
}
.nav-btn .icon { font-size: 15px; width: 20px; text-align: center; opacity: 0.8; }
.nav-btn:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.85); }
.nav-btn.active {
    background: var(--accent);
    color: #fff;
    box-shadow: 0 2px 12px rgba(37,99,235,0.4);
}
.nav-btn.active .icon { opacity: 1; }
.nav-badge {
    margin-left: auto;
    background: var(--red);
    color: #fff; font-size: 10px; font-weight: 700;
    font-family: var(--mono);
    padding: 2px 7px; border-radius: 20px;
}

.sidebar-footer {
    padding: 16px 24px;
    border-top: 1px solid rgba(255,255,255,0.08);
    font-size: 11px; color: rgba(255,255,255,0.25);
    font-family: var(--mono);
}

/* ── MAIN ─────────────────────────────────────────────────── */
.main {
    margin-left: 248px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.topbar {
    background: var(--bg2);
    border-bottom: 1px solid var(--border);
    padding: 0 32px;
    height: 64px;
    display: flex; align-items: center; gap: 16px;
    position: sticky; top: 0; z-index: 50;
    box-shadow: 0 1px 0 var(--border);
}
.topbar-left { display: flex; flex-direction: column; gap: 2px; }
.topbar h2 { font-size: 17px; font-weight: 800; color: var(--text); letter-spacing: -0.02em; }
.topbar .breadcrumb { font-size: 11px; color: var(--text3); font-family: var(--mono); }
.topbar-actions { margin-left: auto; display: flex; gap: 10px; }

.content { padding: 32px; flex: 1; }

/* ── STAT CARDS ──────────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 14px;
    margin-bottom: 32px;
}
.stat-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 18px 20px;
    position: relative;
    overflow: hidden;
    transition: box-shadow var(--transition), transform var(--transition);
}
.stat-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
.stat-card-icon {
    font-size: 22px; margin-bottom: 10px; display: block;
}
.stat-label { font-size: 11px; color: var(--text3); font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 4px; }
.stat-value { font-size: 30px; font-weight: 800; color: var(--text); line-height: 1; }
.stat-sub { font-size: 11px; color: var(--text3); margin-top: 3px; }

.stat-card.total   { border-top: 3px solid var(--accent); }
.stat-card.update  { border-top: 3px solid var(--red); }
.stat-card.alpha   { border-top: 3px solid var(--alpha); }
.stat-card.beta    { border-top: 3px solid var(--beta); }
.stat-card.community { border-top: 3px solid var(--community); }

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
    box-shadow: 0 2px 8px rgba(37,99,235,0.25);
}
.btn-primary:hover { background: var(--accent2); box-shadow: 0 4px 16px rgba(37,99,235,0.35); transform: translateY(-1px); }
.btn-ghost {
    background: transparent;
    color: var(--text2);
    border: 1px solid var(--border2);
}
.btn-ghost:hover { background: var(--bg3); color: var(--text); }
.btn-danger { background: var(--red-lt); color: var(--red); border: 1px solid #fecaca; }
.btn-danger:hover { background: #fecaca; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-icon { padding: 7px; border-radius: 8px; }

/* ── TOOLBAR ─────────────────────────────────────────────── */
.toolbar {
    display: flex; align-items: center; gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 12px 16px;
    box-shadow: var(--shadow);
}
.search-wrap { position: relative; flex: 1; min-width: 200px; }
.search-wrap .search-icon {
    position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
    color: var(--text3); font-size: 14px; pointer-events: none;
}
.search-input {
    width: 100%; padding: 9px 12px 9px 34px;
    background: var(--bg3); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text);
    font-family: var(--sans); font-size: 13px;
    outline: none; transition: border-color var(--transition), box-shadow var(--transition);
}
.search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
.search-input::placeholder { color: var(--text3); }

select.filter-select, select.sort-select {
    padding: 9px 30px 9px 12px;
    background: var(--bg3) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238892b0' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center;
    border: 1px solid var(--border);
    border-radius: 8px; color: var(--text);
    font-family: var(--sans); font-size: 13px;
    outline: none; cursor: pointer; appearance: none;
    transition: border-color var(--transition);
}
select.filter-select:focus, select.sort-select:focus { border-color: var(--accent); }

/* ── TABLE ───────────────────────────────────────────────── */
.table-wrap {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow);
}
table { width: 100%; border-collapse: collapse; }
thead th {
    padding: 11px 16px;
    background: var(--bg3);
    font-size: 11px; font-weight: 700;
    color: var(--text3); text-transform: uppercase;
    letter-spacing: 0.08em; text-align: left;
    border-bottom: 1px solid var(--border);
    white-space: nowrap; cursor: pointer; user-select: none;
    transition: color var(--transition);
}
thead th:hover { color: var(--text2); }
thead th.sort-active { color: var(--accent); }
thead th .sort-arrow { margin-left: 4px; opacity: 0.5; }
thead th.sort-active .sort-arrow { opacity: 1; }

tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background var(--transition);
    cursor: pointer;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: var(--accent-lt); }
tbody td { padding: 13px 16px; font-size: 13px; color: var(--text2); vertical-align: middle; }
tbody td:first-child { color: var(--text); font-weight: 600; }

.type-badge {
    display: inline-flex; align-items: center;
    padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 700;
    font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.05em;
}
.type-badge.Alpha     { background: var(--alpha-lt);     color: var(--alpha);     border: 1px solid #fecaca; }
.type-badge.Beta      { background: var(--beta-lt);      color: var(--beta);      border: 1px solid #fde68a; }
.type-badge.Community { background: var(--community-lt); color: var(--community); border: 1px solid #a7f3d0; }

.update-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-family: var(--mono); }
.update-badge.yes { color: var(--red); }
.update-badge.no  { color: var(--green); }

.file-link {
    color: var(--accent); text-decoration: none;
    font-size: 11px; font-family: var(--mono);
    display: inline-flex; align-items: center; gap: 3px;
    padding: 2px 7px; border-radius: 5px;
    background: var(--accent-lt);
    border: 1px solid rgba(37,99,235,0.15);
    white-space: nowrap;
    transition: background var(--transition);
}
.file-link:hover { background: #dbeafe; }

.row-actions { display: flex; gap: 6px; }

.empty-state {
    text-align: center; padding: 60px 20px;
    color: var(--text3);
}
.empty-state .icon { font-size: 44px; margin-bottom: 14px; opacity: 0.35; }
.empty-state p { font-size: 14px; font-weight: 500; }

/* ── MODAL ───────────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(15,20,40,0.45);
    backdrop-filter: blur(5px);
    z-index: 200;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
    opacity: 0; pointer-events: none;
    transition: opacity 0.2s;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }

.modal {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    width: 100%; max-width: 720px;
    max-height: 90vh;
    overflow: hidden;
    display: flex; flex-direction: column;
    box-shadow: var(--shadow-lg);
    transform: scale(0.96) translateY(8px);
    transition: transform 0.2s;
}
.modal-overlay.open .modal { transform: scale(1) translateY(0); }

.modal-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
    background: var(--bg3);
}
.modal-header h3 { font-size: 15px; font-weight: 800; color: var(--text); }
.modal-close {
    background: transparent; border: none;
    color: var(--text3); font-size: 18px; cursor: pointer;
    width: 30px; height: 30px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 6px; transition: all var(--transition);
}
.modal-close:hover { background: var(--border); color: var(--text); }

.modal-body { padding: 24px; overflow-y: auto; flex: 1; }
.modal-footer {
    padding: 14px 24px;
    border-top: 1px solid var(--border);
    display: flex; gap: 10px; justify-content: flex-end;
    flex-shrink: 0; background: var(--bg3);
}

/* ── FORM ────────────────────────────────────────────────── */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-grid .full { grid-column: 1 / -1; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 12px; font-weight: 700; color: var(--text2); letter-spacing: 0.03em; }
.form-label .required { color: var(--red); margin-left: 3px; }
.form-input, .form-select, .form-textarea {
    padding: 9px 12px;
    background: var(--bg3); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text);
    font-family: var(--sans); font-size: 13px;
    outline: none; transition: border-color var(--transition), box-shadow var(--transition);
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}
.form-textarea { resize: vertical; min-height: 80px; }
.form-select { appearance: none; cursor: pointer; }

.checkbox-group { display: flex; align-items: center; gap: 10px; padding: 8px 0; }
.checkbox-group input[type=checkbox] { width: 16px; height: 16px; accent-color: var(--accent); cursor: pointer; }
.checkbox-group label { font-size: 13px; color: var(--text2); cursor: pointer; }

.detail-divider { grid-column: 1/-1; height: 1px; background: var(--border); margin: 4px 0; }

/* ── FILE UPLOAD ─────────────────────────────────────────── */
.file-upload-wrap {
    border: 1.5px dashed var(--border2);
    border-radius: 8px; padding: 10px 12px;
    background: var(--bg3);
    transition: border-color var(--transition);
}
.file-upload-wrap:hover { border-color: var(--accent); }
.file-upload-wrap input[type=file] {
    width: 100%; font-size: 12px; color: var(--text2);
    background: transparent; border: none; outline: none;
    cursor: pointer; font-family: var(--mono);
}
.file-upload-wrap input[type=file]::file-selector-button {
    background: var(--bg2); border: 1px solid var(--border2);
    color: var(--text2); border-radius: 6px;
    padding: 4px 10px; font-family: var(--sans);
    font-size: 12px; font-weight: 600;
    cursor: pointer; margin-right: 8px;
    transition: all var(--transition);
}
.file-upload-wrap input[type=file]::file-selector-button:hover {
    background: var(--accent); color: #fff; border-color: var(--accent);
}
.existing-file {
    margin-top: 6px; font-size: 11px; color: var(--accent);
    font-family: var(--mono); display: flex; align-items: center; gap: 4px;
}
.existing-file a { color: var(--accent); }

/* ── VIEW MODAL ──────────────────────────────────────────── */
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.detail-item { display: flex; flex-direction: column; gap: 4px; }
.detail-item.full { grid-column: 1 / -1; }
.detail-key { font-size: 10px; color: var(--text3); font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.09em; }
.detail-val { font-size: 13px; color: var(--text); line-height: 1.5; }
.detail-val.longtext {
    white-space: pre-wrap; background: var(--bg3);
    border: 1px solid var(--border); border-radius: 8px;
    padding: 10px 12px; font-size: 12px; font-family: var(--mono);
    max-height: 120px; overflow-y: auto; color: var(--text2);
}
.files-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.file-card {
    background: var(--bg3); border: 1px solid var(--border);
    border-radius: 8px; padding: 10px 12px;
    display: flex; align-items: center; gap: 8px;
}
.file-card-icon { font-size: 18px; flex-shrink: 0; }
.file-card-info { flex: 1; min-width: 0; }
.file-card-label { font-size: 11px; color: var(--text3); font-family: var(--mono); }
.file-card-link { font-size: 12px; color: var(--accent); font-weight: 600; text-decoration: none; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.file-card-link:hover { text-decoration: underline; }
.file-card-none { font-size: 12px; color: var(--text3); }

/* ── DELETE CONFIRM ──────────────────────────────────────── */
.confirm-modal {
    background: var(--bg2); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 28px;
    max-width: 380px; width: 100%;
    box-shadow: var(--shadow-lg);
    transform: scale(0.96);
    transition: transform 0.2s;
}
.modal-overlay.open .confirm-modal { transform: scale(1); }
.confirm-modal .warn-icon { font-size: 32px; margin-bottom: 12px; }
.confirm-modal h3 { font-size: 16px; font-weight: 800; margin-bottom: 8px; }
.confirm-modal p { font-size: 13px; color: var(--text2); line-height: 1.6; margin-bottom: 20px; }
.confirm-actions { display: flex; gap: 10px; justify-content: flex-end; }

/* ── TOAST ───────────────────────────────────────────────── */
.toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 999; display: flex; flex-direction: column; gap: 8px; }
.toast {
    background: var(--text); border-radius: 10px;
    padding: 12px 16px; font-size: 13px; color: #fff;
    box-shadow: var(--shadow-lg);
    display: flex; align-items: center; gap: 10px;
    animation: toastIn 0.3s ease forwards;
    max-width: 300px;
}
.toast.success .t-icon { color: var(--green); }
.toast.error   .t-icon { color: #f87171; }
@keyframes toastIn  { from { opacity:0; transform: translateX(20px); } to { opacity:1; transform: translateX(0); } }
@keyframes toastOut { to   { opacity:0; transform: translateX(20px); } }

/* ── LOADING ─────────────────────────────────────────────── */
.loading-overlay {
    position: fixed; inset: 0;
    background: var(--bg);
    z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 14px;
    transition: opacity 0.4s;
}
.loading-overlay.hidden { opacity: 0; pointer-events: none; }
.loading-spinner {
    width: 36px; height: 36px;
    border: 3px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.75s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── SCROLLBAR ───────────────────────────────────────────── */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: var(--bg3); }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--text3); }

/* ── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .main { margin-left: 0; }
    .form-grid { grid-template-columns: 1fr; }
    .detail-grid { grid-template-columns: 1fr; }
    .files-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
    <span style="font-family:var(--mono);font-size:12px;color:var(--text3)">Loading web apps...</span>
</div>

<!-- App Layout -->
<div class="app">

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <div class="logo-row">
                <div class="logo-icon">🌐</div>
                <h1>Web Apps<br>Manager</h1>
            </div>
            <span>v1.0</span>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section-label">Navigation</div>
            <button class="nav-btn active" id="navDashboard" onclick="showView('dashboard')">
                <span class="icon">⊞</span> Dashboard
            </button>
            <button class="nav-btn" id="navApps" onclick="showView('apps')">
                <span class="icon">◫</span> All Web Apps
            </button>
            <button class="nav-btn" id="navUpdates" onclick="showView('updates')">
                <span class="icon">↻</span> Needs Update
                <span class="nav-badge" id="updateCount" style="display:none">0</span>
            </button>
            <div class="nav-section-label" style="margin-top:16px">Actions</div>
            <button class="nav-btn" onclick="openAddModal()">
                <span class="icon">+</span> Add Web App
            </button>
        </nav>
        <div class="sidebar-footer">
            © 2025 Web Apps Manager
        </div>
    </aside>

    <!-- Main -->
    <main class="main">

        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-left">
                <div class="breadcrumb" id="breadcrumb">Home / Dashboard</div>
                <h2 id="pageTitle">Dashboard</h2>
            </div>
            <div class="topbar-actions">
                <button class="btn btn-primary" onclick="openAddModal()">
                    + Add Web App
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="content">

            <!-- DASHBOARD VIEW -->
            <div id="viewDashboard">
                <div class="stats-grid">
                    <div class="stat-card total">
                        <span class="stat-card-icon">🌐</span>
                        <div class="stat-label">Total Apps</div>
                        <div class="stat-value" id="statTotal">—</div>
                        <div class="stat-sub">in database</div>
                    </div>
                    <div class="stat-card update">
                        <span class="stat-card-icon">🔴</span>
                        <div class="stat-label">Needs Update</div>
                        <div class="stat-value" id="statUpdate">—</div>
                        <div class="stat-sub">flagged</div>
                    </div>
                    <div class="stat-card alpha">
                        <span class="stat-card-icon">🧪</span>
                        <div class="stat-label">Alpha</div>
                        <div class="stat-value" id="statAlpha">—</div>
                        <div class="stat-sub">apps</div>
                    </div>
                    <div class="stat-card beta">
                        <span class="stat-card-icon">⚡</span>
                        <div class="stat-label">Beta</div>
                        <div class="stat-value" id="statBeta">—</div>
                        <div class="stat-sub">apps</div>
                    </div>
                    <div class="stat-card community">
                        <span class="stat-card-icon">✅</span>
                        <div class="stat-label">Community</div>
                        <div class="stat-value" id="statCommunity">—</div>
                        <div class="stat-sub">apps</div>
                    </div>
                </div>

                <div style="margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="font-size:15px;font-weight:800;color:var(--text)">Recent Web Apps</h3>
                    <button class="btn btn-ghost btn-sm" onclick="showView('apps')">View All →</button>
                </div>
                <div class="table-wrap" id="dashboardTable">
                    <div class="empty-state"><div class="icon">🌐</div><p>Loading...</p></div>
                </div>
            </div>

            <!-- APPS VIEW -->
            <div id="viewApps" style="display:none">
                <div class="toolbar">
                    <div class="search-wrap">
                        <span class="search-icon">⌕</span>
                        <input type="text" class="search-input" id="searchInput" placeholder="Search by name, author, category..." oninput="debounceLoad()">
                    </div>
                    <select class="filter-select" id="typeFilter" onchange="loadApps()">
                        <option value="">All Types</option>
                        <option value="Alpha">Alpha</option>
                        <option value="Beta">Beta</option>
                        <option value="Community">Community</option>
                    </select>
                    <select class="filter-select" id="updateFilter" onchange="loadApps()">
                        <option value="">All Status</option>
                        <option value="1">Needs Update</option>
                        <option value="0">Up to Date</option>
                    </select>
                    <select class="sort-select" id="sortCol" onchange="loadApps()">
                        <option value="app_name">Sort: Name</option>
                        <option value="type">Sort: Type</option>
                        <option value="category">Sort: Category</option>
                        <option value="author">Sort: Author</option>
                        <option value="version">Sort: Version</option>
                        <option value="release_date">Sort: Release Date</option>
                        <option value="needs_update">Sort: Needs Update</option>
                        <option value="created_at">Sort: Created</option>
                    </select>
                    <select class="sort-select" id="sortDir" onchange="loadApps()">
                        <option value="asc">↑ Asc</option>
                        <option value="desc">↓ Desc</option>
                    </select>
                </div>
                <div class="table-wrap" id="appsTable">
                    <div class="empty-state"><div class="icon">🌐</div><p>Loading...</p></div>
                </div>
            </div>

            <!-- UPDATES VIEW -->
            <div id="viewUpdates" style="display:none">
                <div style="margin-bottom:18px;">
                    <p style="font-size:13px;color:var(--text2)">Web apps flagged as needing an update.</p>
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
            <h3 id="formModalTitle">Add Web App</h3>
            <button class="modal-close" onclick="closeModal('formModal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="appForm" enctype="multipart/form-data">
                <input type="hidden" id="formId" name="id" value="0">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">App Name <span class="required">*</span></label>
                        <input type="text" class="form-input" id="f_app_name" name="app_name" placeholder="e.g. Invoice Portal" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select class="form-select" id="f_type" name="type">
                            <option value="Alpha">Alpha</option>
                            <option value="Beta" selected>Beta</option>
                            <option value="Community">Community</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Description</label>
                        <textarea class="form-textarea" id="f_description" name="description" placeholder="What does this app do?" style="min-height:60px"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <input type="text" class="form-input" id="f_category" name="category" placeholder="e.g. Finance, CRM, Utility">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Version</label>
                        <input type="text" class="form-input" id="f_version" name="version" placeholder="e.g. 2.1.0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Release Date</label>
                        <input type="text" class="form-input" id="f_release_date" name="release_date" placeholder="e.g. 2024-06-01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Author</label>
                        <input type="text" class="form-input" id="f_author" name="author" placeholder="e.g. Jane Smith">
                    </div>

                    <div class="detail-divider full"></div>
                    <div class="full" style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:-4px">File Attachments</div>

                    <div class="form-group">
                        <label class="form-label">Main App File</label>
                        <div class="file-upload-wrap">
                            <input type="file" name="mainapp_file" id="f_mainapp_file">
                        </div>
                        <div class="existing-file" id="ex_mainapp_file"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">DB Config File</label>
                        <div class="file-upload-wrap">
                            <input type="file" name="dbconfig_file" id="f_dbconfig_file">
                        </div>
                        <div class="existing-file" id="ex_dbconfig_file"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">API File</label>
                        <div class="file-upload-wrap">
                            <input type="file" name="api_file" id="f_api_file">
                        </div>
                        <div class="existing-file" id="ex_api_file"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ReadMe File</label>
                        <div class="file-upload-wrap">
                            <input type="file" name="readme_file" id="f_readme_file">
                        </div>
                        <div class="existing-file" id="ex_readme_file"></div>
                    </div>

                    <div class="detail-divider full"></div>

                    <div class="form-group full">
                        <div class="checkbox-group">
                            <input type="checkbox" id="f_needs_update" name="needs_update">
                            <label for="f_needs_update">🔴 Needs Update — flag this app for a pending update</label>
                        </div>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Developer Notes</label>
                        <textarea class="form-textarea" id="f_dev_notes" name="dev_notes" placeholder="Internal notes, todos, known bugs..." style="min-height:80px"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('formModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveApp()" id="saveBtn">Save App</button>
        </div>
    </div>
</div>

<!-- ── VIEW MODAL ─────────────────────────────────────────── -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="viewModalTitle">App Details</h3>
            <button class="modal-close" onclick="closeModal('viewModal')">✕</button>
        </div>
        <div class="modal-body" id="viewModalBody"></div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('viewModal')">Close</button>
            <button class="btn btn-primary" id="viewEditBtn">Edit</button>
        </div>
    </div>
</div>

<!-- ── DELETE CONFIRM ─────────────────────────────────────── -->
<div class="modal-overlay" id="deleteModal">
    <div class="confirm-modal">
        <div class="warn-icon">⚠️</div>
        <h3>Delete Web App?</h3>
        <p>This will permanently delete <strong id="deleteAppName"></strong> and all associated files. This action cannot be undone.</p>
        <div class="confirm-actions">
            <button class="btn btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
            <button class="btn btn-danger" onclick="confirmDelete()" id="confirmDeleteBtn">Delete</button>
        </div>
    </div>
</div>

<!-- ── TOAST ──────────────────────────────────────────────── -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ── State ────────────────────────────────────────────────────
let currentView = 'dashboard';
let deleteTargetId = null;
let debounceTimer = null;

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
    ['dashboard','apps','updates'].forEach(id => {
        document.getElementById('view' + cap(id)).style.display = id === v ? '' : 'none';
        document.getElementById('nav'  + cap(id)).classList.toggle('active', id === v);
    });
    const titles = { dashboard:'Dashboard', apps:'All Web Apps', updates:'Needs Update' };
    const crumbs = { dashboard:'Home / Dashboard', apps:'Home / All Web Apps', updates:'Home / Needs Update' };
    document.getElementById('pageTitle').textContent  = titles[v];
    document.getElementById('breadcrumb').textContent = crumbs[v];
    if (v === 'apps')    loadApps();
    if (v === 'updates') loadUpdates();
    if (v === 'dashboard') loadDashboard();
}

function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

// ── Load Stats / Dashboard ───────────────────────────────────
async function loadStats() {
    const data = await apiFetch('?action=list');
    if (!data || data.error) return;
    document.getElementById('statTotal').textContent     = data.stats.total;
    document.getElementById('statUpdate').textContent    = data.stats.needsUpdate;
    document.getElementById('statAlpha').textContent     = data.stats.types['Alpha']     || 0;
    document.getElementById('statBeta').textContent      = data.stats.types['Beta']      || 0;
    document.getElementById('statCommunity').textContent = data.stats.types['Community'] || 0;

    if (data.stats.needsUpdate > 0) {
        const badge = document.getElementById('updateCount');
        badge.textContent = data.stats.needsUpdate;
        badge.style.display = '';
    }

    const recent = data.apps.slice(0, 5);
    document.getElementById('dashboardTable').innerHTML = renderTable(recent, true);
}

async function loadDashboard() { await loadStats(); }

// ── Load Apps ────────────────────────────────────────────────
async function loadApps() {
    document.getElementById('appsTable').innerHTML = '<div class="empty-state"><div class="loading-spinner" style="margin:0 auto"></div></div>';
    const search = encodeURIComponent(document.getElementById('searchInput').value);
    const type   = document.getElementById('typeFilter').value;
    const upd    = document.getElementById('updateFilter').value;
    const sort   = document.getElementById('sortCol').value;
    const dir    = document.getElementById('sortDir').value;
    const data   = await apiFetch(`?action=list&search=${search}&type_filter=${type}&needs_update=${upd}&sort=${sort}&dir=${dir}`);
    if (!data || data.error) { document.getElementById('appsTable').innerHTML = '<div class="empty-state"><p>Error loading data.</p></div>'; return; }
    document.getElementById('appsTable').innerHTML = renderTable(data.apps);
}

// ── Load Updates ─────────────────────────────────────────────
async function loadUpdates() {
    document.getElementById('updatesTable').innerHTML = '<div class="empty-state"><div class="loading-spinner" style="margin:0 auto"></div></div>';
    const data = await apiFetch('?action=list&needs_update=1&sort=app_name&dir=asc');
    if (!data || data.error) return;
    document.getElementById('updatesTable').innerHTML = renderTable(data.apps);
}

// ── Render Table ─────────────────────────────────────────────
function renderTable(apps, compact = false) {
    if (!apps || !apps.length) {
        return `<div class="empty-state"><div class="icon">🌐</div><p>No web apps found.</p></div>`;
    }
    const files = (a) => {
        let f = [];
        if (a.mainapp_file)  f.push(`<a class="file-link" href="?action=download&file=${encodeURIComponent(a.mainapp_file)}"  onclick="event.stopPropagation()">📦 Main App</a>`);
        if (a.dbconfig_file) f.push(`<a class="file-link" href="?action=download&file=${encodeURIComponent(a.dbconfig_file)}" onclick="event.stopPropagation()">🗄 DB Config</a>`);
        if (a.api_file)      f.push(`<a class="file-link" href="?action=download&file=${encodeURIComponent(a.api_file)}"      onclick="event.stopPropagation()">🔌 API</a>`);
        if (a.readme_file)   f.push(`<a class="file-link" href="?action=download&file=${encodeURIComponent(a.readme_file)}"   onclick="event.stopPropagation()">📄 ReadMe</a>`);
        return f.join(' ');
    };
    const rows = apps.map(a => `
        <tr onclick="viewApp(${a.id})">
            <td>${esc(a.app_name)}</td>
            <td><span class="type-badge ${esc(a.type)}">${esc(a.type)}</span></td>
            <td>${esc(a.category||'—')}</td>
            ${!compact ? `<td>${esc(a.version||'—')}</td>` : ''}
            ${!compact ? `<td>${esc(a.author||'—')}</td>` : ''}
            ${!compact ? `<td>${esc(a.release_date||'—')}</td>` : ''}
            <td><span class="update-badge ${a.needs_update?'yes':'no'}">${a.needs_update?'🔴 Yes':'✅ No'}</span></td>
            ${!compact ? `<td style="min-width:220px">${files(a)||'<span style="color:var(--text3)">—</span>'}</td>` : ''}
            <td>
                <div class="row-actions" onclick="event.stopPropagation()">
                    <button class="btn btn-ghost btn-sm btn-icon" title="Edit"   onclick="editApp(${a.id})">✎</button>
                    <button class="btn btn-danger btn-sm btn-icon" title="Delete" onclick="deleteApp(${a.id},'${esc(a.app_name).replace(/'/g,"\\'")}')">🗑</button>
                </div>
            </td>
        </tr>`).join('');

    return `<table>
        <thead><tr>
            <th>App Name</th><th>Type</th><th>Category</th>
            ${!compact ? '<th>Version</th><th>Author</th><th>Release Date</th>' : ''}
            <th>Update?</th>
            ${!compact ? '<th>Files</th>' : ''}
            <th>Actions</th>
        </tr></thead>
        <tbody>${rows}</tbody>
    </table>`;
}

// ── View App ─────────────────────────────────────────────────
async function viewApp(id) {
    const a = await apiFetch(`?action=get&id=${id}`);
    if (!a || a.error) return;
    document.getElementById('viewModalTitle').textContent = a.app_name;

    const fileCard = (f, label, icon) => `
        <div class="file-card">
            <div class="file-card-icon">${icon}</div>
            <div class="file-card-info">
                <div class="file-card-label">${label}</div>
                ${f ? `<a class="file-card-link" href="?action=download&file=${encodeURIComponent(f)}" target="_blank">↓ ${esc(f)}</a>`
                    : '<span class="file-card-none">No file attached</span>'}
            </div>
        </div>`;

    document.getElementById('viewModalBody').innerHTML = `
        <div class="detail-grid">
            <div class="detail-item"><div class="detail-key">App Name</div><div class="detail-val">${esc(a.app_name)}</div></div>
            <div class="detail-item"><div class="detail-key">Type</div><div class="detail-val"><span class="type-badge ${esc(a.type)}">${esc(a.type)}</span></div></div>
            <div class="detail-item"><div class="detail-key">Category</div><div class="detail-val">${esc(a.category||'—')}</div></div>
            <div class="detail-item"><div class="detail-key">Version</div><div class="detail-val">${esc(a.version||'—')}</div></div>
            <div class="detail-item"><div class="detail-key">Author</div><div class="detail-val">${esc(a.author||'—')}</div></div>
            <div class="detail-item"><div class="detail-key">Release Date</div><div class="detail-val">${esc(a.release_date||'—')}</div></div>
            <div class="detail-item"><div class="detail-key">Needs Update</div><div class="detail-val"><span class="update-badge ${a.needs_update?'yes':'no'}">${a.needs_update?'🔴 Yes — needs update':'✅ Up to date'}</span></div></div>
            <div class="detail-item"><div class="detail-key">Created</div><div class="detail-val">${esc(a.created_at||'—')}</div></div>
            <div class="detail-divider"></div>
            <div class="detail-item full"><div class="detail-key">Description</div><div class="detail-val longtext">${esc(a.description||'—')}</div></div>
            <div class="detail-item full"><div class="detail-key">Developer Notes</div><div class="detail-val longtext">${esc(a.dev_notes||'—')}</div></div>
            <div class="detail-divider"></div>
            <div class="detail-item full">
                <div class="detail-key" style="margin-bottom:8px">Attached Files</div>
                <div class="files-grid">
                    ${fileCard(a.mainapp_file,  'Main App File', '📦')}
                    ${fileCard(a.dbconfig_file, 'DB Config File','🗄')}
                    ${fileCard(a.api_file,      'API File',      '🔌')}
                    ${fileCard(a.readme_file,   'ReadMe File',   '📄')}
                </div>
            </div>
        </div>`;

    document.getElementById('viewEditBtn').onclick = () => { closeModal('viewModal'); editApp(id); };
    openModal('viewModal');
}

// ── Add Modal ─────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('formModalTitle').textContent = 'Add Web App';
    document.getElementById('appForm').reset();
    document.getElementById('formId').value = 0;
    ['mainapp_file','dbconfig_file','api_file','readme_file'].forEach(f => {
        document.getElementById('ex_'+f).innerHTML = '';
    });
    openModal('formModal');
}

// ── Edit Modal ────────────────────────────────────────────────
async function editApp(id) {
    const a = await apiFetch(`?action=get&id=${id}`);
    if (!a || a.error) return;
    document.getElementById('formModalTitle').textContent = 'Edit Web App';
    document.getElementById('formId').value       = a.id;
    document.getElementById('f_app_name').value   = a.app_name    || '';
    document.getElementById('f_description').value= a.description || '';
    document.getElementById('f_type').value        = a.type        || 'Beta';
    document.getElementById('f_category').value   = a.category    || '';
    document.getElementById('f_version').value    = a.version     || '';
    document.getElementById('f_release_date').value = a.release_date || '';
    document.getElementById('f_author').value     = a.author      || '';
    document.getElementById('f_needs_update').checked = !!parseInt(a.needs_update);
    document.getElementById('f_dev_notes').value  = a.dev_notes   || '';

    const fileMap = { mainapp_file: a.mainapp_file, dbconfig_file: a.dbconfig_file, api_file: a.api_file, readme_file: a.readme_file };
    for (const [k, v] of Object.entries(fileMap)) {
        const el = document.getElementById('ex_'+k);
        el.innerHTML = v ? `📎 Current: <a href="?action=download&file=${encodeURIComponent(v)}" target="_blank">${esc(v)}</a>` : '';
    }
    openModal('formModal');
}

// ── Save App ──────────────────────────────────────────────────
async function saveApp() {
    const btn  = document.getElementById('saveBtn');
    const name = document.getElementById('f_app_name').value.trim();
    if (!name) { showToast('App name is required.', 'error'); return; }

    btn.disabled = true;
    btn.textContent = 'Saving...';

    const formData = new FormData(document.getElementById('appForm'));
    formData.set('action', 'save');

    try {
        const res  = await fetch(window.location.pathname, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            closeModal('formModal');
            showToast('Web app saved successfully!', 'success');
            loadStats();
            if (currentView === 'apps')    loadApps();
            if (currentView === 'updates') loadUpdates();
        } else {
            showToast(data.error || 'Save failed.', 'error');
        }
    } catch(e) {
        showToast('Network error: ' + e.message, 'error');
    }
    btn.disabled = false;
    btn.textContent = 'Save App';
}

// ── Delete App ────────────────────────────────────────────────
function deleteApp(id, name) {
    deleteTargetId = id;
    document.getElementById('deleteAppName').textContent = name;
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
        showToast('Web app deleted.', 'success');
        loadStats();
        if (currentView === 'apps')    loadApps();
        if (currentView === 'updates') loadUpdates();
    } else {
        showToast('Delete failed.', 'error');
    }
    btn.disabled = false; btn.textContent = 'Delete';
}

// ── Modals ────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) closeModal(el.id); });
});

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const icons = { success: '✓', error: '✗' };
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span class="t-icon" style="font-size:16px">${icons[type]||'ℹ'}</span> ${msg}`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.animation = 'toastOut 0.3s forwards'; setTimeout(() => t.remove(), 300); }, 3000);
}

// ── Debounce ──────────────────────────────────────────────────
function debounceLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadApps, 300);
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
    if (e.key === 'Escape') ['formModal','viewModal','deleteModal'].forEach(closeModal);
});
</script>
</body>
</html>
