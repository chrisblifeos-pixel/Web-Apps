<?php
/**
 * Database Configuration
 */
$host     = "sql105.byethost10.com";
$db_name  = "b10_39913602_oracle";
$username = "b10_39913602";
$password = "Cr0ssfire";

// Create Connection
$conn = new mysqli($host, $username, $password, $db_name);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/**
 * 1. Initialize Table Structure
 */
$table_sql = "CREATE TABLE IF NOT EXISTS drugs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drug_name VARCHAR(255),
    category VARCHAR(100),
    generic_name VARCHAR(255),
    brand_name VARCHAR(255),
    dosage_forms TEXT,
    drug_class TEXT,
    pill_image VARCHAR(255),
    uses TEXT,
    warnings TEXT,
    before_taking TEXT,
    dosage TEXT,
    side_effects TEXT,
    interactions TEXT,
    availability VARCHAR(100),
    csa_schedule VARCHAR(50),
    manufacturer VARCHAR(255),
    chemical_composition TEXT,
    molecule_image VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$conn->query($table_sql);

/**
 * 2. Handle Form Submission
 */
$status_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Setup File Uploads
    $upload_dir = 'attachments/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    function processUpload($file_key, $dir) {
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
            $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES[$file_key]["name"]));
            $target_path = $dir . $filename;
            if (move_uploaded_file($_FILES[$file_key]["tmp_name"], $target_path)) {
                return $target_path;
            }
        }
        return null;
    }

    $pill_path = processUpload('pill_image', $upload_dir);
    $molecule_path = processUpload('molecule_image', $upload_dir);

    // Prepare SQL Statement
    $stmt = $conn->prepare("INSERT INTO drugs (
        drug_name, category, generic_name, brand_name, dosage_forms, drug_class, pill_image, 
        uses, warnings, before_taking, dosage, side_effects, interactions, availability, 
        csa_schedule, manufacturer, chemical_composition, molecule_image, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("sssssssssssssssssss", 
        $_POST['drug_name'], $_POST['category'], $_POST['generic_name'], $_POST['brand_name'], 
        $_POST['dosage_forms'], $_POST['drug_class'], $pill_path, $_POST['uses'], 
        $_POST['warnings'], $_POST['before_taking'], $_POST['dosage'], $_POST['side_effects'], 
        $_POST['interactions'], $_POST['availability'], $_POST['csa_schedule'], 
        $_POST['manufacturer'], $_POST['chemical_composition'], $molecule_path, $_POST['notes']
    );

    if ($stmt->execute()) {
        $status_message = "<div class='alert success'>Record successfully added!</div>";
    } else {
        $status_message = "<div class='alert error'>Database Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drug Database Interface</title>
    <style>
        :root {
            --bg-color: #121212;
            --container-bg: #1e1e1e;
            --text-color: #e0e0e0;
            --accent-color: #03dac6;
            --input-bg: #2c2c2c;
            --border-color: #333;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 800px;
            background: var(--container-bg);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }

        h2 { border-bottom: 2px solid var(--accent-color); padding-bottom: 10px; color: var(--accent-color); }

        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9em; }

        input[type="text"], select, textarea, input[type="file"] {
            width: 100%;
            padding: 10px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: white;
            box-sizing: border-box;
        }

        textarea { height: 100px; resize: vertical; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        button {
            background-color: var(--accent-color);
            color: #000;
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1em;
            margin-top: 20px;
            width: 100%;
        }

        button:hover { opacity: 0.9; }

        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { background: #1b5e20; color: #c8e6c9; }
        .error { background: #b71c1c; color: #ffcdd2; }
    </style>
</head>
<body>

<div class="container">
    <h2>Add New Medication</h2>
    <?php echo $status_message; ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <div class="grid-2">
            <div class="form-group">
                <label>Drug Name</label>
                <input type="text" name="drug_name" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category">
                    <option value="Analgesic">Analgesic</option>
                    <option value="Antibiotic">Antibiotic</option>
                    <option value="Antiviral">Antiviral</option>
                    <option value="Antidepressant">Antidepressant</option>
                    <option value="Antihistamine">Antihistamine</option>
                    <option value="Cardiovascular">Cardiovascular</option>
                    <option value="Gastrointestinal">Gastrointestinal</option>
                    <option value="Neurological">Neurological</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>Generic Name</label>
                <input type="text" name="generic_name">
            </div>
            <div class="form-group">
                <label>Brand Name</label>
                <input type="text" name="brand_name">
            </div>
        </div>

        <div class="form-group">
            <label>Dosage Forms</label>
            <textarea name="dosage_forms"></textarea>
        </div>

        <div class="form-group">
            <label>Drug Class</label>
            <textarea name="drug_class"></textarea>
        </div>

        <div class="form-group">
            <label>Pill Image</label>
            <input type="file" name="pill_image" accept="image/*">
        </div>

        <div class="form-group">
            <label>Uses</label>
            <textarea name="uses"></textarea>
        </div>

        <div class="form-group">
            <label>Warnings</label>
            <textarea name="warnings"></textarea>
        </div>

        <div class="form-group">
            <label>Before Taking</label>
            <textarea name="before_taking"></textarea>
        </div>

        <div class="form-group">
            <label>Dosage Instructions</label>
            <textarea name="dosage"></textarea>
        </div>

        <div class="form-group">
            <label>Side Effects</label>
            <textarea name="side_effects"></textarea>
        </div>

        <div class="form-group">
            <label>Interactions</label>
            <textarea name="interactions"></textarea>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>Availability (e.g. Prescription only)</label>
                <input type="text" name="availability">
            </div>
            <div class="form-group">
                <label>CSA Schedule</label>
                <input type="text" name="csa_schedule">
            </div>
        </div>

        <div class="form-group">
            <label>Manufacturer</label>
            <input type="text" name="manufacturer">
        </div>

        <div class="form-group">
            <label>Chemical Composition</label>
            <textarea name="chemical_composition"></textarea>
        </div>

        <div class="form-group">
            <label>Molecule Image</label>
            <input type="file" name="molecule_image" accept="image/*">
        </div>

        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes"></textarea>
        </div>

        <button type="submit">Save Drug Entry</button>
    </form>
</div>

</body>
</html>
