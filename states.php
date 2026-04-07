<?php
// ==========================================
// DATABASE CONFIGURATION
// ==========================================
$host = 'sql105.byethost10.com';
$db   = 'b10_39913602_us_state_facts';
$user = 'b10_39913602';
$pass = 'Cr0ssfire';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("<div style='color:red; padding:20px;'><strong>Database Connection Failed:</strong> " . $e->getMessage() . "</div>");
}

// ==========================================
// SCHEMA DEFINITIONS
// ==========================================
$text_fields = [
    'abbreviation', 'capital', 'nickname', 'motto', 'anthem', 'largest_city', 
    'largest_county', 'largest_metro_area', 'governor', 'lieutenant_governor', 
    'area_total', 'area_land', 'area_water', 'area_rank', 'dimensions_length', 
    'dimensions_width', 'highest_point', 'highest_elevation', 'lowest_point', 
    'lowest_elevation', 'population_total', 'population_rank', 'population_density', 
    'median_household_income', 'unemployment_rate', 'income_rank', 'latitude', 
    'longitude', 'website', 'state_bird', 'state_flower', 'state_tree'
];

$longtext_fields = [
    'state_introduction', 'state_etymology', 'state_history', 'state_geography', 
    'state_demographics', 'state_economy', 'state_law', 'state_government', 
    'state_education', 'state_media', 'state_culture', 'state_transportation'
];

$image_fields = [
    'state_flag', 'state_seal', 'state_map', 'state_outline', 
    'state_quarter', 'state_license_plate', 'state_license', 'state_welcome_sign'
];

$us_timezones = [
    'Eastern Time (ET)', 'Central Time (CT)', 'Mountain Time (MT)', 
    'Pacific Time (PT)', 'Alaska Time (AKT)', 'Hawaii-Aleutian Time (HAT)', 
    'Samoa Time (SST)', 'Chamorro Time (ChST)', 'Atlantic Time (AST)'
];

$states_list = [
    "Alabama", "Alaska", "Arizona", "Arkansas", "California", "Colorado", "Connecticut", 
    "Delaware", "Florida", "Georgia", "Hawaii", "Idaho", "Illinois", "Indiana", "Iowa", 
    "Kansas", "Kentucky", "Louisiana", "Maine", "Maryland", "Massachusetts", "Michigan", 
    "Minnesota", "Mississippi", "Missouri", "Montana", "Nebraska", "Nevada", "New Hampshire", 
    "New Jersey", "New Mexico", "New York", "North Carolina", "North Dakota", "Ohio", 
    "Oklahoma", "Oregon", "Pennsylvania", "Rhode Island", "South Carolina", "South Dakota", 
    "Tennessee", "Texas", "Utah", "Vermont", "Virginia", "Washington", "West Virginia", 
    "Wisconsin", "Wyoming"
];

// ==========================================
// AUTO-CONFIGURE DATABASE TABLE
// ==========================================
$create_table_sql = "CREATE TABLE IF NOT EXISTS state_facts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    state_name VARCHAR(100) UNIQUE NOT NULL,
    founded_date DATE,
    time_zone VARCHAR(100),
    secondary_time_zone VARCHAR(100),
";
foreach ($text_fields as $tf) { $create_table_sql .= "`$tf` VARCHAR(255), "; }
foreach ($longtext_fields as $ltf) { $create_table_sql .= "`$ltf` LONGTEXT, "; }
foreach ($image_fields as $imf) { $create_table_sql .= "`$imf` VARCHAR(500), "; }
$create_table_sql = rtrim($create_table_sql, ", ") . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$pdo->exec($create_table_sql);

