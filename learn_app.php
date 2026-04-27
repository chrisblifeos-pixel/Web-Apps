<?php
// ============================================================
//  LEARNING APP — PHP + MySQL Single-File CRUD Reference
//  Chris's personal learning reference, April 2026
//
//  WHAT THIS FILE COVERS:
//    1. Database connection
//    2. Auto-creating tables (with seed data)
//    3. INSERT  — handling every field type
//    4. SELECT  — table view + card view
//    5. UPDATE  — edit existing rows
//    6. DELETE  — remove rows
//    7. Image upload to /attachments/
//    8. Visual statistics (chart.js)
//
//  HOW THIS FILE IS STRUCTURED:
//    ① PHP "backend" block at the top  — handles all form
//      submissions and database work BEFORE any HTML is output.
//    ② HTML/CSS/JS block at the bottom — the actual page.
//
//  ALWAYS keep the PHP block above <!DOCTYPE html>.
//  If you echo anything before the headers are sent PHP will
//  throw "headers already sent" errors.
// ============================================================


// ════════════════════════════════════════════════════════════
//  SECTION 1 — DATABASE CONNECTION
//  mysqli is the recommended way to talk to MySQL from PHP.
//  Always store credentials in variables so you only have to
//  change them in one place.
// ════════════════════════════════════════════════════════════
$db_host = "sql105.byethost10.com";
$db_name = "b10_39913602_cob";
$db_user = "b10_39913602";
$db_pass = "Cr0ssfire";

// mysqli_connect() returns a connection object or FALSE.
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Always check the connection — a bad connection will cause
// confusing errors later on if you don't catch it here.
if (!$conn) {
    die("<h2 style='color:red;font-family:monospace'>
         Database connection failed:<br>" .
         mysqli_connect_error() . "</h2>");
}

// Tell MySQL to use UTF-8 so special characters are stored
// correctly. Always do this right after connecting.
mysqli_set_charset($conn, "utf8mb4");


// ════════════════════════════════════════════════════════════
//  SECTION 2 — AUTO-CREATE TABLES
//
//  "IF NOT EXISTS" means the query is safe to run on every
//  page load — it only creates the table the very first time.
//
//  TABLE OVERVIEW:
//    la_genres      — simple lookup list (for the single dropdown)
//    la_tags        — another lookup list (for the multi-select)
//    la_projects    — the MAIN table this app manages
//    la_project_tags — a "junction" / "pivot" table linking
//                      projects to many tags (many-to-many)
// ════════════════════════════════════════════════════════════

