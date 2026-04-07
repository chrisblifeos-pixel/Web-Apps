<?php
// ============================================================
// DATABASE CONFIG & CONNECTION
// ============================================================
define('DB_HOST', 'sql105.byethost10.com');
define('DB_USER', 'b10_39913602');
define('DB_PASS', 'Cr0ssfire');
define('DB_NAME', 'b10_39913602_activitylogs');

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
            die(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ============================================================
// TABLE DEFINITIONS - auto-create on first run
// ============================================================
$TABLE_SCHEMAS = [
    'bp' => "CREATE TABLE IF NOT EXISTS `log_bp` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `time` TIME, `name` VARCHAR(100),
        `systolic` INT, `diastolic` INT, `pulse` INT,
        `status` VARCHAR(50), `arm` VARCHAR(20),
        `position` VARCHAR(50), `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cigarette' => "CREATE TABLE IF NOT EXISTS `log_cigarette` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `time` TIME, `brand` VARCHAR(100),
        `type` VARCHAR(50), `size` VARCHAR(30),
        `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'device' => "CREATE TABLE IF NOT EXISTS `log_device` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `device` VARCHAR(100),
        `start_time` TIME, `pct_start` INT,
        `end_time` TIME, `pct_end` INT,
        `full_charge` VARCHAR(5), `amt_charged` INT,
        `duration` VARCHAR(30), `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'exercise' => "CREATE TABLE IF NOT EXISTS `log_exercise` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `start_time` TIME,
        `ex_type` VARCHAR(50), `exercise` VARCHAR(100),
        `sets` INT, `reps` INT, `details` TEXT,
        `end_time` TIME, `rating` INT,
        `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'food' => "CREATE TABLE IF NOT EXISTS `log_food` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `time` TIME, `meal` VARCHAR(30),
        `prepared_by` VARCHAR(50), `main_dish` VARCHAR(150),
        `sides` TEXT, `rating` INT,
        `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'glucose' => "CREATE TABLE IF NOT EXISTS `log_glucose` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `time` TIME, `name` VARCHAR(100),
        `glucose` INT, `context` VARCHAR(30),
        `status` VARCHAR(50), `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'hygiene' => "CREATE TABLE IF NOT EXISTS `log_hygiene` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `time` TIME, `activity` VARCHAR(100),
        `details` TEXT, `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'medication' => "CREATE TABLE IF NOT EXISTS `log_medication` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `time` TIME, `medication` VARCHAR(100),
        `dosage` VARCHAR(30), `melt_start` TIME, `melt_end` TIME,
        `melt_dur` VARCHAR(30), `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'sexual' => "CREATE TABLE IF NOT EXISTS `log_sexual` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `partner` VARCHAR(5), `partner_name` VARCHAR(100),
        `activities` TEXT, `protection` VARCHAR(5),
        `toys` VARCHAR(5), `duration` VARCHAR(50),
        `rating` INT, `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'sleep' => "CREATE TABLE IF NOT EXISTS `log_sleep` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `sleep_time` TIME, `wake_time` TIME,
        `duration` VARCHAR(30), `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'steps' => "CREATE TABLE IF NOT EXISTS `log_steps` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `watch` INT, `ring` INT, `steps` INT,
        `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'fueloil' => "CREATE TABLE IF NOT EXISTS `log_fueloil` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `pu_date` DATE, `pu_time` TIME, `pu_by` VARCHAR(100),
        `vendor` VARCHAR(100), `gallons` DECIMAL(8,2),
        `balance` DECIMAL(10,2), `add_date` DATE, `add_time` TIME,
        `added_by` VARCHAR(100), `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'lptank' => "CREATE TABLE IF NOT EXISTS `log_lptank` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `time` TIME, `swapped_with` VARCHAR(30),
        `swapped_by` VARCHAR(100), `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'travel' => "CREATE TABLE IF NOT EXISTS `log_travel` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `start_loc` VARCHAR(255),
        `start_time` TIME, `arrival_time` TIME,
        `destination` VARCHAR(255), `distance` DECIMAL(8,1),
        `duration` VARCHAR(30), `reason` TEXT, `comments` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'weight' => "CREATE TABLE IF NOT EXISTS `log_weight` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `date` DATE, `time` TIME, `name` VARCHAR(100),
        `weight` DECIMAL(6,1), `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'vape' => "CREATE TABLE IF NOT EXISTS `log_vape` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `brand` VARCHAR(100), `strain` VARCHAR(100),
        `size` VARCHAR(20), `strength` INT,
        `prod_date` DATE, `best_by` DATE,
        `rating` INT, `comments` TEXT,
        `pur_date` DATE, `price` DECIMAL(8,2), `dispensary` VARCHAR(100),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'chore' => "CREATE TABLE IF NOT EXISTS `log_chore` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `household` VARCHAR(100), `chore` VARCHAR(150),
        `assigned_to` VARCHAR(100), `assigned_date` DATE,
        `due_date` DATE, `completed` VARCHAR(5),
        `comments` TEXT, `location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

function initTables() {
    global $TABLE_SCHEMAS;
    $db = getDB();
    foreach ($TABLE_SCHEMAS as $sql) {
        $db->exec($sql);
    }
}

// Allowed columns per table (whitelist for safety)
$TABLE_COLUMNS = [
    'bp'         => ['date','time','name','systolic','diastolic','pulse','status','arm','position','comments','location'],
    'cigarette'  => ['date','time','brand','type','size','comments','location'],
    'device'     => ['date','device','start_time','pct_start','end_time','pct_end','full_charge','amt_charged','duration','comments','location'],
    'exercise'   => ['date','start_time','ex_type','exercise','sets','reps','details','end_time','rating','comments','location'],
    'food'       => ['date','time','meal','prepared_by','main_dish','sides','rating','comments','location'],
    'glucose'    => ['date','time','name','glucose','context','status','comments','location'],
    'hygiene'    => ['date','time','activity','details','comments','location'],
    'medication' => ['date','time','medication','dosage','melt_start','melt_end','melt_dur','comments','location'],
    'sexual'     => ['date','partner','partner_name','activities','protection','toys','duration','rating','comments','location'],
    'sleep'      => ['date','sleep_time','wake_time','duration','comments','location'],
    'steps'      => ['date','watch','ring','steps','comments','location'],
    'fueloil'    => ['pu_date','pu_time','pu_by','vendor','gallons','balance','add_date','add_time','added_by','comments','location'],
    'lptank'     => ['date','time','swapped_with','swapped_by','comments','location'],
    'travel'     => ['date','start_loc','start_time','arrival_time','destination','distance','duration','reason','comments'],
    'weight'     => ['date','time','name','weight','comments','location'],
    'vape'       => ['brand','strain','size','strength','prod_date','best_by','rating','comments','pur_date','price','dispensary'],
    'chore'      => ['household','chore','assigned_to','assigned_date','due_date','completed','comments','location'],
];

// ============================================================
// API HANDLER
// ============================================================
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    initTables();

    $action = $_GET['api'] ?? '';
    $logKey = preg_replace('/[^a-z]/', '', strtolower($_GET['log'] ?? ''));

    global $TABLE_COLUMNS;
    $allowed = $TABLE_COLUMNS[$logKey] ?? null;

    if ($action === 'counts') {
        // Return counts for all logs
        $db = getDB();
        $counts = [];
        foreach ($TABLE_COLUMNS as $key => $cols) {
            try {
                $stmt = $db->query("SELECT COUNT(*) as c FROM `log_{$key}`");
                $counts[$key] = (int)$stmt->fetch()['c'];
            } catch (Exception $e) {
                $counts[$key] = 0;
            }
        }
        echo json_encode($counts);
        exit;
    }

    if ($action === 'list' && $allowed) {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM `log_{$logKey}` ORDER BY id DESC LIMIT 500");
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'save' && $allowed) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { echo json_encode(['error'=>'No data']); exit; }

        $id = isset($data['id']) ? (int)$data['id'] : 0;
        unset($data['id'], $data['created_at']);

        // Filter to allowed columns only
        $filtered = array_filter($data, fn($k) => in_array($k, $allowed), ARRAY_FILTER_USE_KEY);

        // Sanitize empty strings to null for numeric/date fields
        foreach ($filtered as $k => &$v) {
            if ($v === '' || $v === null) $v = null;
        }
        unset($v);

        $db = getDB();
        if ($id > 0) {
            // UPDATE
            $sets = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($filtered)));
            $filtered['id'] = $id;
            $stmt = $db->prepare("UPDATE `log_{$logKey}` SET {$sets} WHERE id = :id");
        } else {
            // INSERT
            $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($filtered)));
            $vals = implode(', ', array_map(fn($k) => ":$k", array_keys($filtered)));
            $stmt = $db->prepare("INSERT INTO `log_{$logKey}` ({$cols}) VALUES ({$vals})");
        }
        $stmt->execute($filtered);
        $newId = $id > 0 ? $id : $db->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId]);
        exit;
    }

    if ($action === 'delete' && $allowed) {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { echo json_encode(['error'=>'No ID']); exit; }
        $db = getDB();
        $db->prepare("DELETE FROM `log_{$logKey}` WHERE id = :id")->execute([':id' => $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'clear' && $allowed) {
        $db = getDB();
        $db->exec("DELETE FROM `log_{$logKey}`");
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// First time: ensure tables exist when loading the page too
try { initTables(); } catch (Exception $e) { /* silent */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Activity Logger</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --bg-primary: #0a0c10;
  --bg-secondary: #111318;
  --bg-card: #161a22;
  --bg-elevated: #1d2230;
  --accent-blue: #3d8ef0;
  --accent-teal: #00c9a7;
  --accent-purple: #9b6dff;
  --accent-orange: #ff7043;
  --accent-red: #f44336;
  --accent-green: #4caf50;
  --accent-yellow: #ffc107;
  --accent-pink: #e91e8c;
  --text-primary: #e8eaf0;
  --text-secondary: #8892a4;
  --text-muted: #4a5568;
  --border: #252d3d;
  --border-light: #2d3748;
  --radius: 14px;
  --radius-sm: 8px;
  --shadow: 0 4px 24px rgba(0,0,0,0.4);
  --shadow-lg: 0 8px 40px rgba(0,0,0,0.6);
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Exo 2',sans-serif;background:var(--bg-primary);color:var(--text-primary);min-height:100vh;overflow-x:hidden;}
h1,h2,h3,h4{font-family:'Rajdhani',sans-serif;letter-spacing:0.5px;}
::-webkit-scrollbar{width:6px;height:6px;}
::-webkit-scrollbar-track{background:var(--bg-secondary);}
::-webkit-scrollbar-thumb{background:var(--border-light);border-radius:3px;}
.screen{display:none;min-height:100vh;}
.screen.active{display:block;}
.topbar{background:var(--bg-secondary);border-bottom:1px solid var(--border);padding:12px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.topbar-title{font-family:'Rajdhani',sans-serif;font-size:1.4rem;font-weight:700;color:var(--accent-blue);}
.topbar-actions{display:flex;gap:10px;}
.btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:24px;border:none;cursor:pointer;font-family:'Exo 2',sans-serif;font-weight:600;font-size:0.85rem;transition:all 0.2s;text-decoration:none;white-space:nowrap;}
.btn-primary{background:linear-gradient(135deg,var(--accent-blue),#2563eb);color:#fff;box-shadow:0 2px 12px rgba(61,142,240,0.35);}
.btn-primary:hover{box-shadow:0 4px 20px rgba(61,142,240,0.55);transform:translateY(-1px);}
.btn-teal{background:linear-gradient(135deg,var(--accent-teal),#009688);color:#fff;box-shadow:0 2px 12px rgba(0,201,167,0.3);}
.btn-teal:hover{box-shadow:0 4px 20px rgba(0,201,167,0.5);transform:translateY(-1px);}
.btn-purple{background:linear-gradient(135deg,var(--accent-purple),#7c3aed);color:#fff;box-shadow:0 2px 12px rgba(155,109,255,0.3);}
.btn-purple:hover{box-shadow:0 4px 20px rgba(155,109,255,0.5);transform:translateY(-1px);}
.btn-orange{background:linear-gradient(135deg,var(--accent-orange),#e64a19);color:#fff;box-shadow:0 2px 12px rgba(255,112,67,0.3);}
.btn-red{background:linear-gradient(135deg,var(--accent-red),#c62828);color:#fff;box-shadow:0 2px 12px rgba(244,67,54,0.3);}
.btn-red:hover{box-shadow:0 4px 20px rgba(244,67,54,0.5);transform:translateY(-1px);}
.btn-green{background:linear-gradient(135deg,var(--accent-green),#388e3c);color:#fff;box-shadow:0 2px 12px rgba(76,175,80,0.3);}
.btn-green:hover{box-shadow:0 4px 20px rgba(76,175,80,0.5);transform:translateY(-1px);}
.btn-yellow{background:linear-gradient(135deg,var(--accent-yellow),#f57c00);color:#111;box-shadow:0 2px 12px rgba(255,193,7,0.3);}
.btn-ghost{background:transparent;color:var(--text-secondary);border:1px solid var(--border);}
.btn-ghost:hover{background:var(--bg-elevated);color:var(--text-primary);}
.btn-sm{padding:6px 14px;font-size:0.78rem;border-radius:20px;}
.btn-icon{padding:8px 12px;}
.dash-container{padding:20px;max-width:1200px;margin:0 auto;}
.dash-header{margin-bottom:24px;text-align:center;}
.dash-header h1{font-size:2.2rem;background:linear-gradient(135deg,var(--accent-blue),var(--accent-teal));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.dash-header p{color:var(--text-secondary);font-size:0.9rem;margin-top:4px;}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px;}
.stat-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:16px;text-align:center;}
.stat-card .stat-val{font-family:'Rajdhani',sans-serif;font-size:2rem;font-weight:700;color:var(--accent-blue);}
.stat-card .stat-lbl{font-size:0.75rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;}
.log-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:16px;margin-bottom:24px;}
.log-btn{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:20px 14px;text-align:center;cursor:pointer;transition:all 0.2s;display:flex;flex-direction:column;align-items:center;gap:10px;position:relative;overflow:hidden;}
.log-btn::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,transparent,rgba(255,255,255,0.02));pointer-events:none;}
.log-btn:hover{transform:translateY(-2px);box-shadow:var(--shadow);border-color:var(--accent-blue);}
.log-btn .log-icon{font-size:2.2rem;line-height:1;}
.log-btn .log-label{font-family:'Rajdhani',sans-serif;font-size:0.95rem;font-weight:600;color:var(--text-primary);}
.log-btn .log-count{font-size:0.72rem;color:var(--text-muted);background:var(--bg-elevated);padding:2px 8px;border-radius:10px;}
.sub-header{display:flex;align-items:center;gap:14px;margin-bottom:20px;flex-wrap:wrap;}
.sub-header-icon{font-size:2rem;}
.sub-header h2{font-size:1.8rem;font-weight:700;}
.sub-actions{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.sub-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:24px;}
.sub-stat{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;text-align:center;}
.sub-stat .sv{font-family:'Rajdhani',sans-serif;font-size:1.7rem;font-weight:700;}
.sub-stat .sl{font-size:0.72rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;}
.chart-area{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:20px;height:200px;display:flex;align-items:flex-end;gap:4px;overflow:hidden;}
.chart-bar{flex:1;background:linear-gradient(180deg,var(--accent-blue),#1a4fa3);border-radius:4px 4px 0 0;min-height:4px;transition:height 0.5s ease;position:relative;}
.chart-bar:hover::after{content:attr(data-val);position:absolute;top:-22px;left:50%;transform:translateX(-50%);background:var(--bg-elevated);color:var(--text-primary);font-size:0.7rem;padding:2px 6px;border-radius:4px;white-space:nowrap;}
.table-wrap{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);overflow:auto;}
.data-table{width:100%;border-collapse:collapse;font-size:0.83rem;}
.data-table th{background:var(--bg-elevated);color:var(--text-secondary);text-transform:uppercase;font-size:0.7rem;letter-spacing:0.5px;padding:10px 12px;text-align:left;border-bottom:1px solid var(--border);font-family:'Rajdhani',sans-serif;font-weight:600;}
.data-table td{padding:9px 12px;border-bottom:1px solid var(--border);color:var(--text-primary);vertical-align:middle;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tr:hover td{background:var(--bg-elevated);}
.data-table .act-btns{display:flex;gap:6px;}
.empty-state{text-align:center;padding:40px;color:var(--text-muted);font-size:0.9rem;}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:1000;align-items:center;justify-content:center;padding:16px;}
.modal-overlay.open{display:flex;}
.modal{background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);width:100%;max-width:520px;max-height:92vh;overflow-y:auto;box-shadow:var(--shadow-lg);}
.modal-wide{max-width:860px;}
.modal-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--bg-secondary);z-index:1;}
.modal-header h3{font-size:1.2rem;font-weight:700;}
.modal-close{background:none;border:none;color:var(--text-secondary);font-size:1.4rem;cursor:pointer;padding:4px 8px;border-radius:6px;transition:color 0.2s;}
.modal-close:hover{color:var(--text-primary);}
.modal-body{padding:20px;}
.modal-footer{padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;}
.form-group{margin-bottom:14px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
label{display:block;font-size:0.78rem;color:var(--text-secondary);margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px;font-weight:600;}
input,select,textarea{width:100%;background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-primary);padding:9px 12px;font-family:'Exo 2',sans-serif;font-size:0.88rem;transition:border-color 0.2s;outline:none;}
input:focus,select:focus,textarea:focus{border-color:var(--accent-blue);}
textarea{resize:vertical;min-height:70px;}
select option{background:var(--bg-elevated);}
input[type=checkbox]{width:auto;accent-color:var(--accent-blue);}
.check-row{display:flex;align-items:center;gap:8px;}
.check-row input{width:18px;height:18px;}
.star-rating{display:flex;gap:4px;flex-direction:row-reverse;justify-content:flex-end;}
.star-rating input{display:none;}
.star-rating label{font-size:1.4rem;cursor:pointer;color:var(--text-muted);transition:color 0.15s;}
.star-rating input:checked ~ label,.star-rating label:hover,.star-rating label:hover ~ label{color:var(--accent-yellow);}
.chat-container{display:flex;flex-direction:column;height:calc(100vh - 60px);}
.chat-messages{flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:12px;}
.chat-msg{max-width:75%;padding:12px 16px;border-radius:var(--radius);font-size:0.88rem;line-height:1.5;}
.chat-msg.user{background:linear-gradient(135deg,var(--accent-blue),#2563eb);color:#fff;align-self:flex-end;border-radius:var(--radius) var(--radius) 4px var(--radius);}
.chat-msg.ai{background:var(--bg-card);border:1px solid var(--border);align-self:flex-start;border-radius:var(--radius) var(--radius) var(--radius) 4px;}
.chat-input-area{padding:16px;border-top:1px solid var(--border);display:flex;gap:10px;}
.chat-input-area input{flex:1;}
.chat-thinking{opacity:0.6;font-style:italic;}
.db-section{margin-bottom:20px;}
.db-section h4{font-size:1rem;margin-bottom:10px;color:var(--accent-teal);}
.db-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;}
.db-item{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;display:flex;flex-direction:column;gap:8px;}
.db-item-name{font-size:0.85rem;font-weight:600;}
.db-item-count{font-size:0.72rem;color:var(--text-muted);}
.db-item-btns{display:flex;gap:6px;}
.notif{position:fixed;bottom:20px;right:20px;background:var(--bg-elevated);border:1px solid var(--accent-teal);color:var(--text-primary);padding:12px 20px;border-radius:var(--radius);font-size:0.88rem;z-index:9999;transform:translateY(100px);opacity:0;transition:all 0.3s;box-shadow:var(--shadow);}
.notif.show{transform:translateY(0);opacity:1;}
.notif.error{border-color:var(--accent-red);}
.badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.3px;}
.badge-normal{background:rgba(76,175,80,0.2);color:#4caf50;}
.badge-elevated{background:rgba(255,193,7,0.2);color:#ffc107;}
.badge-high{background:rgba(255,112,67,0.2);color:#ff7043;}
.badge-crisis{background:rgba(244,67,54,0.2);color:#f44336;}
.badge-low{background:rgba(61,142,240,0.2);color:#3d8ef0;}
.db-status{font-size:0.72rem;padding:3px 10px;border-radius:10px;font-weight:600;}
.db-ok{background:rgba(76,175,80,0.15);color:#4caf50;border:1px solid rgba(76,175,80,0.3);}
.db-err{background:rgba(244,67,54,0.15);color:#f44336;border:1px solid rgba(244,67,54,0.3);}
@media(max-width:600px){
  .form-row{grid-template-columns:1fr;}
  .log-grid{grid-template-columns:repeat(auto-fill,minmax(140px,1fr));}
  .stats-row{grid-template-columns:repeat(2,1fr);}
  .dash-container{padding:12px;}
  .modal{max-height:98vh;}
}
</style>
</head>
<body>

<div class="notif" id="notif"></div>

<!-- ===== MAIN DASHBOARD ===== -->
<div class="screen active" id="screen-main">
  <div class="topbar">
    <div class="topbar-title">📊 Activity Logger</div>
    <div class="topbar-actions">
      <button class="btn btn-teal btn-sm" onclick="openChat()">🤖 Chat AI</button>
      <button class="btn btn-ghost btn-sm" onclick="openDbManager()">🗄️ Database</button>
    </div>
  </div>
  <div class="dash-container">
    <div class="dash-header">
      <h1>Activity Dashboard</h1>
      <p>Track your health, habits, and household in one place</p>
    </div>
    <div class="stats-row" id="main-stats"></div>
    <div class="log-grid" id="log-grid"></div>
  </div>
</div>

<!-- ===== SUB-DASHBOARD ===== -->
<div class="screen" id="screen-sub">
  <div class="topbar">
    <div class="topbar-title" id="sub-topbar-title">Log Dashboard</div>
    <div class="topbar-actions">
      <button class="btn btn-ghost btn-sm" onclick="goMain()">🏠 Main Dashboard</button>
    </div>
  </div>
  <div class="dash-container">
    <div class="sub-header">
      <div class="sub-header-icon" id="sub-icon"></div>
      <div>
        <h2 id="sub-title"></h2>
        <div style="color:var(--text-secondary);font-size:0.85rem;" id="sub-desc"></div>
      </div>
    </div>
    <div class="sub-actions">
      <button class="btn btn-primary" onclick="currentLog.openAddEntry()">➕ Add Entry</button>
      <button class="btn btn-ghost btn-sm" onclick="currentLog.refresh()">🔄 Refresh</button>
    </div>
    <div class="sub-stats" id="sub-stats-row"></div>
    <div id="sub-chart"></div>
    <div class="table-wrap">
      <table class="data-table" id="sub-table">
        <thead id="sub-thead"></thead>
        <tbody id="sub-tbody"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- ===== CHAT ===== -->
<div class="screen" id="screen-chat">
  <div class="topbar">
    <div class="topbar-title">🤖 AI Health Assistant</div>
    <div class="topbar-actions">
      <button class="btn btn-ghost btn-sm" onclick="goMain()">🏠 Dashboard</button>
    </div>
  </div>
  <div class="chat-container">
    <div class="chat-messages" id="chat-messages">
      <div class="chat-msg ai">👋 Hi! I'm your personal health &amp; activity assistant. Ask me anything about your logged data, patterns, or health insights!</div>
    </div>
    <div class="chat-input-area">
      <input type="text" id="chat-input" placeholder="Ask about your health data..." onkeydown="if(event.key==='Enter')sendChat()">
      <button class="btn btn-primary" onclick="sendChat()">Send</button>
    </div>
  </div>
</div>

<!-- ===== ADD/EDIT MODAL ===== -->
<div class="modal-overlay" id="modal-entry">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-entry-title">Add Entry</h3>
      <button class="modal-close" onclick="closeModal('modal-entry')">✕</button>
    </div>
    <div class="modal-body" id="modal-entry-body"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('modal-entry')">Cancel</button>
      <button class="btn btn-primary" onclick="saveEntry()">💾 Save</button>
    </div>
  </div>
</div>

<!-- ===== DB MANAGER MODAL ===== -->
<div class="modal-overlay" id="modal-db">
  <div class="modal modal-wide">
    <div class="modal-header">
      <h3>🗄️ Database Manager</h3>
      <button class="modal-close" onclick="closeModal('modal-db')">✕</button>
    </div>
    <div class="modal-body" id="modal-db-body"></div>
  </div>
</div>

<script>
// ============================================================
// API HELPERS
// ============================================================
async function apiCall(params, body=null) {
  const url = '?' + new URLSearchParams(params).toString();
  const opts = body
    ? { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) }
    : { method:'GET' };
  const r = await fetch(url, opts);
  return r.json();
}

// ============================================================
// UTILITY
// ============================================================
function today(){ return new Date().toISOString().slice(0,10); }
function nowTime(){ return new Date().toTimeString().slice(0,5); }
function notify(msg, err=false){
  const n=document.getElementById('notif');
  n.textContent=msg;
  n.className='notif'+(err?' error':'');
  n.classList.add('show');
  setTimeout(()=>n.classList.remove('show'),3000);
}
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
function showScreen(id){
  document.querySelectorAll('.screen').forEach(s=>s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
}
function goMain(){ showScreen('screen-main'); renderMainDash(); }
function openChat(){ showScreen('screen-chat'); }

function starRatingHTML(name){
  let h=`<div class="star-rating">`;
  for(let i=5;i>=1;i--) h+=`<input type="radio" id="${name}_${i}" name="${name}" value="${i}"><label for="${name}_${i}">★</label>`;
  return h+'</div>';
}
function getStarVal(name){
  const el=document.querySelector(`input[name="${name}"]:checked`);
  return el?el.value:'0';
}
function setStarVal(name,val){
  if(!val||val==='0')return;
  const el=document.getElementById(name+'_'+val);
  if(el)el.checked=true;
}
function toggleOther(sel,otherId){
  const other=document.getElementById(otherId);
  if(other)other.style.display=sel.value==='Other'?'block':'none';
}
function V(id){const el=document.getElementById(id);return el?el.value:'';}
function S(id,val){const el=document.getElementById(id);if(el&&val!==undefined&&val!==null)el.value=val;}

function isThisWeek(d){ if(!d)return false; const now=new Date(),dt=new Date(d+'T12:00:00'); return (now-dt)<7*24*60*60*1000&&dt<=now; }
function isThisMonth(d){ if(!d)return false; const now=new Date(),dt=new Date(d+'T12:00:00'); return dt.getMonth()===now.getMonth()&&dt.getFullYear()===now.getFullYear(); }
function avgPerDay(d){ if(!d.length)return 0; const dates=[...new Set(d.map(x=>x.date))].length; return Math.round(d.length/dates*10)/10; }
function avgRating(d){ const r=d.filter(x=>+x.rating>0); if(!r.length)return 0; return (r.reduce((a,b)=>a+(+b.rating),0)/r.length).toFixed(1)+'★'; }
function avgDuration(d,key){
  const p=d.map(x=>{ const m=x[key]?.match(/(\d+)h\s*(\d+)m/); return m?(+m[1]*60+(+m[2])):0; }).filter(x=>x>0);
  if(!p.length)return '-'; const a=Math.round(p.reduce((a,b)=>a+b,0)/p.length); return `${Math.floor(a/60)}h${a%60}m`;
}

// ============================================================
// CALCULATED FIELDS
// ============================================================
function calcBPStatus(){
  const sys=+V('f_systolic'),dia=+V('f_diastolic');
  if(!sys||!dia)return;
  let s='';
  if(sys<90||dia<60)s='Low';
  else if(sys<120&&dia<80)s='Normal';
  else if(sys<130&&dia<80)s='Elevated';
  else if(sys<140||dia<90)s='High Stage 1';
  else if(sys<180||dia<120)s='High Stage 2';
  else s='Hypertensive Crisis';
  S('f_status',s);
}
function calcGlucoseStatus(){
  const g=+V('f_glucose'); if(!g)return;
  let s=''; if(g<70)s='Low'; else if(g<=99)s='Normal'; else if(g<=125)s='Pre-Diabetic'; else s='High';
  S('f_status',s);
}
function calcMeltDuration(){
  const s=V('f_melt_start'),e=V('f_melt_end'); if(!s||!e)return;
  const diff=timeToMin(e)-timeToMin(s);
  if(diff>0)S('f_melt_dur',Math.floor(diff/60)+'h '+diff%60+'m');
}
function calcDevDuration(){
  const s=V('f_start_time'),e=V('f_end_time'); if(!s||!e)return;
  const diff=timeToMin(e)-timeToMin(s);
  if(diff>0)S('f_duration',Math.floor(diff/60)+'h '+diff%60+'m');
}
function calcSleepDuration(){
  const s=V('f_sleep_time'),w=V('f_wake_time'); if(!s||!w)return;
  let diff=timeToMin(w)-timeToMin(s); if(diff<0)diff+=24*60;
  S('f_duration',Math.floor(diff/60)+'h '+diff%60+'m');
}
function calcTravelDuration(){
  const s=V('f_start_time'),a=V('f_arrival_time'); if(!s||!a)return;
  const diff=timeToMin(a)-timeToMin(s);
  if(diff>0)S('f_duration',Math.floor(diff/60)+'h '+diff%60+'m');
}
function calcSteps(){
  const w=+V('f_watch'),r=+V('f_ring');
  if(w&&r)S('f_steps',Math.round((w+r)/2));
  else if(w)S('f_steps',w);
  else if(r)S('f_steps',r);
}
function timeToMin(t){ if(!t)return 0; const[h,m]=t.split(':'); return +h*60+(+m); }

// ============================================================
// LOG DEFINITIONS
// ============================================================
const LOGS = [
  {key:'bp',icon:'❤️',label:'Blood Pressure',color:'#f44336',
   fields:()=>`
    <div class="form-row"><div class="form-group"><label>Date</label><input type="date" id="f_date" value="${today()}"></div><div class="form-group"><label>Time</label><input type="time" id="f_time" value="${nowTime()}"></div></div>
    <div class="form-group"><label>Name</label><select id="f_name" onchange="toggleOther(this,'f_name_other')"><option>Chris</option><option>Crystal</option><option>Tammi</option><option>Marrian</option><option>Other</option></select><input type="text" id="f_name_other" placeholder="Specify name" style="margin-top:6px;display:none"></div>
    <div class="form-row"><div class="form-group"><label>Systolic (mmHg)</label><input type="number" id="f_systolic" oninput="calcBPStatus()"></div><div class="form-group"><label>Diastolic (mmHg)</label><input type="number" id="f_diastolic" oninput="calcBPStatus()"></div></div>
    <div class="form-row"><div class="form-group"><label>Pulse (BPM)</label><input type="number" id="f_pulse"></div><div class="form-group"><label>Status</label><input type="text" id="f_status" readonly placeholder="Auto-calculated"></div></div>
    <div class="form-row"><div class="form-group"><label>Arm</label><select id="f_arm"><option>Left</option><option>Right</option></select></div><div class="form-group"><label>Position</label><select id="f_position"><option>Sitting</option><option>Standing</option><option>Lying Down</option></select></div></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({date:V('f_date'),time:V('f_time'),name:V('f_name')==='Other'?V('f_name_other'):V('f_name'),systolic:V('f_systolic'),diastolic:V('f_diastolic'),pulse:V('f_pulse'),status:V('f_status'),arm:V('f_arm'),position:V('f_position'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_date',e.date);S('f_time',e.time);S('f_name',e.name);S('f_systolic',e.systolic);S('f_diastolic',e.diastolic);S('f_pulse',e.pulse);S('f_status',e.status);S('f_arm',e.arm);S('f_position',e.position);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['date','time','name','systolic','diastolic','pulse','status'],
   stats:(d)=>{
    const avg_s=d.length?Math.round(d.reduce((a,b)=>a+(+b.systolic),0)/d.length):0;
    const avg_d=d.length?Math.round(d.reduce((a,b)=>a+(+b.diastolic),0)/d.length):0;
    return [{lbl:'Total',val:d.length,c:'#f44336'},{lbl:'Avg Sys',val:avg_s,c:'#ff7043'},{lbl:'Avg Dia',val:avg_d,c:'#ffc107'},{lbl:'Today',val:d.filter(x=>x.date===today()).length,c:'#3d8ef0'}];
   }
  },
  {key:'cigarette',icon:'🚬',label:'Cigarette',color:'#795548',
   fields:()=>`
    <div class="form-row"><div class="form-group"><label>Date</label><input type="date" id="f_date" value="${today()}"></div><div class="form-group"><label>Time</label><input type="time" id="f_time" value="${nowTime()}"></div></div>
    <div class="form-group"><label>Brand</label><select id="f_brand" onchange="toggleOther(this,'f_brand_other')"><option>Newport</option><option>Marlboro</option><option>Camel</option><option>Lucky Strike</option><option>American Spirit</option><option>Other</option></select><input type="text" id="f_brand_other" placeholder="Specify brand" style="margin-top:6px;display:none"></div>
    <div class="form-group"><label>Type</label><select id="f_type"><option>Full Flavor</option><option>Regular</option><option>Light</option><option>Ultra-Light</option><option>Menthol</option><option>Menthol Light</option></select></div>
    <div class="form-group"><label>Size</label><select id="f_size"><option>Regular</option><option>100s</option><option>Slim</option></select></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({date:V('f_date'),time:V('f_time'),brand:V('f_brand')==='Other'?V('f_brand_other'):V('f_brand'),type:V('f_type'),size:V('f_size'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_date',e.date);S('f_time',e.time);S('f_brand',e.brand);S('f_type',e.type);S('f_size',e.size);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['date','time','brand','type','size'],
   stats:(d)=>[{lbl:'Total',val:d.length,c:'#795548'},{lbl:'Today',val:d.filter(x=>x.date===today()).length,c:'#ff7043'},{lbl:'This Week',val:d.filter(x=>isThisWeek(x.date)).length,c:'#ffc107'},{lbl:'Avg/Day',val:avgPerDay(d),c:'#9b6dff'}]
  },
  {key:'device',icon:'🔋',label:'Device Charge',color:'#ffc107',
   fields:()=>`
    <div class="form-row"><div class="form-group"><label>Date</label><input type="date" id="f_date" value="${today()}"></div></div>
    <div class="form-group"><label>Device</label><select id="f_device" onchange="toggleOther(this,'f_device_other')"><option>Smart Watch</option><option>Smart Ring</option><option>Earbuds</option><option>Buds Case</option><option>Ring Case</option><option>Phone</option><option>Tablet</option><option>Keyboard</option><option>Mouse</option><option>Other</option></select><input type="text" id="f_device_other" placeholder="Specify device" style="margin-top:6px;display:none"></div>
    <div class="form-row"><div class="form-group"><label>Start Time</label><input type="time" id="f_start_time" oninput="calcDevDuration()"></div><div class="form-group"><label>% at Start</label><input type="number" id="f_pct_start" min="0" max="100"></div></div>
    <div class="form-row"><div class="form-group"><label>End Time</label><input type="time" id="f_end_time" oninput="calcDevDuration()"></div><div class="form-group"><label>% at End</label><input type="number" id="f_pct_end" min="0" max="100"></div></div>
    <div class="form-row"><div class="form-group"><label>Fully Charged</label><div class="check-row"><input type="checkbox" id="f_full_charge"><label for="f_full_charge" style="text-transform:none;font-size:0.9rem;">Yes</label></div></div><div class="form-group"><label>Amount Charged (%)</label><input type="number" id="f_amt_charged"></div></div>
    <div class="form-group"><label>Duration</label><input type="text" id="f_duration" readonly placeholder="Auto-calculated"></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({date:V('f_date'),device:V('f_device')==='Other'?V('f_device_other'):V('f_device'),start_time:V('f_start_time'),pct_start:V('f_pct_start'),end_time:V('f_end_time'),pct_end:V('f_pct_end'),full_charge:document.getElementById('f_full_charge')?.checked?'Yes':'No',amt_charged:V('f_amt_charged'),duration:V('f_duration'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_date',e.date);S('f_device',e.device);S('f_start_time',e.start_time);S('f_pct_start',e.pct_start);S('f_end_time',e.end_time);S('f_pct_end',e.pct_end);if(document.getElementById('f_full_charge'))document.getElementById('f_full_charge').checked=e.full_charge==='Yes';S('f_amt_charged',e.amt_charged);S('f_duration',e.duration);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['date','device','start_time','end_time','duration','pct_start','pct_end'],
   stats:(d)=>[{lbl:'Total',val:d.length,c:'#ffc107'},{lbl:'Today',val:d.filter(x=>x.date===today()).length,c:'#3d8ef0'},{lbl:'Full Charges',val:d.filter(x=>x.full_charge==='Yes').length,c:'#4caf50'},{lbl:'This Week',val:d.filter(x=>isThisWeek(x.date)).length,c:'#9b6dff'}]
  },
  {key:'exercise',icon:'💪',label:'Exercise',color:'#e91e8c',
   fields:()=>`
    <div class="form-row"><div class="form-group"><label>Date</label><input type="date" id="f_date" value="${today()}"></div><div class="form-group"><label>Start Time</label><input type="time" id="f_start_time" value="${nowTime()}"></div></div>
    <div class="form-row"><div class="form-group"><label>Exercise Type</label><select id="f_ex_type"><option>Core</option><option>Arms</option><option>Legs</option><option>Cardio</option><option>Endurance</option><option>Strength</option></select></div><div class="form-group"><label>Exercise Name</label><input type="text" id="f_exercise" placeholder="e.g. Push-ups"></div></div>
    <div class="form-row"><div class="form-group"><label>No. of Sets</label><input type="number" id="f_sets"></div><div class="form-group"><label>No. of Reps</label><input type="number" id="f_reps"></div></div>
    <div class="form-group"><label>Details</label><textarea id="f_details"></textarea></div>
    <div class="form-row"><div class="form-group"><label>End Time</label><input type="time" id="f_end_time"></div><div class="form-group"><label>Workout Rating</label>${starRatingHTML('f_rating')}</div></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({date:V('f_date'),start_time:V('f_start_time'),ex_type:V('f_ex_type'),exercise:V('f_exercise'),sets:V('f_sets'),reps:V('f_reps'),details:V('f_details'),end_time:V('f_end_time'),rating:getStarVal('f_rating'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_date',e.date);S('f_start_time',e.start_time);S('f_ex_type',e.ex_type);S('f_exercise',e.exercise);S('f_sets',e.sets);S('f_reps',e.reps);S('f_details',e.details);S('f_end_time',e.end_time);setStarVal('f_rating',e.rating);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['date','ex_type','exercise','sets','reps','rating'],
   stats:(d)=>[{lbl:'Total',val:d.length,c:'#e91e8c'},{lbl:'This Week',val:d.filter(x=>isThisWeek(x.date)).length,c:'#3d8ef0'},{lbl:'Types',val:new Set(d.map(x=>x.ex_type)).size,c:'#ffc107'},{lbl:'Today',val:d.filter(x=>x.date===today()).length,c:'#4caf50'}]
  },
  {key:'food',icon:'🍽️',label:'Food',color:'#ff7043',
   fields:()=>`
    <div class="form-row"><div class="form-group"><label>Date</label><input type="date" id="f_date" value="${today()}"></div><div class="form-group"><label>Time</label><input type="time" id="f_time" value="${nowTime()}"></div></div>
    <div class="form-row"><div class="form-group"><label>Meal</label><select id="f_meal"><option>Breakfast</option><option>Lunch</option><option>Dinner</option><option>Snack</option></select></div><div class="form-group"><label>Prepared By</label><select id="f_prepared_by"><option>Crystal</option><option>Chris</option><option>Tammi</option><option>Marrian</option><option>Take-out</option><option>Delivery</option><option>Restaurant</option></select></div></div>
    <div class="form-group"><label>Main Dish</label><input type="text" id="f_main_dish" placeholder="Main dish name"></div>
    <div class="form-group"><label>Side Dishes</label><textarea id="f_sides" placeholder="Side dishes"></textarea></div>
    <div class="form-group"><label>Rating</label>${starRatingHTML('f_rating')}</div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({date:V('f_date'),time:V('f_time'),meal:V('f_meal'),prepared_by:V('f_prepared_by'),main_dish:V('f_main_dish'),sides:V('f_sides'),rating:getStarVal('f_rating'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_date',e.date);S('f_time',e.time);S('f_meal',e.meal);S('f_prepared_by',e.prepared_by);S('f_main_dish',e.main_dish);S('f_sides',e.sides);setStarVal('f_rating',e.rating);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['date','time','meal','prepared_by','main_dish','rating'],
   stats:(d)=>[{lbl:'Total',val:d.length,c:'#ff7043'},{lbl:'Today',val:d.filter(x=>x.date===today()).length,c:'#3d8ef0'},{lbl:'This Week',val:d.filter(x=>isThisWeek(x.date)).length,c:'#4caf50'},{lbl:'Avg Rating',val:avgRating(d),c:'#ffc107'}]
  },
  {key:'glucose',icon:'🩸',label:'Glucose',color:'#e91e63',
   fields:()=>`
    <div class="form-row"><div class="form-group"><label>Date</label><input type="date" id="f_date" value="${today()}"></div><div class="form-group"><label>Time</label><input type="time" id="f_time" value="${nowTime()}"></div></div>
    <div class="form-group"><label>Name</label><select id="f_name" onchange="toggleOther(this,'f_name_other')"><option>Chris</option><option>Crystal</option><option>Tammi</option><option>Marrian</option><option>Other</option></select><input type="text" id="f_name_other" placeholder="Specify name" style="margin-top:6px;display:none"></div>
    <div class="form-row"><div class="form-group"><label>Glucose Reading (mg/dL)</label><input type="number" id="f_glucose" placeholder="e.g. 95" oninput="calcGlucoseStatus()"></div><div class="form-group"><label>Context</label><select id="f_context"><option>Fasting</option><option>Before Meal</option><option>After Meal</option></select></div></div>
    <div class="form-group"><label>Status</label><input type="text" id="f_status" readonly placeholder="Auto-calculated"></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({date:V('f_date'),time:V('f_time'),name:V('f_name')==='Other'?V('f_name_other'):V('f_name'),glucose:V('f_glucose'),context:V('f_context'),status:V('f_status'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_date',e.date);S('f_time',e.time);S('f_name',e.name);S('f_glucose',e.glucose);S('f_context',e.context);S('f_status',e.status);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['date','time','name','glucose','context','status'],
   stats:(d)=>{
    const avg=d.length?Math.round(d.reduce((a,b)=>a+(+b.glucose),0)/d.length):0;
    return [{lbl:'Total',val:d.length,c:'#e91e63'},{lbl:'Avg Reading',val:avg,c:'#f44336'},{lbl:'Today',val:d.filter(x=>x.date===today()).length,c:'#3d8ef0'},{lbl:'High Alerts',val:d.filter(x=>+x.glucose>200).length,c:'#ffc107'}];
   }
  },
  {key:'hygiene',icon:'🧼',label:'Hygiene',color:'#26c6da',
   fields:()=>`
    <div class="form-row"><div class="form-group"><label>Date</label><input type="date" id="f_date" value="${today()}"></div><div class="form-group"><label>Time</label><input type="time" id="f_time" value="${nowTime()}"></div></div>
    <div class="form-group"><label>Activity</label><select id="f_activity" onchange="toggleOther(this,'f_activity_other')"><option>Shave</option><option>Shower</option><option>Haircut</option><option>Clip Fingernails</option><option>Clip Toenails</option><option>Pluck Eyebrows</option><option>Clean Ears</option><option>Ped Stone</option><option>Other</option></select><input type="text" id="f_activity_other" placeholder="Specify activity" style="margin-top:6px;display:none"></div>
    <div class="form-group"><label>Details</label><textarea id="f_details"></textarea></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({date:V('f_date'),time:V('f_time'),activity:V('f_activity')==='Other'?V('f_activity_other'):V('f_activity'),details:V('f_details'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_date',e.date);S('f_time',e.time);S('f_activity',e.activity);S('f_details',e.details);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['date','time','activity','details'],
   stats:(d)=>[{lbl:'Total',val:d.length,c:'#26c6da'},{lbl:'This Week',val:d.filter(x=>isThisWeek(x.date)).length,c:'#4caf50'},{lbl:'Showers',val:d.filter(x=>x.activity==='Shower').length,c:'#3d8ef0'},{lbl:'This Month',val:d.filter(x=>isThisMonth(x.date)).length,c:'#9b6dff'}]
  },
  {key:'medication',icon:'💊',label:'Medication',color:'#ab47bc',
   fields:()=>`
    <div class="form-row"><div class="form-group"><label>Date</label><input type="date" id="f_date" value="${today()}"></div><div class="form-group"><label>Time</label><input type="time" id="f_time" value="${nowTime()}"></div></div>
    <div class="form-group"><label>Medication</label><select id="f_medication" onchange="toggleOther(this,'f_med_other')"><option>Buprenorphine</option><option>Acetaminophen</option><option>Ibuprofen</option><option>Asperin</option><option>Cyclobenzaprine</option><option>Amitryptoline</option><option>Other</option></select><input type="text" id="f_med_other" placeholder="Specify medication" style="margin-top:6px;display:none"></div>
    <div class="form-group"><label>Dosage</label><select id="f_dosage" onchange="toggleOther(this,'f_dosage_other')"><option>2mg.</option><option>4mg.</option><option>6mg.</option><option>10mg.</option><option>20mg.</option><option>30mg.</option><option>50mg.</option><option>100mg.</option><option>200mg.</option><option>400mg.</option><option>600mg.</option><option>800mg.</option><option>1000mg.</option><option>1500mg.</option><option>2000mg.</option><option>8mg./2mg.</option><option>16mg./4mg.</option><option>24mg./6mg.</option><option>32mg./8mg.</option><option>Other</option></select><input type="text" id="f_dosage_other" placeholder="Specify dosage" style="margin-top:6px;display:none"></div>
    <div class="form-row"><div class="form-group"><label>Melt Start Time</label><input type="time" id="f_melt_start" oninput="calcMeltDuration()"></div><div class="form-group"><label>Melt End Time</label><input type="time" id="f_melt_end" oninput="calcMeltDuration()"></div></div>
    <div class="form-group"><label>Melt Duration</label><input type="text" id="f_melt_dur" readonly placeholder="Auto-calculated"></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({date:V('f_date'),time:V('f_time'),medication:V('f_medication')==='Other'?V('f_med_other'):V('f_medication'),dosage:V('f_dosage')==='Other'?V('f_dosage_other'):V('f_dosage'),melt_start:V('f_melt_start'),melt_end:V('f_melt_end'),melt_dur:V('f_melt_dur'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_date',e.date);S('f_time',e.time);S('f_medication',e.medication);S('f_dosage',e.dosage);S('f_melt_start',e.melt_start);S('f_melt_end',e.melt_end);S('f_melt_dur',e.melt_dur);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['date','time','medication','dosage','melt_dur'],
   stats:(d)=>[{lbl:'Total',val:d.length,c:'#ab47bc'},{lbl:'Today',val:d.filter(x=>x.date===today()).length,c:'#3d8ef0'},{lbl:'This Week',val:d.filter(x=>isThisWeek(x.date)).length,c:'#4caf50'},{lbl:'Types Used',val:new Set(d.map(x=>x.medication)).size,c:'#ffc107'}]
  },
  {key:'sexual',icon:'💞',label:'Sexual Activity',color:'#ff4081',
   fields:()=>`
    <div class="form-group"><label>Date</label><input type="date" id="f_date" value="${today()}"></div>
    <div class="form-row"><div class="form-group"><label>Partner</label><select id="f_partner"><option>Yes</option><option>No</option></select></div><div class="form-group"><label>Partner Name</label><input type="text" id="f_partner_name"></div></div>
    <div class="form-group"><label>Activities Performed</label><textarea id="f_activities"></textarea></div>
    <div class="form-row"><div class="form-group"><label>Protection</label><select id="f_protection"><option>Yes</option><option>No</option></select></div><div class="form-group"><label>Toys Used</label><select id="f_toys"><option>Yes</option><option>No</option></select></div></div>
    <div class="form-group"><label>Duration</label><input type="text" id="f_duration" placeholder="e.g. 30 minutes"></div>
    <div class="form-group"><label>Rating</label>${starRatingHTML('f_rating')}</div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({date:V('f_date'),partner:V('f_partner'),partner_name:V('f_partner_name'),activities:V('f_activities'),protection:V('f_protection'),toys:V('f_toys'),duration:V('f_duration'),rating:getStarVal('f_rating'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_date',e.date);S('f_partner',e.partner);S('f_partner_name',e.partner_name);S('f_activities',e.activities);S('f_protection',e.protection);S('f_toys',e.toys);S('f_duration',e.duration);setStarVal('f_rating',e.rating);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['date','partner','partner_name','duration','rating'],
   stats:(d)=>[{lbl:'Total',val:d.length,c:'#ff4081'},{lbl:'With Partner',val:d.filter(x=>x.partner==='Yes').length,c:'#e91e8c'},{lbl:'This Month',val:d.filter(x=>isThisMonth(x.date)).length,c:'#9b6dff'},{lbl:'Avg Rating',val:avgRating(d),c:'#ffc107'}]
  },
  {key:'sleep',icon:'😴',label:'Sleep',color:'#5c6bc0',
   fields:()=>`
    <div class="form-group"><label>Date</label><input type="date" id="f_date" value="${today()}"></div>
    <div class="form-row"><div class="form-group"><label>Sleep Time</label><input type="time" id="f_sleep_time" oninput="calcSleepDuration()"></div><div class="form-group"><label>Wake-Up Time</label><input type="time" id="f_wake_time" oninput="calcSleepDuration()"></div></div>
    <div class="form-group"><label>Duration</label><input type="text" id="f_duration" readonly placeholder="Auto-calculated"></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({date:V('f_date'),sleep_time:V('f_sleep_time'),wake_time:V('f_wake_time'),duration:V('f_duration'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_date',e.date);S('f_sleep_time',e.sleep_time);S('f_wake_time',e.wake_time);S('f_duration',e.duration);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['date','sleep_time','wake_time','duration'],
   stats:(d)=>[{lbl:'Total',val:d.length,c:'#5c6bc0'},{lbl:'This Week',val:d.filter(x=>isThisWeek(x.date)).length,c:'#3d8ef0'},{lbl:'Avg Duration',val:avgDuration(d,'duration'),c:'#9b6dff'},{lbl:'This Month',val:d.filter(x=>isThisMonth(x.date)).length,c:'#4caf50'}]
  },
  {key:'steps',icon:'👣',label:'Steps',color:'#26a69a',
   fields:()=>`
    <div class="form-group"><label>Date</label><input type="date" id="f_date" value="${today()}"></div>
    <div class="form-row"><div class="form-group"><label>Watch Reading</label><input type="number" id="f_watch" oninput="calcSteps()"></div><div class="form-group"><label>Ring Reading</label><input type="number" id="f_ring" oninput="calcSteps()"></div></div>
    <div class="form-group"><label>Day's Step Count (Average)</label><input type="number" id="f_steps" readonly></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({date:V('f_date'),watch:V('f_watch'),ring:V('f_ring'),steps:V('f_steps'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_date',e.date);S('f_watch',e.watch);S('f_ring',e.ring);S('f_steps',e.steps);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['date','watch','ring','steps'],
   stats:(d)=>{
    const avg=d.length?Math.round(d.reduce((a,b)=>a+(+b.steps),0)/d.length):0;
    return [{lbl:'Total Days',val:d.length,c:'#26a69a'},{lbl:'Avg Steps',val:avg.toLocaleString(),c:'#3d8ef0'},{lbl:'This Week',val:d.filter(x=>isThisWeek(x.date)).length,c:'#4caf50'},{lbl:'Best Day',val:d.length?Math.max(...d.map(x=>+x.steps)).toLocaleString():0,c:'#ffc107'}];
   }
  },
  {key:'fueloil',icon:'🛢️',label:'Fuel Oil',color:'#8d6e63',
   fields:()=>`
    <div class="form-row"><div class="form-group"><label>Picked Up Date</label><input type="date" id="f_pu_date" value="${today()}"></div><div class="form-group"><label>Picked Up Time</label><input type="time" id="f_pu_time" value="${nowTime()}"></div></div>
    <div class="form-group"><label>Picked Up By</label><input type="text" id="f_pu_by"></div>
    <div class="form-row"><div class="form-group"><label>Vendor</label><input type="text" id="f_vendor"></div><div class="form-group"><label>Total Gallons</label><input type="number" id="f_gallons" step="0.01"></div></div>
    <div class="form-group"><label>Balance Remaining ($)</label><input type="number" id="f_balance" step="0.01"></div>
    <div class="form-row"><div class="form-group"><label>Date Added to Tank</label><input type="date" id="f_add_date"></div><div class="form-group"><label>Time Added to Tank</label><input type="time" id="f_add_time"></div></div>
    <div class="form-group"><label>Added By</label><input type="text" id="f_added_by"></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({pu_date:V('f_pu_date'),pu_time:V('f_pu_time'),pu_by:V('f_pu_by'),vendor:V('f_vendor'),gallons:V('f_gallons'),balance:V('f_balance'),add_date:V('f_add_date'),add_time:V('f_add_time'),added_by:V('f_added_by'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_pu_date',e.pu_date);S('f_pu_time',e.pu_time);S('f_pu_by',e.pu_by);S('f_vendor',e.vendor);S('f_gallons',e.gallons);S('f_balance',e.balance);S('f_add_date',e.add_date);S('f_add_time',e.add_time);S('f_added_by',e.added_by);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['pu_date','vendor','gallons','balance','pu_by'],
   stats:(d)=>{
    const totGal=d.reduce((a,b)=>a+(+b.gallons||0),0).toFixed(1);
    return [{lbl:'Pickups',val:d.length,c:'#8d6e63'},{lbl:'Total Gal',val:totGal,c:'#ff7043'},{lbl:'This Month',val:d.filter(x=>isThisMonth(x.pu_date)).length,c:'#4caf50'},{lbl:'Last Balance',val:'$'+(d.length?d[d.length-1].balance||0:0),c:'#ffc107'}];
   }
  },
  {key:'lptank',icon:'🔥',label:'LP Tank',color:'#ef5350',
   fields:()=>`
    <div class="form-row"><div class="form-group"><label>Date Swapped</label><input type="date" id="f_date" value="${today()}"></div><div class="form-group"><label>Time Swapped</label><input type="time" id="f_time" value="${nowTime()}"></div></div>
    <div class="form-group"><label>Swapped With</label><select id="f_swapped_with"><option>Full Tank</option><option>Partial Tank</option></select></div>
    <div class="form-group"><label>Swapped By</label><input type="text" id="f_swapped_by"></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({date:V('f_date'),time:V('f_time'),swapped_with:V('f_swapped_with'),swapped_by:V('f_swapped_by'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_date',e.date);S('f_time',e.time);S('f_swapped_with',e.swapped_with);S('f_swapped_by',e.swapped_by);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['date','time','swapped_with','swapped_by'],
   stats:(d)=>[{lbl:'Total Swaps',val:d.length,c:'#ef5350'},{lbl:'Full Tanks',val:d.filter(x=>x.swapped_with==='Full Tank').length,c:'#ff7043'},{lbl:'Partial',val:d.filter(x=>x.swapped_with==='Partial Tank').length,c:'#ffc107'},{lbl:'This Month',val:d.filter(x=>isThisMonth(x.date)).length,c:'#4caf50'}]
  },
  {key:'travel',icon:'🚗',label:'Travel',color:'#42a5f5',
   fields:()=>`
    <div class="form-group"><label>Date</label><input type="date" id="f_date" value="${today()}"></div>
    <div class="form-group"><label>Start Location</label><input type="text" id="f_start_loc" placeholder="Address"></div>
    <div class="form-row"><div class="form-group"><label>Start Time</label><input type="time" id="f_start_time" oninput="calcTravelDuration()"></div><div class="form-group"><label>Arrival Time</label><input type="time" id="f_arrival_time" oninput="calcTravelDuration()"></div></div>
    <div class="form-group"><label>Destination</label><input type="text" id="f_destination" placeholder="Address"></div>
    <div class="form-row"><div class="form-group"><label>Distance (miles)</label><input type="number" id="f_distance" step="0.1"></div><div class="form-group"><label>Duration</label><input type="text" id="f_duration" readonly placeholder="Auto-calculated"></div></div>
    <div class="form-group"><label>Reason for Trip</label><textarea id="f_reason"></textarea></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>`,
   collect:()=>({date:V('f_date'),start_loc:V('f_start_loc'),start_time:V('f_start_time'),arrival_time:V('f_arrival_time'),destination:V('f_destination'),distance:V('f_distance'),duration:V('f_duration'),reason:V('f_reason'),comments:V('f_comments')}),
   fill:(e)=>{S('f_date',e.date);S('f_start_loc',e.start_loc);S('f_start_time',e.start_time);S('f_arrival_time',e.arrival_time);S('f_destination',e.destination);S('f_distance',e.distance);S('f_duration',e.duration);S('f_reason',e.reason);S('f_comments',e.comments);},
   cols:['date','start_loc','destination','distance','duration'],
   stats:(d)=>{
    const totMi=d.reduce((a,b)=>a+(+b.distance||0),0).toFixed(1);
    return [{lbl:'Trips',val:d.length,c:'#42a5f5'},{lbl:'Total Miles',val:totMi,c:'#3d8ef0'},{lbl:'This Week',val:d.filter(x=>isThisWeek(x.date)).length,c:'#4caf50'},{lbl:'This Month',val:d.filter(x=>isThisMonth(x.date)).length,c:'#ffc107'}];
   }
  },
  {key:'weight',icon:'⚖️',label:'Weight',color:'#78909c',
   fields:()=>`
    <div class="form-row"><div class="form-group"><label>Date</label><input type="date" id="f_date" value="${today()}"></div><div class="form-group"><label>Time</label><input type="time" id="f_time" value="${nowTime()}"></div></div>
    <div class="form-row"><div class="form-group"><label>Name</label><input type="text" id="f_name"></div><div class="form-group"><label>Weight (lbs)</label><input type="number" id="f_weight" step="0.1"></div></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({date:V('f_date'),time:V('f_time'),name:V('f_name'),weight:V('f_weight'),comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_date',e.date);S('f_time',e.time);S('f_name',e.name);S('f_weight',e.weight);S('f_comments',e.comments);S('f_location',e.location);},
   cols:['date','name','weight'],
   stats:(d)=>{
    const avg=d.length?Math.round(d.reduce((a,b)=>a+(+b.weight),0)/d.length*10)/10:0;
    return [{lbl:'Entries',val:d.length,c:'#78909c'},{lbl:'Avg Weight',val:avg+' lbs',c:'#3d8ef0'},{lbl:'Latest',val:d.length?(+d[d.length-1].weight)+' lbs':'-',c:'#4caf50'},{lbl:'This Month',val:d.filter(x=>isThisMonth(x.date)).length,c:'#ffc107'}];
   }
  },
  {key:'vape',icon:'💨',label:'Vape Cartridge',color:'#66bb6a',
   fields:()=>`
    <div class="form-row"><div class="form-group"><label>Brand</label><input type="text" id="f_brand"></div><div class="form-group"><label>Strain</label><input type="text" id="f_strain"></div></div>
    <div class="form-row"><div class="form-group"><label>Size</label><select id="f_size"><option>.5g.</option><option>1g.</option><option>2g.</option></select></div><div class="form-group"><label>Strength (%)</label><input type="number" id="f_strength"></div></div>
    <div class="form-row"><div class="form-group"><label>Produced Date</label><input type="date" id="f_prod_date"></div><div class="form-group"><label>Best By Date</label><input type="date" id="f_best_by"></div></div>
    <div class="form-group"><label>My Rating</label>${starRatingHTML('f_rating')}</div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-row"><div class="form-group"><label>Date Purchased</label><input type="date" id="f_pur_date" value="${today()}"></div><div class="form-group"><label>Price ($)</label><input type="number" id="f_price" step="0.01"></div></div>
    <div class="form-group"><label>Dispensary</label><select id="f_dispensary" onchange="toggleOther(this,'f_disp_other')"><option>The Magic Mushroom</option><option>Rollin' Twenties</option><option>Other</option></select><input type="text" id="f_disp_other" placeholder="Specify dispensary" style="margin-top:6px;display:none"></div>`,
   collect:()=>({brand:V('f_brand'),strain:V('f_strain'),size:V('f_size'),strength:V('f_strength'),prod_date:V('f_prod_date'),best_by:V('f_best_by'),rating:getStarVal('f_rating'),comments:V('f_comments'),pur_date:V('f_pur_date'),price:V('f_price'),dispensary:V('f_dispensary')==='Other'?V('f_disp_other'):V('f_dispensary')}),
   fill:(e)=>{S('f_brand',e.brand);S('f_strain',e.strain);S('f_size',e.size);S('f_strength',e.strength);S('f_prod_date',e.prod_date);S('f_best_by',e.best_by);setStarVal('f_rating',e.rating);S('f_pur_date',e.pur_date);S('f_price',e.price);S('f_dispensary',e.dispensary);S('f_comments',e.comments);},
   cols:['pur_date','brand','strain','size','price','rating'],
   stats:(d)=>{
    const tot=d.reduce((a,b)=>a+(+b.price||0),0).toFixed(2);
    return [{lbl:'Total',val:d.length,c:'#66bb6a'},{lbl:'Total Spent',val:'$'+tot,c:'#4caf50'},{lbl:'This Month',val:d.filter(x=>isThisMonth(x.pur_date)).length,c:'#3d8ef0'},{lbl:'Avg Rating',val:avgRating(d),c:'#ffc107'}];
   }
  },
  {key:'chore',icon:'🧹',label:'Household Chore',color:'#ffca28',
   fields:()=>`
    <div class="form-row"><div class="form-group"><label>Household</label><input type="text" id="f_household"></div><div class="form-group"><label>Chore</label><input type="text" id="f_chore"></div></div>
    <div class="form-row"><div class="form-group"><label>Assigned To</label><input type="text" id="f_assigned_to"></div><div class="form-group"><label>Date Assigned</label><input type="date" id="f_assigned_date" value="${today()}"></div></div>
    <div class="form-row"><div class="form-group"><label>Due Date</label><input type="date" id="f_due_date"></div><div class="form-group"><label>Completed</label><div class="check-row" style="padding-top:22px"><input type="checkbox" id="f_completed"><label for="f_completed" style="text-transform:none;font-size:0.9rem;">Yes</label></div></div></div>
    <div class="form-group"><label>Comments</label><textarea id="f_comments"></textarea></div>
    <div class="form-group"><label>Location</label><input type="text" id="f_location" placeholder="Address"></div>`,
   collect:()=>({household:V('f_household'),chore:V('f_chore'),assigned_to:V('f_assigned_to'),assigned_date:V('f_assigned_date'),due_date:V('f_due_date'),completed:document.getElementById('f_completed')?.checked?'Yes':'No',comments:V('f_comments'),location:V('f_location')}),
   fill:(e)=>{S('f_household',e.household);S('f_chore',e.chore);S('f_assigned_to',e.assigned_to);S('f_assigned_date',e.assigned_date);S('f_due_date',e.due_date);if(document.getElementById('f_completed'))document.getElementById('f_completed').checked=e.completed==='Yes';S('f_comments',e.comments);S('f_location',e.location);},
   cols:['assigned_date','household','chore','assigned_to','due_date','completed'],
   stats:(d)=>[{lbl:'Total',val:d.length,c:'#ffca28'},{lbl:'Completed',val:d.filter(x=>x.completed==='Yes').length,c:'#4caf50'},{lbl:'Pending',val:d.filter(x=>x.completed!=='Yes').length,c:'#ff7043'},{lbl:'Overdue',val:d.filter(x=>x.completed!=='Yes'&&x.due_date&&x.due_date<today()).length,c:'#f44336'}]
  }
];

// ============================================================
// STATE
// ============================================================
let currentLog = null;
let editingId = null;
let allCounts = {};
let allData = {};

// ============================================================
// MAIN DASHBOARD
// ============================================================
async function renderMainDash() {
  // Load counts from DB
  try {
    allCounts = await apiCall({api:'counts'});
  } catch(e) {
    allCounts = {};
  }

  const totalEntries = Object.values(allCounts).reduce((a,b)=>a+b,0);
  const activeLogs = Object.values(allCounts).filter(c=>c>0).length;

  document.getElementById('main-stats').innerHTML = `
    <div class="stat-card"><div class="stat-val">${LOGS.length}</div><div class="stat-lbl">Log Types</div></div>
    <div class="stat-card"><div class="stat-val">${totalEntries.toLocaleString()}</div><div class="stat-lbl">Total Entries</div></div>
    <div class="stat-card"><div class="stat-val">${activeLogs}</div><div class="stat-lbl">Active Logs</div></div>
    <div class="stat-card"><div class="stat-val" style="color:var(--accent-teal)">MySQL</div><div class="stat-lbl">Database</div></div>`;

  document.getElementById('log-grid').innerHTML = LOGS.map(l => `
    <div class="log-btn" onclick="openLog('${l.key}')" style="border-top:3px solid ${l.color}">
      <div class="log-icon">${l.icon}</div>
      <div class="log-label">${l.label}</div>
      <div class="log-count">${(allCounts[l.key]||0).toLocaleString()} entries</div>
    </div>`).join('');
}

// ============================================================
// SUB-DASHBOARD
// ============================================================
async function openLog(key) {
  const log = LOGS.find(l=>l.key===key);
  if (!log) return;
  currentLog = {
    ...log,
    openAddEntry: () => openEntryModal(log, null),
    refresh: () => openLog(key)
  };

  document.getElementById('sub-topbar-title').textContent = log.label;
  document.getElementById('sub-icon').textContent = log.icon;
  document.getElementById('sub-title').textContent = log.label;
  document.getElementById('sub-desc').textContent = 'Loading...';

  showScreen('screen-sub');

  try {
    const data = await apiCall({api:'list', log:key});
    allData[key] = data;
    renderSubDash(log, data);
  } catch(e) {
    document.getElementById('sub-desc').textContent = 'Error loading data.';
    notify('Failed to load data', true);
  }
}

function renderSubDash(log, data) {
  document.getElementById('sub-desc').textContent = data.length + ' entries';

  // Stats
  const stats = log.stats(data);
  document.getElementById('sub-stats-row').innerHTML = stats.map(s =>
    `<div class="sub-stat"><div class="sv" style="color:${s.c}">${s.val}</div><div class="sl">${s.lbl}</div></div>`
  ).join('');

  // Chart - last 7 days
  const dateField = log.cols.find(c=>c==='date'||c==='pu_date'||c==='assigned_date'||c==='pur_date') || 'date';
  const last7 = [];
  for(let i=6;i>=0;i--){
    const d=new Date(); d.setDate(d.getDate()-i);
    const ds=d.toISOString().slice(0,10);
    last7.push({label:d.toLocaleDateString('en',{weekday:'short'}),cnt:data.filter(x=>x[dateField]===ds).length});
  }
  const max=Math.max(...last7.map(x=>x.cnt),1);
  document.getElementById('sub-chart').innerHTML = `
    <div class="chart-area" style="align-items:flex-end">
      <div style="display:flex;align-items:flex-end;gap:6px;flex:1;width:100%;">
        ${last7.map(x=>`<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;">
          <div style="flex:1;width:100%;display:flex;align-items:flex-end;">
            <div data-val="${x.cnt}" style="width:100%;height:${Math.max(x.cnt/max*100,4)}%;background:linear-gradient(180deg,${log.color},${log.color}88);border-radius:4px 4px 0 0;min-height:4px;" class="chart-bar"></div>
          </div>
          <div style="font-size:0.65rem;color:var(--text-muted)">${x.label}</div>
          <div style="font-size:0.7rem;color:var(--text-secondary);font-weight:600">${x.cnt}</div>
        </div>`).join('')}
      </div>
    </div>`;

  // Table
  const cols = log.cols;
  document.getElementById('sub-thead').innerHTML = `<tr>${cols.map(c=>`<th>${c.replace(/_/g,' ')}</th>`).join('')}<th>Actions</th></tr>`;

  if (!data.length) {
    document.getElementById('sub-tbody').innerHTML = `<tr><td colspan="${cols.length+1}" class="empty-state">No entries yet. Click ➕ Add Entry to get started.</td></tr>`;
    return;
  }

  document.getElementById('sub-tbody').innerHTML = data.map(row => `
    <tr>
      ${cols.map(c=>`<td>${row[c]??''}</td>`).join('')}
      <td><div class="act-btns">
        <button class="btn btn-primary btn-sm btn-icon" onclick="editEntry('${log.key}',${row.id})">✏️</button>
        <button class="btn btn-red btn-sm btn-icon" onclick="deleteEntry('${log.key}',${row.id})">🗑️</button>
      </div></td>
    </tr>`).join('');
}

// ============================================================
// ENTRY MODAL
// ============================================================
function openEntryModal(log, entry) {
  editingId = entry ? entry.id : null;
  document.getElementById('modal-entry-title').textContent = entry ? '✏️ Edit Entry' : `➕ Add ${log.label}`;
  document.getElementById('modal-entry-body').innerHTML = log.fields();
  if (entry) { setTimeout(()=>log.fill(entry), 50); }
  openModal('modal-entry');
}

function editEntry(key, id) {
  const log = LOGS.find(l=>l.key===key);
  const data = allData[key] || [];
  const entry = data.find(e=>+e.id===+id);
  if (log && entry) openEntryModal(log, entry);
}

async function saveEntry() {
  if (!currentLog) return;
  const data = currentLog.collect();
  if (editingId) data.id = editingId;

  try {
    const res = await apiCall({api:'save', log:currentLog.key}, data);
    if (res.error) { notify(res.error, true); return; }
    closeModal('modal-entry');
    notify(editingId ? 'Entry updated!' : 'Entry saved!');
    openLog(currentLog.key);
  } catch(e) {
    notify('Save failed. Check connection.', true);
  }
}

async function deleteEntry(key, id) {
  if (!confirm('Delete this entry?')) return;
  try {
    await apiCall({api:'delete', log:key, id:id});
    notify('Entry deleted.');
    openLog(key);
  } catch(e) {
    notify('Delete failed.', true);
  }
}

// ============================================================
// DB MANAGER
// ============================================================
async function openDbManager() {
  let counts = {};
  try { counts = await apiCall({api:'counts'}); } catch(e) {}

  const body = document.getElementById('modal-db-body');
  body.innerHTML = `
    <div class="db-section">
      <h4>📊 Database Status</h4>
      <div style="margin-bottom:12px">
        <span class="db-status db-ok">✅ Connected to MySQL</span>
        <span style="font-size:0.75rem;color:var(--text-muted);margin-left:10px">${DB_HOST_DISPLAY}</span>
      </div>
      <div class="db-grid">
        ${LOGS.map(l=>`<div class="db-item">
          <div class="db-item-name">${l.icon} ${l.label}</div>
          <div class="db-item-count">${(counts[l.key]||0).toLocaleString()} entries</div>
          <div class="db-item-btns">
            <button class="btn btn-red btn-sm" onclick="clearLog('${l.key}','${l.label}')">🗑️ Clear</button>
          </div>
        </div>`).join('')}
      </div>
    </div>
    <div class="db-section">
      <h4>⚠️ Danger Zone</h4>
      <button class="btn btn-red" onclick="clearAll()">⚠️ Clear ALL Data</button>
    </div>`;
  openModal('modal-db');
}

const DB_HOST_DISPLAY = 'sql105.byethost10.com';

async function clearLog(key, label) {
  if (!confirm(`Clear all ${label} data? This cannot be undone.`)) return;
  await apiCall({api:'clear', log:key});
  notify('Cleared ' + label + ' data.');
  openDbManager();
  renderMainDash();
}

async function clearAll() {
  if (!confirm('⚠️ Clear ALL log data? This cannot be undone!')) return;
  for (const l of LOGS) { await apiCall({api:'clear', log:l.key}); }
  notify('All data cleared.');
  openDbManager();
  renderMainDash();
}

// ============================================================
// AI CHAT
// ============================================================
async function sendChat() {
  const inp = document.getElementById('chat-input');
  const msg = inp.value.trim();
  if (!msg) return;
  inp.value = '';
  addChatMsg(msg, 'user');

  // Gather log summaries
  let context = '';
  try {
    const counts = await apiCall({api:'counts'});
    context = LOGS.map(l => `${l.label}: ${counts[l.key]||0} entries`).join('\n');
  } catch(e) { context = 'Data unavailable'; }

  const thinking = addChatMsg('Thinking...', 'ai chat-thinking');

  try {
    const resp = await fetch('https://api.anthropic.com/v1/messages', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        model: 'claude-sonnet-4-20250514',
        max_tokens: 1000,
        system: `You are a personal health and activity assistant. The user has the following log data:\n\n${context}\n\nAnswer questions about their data, provide health insights, and help them understand their patterns. Be concise and helpful.`,
        messages: [{role:'user', content:msg}]
      })
    });
    const data = await resp.json();
    thinking.remove();
    const text = data.content?.[0]?.text || 'Sorry, I had trouble responding.';
    addChatMsg(text, 'ai');
  } catch(e) {
    thinking.remove();
    addChatMsg('Connection error. Please check your network.', 'ai');
  }
}

function addChatMsg(text, cls) {
  const el = document.createElement('div');
  el.className = 'chat-msg ' + cls;
  el.textContent = text;
  const msgs = document.getElementById('chat-messages');
  msgs.appendChild(el);
  msgs.scrollTop = msgs.scrollHeight;
  return el;
}

// ============================================================
// INIT
// ============================================================
renderMainDash();
</script>
</body>
</html>