// ==========================================
// HANDLE FORM SUBMISSION (SAVE DATA)
// ==========================================
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['state_name'])) {
    $state_name = $_POST['state_name'];
    
    // Process File Uploads
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/attachments/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    $uploaded_images = [];
    foreach ($image_fields as $imf) {
        if (isset($_FILES[$imf]) && $_FILES[$imf]['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES[$imf]['tmp_name'];
            $ext = pathinfo($_FILES[$imf]['name'], PATHINFO_EXTENSION);
            $new_filename = strtolower(str_replace(' ', '_', $state_name)) . '_' . $imf . '.' . $ext;
            $destination = $upload_dir . $new_filename;
            
            if (move_uploaded_file($tmp_name, $destination)) {
                $uploaded_images[$imf] = '/attachments/' . $new_filename;
            }
        }
    }

    // Prepare SQL Insert/Update
    $check_stmt = $pdo->prepare("SELECT id FROM state_facts WHERE state_name = ?");
    $check_stmt->execute([$state_name]);
    $exists = $check_stmt->fetch();

    $data = [
        'founded_date' => $_POST['founded_date'] ?: null,
        'time_zone' => $_POST['time_zone'] ?: null,
        'secondary_time_zone' => $_POST['secondary_time_zone'] ?: null,
    ];
    
    foreach ($text_fields as $tf) { $data[$tf] = $_POST[$tf] ?? null; }
    foreach ($longtext_fields as $ltf) { $data[$ltf] = $_POST[$ltf] ?? null; }
    foreach ($image_fields as $imf) { 
        if (isset($uploaded_images[$imf])) {
            $data[$imf] = $uploaded_images[$imf]; // New upload
        } else {
            $data[$imf] = $_POST['existing_'.$imf] ?? null; // Keep existing if no new upload
        }
    }

    if ($exists) {
        // UPDATE
        $set_clause = [];
        foreach ($data as $key => $val) { $set_clause[] = "`$key` = :$key"; }
        $sql = "UPDATE state_facts SET " . implode(', ', $set_clause) . " WHERE state_name = :state_name";
        $data['state_name'] = $state_name;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $message = "Record for $state_name updated successfully!";
    } else {
        // INSERT
        $data['state_name'] = $state_name;
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);
        $sql = "INSERT INTO state_facts (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $message = "Record for $state_name created successfully!";
    }
}

// ==========================================
// FETCH CURRENT STATE DATA
// ==========================================
$current_state = $_GET['state'] ?? null;
$state_data = [];
if ($current_state) {
    $stmt = $pdo->prepare("SELECT * FROM state_facts WHERE state_name = ?");
    $stmt->execute([$current_state]);
    $state_data = $stmt->fetch() ?: [];
}