// --- LOOKUP TABLE: genres (feeds the single dropdown) ---
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS la_genres (
        id   INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// --- LOOKUP TABLE: tags (feeds the multi-select) ---
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS la_tags (
        id   INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// --- MAIN TABLE: projects ---
// Every column type used in the form has a comment explaining
// the best MySQL data type to use for it.
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS la_projects (
        id            INT AUTO_INCREMENT PRIMARY KEY,  -- unique row ID; always have one
        title         VARCHAR(255) NOT NULL,           -- single-line text → VARCHAR
        description   TEXT,                            -- multi-line text → TEXT
        genre_id      INT,                             -- single dropdown → store the FK id
        status        VARCHAR(50),                     -- hard-coded dropdown → VARCHAR
        is_featured   TINYINT(1) DEFAULT 0,            -- checkbox (yes/no) → TINYINT 0|1
        is_archived   TINYINT(1) DEFAULT 0,
        has_notes     TINYINT(1) DEFAULT 0,
        image_path    VARCHAR(500),                    -- file upload → store the PATH, not the file
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP  -- auto-timestamp; very useful
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// --- JUNCTION TABLE: project ↔ tags (many-to-many) ---
// A project can have many tags; a tag can belong to many projects.
// This is the standard relational way to model that.
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS la_project_tags (
        project_id INT NOT NULL,
        tag_id     INT NOT NULL,
        PRIMARY KEY (project_id, tag_id)   -- composite PK prevents duplicates
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// --- SEED DATA: add sample rows if the tables are empty ---
// COUNT(*) returns 0 when a table has no rows.
$genres_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM la_genres"))['n'];
if ($genres_count == 0) {
    mysqli_query($conn, "
        INSERT INTO la_genres (name) VALUES
            ('Web App'), ('Mobile App'), ('Data Tool'),
            ('Game'), ('Automation'), ('Other')
    ");
}

$tags_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM la_tags"))['n'];
if ($tags_count == 0) {
    mysqli_query($conn, "
        INSERT INTO la_tags (name) VALUES
            ('PHP'), ('JavaScript'), ('MySQL'), ('CSS'),
            ('Python'), ('Obsidian'), ('API'), ('CLI')
    ");
}


// ════════════════════════════════════════════════════════════
//  SECTION 3 — HANDLE FORM SUBMISSIONS  (POST requests)
//
//  We use a hidden field called 'action' to know which
//  operation the user wants: add / edit / delete.
//
//  IMPORTANT SECURITY NOTE:
//  Always sanitize user input before putting it in a query.
//  We use prepared statements (bind_param) which is the
//  safest way — SQL injection is impossible with them.
// ════════════════════════════════════════════════════════════

$message = "";   // We'll show a success/error message in the HTML below.

// ── 3a. DELETE ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['id'];   // Cast to int — simplest sanitization for a numeric ID.

    // Delete the junction rows first (foreign key cleanup).
    $stmt = mysqli_prepare($conn, "DELETE FROM la_project_tags WHERE project_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);   // "i" = integer
    mysqli_stmt_execute($stmt);

    // Now delete the main row.
    $stmt = mysqli_prepare($conn, "DELETE FROM la_projects WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $message = "✅ Project deleted.";
}

// ── 3b. ADD or EDIT ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    in_array($_POST['action'], ['add', 'edit'])) {

    $action = $_POST['action'];

    // --- Collect & sanitize text fields ---
    // mysqli_real_escape_string() escapes special characters.
    // It's a backup; prepared statements are the real protection.
    $title       = trim(mysqli_real_escape_string($conn, $_POST['title'] ?? ''));
    $description = trim(mysqli_real_escape_string($conn, $_POST['description'] ?? ''));
    $status      = mysqli_real_escape_string($conn, $_POST['status'] ?? '');
    $genre_id    = (int)($_POST['genre_id'] ?? 0);

    // --- Checkboxes ---
    // Checkboxes are ONLY sent in $_POST when they are checked.
    // So we check if the key exists; if not, the value is 0.
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_archived = isset($_POST['is_archived']) ? 1 : 0;
    $has_notes   = isset($_POST['has_notes'])   ? 1 : 0;

    // --- Multi-select tags ---
    // Multi-selects send an array: $_POST['tags'] = ['1','3','5']
    // We'll save these to the junction table after the main insert/update.
    $selected_tags = isset($_POST['tags']) && is_array($_POST['tags'])
                     ? array_map('intval', $_POST['tags'])  // cast each to int
                     : [];

    // --- Image upload ---
    // Files come through $_FILES, NOT $_POST.
    // We only process the upload if the user actually chose a file.
    $image_path = $_POST['existing_image'] ?? '';   // keep old path on edit if no new file

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // UPLOAD_ERR_OK (value 0) means the file arrived without errors.

        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/attachments/';

        // Make sure the folder exists; create it if not.
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Validate the file type — only allow images.
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type     = mime_content_type($_FILES['image']['tmp_name']);  // read the actual file type

        if (in_array($file_type, $allowed_types)) {
            // Build a unique filename to avoid collisions.
            $ext       = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename  = 'proj_' . time() . '_' . uniqid() . '.' . $ext;
            $dest_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest_path)) {
                // Store just the web-accessible path (not the full server path).
                $image_path = '/attachments/' . $filename;
            } else {
                $message .= " ⚠️ Image upload failed (could not move file).";
            }
        } else {
            $message .= " ⚠️ Only JPG, PNG, GIF, WEBP images are allowed.";
        }
    }

    if ($action === 'add') {
        // ── INSERT ──────────────────────────────────────────
        // Prepared statement: ? placeholders are filled by bind_param.
        // bind_param type string: s=string, i=integer, d=double, b=blob
        $stmt = mysqli_prepare($conn, "
            INSERT INTO la_projects (title, description, genre_id, status, is_featured, is_archived, has_notes, image_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "ssissiis",
            $title, $description, $genre_id, $status,
            $is_featured, $is_archived, $has_notes, $image_path
        );
        mysqli_stmt_execute($stmt);
        $new_id = mysqli_insert_id($conn);   // get the auto-generated ID of the new row
        $message = "✅ Project added (ID: $new_id).";

        // Save tags for the new project
        if ($new_id && !empty($selected_tags)) {
            save_project_tags($conn, $new_id, $selected_tags);
        }

    } else {
        // ── UPDATE ──────────────────────────────────────────
        $id = (int)$_POST['id'];
        $stmt = mysqli_prepare($conn, "
            UPDATE la_projects
               SET title=?, description=?, genre_id=?, status=?,
                   is_featured=?, is_archived=?, has_notes=?, image_path=?
             WHERE id=?
        ");
        mysqli_stmt_bind_param($stmt, "ssissiisi",
            $title, $description, $genre_id, $status,
            $is_featured, $is_archived, $has_notes, $image_path, $id
        );
        mysqli_stmt_execute($stmt);
        $message = "✅ Project updated.";

        // Delete old tags, then re-insert the new selection.
        save_project_tags($conn, $id, $selected_tags);
    }
}

// ── Helper function: save project tags ──────────────────────
// Extracted to a function so we can call it from both add & edit.
function save_project_tags($conn, $project_id, $tag_ids) {
    // 1. Remove all existing tags for this project.
    $stmt = mysqli_prepare($conn, "DELETE FROM la_project_tags WHERE project_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    mysqli_stmt_execute($stmt);

    // 2. Insert the freshly selected ones.
    if (!empty($tag_ids)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO la_project_tags (project_id, tag_id) VALUES (?, ?)");
        foreach ($tag_ids as $tag_id) {
            mysqli_stmt_bind_param($stmt, "ii", $project_id, $tag_id);
            mysqli_stmt_execute($stmt);
        }
    }
}


// ════════════════════════════════════════════════════════════
//  SECTION 4 — FETCH DATA FOR THE PAGE
//
//  We grab everything we need BEFORE outputting HTML so we
//  can use the data anywhere on the page without extra queries.
// ════════════════════════════════════════════════════════════

// Fetch all genres for the dropdown.
$genres = mysqli_query($conn, "SELECT id, name FROM la_genres ORDER BY name");

// Fetch all tags for the multi-select.
$tags = mysqli_query($conn, "SELECT id, name FROM la_tags ORDER BY name");

// Fetch all projects, JOINing genre name so we don't need
// a second query for each row.
// LEFT JOIN keeps projects even if they have no genre set.
$projects = mysqli_query($conn, "
    SELECT p.*, g.name AS genre_name
      FROM la_projects p
      LEFT JOIN la_genres g ON p.genre_id = g.id
     ORDER BY p.created_at DESC
");

// Fetch tags for every project in one query (efficient).
// We'll build an associative array: [ project_id => [tag names] ]
$all_project_tags = [];
$tags_result = mysqli_query($conn, "
    SELECT pt.project_id, t.name
      FROM la_project_tags pt
      JOIN la_tags t ON pt.tag_id = t.id
");
while ($row = mysqli_fetch_assoc($tags_result)) {
    $all_project_tags[$row['project_id']][] = $row['name'];
}

// ── Statistics for the charts ────────────────────────────────
// Status breakdown
$stat_status = mysqli_query($conn, "
    SELECT status, COUNT(*) AS cnt
      FROM la_projects
     WHERE status IS NOT NULL AND status != ''
     GROUP BY status
");

// Genre breakdown
$stat_genre = mysqli_query($conn, "
    SELECT g.name, COUNT(p.id) AS cnt
      FROM la_genres g
      LEFT JOIN la_projects p ON g.id = p.genre_id
     GROUP BY g.id, g.name
     ORDER BY cnt DESC
");

// Monthly creates (last 6 months)
$stat_monthly = mysqli_query($conn, "
    SELECT DATE_FORMAT(created_at, '%b %Y') AS month_label,
           COUNT(*) AS cnt
      FROM la_projects
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY YEAR(created_at), MONTH(created_at)
     ORDER BY YEAR(created_at), MONTH(created_at)
");

// ── If editing, fetch the row to pre-fill the form ──────────
$edit_project = null;
$edit_tag_ids = [];
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $res = mysqli_query($conn, "SELECT * FROM la_projects WHERE id = $edit_id LIMIT 1");
    $edit_project = mysqli_fetch_assoc($res);

    // Also grab which tags this project already has.
    $res2 = mysqli_query($conn, "SELECT tag_id FROM la_project_tags WHERE project_id = $edit_id");
    while ($row = mysqli_fetch_assoc($res2)) {
        $edit_tag_ids[] = $row['tag_id'];
    }
}

// Hard-coded status options (used in the dropdown AND in stats)
$status_options = ['Planning', 'In Progress', 'On Hold', 'Complete', 'Abandoned'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PHP Learning App — CRUD Reference</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
/* ────────────────────────────────────────────────────────────
   GLOBAL STYLES
   Using CSS custom properties (variables) so colors are easy
   to change in one place — great practice for any project.
──────────────────────────────────────────────────────────── */
:root {
    --bg:        #0f1117;
    --surface:   #1a1d27;
    --border:    #2a2d3e;
    --accent:    #6c63ff;
    --accent2:   #ff6584;
    --green:     #43e97b;
    --yellow:    #f9c74f;
    --text:      #e2e8f0;
    --muted:     #8892a4;
    --radius:    10px;
    --font:      'Courier New', Courier, monospace;
    --sans:      'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--sans);
    font-size: 15px;
    line-height: 1.6;
}
a { color: var(--accent); text-decoration: none; }

/* ── Page layout ── */
header {
    background: var(--surface);
    border-bottom: 2px solid var(--accent);
    padding: 18px 30px;
    display: flex;
    align-items: center;
    gap: 16px;
}
header h1 { font-size: 1.3rem; color: var(--accent); font-family: var(--font); }
header .subtitle { color: var(--muted); font-size: 0.85rem; }

.container { max-width: 1280px; margin: 0 auto; padding: 24px 20px; }

/* ── Section headings ── */
.section-title {
    font-family: var(--font);
    color: var(--accent2);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
}

/* ── Cards / panels ── */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 22px;
    margin-bottom: 28px;
}

/* ── Message banner ── */
.msg {
    background: #1e3a2f;
    border: 1px solid var(--green);
    color: var(--green);
    padding: 12px 18px;
    border-radius: var(--radius);
    margin-bottom: 20px;
    font-family: var(--font);
    font-size: 0.9rem;
}

/* ── FORM STYLES ── */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}
.form-grid .full-width { grid-column: 1 / -1; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label {
    font-size: 0.8rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 1px;
}
.form-group .hint {
    font-size: 0.75rem;
    color: #555e7a;
    font-style: italic;
    margin-top: 2px;
}

/* Common input styles — we target multiple types at once */
input[type="text"],
textarea,
select {
    background: #12141e;
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    padding: 10px 12px;
    font-size: 0.9rem;
    font-family: var(--sans);
    width: 100%;
    transition: border-color 0.2s;
}
input[type="text"]:focus,
textarea:focus,
select:focus {
    outline: none;
    border-color: var(--accent);
}
textarea { resize: vertical; min-height: 110px; }
select[multiple] { min-height: 130px; }
select option:checked { background: var(--accent); color: #fff; }

/* Checkbox row */
.checkbox-group { display: flex; flex-direction: column; gap: 10px; }
.checkbox-item { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.checkbox-item input[type="checkbox"] {
    width: 18px; height: 18px;
    accent-color: var(--accent);
    cursor: pointer;
}
.checkbox-item span { font-size: 0.9rem; }

/* File input */
input[type="file"] {
    background: #12141e;
    border: 1px dashed var(--border);
    border-radius: 6px;
    color: var(--muted);
    padding: 10px 12px;
    width: 100%;
    cursor: pointer;
}

/* ── Buttons ── */
.btn {
    padding: 10px 22px;
    border-radius: 6px;
    border: none;
    font-size: 0.9rem;
    font-family: var(--sans);
    cursor: pointer;
    font-weight: 600;
    transition: opacity 0.15s, transform 0.1s;
}
.btn:active { transform: scale(0.97); }
.btn-primary  { background: var(--accent);  color: #fff; }
.btn-danger   { background: #c0392b;         color: #fff; }
.btn-ghost    { background: transparent; border: 1px solid var(--border); color: var(--muted); }
.btn-sm       { padding: 5px 14px; font-size: 0.8rem; }
.btn:hover    { opacity: 0.85; }

/* ── View toggle buttons ── */
.view-toggle { display: flex; gap: 8px; margin-bottom: 16px; }
.view-toggle button { padding: 7px 16px; border-radius: 6px; border: 1px solid var(--border);
    background: transparent; color: var(--muted); cursor: pointer; font-size: 0.85rem; }
.view-toggle button.active { background: var(--accent); color: #fff; border-color: var(--accent); }

/* ── TABLE VIEW ── */
.data-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.data-table th {
    text-align: left;
    padding: 10px 14px;
    background: #13151f;
    color: var(--muted);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-bottom: 1px solid var(--border);
}
.data-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.data-table tr:hover td { background: rgba(108,99,255,0.05); }
.data-table td img { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; }

/* ── CARD VIEW ── */
.card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 18px;
    display: none;  /* hidden by default; toggled by JS */
}
.proj-card {
    background: #13151f;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    transition: border-color 0.2s, transform 0.15s;
}
.proj-card:hover { border-color: var(--accent); transform: translateY(-2px); }
.proj-card-img { width: 100%; height: 150px; object-fit: cover; background: #1e2130; }
.proj-card-body { padding: 16px; }
.proj-card-title { font-weight: 700; margin-bottom: 6px; }
.proj-card-desc { color: var(--muted); font-size: 0.82rem; margin-bottom: 10px; }
.proj-card-meta { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
.proj-card-actions { display: flex; gap: 8px; }

/* ── Badges / pills ── */
.badge {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.badge-accent  { background: rgba(108,99,255,0.25); color: var(--accent); }
.badge-green   { background: rgba(67,233,123,0.2);  color: var(--green); }
.badge-yellow  { background: rgba(249,199,79,0.2);  color: var(--yellow); }
.badge-red     { background: rgba(192,57,43,0.2);   color: #e74c3c; }
.badge-muted   { background: rgba(136,146,164,0.15); color: var(--muted); }

/* ── STATS GRID ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}
.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
}
.stat-card h3 { color: var(--muted); font-size: 0.78rem; text-transform: uppercase;
    letter-spacing: 1px; margin-bottom: 14px; }
.stat-num { font-size: 2.5rem; font-weight: 700; font-family: var(--font); color: var(--accent); }
canvas { max-height: 220px; }

/* ── Responsive ── */
@media (max-width: 700px) {
    .form-grid { grid-template-columns: 1fr; }
    .form-grid .full-width { grid-column: 1; }
    header h1 { font-size: 1rem; }
}
</style>
</head>
<body>

<header>
    <div>
        <h1>// PHP Learning App</h1>
        <div class="subtitle">CRUD Reference — Single-file PHP + MySQL</div>
    </div>
</header>

<div class="container">

<?php if ($message): ?>
<!-- ── MESSAGE BANNER ─────────────────────────────────────── -->
<!-- $message is set in the PHP block above after each action. -->
<div class="msg"><?= $message ?></div>
<?php endif; ?>


<!-- ══════════════════════════════════════════════════════════
     SECTION A — THE INPUT FORM
     
     enctype="multipart/form-data" is REQUIRED when the form
     includes a file upload. Without it, $_FILES will be empty.
     
     The hidden 'action' field tells the PHP backend what to do.
     We swap it between 'add' and 'edit' via JavaScript.
═══════════════════════════════════════════════════════════ -->
<div class="section-title">📝 A — Input Form (Add / Edit)</div>

<div class="card">
    <form method="POST" enctype="multipart/form-data" id="main-form">

        <!-- Hidden fields — these are NOT shown to the user but
             are submitted with the form. Crucial for routing. -->
        <input type="hidden" name="action" id="form-action" value="<?= $edit_project ? 'edit' : 'add' ?>">
        <input type="hidden" name="id"     id="form-id"     value="<?= $edit_project['id'] ?? '' ?>">
        <!-- We keep the old image path so it isn't lost on edit when no new image is chosen. -->
        <input type="hidden" name="existing_image" value="<?= htmlspecialchars($edit_project['image_path'] ?? '') ?>">

        <div class="form-grid">

            <!-- ── A1. SINGLE-LINE TEXT INPUT ──────────────────
                 <input type="text"> — best for short strings.
                 'required' is HTML5 validation (client-side).
                 We also validate on the server side (trim check). -->
            <div class="form-group full-width">
                <label for="title">Project Title <span style="color:var(--accent2)">*</span></label>
                <input type="text"
                       id="title"
                       name="title"
                       placeholder="e.g. Food Log Plugin"
                       required
                       value="<?= htmlspecialchars($edit_project['title'] ?? '') ?>">
                <!-- htmlspecialchars() prevents XSS by escaping
                     characters like < > & when re-printing user data. -->
                <span class="hint">Single-line text → &lt;input type="text"&gt;.
                    Stored as VARCHAR(255) in MySQL.</span>
            </div>

            <!-- ── A2. MULTI-LINE TEXT FIELD ───────────────────
                 <textarea> — no 'value' attribute; content goes
                 between the opening and closing tags instead. -->
            <div class="form-group full-width">
                <label for="description">Description / Notes</label>
                <textarea id="description"
                          name="description"
                          placeholder="Describe the project…"><?= htmlspecialchars($edit_project['description'] ?? '') ?></textarea>
                <span class="hint">Multi-line text → &lt;textarea&gt;.
                    Stored as TEXT in MySQL (up to 65,535 chars).</span>
            </div>

            <!-- ── A3. DROPDOWN FROM DATABASE ──────────────────
                 We fetch rows from la_genres above and loop
                 through them here with a PHP while() loop.
                 'selected' highlights the right option on edit. -->
            <div class="form-group">
                <label for="genre_id">Genre (from DB)</label>
                <select id="genre_id" name="genre_id">
                    <option value="">— Select a genre —</option>
                    <?php
                    // Reset pointer in case we already iterated this result elsewhere.
                    mysqli_data_seek($genres, 0);
                    while ($g = mysqli_fetch_assoc($genres)):
                        $selected = ($edit_project && $edit_project['genre_id'] == $g['id']) ? 'selected' : '';
                    ?>
                    <option value="<?= $g['id'] ?>" <?= $selected ?>><?= htmlspecialchars($g['name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <span class="hint">Single &lt;select&gt; fed from la_genres table.
                    Stores the genre's ID (foreign key) in la_projects.</span>
            </div>

            <!-- ── A4. HARD-CODED DROPDOWN ─────────────────────
                 When options are fixed and won't change, hard-
                 coding them in the PHP array is perfectly fine
                 and avoids an unnecessary database table. -->
            <div class="form-group">
                <label for="status">Status (hard-coded)</label>
                <select id="status" name="status">
                    <option value="">— Select status —</option>
                    <?php foreach ($status_options as $opt):
                        $selected = ($edit_project && $edit_project['status'] === $opt) ? 'selected' : '';
                    ?>
                    <option value="<?= $opt ?>" <?= $selected ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">Options are defined in the $status_options PHP array in the code.
                    Stored as VARCHAR in MySQL.</span>
            </div>

            <!-- ── A5. MULTI-SELECT DROPDOWN FROM DATABASE ─────
                 Add the 'multiple' attribute to allow multi picks.
                 The name MUST end with [] to send an array.
                 Users hold Ctrl/Cmd to select more than one. -->
            <div class="form-group full-width">
                <label for="tags">Tags / Tech Stack (multi-select from DB)</label>
                <select id="tags" name="tags[]" multiple>
                    <?php
                    mysqli_data_seek($tags, 0);
                    while ($t = mysqli_fetch_assoc($tags)):
                        // in_array() checks if this tag was already saved for this project.
                        $selected = in_array($t['id'], $edit_tag_ids) ? 'selected' : '';
                    ?>
                    <option value="<?= $t['id'] ?>" <?= $selected ?>><?= htmlspecialchars($t['name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <span class="hint">Hold <kbd>Ctrl</kbd> (Windows) or <kbd>⌘</kbd> (Mac) to pick multiple.
                    Saved to la_project_tags junction table. Each selection
                    = one row linking this project to a tag.</span>
            </div>

            <!-- ── A6. CHECKBOXES ──────────────────────────────
                 Note: checkboxes NOT checked are NOT sent in
                 $_POST at all — that's why we use isset() on
                 the server side. Each needs a unique name. -->
            <div class="form-group full-width">
                <label>Flags / Checkboxes</label>
                <div class="checkbox-group">
                    <label class="checkbox-item">
                        <input type="checkbox" name="is_featured"
                               <?= ($edit_project && $edit_project['is_featured']) ? 'checked' : '' ?>>
                        <span>⭐ Featured project</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="is_archived"
                               <?= ($edit_project && $edit_project['is_archived']) ? 'checked' : '' ?>>
                        <span>📦 Archived</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" name="has_notes"
                               <?= ($edit_project && $edit_project['has_notes']) ? 'checked' : '' ?>>
                        <span>📝 Has separate notes file</span>
                    </label>
                </div>
                <span class="hint">Stored as TINYINT(1) in MySQL — 1 = checked, 0 = unchecked.
                    Unchecked boxes send nothing, so use isset($_POST['name']) ? 1 : 0.</span>
            </div>

            <!-- ── A7. IMAGE / FILE UPLOAD ─────────────────────
                 <input type="file"> lets the user browse their
                 device. The file itself is in $_FILES on submit.
                 We store the resulting file PATH (not the file
                 itself) as a string in the database. -->
            <div class="form-group full-width">
                <label for="image">Cover Image (upload to /attachments/)</label>
                <?php if (!empty($edit_project['image_path'])): ?>
                    <div style="margin-bottom:8px">
                        <img src="<?= htmlspecialchars($edit_project['image_path']) ?>"
                             style="height:60px;border-radius:6px;border:1px solid var(--border)"
                             alt="Current image">
                        <span style="color:var(--muted);font-size:0.8rem;margin-left:8px">
                            Current: <?= htmlspecialchars($edit_project['image_path']) ?>
                        </span>
                    </div>
                <?php endif; ?>
                <input type="file" id="image" name="image" accept="image/*">
                <span class="hint">Saved to /attachments/ on the server.
                    The file PATH is stored in la_projects.image_path.
                    NEVER store binary file data directly in MySQL — always store the path.</span>
            </div>

        </div><!-- /.form-grid -->

        <div style="margin-top:20px;display:flex;gap:10px;align-items:center">
            <button type="submit" class="btn btn-primary" id="submit-btn">
                <?= $edit_project ? '💾 Save Changes' : '➕ Add Project' ?>
            </button>
            <?php if ($edit_project): ?>
            <a href="?" class="btn btn-ghost">✕ Cancel Edit</a>
            <?php endif; ?>
        </div>

    </form>
</div><!-- /.card -->


<!-- ══════════════════════════════════════════════════════════
     SECTION B — ENTRIES VIEW  (Table + Cards)
     
     Two views share the same data — a JS toggle switches
     between them by changing CSS display properties.
═══════════════════════════════════════════════════════════ -->
<div class="section-title">📋 B — Entries (Table View &amp; Card View)</div>

<!-- View toggle -->
<div class="view-toggle">
    <button class="active" onclick="switchView('table', this)">🗂 Table</button>
    <button onclick="switchView('cards', this)">🃏 Cards</button>
</div>

<!-- ── B1. TABLE VIEW ───────────────────────────────────────
     A standard HTML <table>. Good for dense data comparison.
     We echo PHP variables inside td tags. -->
<div id="view-table">
<div class="card" style="padding:0;overflow:auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Title</th>
                <th>Genre</th>
                <th>Status</th>
                <th>Tags</th>
                <th>Flags</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        // Reset result pointer so we can loop through again.
        mysqli_data_seek($projects, 0);

        // Loop through every project row.
        while ($p = mysqli_fetch_assoc($projects)):
            // Build the tags string for this project from our pre-fetched array.
            $ptags = $all_project_tags[$p['id']] ?? [];
        ?>
        <tr>
            <td style="color:var(--muted);font-family:var(--font)"><?= $p['id'] ?></td>
            <td>
                <?php if ($p['image_path']): ?>
                    <img src="<?= htmlspecialchars($p['image_path']) ?>"
                         alt="<?= htmlspecialchars($p['title']) ?>">
                <?php else: ?>
                    <div style="width:48px;height:48px;background:#1e2130;border-radius:6px;
                                display:flex;align-items:center;justify-content:center;color:var(--muted)">—</div>
                <?php endif; ?>
            </td>
            <td style="font-weight:600"><?= htmlspecialchars($p['title']) ?></td>
            <td><?= htmlspecialchars($p['genre_name'] ?? '—') ?></td>
            <td><?= badge_status($p['status']) ?></td>
            <td>
                <?php foreach ($ptags as $tname): ?>
                    <span class="badge badge-muted"><?= htmlspecialchars($tname) ?></span>
                <?php endforeach; ?>
            </td>
            <td>
                <?php if ($p['is_featured']) echo '<span title="Featured">⭐</span> '; ?>
                <?php if ($p['is_archived']) echo '<span title="Archived">📦</span> '; ?>
                <?php if ($p['has_notes'])   echo '<span title="Has Notes">📝</span>'; ?>
            </td>
            <td style="color:var(--muted);font-size:0.8rem;white-space:nowrap">
                <?= date('M j, Y', strtotime($p['created_at'])) ?>
            </td>
            <td style="white-space:nowrap">
                <!-- EDIT: we pass the row ID in the URL (?edit_id=N).
                     The PHP at the top loads that row into $edit_project. -->
                <a href="?edit_id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">✏️ Edit</a>

                <!-- DELETE: a small form so we can POST the id safely.
                     We use a JS confirm() to prevent accidental deletes. -->
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Delete this project?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id"     value="<?= $p['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">🗑 Del</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</div><!-- /#view-table -->


<!-- ── B2. CARD VIEW ────────────────────────────────────────
     Cards give a richer visual layout, good for image-heavy
     content. We loop through the SAME data again. -->
<div id="view-cards" class="card-grid">
    <?php
    mysqli_data_seek($projects, 0);
    while ($p = mysqli_fetch_assoc($projects)):
        $ptags = $all_project_tags[$p['id']] ?? [];
    ?>
    <div class="proj-card">
        <!-- Cover image or placeholder -->
        <?php if ($p['image_path']): ?>
            <img class="proj-card-img" src="<?= htmlspecialchars($p['image_path']) ?>"
                 alt="<?= htmlspecialchars($p['title']) ?>">
        <?php else: ?>
            <div class="proj-card-img" style="display:flex;align-items:center;
                 justify-content:center;color:var(--muted);font-size:2rem">📁</div>
        <?php endif; ?>

        <div class="proj-card-body">
            <div class="proj-card-title"><?= htmlspecialchars($p['title']) ?></div>
            <div class="proj-card-desc">
                <?= htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 80, '…')) ?>
                <!-- mb_strimwidth() truncates text safely for display. -->
            </div>
            <div class="proj-card-meta">
                <?= badge_status($p['status']) ?>
                <?php if ($p['genre_name']): ?>
                    <span class="badge badge-accent"><?= htmlspecialchars($p['genre_name']) ?></span>
                <?php endif; ?>
                <?php foreach ($ptags as $tname): ?>
                    <span class="badge badge-muted"><?= htmlspecialchars($tname) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="proj-card-actions">
                <a href="?edit_id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">✏️ Edit</a>
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Delete this project?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id"     value="<?= $p['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                </form>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div><!-- /#view-cards -->


<!-- ══════════════════════════════════════════════════════════
     SECTION C — STATISTICS
     
     We use Chart.js (loaded via CDN) to draw charts.
     Chart.js works with <canvas> elements.
     Data comes from the SQL queries in SECTION 4 above.
═══════════════════════════════════════════════════════════ -->
<div class="section-title" style="margin-top:32px">📊 C — Visual Statistics (Chart.js)</div>

<div class="stats-grid">

    <!-- ── C1. SUMMARY NUMBER BOXES ───────────────────────── -->
    <?php
    $total      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM la_projects"))['n'];
    $featured   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM la_projects WHERE is_featured=1"))['n'];
    $complete   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM la_projects WHERE status='Complete'"))['n'];
    ?>
    <div class="stat-card" style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">
        <div><div style="color:var(--muted);font-size:0.75rem;text-transform:uppercase;letter-spacing:1px">Total</div>
             <div class="stat-num"><?= $total ?></div></div>
        <div><div style="color:var(--muted);font-size:0.75rem;text-transform:uppercase;letter-spacing:1px">Featured</div>
             <div class="stat-num" style="color:var(--yellow)"><?= $featured ?></div></div>
        <div><div style="color:var(--muted);font-size:0.75rem;text-transform:uppercase;letter-spacing:1px">Complete</div>
             <div class="stat-num" style="color:var(--green)"><?= $complete ?></div></div>
    </div>

    <!-- ── C2. PIE CHART — Status breakdown ────────────────
         We collect the PHP data into JS arrays using json_encode().
         json_encode() converts a PHP array to a valid JSON string. -->
    <div class="stat-card">
        <h3>Status Breakdown</h3>
        <canvas id="chartStatus"></canvas>
    </div>

    <!-- ── C3. BAR CHART — Projects per genre ────────────── -->
    <div class="stat-card">
        <h3>Projects by Genre</h3>
        <canvas id="chartGenre"></canvas>
    </div>

    <!-- ── C4. LINE CHART — Monthly activity ─────────────── -->
    <div class="stat-card" style="grid-column: 1 / -1">
        <h3>Monthly Adds (Last 6 Months)</h3>
        <canvas id="chartMonthly"></canvas>
    </div>

</div><!-- /.stats-grid -->


<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT SECTION
     Handles:
       1. View toggle (table ↔ cards)
       2. Chart.js chart rendering
       3. Scrolling to form when editing
═══════════════════════════════════════════════════════════ -->
<script>
// ── 1. View Toggle ───────────────────────────────────────────
// We toggle CSS display between the table and card grid.
function switchView(view, btn) {
    document.getElementById('view-table').style.display = view === 'table' ? 'block' : 'none';
    document.getElementById('view-cards').style.display = view === 'cards' ? 'grid'  : 'none';

    // Update active button style
    document.querySelectorAll('.view-toggle button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

// If we're in edit mode, scroll the form into view automatically.
<?php if ($edit_project): ?>
document.getElementById('main-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
<?php endif; ?>


// ── 2. Chart.js ─────────────────────────────────────────────
// json_encode() converts PHP arrays to JavaScript arrays.
// We print them directly into the JS source.

// -- Status chart data --
<?php
$status_labels = [];
$status_data   = [];
mysqli_data_seek($stat_status, 0);
while ($row = mysqli_fetch_assoc($stat_status)) {
    $status_labels[] = $row['status'];
    $status_data[]   = (int)$row['cnt'];
}
?>
const statusLabels = <?= json_encode($status_labels) ?>;
const statusData   = <?= json_encode($status_data) ?>;

// -- Genre chart data --
<?php
$genre_labels = [];
$genre_data   = [];
mysqli_data_seek($stat_genre, 0);
while ($row = mysqli_fetch_assoc($stat_genre)) {
    $genre_labels[] = $row['name'];
    $genre_data[]   = (int)$row['cnt'];
}
?>
const genreLabels = <?= json_encode($genre_labels) ?>;
const genreData   = <?= json_encode($genre_data) ?>;

// -- Monthly chart data --
<?php
$month_labels = [];
$month_data   = [];
mysqli_data_seek($stat_monthly, 0);
while ($row = mysqli_fetch_assoc($stat_monthly)) {
    $month_labels[] = $row['month_label'];
    $month_data[]   = (int)$row['cnt'];
}
?>
const monthLabels = <?= json_encode($month_labels) ?>;
const monthData   = <?= json_encode($month_data) ?>;

// Chart.js global defaults
Chart.defaults.color           = '#8892a4';
Chart.defaults.borderColor     = '#2a2d3e';
Chart.defaults.font.family     = "'Segoe UI', sans-serif";

// ── PIE CHART ───────────────────────────────────────────────
// Good for showing how parts relate to a whole.
new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',   // 'pie' also works; doughnut has a hole
    data: {
        labels: statusLabels.length ? statusLabels : ['No data'],
        datasets: [{
            data: statusData.length ? statusData : [1],
            backgroundColor: ['#6c63ff','#43e97b','#f9c74f','#ff6584','#8892a4'],
            borderWidth: 2,
            borderColor: '#1a1d27',
        }]
    },
    options: {
        plugins: { legend: { position: 'bottom', labels: { padding: 14 } } }
    }
});

// ── BAR CHART ───────────────────────────────────────────────
// Great for comparing counts across categories.
new Chart(document.getElementById('chartGenre'), {
    type: 'bar',
    data: {
        labels: genreLabels,
        datasets: [{
            label: 'Projects',
            data: genreData,
            backgroundColor: 'rgba(108,99,255,0.7)',
            borderColor: '#6c63ff',
            borderWidth: 1,
            borderRadius: 5,
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});

// ── LINE CHART ──────────────────────────────────────────────
// Shows trends over time — great for activity or counts.
new Chart(document.getElementById('chartMonthly'), {
    type: 'line',
    data: {
        labels: monthLabels.length ? monthLabels : ['No data'],
        datasets: [{
            label: 'Projects added',
            data: monthData.length ? monthData : [0],
            borderColor: '#43e97b',
            backgroundColor: 'rgba(67,233,123,0.08)',
            tension: 0.35,   // curve smoothing 0=straight, 1=very curved
            fill: true,
            pointBackgroundColor: '#43e97b',
            pointRadius: 5,
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});
</script>

</div><!-- /.container -->

<?php
// ════════════════════════════════════════════════════════════
//  HELPER FUNCTION — badge_status()
//  Returns a colored badge HTML string based on status value.
//  Defined here (after the HTML) because PHP doesn't care where
//  functions are defined as long as they're called at runtime.
//  (But convention is to put helpers at the top.)
// ════════════════════════════════════════════════════════════
function badge_status($status) {
    $map = [
        'Complete'    => 'badge-green',
        'In Progress' => 'badge-accent',
        'Planning'    => 'badge-yellow',
        'On Hold'     => 'badge-yellow',
        'Abandoned'   => 'badge-red',
    ];
    if (!$status) return '<span class="badge badge-muted">—</span>';
    $class = $map[$status] ?? 'badge-muted';
    return '<span class="badge ' . $class . '">' . htmlspecialchars($status) . '</span>';
}

// Always close the database connection at the end.
mysqli_close($conn);
?>

</body>
</html>