// Helper function to get value
function val($key, $data) {
    return isset($data[$key]) ? htmlspecialchars($data[$key]) : '';
}
function formatLabel($str) {
    return ucwords(str_replace('_', ' ', $str));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>US States Facts Dashboard</title>
    <style>
        :root {
            --usa-navy: #0A3161;
            --usa-red: #B31942;
            --usa-white: #FFFFFF;
            --bg-light: #f4f7f6;
            --text-dark: #333333;
            --border-color: #dddddd;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-light);
            color: var(--text-dark);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        /* Sidebar Navigation */
        .sidebar {
            width: 250px;
            background-color: var(--usa-navy);
            color: var(--usa-white);
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            z-index: 10;
        }
        .sidebar-header {
            padding: 20px;
            background-color: #08254a;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            border-bottom: 3px solid var(--usa-red);
        }
        .sidebar-header a {
            color: var(--usa-white);
            text-decoration: none;
        }
        .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
            overflow-y: auto;
            flex-grow: 1;
        }
        .nav-list li {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .nav-list a {
            display: block;
            padding: 12px 20px;
            color: #d1d9e6;
            text-decoration: none;
            transition: all 0.2s;
        }
        .nav-list a:hover, .nav-list a.active {
            background-color: var(--usa-red);
            color: var(--usa-white);
            padding-left: 25px;
        }
        
        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            overflow-y: auto;
            padding: 40px;
            position: relative;
        }
        .dashboard-welcome {
            text-align: center;
            margin-top: 10vh;
        }
        .dashboard-welcome h1 {
            color: var(--usa-navy);
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .dashboard-welcome p {
            color: #666;
            font-size: 1.1rem;
        }
        
        /* Form Styling */
        .form-header {
            border-bottom: 3px solid var(--usa-red);
            padding-bottom: 10px;
            margin-bottom: 30px;
            color: var(--usa-navy);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .alert {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--usa-navy);
            font-size: 0.9rem;
        }
        input[type="text"], input[type="date"], input[type="url"], select, textarea, input[type="file"] {
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-family: inherit;
            background-color: #fff;
            transition: border-color 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--usa-navy);
            box-shadow: 0 0 5px rgba(10, 49, 97, 0.2);
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        .full-width {
            grid-column: 1 / -1;
        }
        
        /* Buttons */
        .btn-submit {
            background-color: var(--usa-red);
            color: var(--usa-white);
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            font-weight: bold;
            border-radius: 8px; /* Rounded rectangles */
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.15); /* Slight dropshadow */
            transition: background-color 0.2s, transform 0.1s;
        }
        .btn-submit:hover {
            background-color: #921435;
        }
        .btn-submit:active {
            transform: translateY(2px);
            box-shadow: 0 2px 3px rgba(0,0,0,0.15);
        }
        
        /* Image Preview styling */
        .image-preview-container {
            margin-top: 10px;
            padding: 10px;
            background: #eee;
            border-radius: 4px;
            text-align: center;
        }
        .image-preview-container img {
            max-width: 100%;
            max-height: 150px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <a href="?">US State Facts</a>
        </div>
        <ul class="nav-list">
            <?php foreach ($states_list as $state): ?>
                <li>
                    <a href="?state=<?= urlencode($state) ?>" class="<?= ($current_state === $state) ? 'active' : '' ?>">
                        <?= htmlspecialchars($state) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="main-content">
        <?php if ($message): ?>
            <div class="alert"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($current_state && in_array($current_state, $states_list)): ?>
            <form action="?state=<?= urlencode($current_state) ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="state_name" value="<?= htmlspecialchars($current_state) ?>">
                
                <div class="form-header">
                    <h2><?= htmlspecialchars($current_state) ?> Profile</h2>
                    <button type="submit" class="btn-submit">Save State Data</button>
                </div>

                <h3>General Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Founded Date</label>
                        <input type="date" name="founded_date" value="<?= val('founded_date', $state_data) ?>">
                    </div>
                    <div class="form-group">
                        <label>Time Zone</label>
                        <select name="time_zone">
                            <option value="">Select Time Zone</option>
                            <?php foreach ($us_timezones as $tz): ?>
                                <option value="<?= $tz ?>" <?= val('time_zone', $state_data) === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Secondary Time Zone</label>
                        <select name="secondary_time_zone">
                            <option value="">Select Time Zone</option>
                            <?php foreach ($us_timezones as $tz): ?>
                                <option value="<?= $tz ?>" <?= val('secondary_time_zone', $state_data) === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php foreach ($text_fields as $field): ?>
                        <div class="form-group">
                            <label><?= formatLabel($field) ?></label>
                            <input type="<?= $field === 'website' ? 'url' : 'text' ?>" name="<?= $field ?>" value="<?= val($field, $state_data) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3>Detailed Text Content</h3>
                <div class="form-grid" style="grid-template-columns: 1fr;">
                    <?php foreach ($longtext_fields as $field): ?>
                        <div class="form-group full-width">
                            <label><?= formatLabel($field) ?></label>
                            <textarea name="<?= $field ?>"><?= val($field, $state_data) ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3>Media & Attachments</h3>
                <p style="color: #666; font-size: 0.9rem; margin-top: -10px; margin-bottom: 20px;">
                    Upload image files from your device. They will be saved to your server's /attachments folder.
                </p>
                <div class="form-grid">
                    <?php foreach ($image_fields as $field): ?>
                        <div class="form-group">
                            <label><?= formatLabel($field) ?></label>
                            <input type="file" name="<?= $field ?>" accept="image/png, image/jpeg, image/gif, image/webp">
                            
                            <input type="hidden" name="existing_<?= $field ?>" value="<?= val($field, $state_data) ?>">
                            
                            <?php if (val($field, $state_data)): ?>
                                <div class="image-preview-container">
                                    <small style="display:block; margin-bottom:5px;">Current Image:</small>
                                    <img src="<?= val($field, $state_data) ?>" alt="Current <?= formatLabel($field) ?>">
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn-submit">Save State Data</button>
                </div>
            </form>

        <?php else: ?>
            <div class="dashboard-welcome">
                <h1>United States Facts Database</h1>
                <p>Welcome to the central administrative portal.</p>
                <p>Please select a state from the navigation menu on the left to view, input, or update its historical, geographical, and demographic data.</p>
                
                <div style="margin-top: 40px; color: var(--usa-navy);">
                    <svg width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 15v4c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2v-4M17 9l-5 5-5-5M12 12.8V2.5"/>
                    </svg>
                    <h3>Database Initialized & Ready</h3>
                </div>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
