<?php
// ============================================================
// ENTERTAINMENT TRACKER - entertainment.php
// ============================================================

// --- DB CONFIG ---
define('DB_HOST', 'sql105.byethost10.com');
define('DB_NAME', 'b10_39913602_entertainment');
define('DB_USER', 'b10_39913602');
define('DB_PASS', 'Cr0ssfire');
define('UPLOAD_DIR', __DIR__ . '/attachments/');
define('UPLOAD_URL', 'attachments/');

// --- DB CONNECTION ---
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'DB Connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// --- ENSURE TABLES EXIST ---
function ensureTables() {
    $db = getDB();
    $db->exec("
    CREATE TABLE IF NOT EXISTS tv_series (
        id INT AUTO_INCREMENT PRIMARY KEY,
        series_name VARCHAR(255) NOT NULL,
        series_synopsis LONGTEXT,
        created_by VARCHAR(255),
        genre VARCHAR(255),
        premiere_date DATE,
        series_status ENUM('On Going','Ended','Canceled','On Hold') DEFAULT 'On Going',
        total_seasons INT DEFAULT 0,
        total_episodes INT DEFAULT 0,
        finale_date DATE,
        original_network VARCHAR(255),
        where_to_watch VARCHAR(255),
        content_rating ENUM('TV-G','TV-PG','TV-14','TV-R','TV-M') DEFAULT 'TV-PG',
        logo_image VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS tv_seasons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        series_id INT NOT NULL,
        season_number INT NOT NULL,
        season_synopsis LONGTEXT,
        premiere_date DATE,
        total_episodes INT DEFAULT 0,
        finale_date DATE,
        cover_image VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (series_id) REFERENCES tv_series(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS tv_episodes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        season_id INT NOT NULL,
        series_id INT NOT NULL,
        episode_number INT NOT NULL,
        episode_title VARCHAR(255),
        episode_synopsis LONGTEXT,
        air_date DATE,
        directed_by VARCHAR(255),
        runtime VARCHAR(50),
        clip_image VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (season_id) REFERENCES tv_seasons(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS tv_watchlog (
        id INT AUTO_INCREMENT PRIMARY KEY,
        series_id INT NOT NULL,
        season_id INT NOT NULL,
        episode_id INT NOT NULL,
        watch_date DATE,
        watch_time TIME,
        rating TINYINT DEFAULT 0,
        comments LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS movies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        movie_title VARCHAR(255) NOT NULL,
        synopsis LONGTEXT,
        created_by VARCHAR(255),
        directed_by VARCHAR(255),
        release_date DATE,
        genre VARCHAR(255),
        runtime VARCHAR(50),
        cover_image VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS movie_watchlog (
        id INT AUTO_INCREMENT PRIMARY KEY,
        movie_id INT NOT NULL,
        watch_date DATE,
        watch_time TIME,
        rating TINYINT DEFAULT 0,
        comments LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS books (
        id INT AUTO_INCREMENT PRIMARY KEY,
        book_title VARCHAR(255) NOT NULL,
        synopsis LONGTEXT,
        author VARCHAR(255),
        release_date DATE,
        genre VARCHAR(255),
        series_name VARCHAR(255),
        book_number VARCHAR(50),
        num_pages INT DEFAULT 0,
        isbn VARCHAR(50),
        publisher VARCHAR(255),
        cover_image VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS reading_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        book_id INT NOT NULL,
        start_time TIME,
        starting_page INT DEFAULT 0,
        end_time TIME,
        ending_page INT DEFAULT 0,
        pages_read INT GENERATED ALWAYS AS (ending_page - starting_page) STORED,
        rating TINYINT DEFAULT 0,
        comments LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

// --- IMAGE UPLOAD HELPER ---
function handleImageUpload($fieldName) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) return null;
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $ext = pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . $ext;
    $dest = UPLOAD_DIR . $filename;
    if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $dest)) {
        return UPLOAD_URL . $filename;
    }
    return null;
}

// ============================================================
// API HANDLER
// ============================================================
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    ensureTables();
    $action = $_GET['api'];
    $db = getDB();

    try {
        // ---- TV SERIES ----
        if ($action === 'get_series') {
            echo json_encode($db->query("SELECT * FROM tv_series ORDER BY series_name")->fetchAll(PDO::FETCH_ASSOC));
        }
        elseif ($action === 'add_series') {
            $img = handleImageUpload('logo_image');
            $st = $db->prepare("INSERT INTO tv_series (series_name,series_synopsis,created_by,genre,premiere_date,series_status,total_seasons,total_episodes,finale_date,original_network,where_to_watch,content_rating,logo_image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $st->execute([$_POST['series_name'],$_POST['series_synopsis'],$_POST['created_by'],$_POST['genre'],$_POST['premiere_date']?:null,$_POST['series_status'],$_POST['total_seasons']?:0,$_POST['total_episodes']?:0,$_POST['finale_date']?:null,$_POST['original_network'],$_POST['where_to_watch'],$_POST['content_rating'],$img]);
            echo json_encode(['success'=>true,'id'=>$db->lastInsertId()]);
        }
        elseif ($action === 'edit_series') {
            $id = intval($_POST['id']);
            $row = $db->query("SELECT logo_image FROM tv_series WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            $img = handleImageUpload('logo_image') ?? $row['logo_image'];
            $st = $db->prepare("UPDATE tv_series SET series_name=?,series_synopsis=?,created_by=?,genre=?,premiere_date=?,series_status=?,total_seasons=?,total_episodes=?,finale_date=?,original_network=?,where_to_watch=?,content_rating=?,logo_image=? WHERE id=?");
            $st->execute([$_POST['series_name'],$_POST['series_synopsis'],$_POST['created_by'],$_POST['genre'],$_POST['premiere_date']?:null,$_POST['series_status'],$_POST['total_seasons']?:0,$_POST['total_episodes']?:0,$_POST['finale_date']?:null,$_POST['original_network'],$_POST['where_to_watch'],$_POST['content_rating'],$img,$id]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'delete_series') {
            $db->prepare("DELETE FROM tv_series WHERE id=?")->execute([intval($_POST['id'])]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'get_series_single') {
            $row = $db->query("SELECT * FROM tv_series WHERE id=".intval($_GET['id']))->fetch(PDO::FETCH_ASSOC);
            echo json_encode($row);
        }

        // ---- SEASONS ----
        elseif ($action === 'get_seasons') {
            echo json_encode($db->query("SELECT * FROM tv_seasons WHERE series_id=".intval($_GET['series_id'])." ORDER BY season_number")->fetchAll(PDO::FETCH_ASSOC));
        }
        elseif ($action === 'add_season') {
            $img = handleImageUpload('cover_image');
            $st = $db->prepare("INSERT INTO tv_seasons (series_id,season_number,season_synopsis,premiere_date,total_episodes,finale_date,cover_image) VALUES (?,?,?,?,?,?,?)");
            $st->execute([$_POST['series_id'],$_POST['season_number'],$_POST['season_synopsis'],$_POST['premiere_date']?:null,$_POST['total_episodes']?:0,$_POST['finale_date']?:null,$img]);
            echo json_encode(['success'=>true,'id'=>$db->lastInsertId()]);
        }
        elseif ($action === 'edit_season') {
            $id = intval($_POST['id']);
            $row = $db->query("SELECT cover_image FROM tv_seasons WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            $img = handleImageUpload('cover_image') ?? $row['cover_image'];
            $st = $db->prepare("UPDATE tv_seasons SET season_number=?,season_synopsis=?,premiere_date=?,total_episodes=?,finale_date=?,cover_image=? WHERE id=?");
            $st->execute([$_POST['season_number'],$_POST['season_synopsis'],$_POST['premiere_date']?:null,$_POST['total_episodes']?:0,$_POST['finale_date']?:null,$img,$id]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'delete_season') {
            $db->prepare("DELETE FROM tv_seasons WHERE id=?")->execute([intval($_POST['id'])]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'get_season_single') {
            $row = $db->query("SELECT * FROM tv_seasons WHERE id=".intval($_GET['id']))->fetch(PDO::FETCH_ASSOC);
            echo json_encode($row);
        }

        // ---- EPISODES ----
        elseif ($action === 'get_episodes') {
            echo json_encode($db->query("SELECT * FROM tv_episodes WHERE season_id=".intval($_GET['season_id'])." ORDER BY episode_number")->fetchAll(PDO::FETCH_ASSOC));
        }
        elseif ($action === 'add_episode') {
            $img = handleImageUpload('clip_image');
            $st = $db->prepare("INSERT INTO tv_episodes (season_id,series_id,episode_number,episode_title,episode_synopsis,air_date,directed_by,runtime,clip_image) VALUES (?,?,?,?,?,?,?,?,?)");
            $st->execute([$_POST['season_id'],$_POST['series_id'],$_POST['episode_number'],$_POST['episode_title'],$_POST['episode_synopsis'],$_POST['air_date']?:null,$_POST['directed_by'],$_POST['runtime'],$img]);
            echo json_encode(['success'=>true,'id'=>$db->lastInsertId()]);
        }
        elseif ($action === 'edit_episode') {
            $id = intval($_POST['id']);
            $row = $db->query("SELECT clip_image FROM tv_episodes WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            $img = handleImageUpload('clip_image') ?? $row['clip_image'];
            $st = $db->prepare("UPDATE tv_episodes SET episode_number=?,episode_title=?,episode_synopsis=?,air_date=?,directed_by=?,runtime=?,clip_image=? WHERE id=?");
            $st->execute([$_POST['episode_number'],$_POST['episode_title'],$_POST['episode_synopsis'],$_POST['air_date']?:null,$_POST['directed_by'],$_POST['runtime'],$img,$id]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'delete_episode') {
            $db->prepare("DELETE FROM tv_episodes WHERE id=?")->execute([intval($_POST['id'])]);
            echo json_encode(['success'=>true]);
        }

        // ---- TV WATCHLOG ----
        elseif ($action === 'get_tv_watchlog') {
            $rows = $db->query("SELECT w.*,s.series_name,se.season_number,e.episode_title,e.episode_number FROM tv_watchlog w LEFT JOIN tv_series s ON s.id=w.series_id LEFT JOIN tv_seasons se ON se.id=w.season_id LEFT JOIN tv_episodes e ON e.id=w.episode_id ORDER BY w.watch_date DESC, w.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($rows);
        }
        elseif ($action === 'add_tv_watchlog') {
            $st = $db->prepare("INSERT INTO tv_watchlog (series_id,season_id,episode_id,watch_date,watch_time,rating,comments) VALUES (?,?,?,?,?,?,?)");
            $st->execute([$_POST['series_id'],$_POST['season_id'],$_POST['episode_id'],$_POST['watch_date']?:null,$_POST['watch_time']?:null,$_POST['rating']?:0,$_POST['comments']]);
            echo json_encode(['success'=>true,'id'=>$db->lastInsertId()]);
        }
        elseif ($action === 'edit_tv_watchlog') {
            $st = $db->prepare("UPDATE tv_watchlog SET series_id=?,season_id=?,episode_id=?,watch_date=?,watch_time=?,rating=?,comments=? WHERE id=?");
            $st->execute([$_POST['series_id'],$_POST['season_id'],$_POST['episode_id'],$_POST['watch_date']?:null,$_POST['watch_time']?:null,$_POST['rating']?:0,$_POST['comments'],intval($_POST['id'])]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'delete_tv_watchlog') {
            $db->prepare("DELETE FROM tv_watchlog WHERE id=?")->execute([intval($_POST['id'])]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'get_tv_watchlog_single') {
            echo json_encode($db->query("SELECT * FROM tv_watchlog WHERE id=".intval($_GET['id']))->fetch(PDO::FETCH_ASSOC));
        }
        elseif ($action === 'get_seasons_for_series') {
            echo json_encode($db->query("SELECT * FROM tv_seasons WHERE series_id=".intval($_GET['series_id'])." ORDER BY season_number")->fetchAll(PDO::FETCH_ASSOC));
        }
        elseif ($action === 'get_episodes_for_season') {
            echo json_encode($db->query("SELECT * FROM tv_episodes WHERE season_id=".intval($_GET['season_id'])." ORDER BY episode_number")->fetchAll(PDO::FETCH_ASSOC));
        }

        // ---- MOVIES ----
        elseif ($action === 'get_movies') {
            echo json_encode($db->query("SELECT * FROM movies ORDER BY movie_title")->fetchAll(PDO::FETCH_ASSOC));
        }
        elseif ($action === 'add_movie') {
            $img = handleImageUpload('cover_image');
            $st = $db->prepare("INSERT INTO movies (movie_title,synopsis,created_by,directed_by,release_date,genre,runtime,cover_image) VALUES (?,?,?,?,?,?,?,?)");
            $st->execute([$_POST['movie_title'],$_POST['synopsis'],$_POST['created_by'],$_POST['directed_by'],$_POST['release_date']?:null,$_POST['genre'],$_POST['runtime'],$img]);
            echo json_encode(['success'=>true,'id'=>$db->lastInsertId()]);
        }
        elseif ($action === 'edit_movie') {
            $id = intval($_POST['id']);
            $row = $db->query("SELECT cover_image FROM movies WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            $img = handleImageUpload('cover_image') ?? $row['cover_image'];
            $st = $db->prepare("UPDATE movies SET movie_title=?,synopsis=?,created_by=?,directed_by=?,release_date=?,genre=?,runtime=?,cover_image=? WHERE id=?");
            $st->execute([$_POST['movie_title'],$_POST['synopsis'],$_POST['created_by'],$_POST['directed_by'],$_POST['release_date']?:null,$_POST['genre'],$_POST['runtime'],$img,$id]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'delete_movie') {
            $db->prepare("DELETE FROM movies WHERE id=?")->execute([intval($_POST['id'])]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'get_movie_single') {
            echo json_encode($db->query("SELECT * FROM movies WHERE id=".intval($_GET['id']))->fetch(PDO::FETCH_ASSOC));
        }

        // ---- MOVIE WATCHLOG ----
        elseif ($action === 'get_movie_watchlog') {
            $rows = $db->query("SELECT w.*,m.movie_title FROM movie_watchlog w LEFT JOIN movies m ON m.id=w.movie_id ORDER BY w.watch_date DESC, w.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($rows);
        }
        elseif ($action === 'add_movie_watchlog') {
            $st = $db->prepare("INSERT INTO movie_watchlog (movie_id,watch_date,watch_time,rating,comments) VALUES (?,?,?,?,?)");
            $st->execute([$_POST['movie_id'],$_POST['watch_date']?:null,$_POST['watch_time']?:null,$_POST['rating']?:0,$_POST['comments']]);
            echo json_encode(['success'=>true,'id'=>$db->lastInsertId()]);
        }
        elseif ($action === 'edit_movie_watchlog') {
            $st = $db->prepare("UPDATE movie_watchlog SET movie_id=?,watch_date=?,watch_time=?,rating=?,comments=? WHERE id=?");
            $st->execute([$_POST['movie_id'],$_POST['watch_date']?:null,$_POST['watch_time']?:null,$_POST['rating']?:0,$_POST['comments'],intval($_POST['id'])]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'delete_movie_watchlog') {
            $db->prepare("DELETE FROM movie_watchlog WHERE id=?")->execute([intval($_POST['id'])]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'get_movie_watchlog_single') {
            echo json_encode($db->query("SELECT * FROM movie_watchlog WHERE id=".intval($_GET['id']))->fetch(PDO::FETCH_ASSOC));
        }

        // ---- BOOKS ----
        elseif ($action === 'get_books') {
            echo json_encode($db->query("SELECT * FROM books ORDER BY book_title")->fetchAll(PDO::FETCH_ASSOC));
        }
        elseif ($action === 'add_book') {
            $img = handleImageUpload('cover_image');
            $st = $db->prepare("INSERT INTO books (book_title,synopsis,author,release_date,genre,series_name,book_number,num_pages,isbn,publisher,cover_image) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $st->execute([$_POST['book_title'],$_POST['synopsis'],$_POST['author'],$_POST['release_date']?:null,$_POST['genre'],$_POST['series_name'],$_POST['book_number'],$_POST['num_pages']?:0,$_POST['isbn'],$_POST['publisher'],$img]);
            echo json_encode(['success'=>true,'id'=>$db->lastInsertId()]);
        }
        elseif ($action === 'edit_book') {
            $id = intval($_POST['id']);
            $row = $db->query("SELECT cover_image FROM books WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
            $img = handleImageUpload('cover_image') ?? $row['cover_image'];
            $st = $db->prepare("UPDATE books SET book_title=?,synopsis=?,author=?,release_date=?,genre=?,series_name=?,book_number=?,num_pages=?,isbn=?,publisher=?,cover_image=? WHERE id=?");
            $st->execute([$_POST['book_title'],$_POST['synopsis'],$_POST['author'],$_POST['release_date']?:null,$_POST['genre'],$_POST['series_name'],$_POST['book_number'],$_POST['num_pages']?:0,$_POST['isbn'],$_POST['publisher'],$img,$id]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'delete_book') {
            $db->prepare("DELETE FROM books WHERE id=?")->execute([intval($_POST['id'])]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'get_book_single') {
            echo json_encode($db->query("SELECT * FROM books WHERE id=".intval($_GET['id']))->fetch(PDO::FETCH_ASSOC));
        }

        // ---- READING LOG ----
        elseif ($action === 'get_reading_log') {
            $rows = $db->query("SELECT r.*,b.book_title FROM reading_log r LEFT JOIN books b ON b.id=r.book_id ORDER BY r.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($rows);
        }
        elseif ($action === 'add_reading_log') {
            $st = $db->prepare("INSERT INTO reading_log (book_id,start_time,starting_page,end_time,ending_page,rating,comments) VALUES (?,?,?,?,?,?,?)");
            $st->execute([$_POST['book_id'],$_POST['start_time']?:null,$_POST['starting_page']?:0,$_POST['end_time']?:null,$_POST['ending_page']?:0,$_POST['rating']?:0,$_POST['comments']]);
            echo json_encode(['success'=>true,'id'=>$db->lastInsertId()]);
        }
        elseif ($action === 'edit_reading_log') {
            $st = $db->prepare("UPDATE reading_log SET book_id=?,start_time=?,starting_page=?,end_time=?,ending_page=?,rating=?,comments=? WHERE id=?");
            $st->execute([$_POST['book_id'],$_POST['start_time']?:null,$_POST['starting_page']?:0,$_POST['end_time']?:null,$_POST['ending_page']?:0,$_POST['rating']?:0,$_POST['comments'],intval($_POST['id'])]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'delete_reading_log') {
            $db->prepare("DELETE FROM reading_log WHERE id=?")->execute([intval($_POST['id'])]);
            echo json_encode(['success'=>true]);
        }
        elseif ($action === 'get_reading_log_single') {
            echo json_encode($db->query("SELECT * FROM reading_log WHERE id=".intval($_GET['id']))->fetch(PDO::FETCH_ASSOC));
        }
        else {
            echo json_encode(['error'=>'Unknown action']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

ensureTables();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entertainment Tracker</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #0d0f14;
    --bg2: #13161e;
    --bg3: #1a1e28;
    --card: #1e2333;
    --card2: #252a3a;
    --border: #2e3348;
    --accent-tv: #e8a84c;
    --accent-movie: #6c8fef;
    --accent-book: #7ecf8e;
    --text: #eaedf5;
    --text2: #9098b4;
    --text3: #5d6480;
    --danger: #e05c5c;
    --shadow: 0 8px 32px rgba(0,0,0,0.45);
    --radius: 14px;
    --radius-sm: 8px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    font-size: 15px;
}

/* HEADER */
.app-header {
    padding: 28px 40px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 18px;
    background: var(--bg2);
}
.app-logo {
    font-family: 'Playfair Display', serif;
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--text);
    letter-spacing: -0.5px;
}
.app-logo span { color: var(--accent-movie); }

/* MAIN NAV */
.main-nav {
    display: flex;
    gap: 12px;
    padding: 24px 40px;
    background: var(--bg2);
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
}
.nav-btn {
    display: flex; align-items: center; gap: 10px;
    padding: 13px 24px;
    border-radius: var(--radius);
    border: none;
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.95rem;
    font-weight: 600;
    letter-spacing: 0.3px;
    transition: all 0.2s;
    box-shadow: var(--shadow);
    color: var(--text);
    background: var(--card);
    border: 1px solid var(--border);
}
.nav-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(0,0,0,0.5); }
.nav-btn.active-tv { background: linear-gradient(135deg,#c47a28,var(--accent-tv)); border-color: var(--accent-tv); color: #fff; }
.nav-btn.active-movie { background: linear-gradient(135deg,#3d5ecf,var(--accent-movie)); border-color: var(--accent-movie); color: #fff; }
.nav-btn.active-book { background: linear-gradient(135deg,#4aa85a,var(--accent-book)); border-color: var(--accent-book); color: #0d0f14; }
.nav-btn .icon { font-size: 1.2rem; }

/* MAIN AREA */
.main-content { padding: 32px 40px; min-height: calc(100vh - 160px); }
.section { display: none; }
.section.active { display: block; }

/* MODULE DASHBOARD */
.module-dashboard { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 32px; }
.module-btn {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 28px; border-radius: var(--radius);
    border: 1px solid var(--border);
    cursor: pointer; background: var(--card);
    color: var(--text); font-family: 'DM Sans', sans-serif;
    font-size: 0.95rem; font-weight: 500;
    transition: all 0.2s; box-shadow: 0 4px 16px rgba(0,0,0,0.3);
}
.module-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.module-btn.tv-accent { border-color: var(--accent-tv); }
.module-btn.tv-accent:hover { background: rgba(232,168,76,0.15); }
.module-btn.movie-accent { border-color: var(--accent-movie); }
.module-btn.movie-accent:hover { background: rgba(108,143,239,0.15); }
.module-btn.book-accent { border-color: var(--accent-book); }
.module-btn.book-accent:hover { background: rgba(126,207,142,0.15); }

/* SUB PANELS */
.sub-panel { display: none; }
.sub-panel.active { display: block; }
.panel-header {
    display: flex; align-items: center; gap: 16px;
    margin-bottom: 24px;
}
.back-btn {
    background: var(--card); border: 1px solid var(--border);
    color: var(--text2); padding: 7px 16px; border-radius: var(--radius-sm);
    cursor: pointer; font-family: 'DM Sans', sans-serif; font-size: 0.85rem;
    transition: all 0.18s;
}
.back-btn:hover { color: var(--text); border-color: var(--text3); }
.panel-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem; font-weight: 600;
}

/* LISTS */
.item-list { display: flex; flex-direction: column; gap: 10px; }
.item-row {
    display: flex; align-items: center; gap: 16px;
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 14px 18px;
    transition: all 0.18s; cursor: pointer;
}
.item-row:hover { border-color: var(--text3); background: var(--card2); }
.item-thumb {
    width: 52px; height: 52px; object-fit: cover;
    border-radius: var(--radius-sm); flex-shrink: 0;
    background: var(--bg3);
}
.item-thumb-placeholder {
    width: 52px; height: 52px; border-radius: var(--radius-sm);
    background: var(--bg3); display: flex; align-items: center;
    justify-content: center; color: var(--text3); font-size: 1.4rem;
    flex-shrink: 0;
}
.item-info { flex: 1; }
.item-title { font-weight: 600; font-size: 0.97rem; margin-bottom: 3px; }
.item-sub { color: var(--text2); font-size: 0.82rem; }
.item-actions { display: flex; gap: 8px; flex-shrink: 0; }

/* BUTTONS */
.btn {
    padding: 9px 18px; border-radius: var(--radius-sm);
    border: none; cursor: pointer; font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem; font-weight: 500; transition: all 0.18s;
}
.btn-primary-tv { background: var(--accent-tv); color: #1a0e00; }
.btn-primary-tv:hover { background: #f0b85a; }
.btn-primary-movie { background: var(--accent-movie); color: #fff; }
.btn-primary-movie:hover { background: #7fa0f5; }
.btn-primary-book { background: var(--accent-book); color: #0a2010; }
.btn-primary-book:hover { background: #8fdda0; }
.btn-edit { background: var(--card2); color: var(--text2); border: 1px solid var(--border); }
.btn-edit:hover { color: var(--text); }
.btn-delete { background: rgba(224,92,92,0.15); color: var(--danger); border: 1px solid rgba(224,92,92,0.3); }
.btn-delete:hover { background: rgba(224,92,92,0.25); }
.btn-secondary { background: var(--card2); color: var(--text2); border: 1px solid var(--border); }
.btn-secondary:hover { color: var(--text); border-color: var(--text3); }

/* MODALS */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.75); z-index: 1000;
    align-items: center; justify-content: center;
    padding: 20px;
    backdrop-filter: blur(4px);
}
.modal-overlay.open { display: flex; }
.modal {
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 30px;
    width: 100%; max-width: 600px; max-height: 90vh;
    overflow-y: auto; box-shadow: 0 24px 64px rgba(0,0,0,0.7);
    animation: slideUp 0.2s ease;
}
@keyframes slideUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
.modal-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.25rem; font-weight: 600; margin-bottom: 22px;
}
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border); }

/* FORM ELEMENTS */
.form-group { margin-bottom: 16px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
label { display: block; color: var(--text2); font-size: 0.82rem; font-weight: 500; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
input[type="text"], input[type="number"], input[type="date"], input[type="time"],
select, textarea {
    width: 100%; background: var(--bg2); border: 1px solid var(--border);
    color: var(--text); padding: 10px 14px; border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif; font-size: 0.92rem;
    transition: border-color 0.18s; outline: none;
}
input:focus, select:focus, textarea:focus { border-color: var(--text3); }
textarea { resize: vertical; min-height: 80px; }
select option { background: var(--card); }
input[type="file"] {
    background: var(--bg3); border: 1px dashed var(--border);
    color: var(--text2); padding: 10px;
}

/* STAR RATING */
.star-rating { display: flex; gap: 5px; }
.star-rating .star {
    font-size: 1.5rem; cursor: pointer; color: var(--text3);
    transition: color 0.15s; line-height: 1;
}
.star-rating .star.on { color: #f0b830; }

/* BREADCRUMB */
.breadcrumb { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; color: var(--text2); font-size: 0.85rem; flex-wrap: wrap; }
.breadcrumb span { color: var(--text3); }
.breadcrumb a { color: var(--text2); cursor: pointer; text-decoration: none; }
.breadcrumb a:hover { color: var(--text); }

/* WATCHLOG ENTRIES */
.log-row {
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 16px 20px;
    display: flex; align-items: flex-start; gap: 16px;
    transition: all 0.18s;
}
.log-row:hover { border-color: var(--text3); }
.log-stars { color: #f0b830; font-size: 0.9rem; }
.log-meta { color: var(--text2); font-size: 0.82rem; margin-top: 4px; }
.log-comment { color: var(--text2); font-size: 0.88rem; margin-top: 6px; font-style: italic; }

/* CURRENT IMAGE PREVIEW */
.current-img-preview { margin-top: 8px; }
.current-img-preview img { height: 60px; border-radius: 6px; object-fit: cover; }

/* EMPTY STATE */
.empty-state { text-align: center; padding: 48px 20px; color: var(--text3); }
.empty-state .empty-icon { font-size: 3rem; margin-bottom: 12px; }
.empty-state p { font-size: 0.95rem; }

/* RESPONSIVE */
@media (max-width: 640px) {
    .app-header, .main-nav, .main-content { padding-left: 20px; padding-right: 20px; }
    .form-row { grid-template-columns: 1fr; }
}

/* NOTIFY */
.notify {
    position: fixed; bottom: 28px; right: 28px; z-index: 9999;
    background: var(--card2); border: 1px solid var(--border);
    color: var(--text); padding: 14px 22px; border-radius: var(--radius);
    box-shadow: var(--shadow); font-size: 0.92rem;
    transform: translateY(80px); opacity: 0;
    transition: all 0.3s; pointer-events: none;
}
.notify.show { transform: translateY(0); opacity: 1; }
.notify.ok { border-color: var(--accent-book); }
.notify.err { border-color: var(--danger); }
</style>
</head>
<body>

<header class="app-header">
    <div class="app-logo">Entertainment<span>.</span>Tracker</div>
</header>

<nav class="main-nav">
    <button class="nav-btn" id="navTV" onclick="showSection('tv')">
        <span class="icon">📺</span> TV Series
    </button>
    <button class="nav-btn" id="navMovie" onclick="showSection('movie')">
        <span class="icon">🎬</span> Movies
    </button>
    <button class="nav-btn" id="navBook" onclick="showSection('book')">
        <span class="icon">📚</span> Books
    </button>
</nav>

<main class="main-content">

<!-- ===== TV SERIES SECTION ===== -->
<div class="section" id="section-tv">
    <!-- Main TV Dashboard -->
    <div class="sub-panel active" id="tv-dashboard">
        <h2 class="panel-title" style="margin-bottom:22px; font-family:'Playfair Display',serif;">TV Series</h2>
        <div class="module-dashboard">
            <button class="module-btn tv-accent" onclick="openAddSeries()"><span>＋</span> Add New Series</button>
            <button class="module-btn tv-accent" onclick="showTVPanel('tv-series-list')">📋 View Series</button>
            <button class="module-btn tv-accent" onclick="showTVPanel('tv-watchlog')">🎧 Watch Log</button>
        </div>
    </div>

    <!-- Series List -->
    <div class="sub-panel" id="tv-series-list">
        <div class="panel-header">
            <button class="back-btn" onclick="showTVPanel('tv-dashboard')">← Back</button>
            <h2 class="panel-title">All Series</h2>
            <button class="btn btn-primary-tv" onclick="openAddSeries()" style="margin-left:auto;">＋ Add Series</button>
        </div>
        <div class="item-list" id="series-list-container"></div>
    </div>

    <!-- Series Detail (seasons) -->
    <div class="sub-panel" id="tv-series-detail">
        <div class="breadcrumb">
            <a onclick="showTVPanel('tv-series-list')">Series</a>
            <span>›</span>
            <span id="series-detail-name">–</span>
        </div>
        <div class="panel-header">
            <button class="back-btn" onclick="showTVPanel('tv-series-list')">← Back</button>
            <h2 class="panel-title" id="series-detail-title">Seasons</h2>
            <div style="margin-left:auto;display:flex;gap:8px;">
                <button class="btn btn-edit" id="series-edit-btn" onclick="openEditSeriesFromDetail()">✏️ Edit Series</button>
                <button class="btn btn-delete" id="series-delete-btn" onclick="deleteSeriesFromDetail()">🗑 Delete</button>
                <button class="btn btn-primary-tv" onclick="openAddSeason()">＋ Add Season</button>
            </div>
        </div>
        <div class="item-list" id="seasons-list-container"></div>
    </div>

    <!-- Season Detail (episodes) -->
    <div class="sub-panel" id="tv-season-detail">
        <div class="breadcrumb">
            <a onclick="showTVPanel('tv-series-list')">Series</a>
            <span>›</span>
            <a onclick="showTVPanel('tv-series-detail')" id="season-detail-series-link">–</a>
            <span>›</span>
            <span id="season-detail-name">–</span>
        </div>
        <div class="panel-header">
            <button class="back-btn" onclick="showTVPanel('tv-series-detail')">← Back</button>
            <h2 class="panel-title" id="season-detail-title">Episodes</h2>
            <div style="margin-left:auto;display:flex;gap:8px;">
                <button class="btn btn-edit" onclick="openEditSeason()">✏️ Edit Season</button>
                <button class="btn btn-delete" onclick="deleteCurrentSeason()">🗑 Delete</button>
                <button class="btn btn-primary-tv" onclick="openAddEpisode()">＋ Add Episode</button>
            </div>
        </div>
        <div class="item-list" id="episodes-list-container"></div>
    </div>

    <!-- TV Watchlog -->
    <div class="sub-panel" id="tv-watchlog">
        <div class="panel-header">
            <button class="back-btn" onclick="showTVPanel('tv-dashboard')">← Back</button>
            <h2 class="panel-title">TV Watch Log</h2>
            <button class="btn btn-primary-tv" onclick="openAddTVLog()" style="margin-left:auto;">＋ Add Entry</button>
        </div>
        <div class="item-list" id="tv-watchlog-container"></div>
    </div>
</div>

<!-- ===== MOVIES SECTION ===== -->
<div class="section" id="section-movie">
    <div class="sub-panel active" id="movie-dashboard">
        <h2 class="panel-title" style="margin-bottom:22px;font-family:'Playfair Display',serif;">Movies</h2>
        <div class="module-dashboard">
            <button class="module-btn movie-accent" onclick="openAddMovie()"><span>＋</span> Add New Movie</button>
            <button class="module-btn movie-accent" onclick="showMoviePanel('movie-list')">🎞 View Movies</button>
            <button class="module-btn movie-accent" onclick="showMoviePanel('movie-watchlog')">🎧 Watch Log</button>
        </div>
    </div>
    <div class="sub-panel" id="movie-list">
        <div class="panel-header">
            <button class="back-btn" onclick="showMoviePanel('movie-dashboard')">← Back</button>
            <h2 class="panel-title">All Movies</h2>
            <button class="btn btn-primary-movie" onclick="openAddMovie()" style="margin-left:auto;">＋ Add Movie</button>
        </div>
        <div class="item-list" id="movie-list-container"></div>
    </div>
    <div class="sub-panel" id="movie-watchlog">
        <div class="panel-header">
            <button class="back-btn" onclick="showMoviePanel('movie-dashboard')">← Back</button>
            <h2 class="panel-title">Movie Watch Log</h2>
            <button class="btn btn-primary-movie" onclick="openAddMovieLog()" style="margin-left:auto;">＋ Add Entry</button>
        </div>
        <div class="item-list" id="movie-watchlog-container"></div>
    </div>
</div>

<!-- ===== BOOKS SECTION ===== -->
<div class="section" id="section-book">
    <div class="sub-panel active" id="book-dashboard">
        <h2 class="panel-title" style="margin-bottom:22px;font-family:'Playfair Display',serif;">Books</h2>
        <div class="module-dashboard">
            <button class="module-btn book-accent" onclick="openAddBook()"><span>＋</span> Add New Book</button>
            <button class="module-btn book-accent" onclick="showBookPanel('book-list')">📖 View Books</button>
            <button class="module-btn book-accent" onclick="showBookPanel('reading-log')">📒 Reading Log</button>
        </div>
    </div>
    <div class="sub-panel" id="book-list">
        <div class="panel-header">
            <button class="back-btn" onclick="showBookPanel('book-dashboard')">← Back</button>
            <h2 class="panel-title">All Books</h2>
            <button class="btn btn-primary-book" onclick="openAddBook()" style="margin-left:auto;">＋ Add Book</button>
        </div>
        <div class="item-list" id="book-list-container"></div>
    </div>
    <div class="sub-panel" id="reading-log">
        <div class="panel-header">
            <button class="back-btn" onclick="showBookPanel('book-dashboard')">← Back</button>
            <h2 class="panel-title">Reading Log</h2>
            <button class="btn btn-primary-book" onclick="openAddReadingLog()" style="margin-left:auto;">＋ Add Entry</button>
        </div>
        <div class="item-list" id="reading-log-container"></div>
    </div>
</div>

</main>

<!-- NOTIFICATION -->
<div class="notify" id="notify"></div>

<!-- =================== MODALS =================== -->

<!-- ADD/EDIT SERIES -->
<div class="modal-overlay" id="modal-series">
<div class="modal">
<h3 class="modal-title" id="modal-series-title">Add New Series</h3>
<input type="hidden" id="series-id">
<div class="form-row">
    <div class="form-group"><label>Series Name *</label><input type="text" id="s-series_name"></div>
    <div class="form-group"><label>Created By</label><input type="text" id="s-created_by"></div>
</div>
<div class="form-group"><label>Synopsis</label><textarea id="s-series_synopsis"></textarea></div>
<div class="form-row">
    <div class="form-group"><label>Genre</label><input type="text" id="s-genre"></div>
    <div class="form-group"><label>Original Network</label><input type="text" id="s-original_network"></div>
</div>
<div class="form-row">
    <div class="form-group"><label>Premiere Date</label><input type="date" id="s-premiere_date"></div>
    <div class="form-group"><label>Finale Date</label><input type="date" id="s-finale_date"></div>
</div>
<div class="form-row">
    <div class="form-group"><label>Status</label>
        <select id="s-series_status">
            <option>On Going</option><option>Ended</option><option>Canceled</option><option>On Hold</option>
        </select>
    </div>
    <div class="form-group"><label>Content Rating</label>
        <select id="s-content_rating">
            <option>TV-G</option><option>TV-PG</option><option>TV-14</option><option>TV-R</option><option>TV-M</option>
        </select>
    </div>
</div>
<div class="form-row">
    <div class="form-group"><label>Total Seasons</label><input type="number" id="s-total_seasons" min="0"></div>
    <div class="form-group"><label>Total Episodes</label><input type="number" id="s-total_episodes" min="0"></div>
</div>
<div class="form-group"><label>Where to Watch</label><input type="text" id="s-where_to_watch"></div>
<div class="form-group"><label>Series Logo Image</label><input type="file" id="s-logo_image" accept="image/*">
    <div class="current-img-preview" id="s-logo-preview"></div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" onclick="closeModal('modal-series')">Cancel</button>
    <button class="btn btn-primary-tv" onclick="saveSeries()">Save Series</button>
</div>
</div>
</div>

<!-- ADD/EDIT SEASON -->
<div class="modal-overlay" id="modal-season">
<div class="modal">
<h3 class="modal-title" id="modal-season-title">Add Season</h3>
<input type="hidden" id="season-id">
<div class="form-row">
    <div class="form-group"><label>Season Number</label><input type="number" id="sn-season_number" min="1"></div>
    <div class="form-group"><label>Total Episodes</label><input type="number" id="sn-total_episodes" min="0"></div>
</div>
<div class="form-group"><label>Season Synopsis</label><textarea id="sn-season_synopsis"></textarea></div>
<div class="form-row">
    <div class="form-group"><label>Premiere Date</label><input type="date" id="sn-premiere_date"></div>
    <div class="form-group"><label>Finale Date</label><input type="date" id="sn-finale_date"></div>
</div>
<div class="form-group"><label>Season Cover Image</label><input type="file" id="sn-cover_image" accept="image/*">
    <div class="current-img-preview" id="sn-cover-preview"></div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" onclick="closeModal('modal-season')">Cancel</button>
    <button class="btn btn-primary-tv" onclick="saveSeason()">Save Season</button>
</div>
</div>
</div>

<!-- ADD/EDIT EPISODE -->
<div class="modal-overlay" id="modal-episode">
<div class="modal">
<h3 class="modal-title" id="modal-episode-title">Add Episode</h3>
<input type="hidden" id="episode-id">
<div class="form-row">
    <div class="form-group"><label>Episode Number</label><input type="number" id="ep-episode_number" min="1"></div>
    <div class="form-group"><label>Episode Title</label><input type="text" id="ep-episode_title"></div>
</div>
<div class="form-group"><label>Synopsis</label><textarea id="ep-episode_synopsis"></textarea></div>
<div class="form-row">
    <div class="form-group"><label>Air Date</label><input type="date" id="ep-air_date"></div>
    <div class="form-group"><label>Runtime</label><input type="text" id="ep-runtime" placeholder="e.g. 42 min"></div>
</div>
<div class="form-group"><label>Directed By</label><input type="text" id="ep-directed_by"></div>
<div class="form-group"><label>Episode Clip Image</label><input type="file" id="ep-clip_image" accept="image/*">
    <div class="current-img-preview" id="ep-clip-preview"></div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" onclick="closeModal('modal-episode')">Cancel</button>
    <button class="btn btn-primary-tv" onclick="saveEpisode()">Save Episode</button>
</div>
</div>
</div>

<!-- TV WATCHLOG ADD/EDIT -->
<div class="modal-overlay" id="modal-tv-log">
<div class="modal">
<h3 class="modal-title" id="modal-tv-log-title">Add Watch Log Entry</h3>
<input type="hidden" id="tv-log-id">
<div class="form-group"><label>Series</label>
    <select id="tvl-series_id" onchange="loadLogSeasons()"><option value="">-- Select Series --</option></select>
</div>
<div class="form-group"><label>Season</label>
    <select id="tvl-season_id" onchange="loadLogEpisodes()"><option value="">-- Select Season --</option></select>
</div>
<div class="form-group"><label>Episode</label>
    <select id="tvl-episode_id"><option value="">-- Select Episode --</option></select>
</div>
<div class="form-row">
    <div class="form-group"><label>Date</label><input type="date" id="tvl-watch_date"></div>
    <div class="form-group"><label>Time</label><input type="time" id="tvl-watch_time"></div>
</div>
<div class="form-group"><label>Rating</label>
    <div class="star-rating" id="tvl-star-rating">
        <span class="star" data-val="1" onclick="setStarRating('tvl',1)">★</span>
        <span class="star" data-val="2" onclick="setStarRating('tvl',2)">★</span>
        <span class="star" data-val="3" onclick="setStarRating('tvl',3)">★</span>
        <span class="star" data-val="4" onclick="setStarRating('tvl',4)">★</span>
        <span class="star" data-val="5" onclick="setStarRating('tvl',5)">★</span>
    </div>
    <input type="hidden" id="tvl-rating" value="0">
</div>
<div class="form-group"><label>Comments</label><textarea id="tvl-comments"></textarea></div>
<div class="modal-footer">
    <button class="btn btn-secondary" onclick="closeModal('modal-tv-log')">Cancel</button>
    <button class="btn btn-primary-tv" onclick="saveTVLog()">Save Entry</button>
</div>
</div>
</div>

<!-- ADD/EDIT MOVIE -->
<div class="modal-overlay" id="modal-movie">
<div class="modal">
<h3 class="modal-title" id="modal-movie-title">Add Movie</h3>
<input type="hidden" id="movie-id">
<div class="form-row">
    <div class="form-group"><label>Movie Title *</label><input type="text" id="mv-movie_title"></div>
    <div class="form-group"><label>Genre</label><input type="text" id="mv-genre"></div>
</div>
<div class="form-group"><label>Synopsis</label><textarea id="mv-synopsis"></textarea></div>
<div class="form-row">
    <div class="form-group"><label>Created By</label><input type="text" id="mv-created_by"></div>
    <div class="form-group"><label>Directed By</label><input type="text" id="mv-directed_by"></div>
</div>
<div class="form-row">
    <div class="form-group"><label>Release Date</label><input type="date" id="mv-release_date"></div>
    <div class="form-group"><label>Runtime</label><input type="text" id="mv-runtime" placeholder="e.g. 2h 15min"></div>
</div>
<div class="form-group"><label>Cover Image</label><input type="file" id="mv-cover_image" accept="image/*">
    <div class="current-img-preview" id="mv-cover-preview"></div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" onclick="closeModal('modal-movie')">Cancel</button>
    <button class="btn btn-primary-movie" onclick="saveMovie()">Save Movie</button>
</div>
</div>
</div>

<!-- MOVIE WATCHLOG -->
<div class="modal-overlay" id="modal-movie-log">
<div class="modal">
<h3 class="modal-title" id="modal-movie-log-title">Add Movie Watch Log</h3>
<input type="hidden" id="movie-log-id">
<div class="form-group"><label>Movie</label>
    <select id="mvl-movie_id"><option value="">-- Select Movie --</option></select>
</div>
<div class="form-row">
    <div class="form-group"><label>Date</label><input type="date" id="mvl-watch_date"></div>
    <div class="form-group"><label>Time</label><input type="time" id="mvl-watch_time"></div>
</div>
<div class="form-group"><label>Rating</label>
    <div class="star-rating" id="mvl-star-rating">
        <span class="star" data-val="1" onclick="setStarRating('mvl',1)">★</span>
        <span class="star" data-val="2" onclick="setStarRating('mvl',2)">★</span>
        <span class="star" data-val="3" onclick="setStarRating('mvl',3)">★</span>
        <span class="star" data-val="4" onclick="setStarRating('mvl',4)">★</span>
        <span class="star" data-val="5" onclick="setStarRating('mvl',5)">★</span>
    </div>
    <input type="hidden" id="mvl-rating" value="0">
</div>
<div class="form-group"><label>Comments</label><textarea id="mvl-comments"></textarea></div>
<div class="modal-footer">
    <button class="btn btn-secondary" onclick="closeModal('modal-movie-log')">Cancel</button>
    <button class="btn btn-primary-movie" onclick="saveMovieLog()">Save Entry</button>
</div>
</div>
</div>

<!-- ADD/EDIT BOOK -->
<div class="modal-overlay" id="modal-book">
<div class="modal">
<h3 class="modal-title" id="modal-book-title">Add Book</h3>
<input type="hidden" id="book-id">
<div class="form-row">
    <div class="form-group"><label>Book Title *</label><input type="text" id="bk-book_title"></div>
    <div class="form-group"><label>Author</label><input type="text" id="bk-author"></div>
</div>
<div class="form-group"><label>Synopsis</label><textarea id="bk-synopsis"></textarea></div>
<div class="form-row">
    <div class="form-group"><label>Genre</label><input type="text" id="bk-genre"></div>
    <div class="form-group"><label>Release Date</label><input type="date" id="bk-release_date"></div>
</div>
<div class="form-row">
    <div class="form-group"><label>Series</label><input type="text" id="bk-series_name"></div>
    <div class="form-group"><label>Book Number</label><input type="text" id="bk-book_number"></div>
</div>
<div class="form-row">
    <div class="form-group"><label>Number of Pages</label><input type="number" id="bk-num_pages" min="0"></div>
    <div class="form-group"><label>ISBN #</label><input type="text" id="bk-isbn"></div>
</div>
<div class="form-group"><label>Publisher</label><input type="text" id="bk-publisher"></div>
<div class="form-group"><label>Cover Image</label><input type="file" id="bk-cover_image" accept="image/*">
    <div class="current-img-preview" id="bk-cover-preview"></div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" onclick="closeModal('modal-book')">Cancel</button>
    <button class="btn btn-primary-book" onclick="saveBook()">Save Book</button>
</div>
</div>
</div>

<!-- READING LOG -->
<div class="modal-overlay" id="modal-reading-log">
<div class="modal">
<h3 class="modal-title" id="modal-reading-log-title">Add Reading Log Entry</h3>
<input type="hidden" id="rl-id">
<div class="form-group"><label>Book</label>
    <select id="rl-book_id"><option value="">-- Select Book --</option></select>
</div>
<div class="form-row">
    <div class="form-group"><label>Start Time</label><input type="time" id="rl-start_time"></div>
    <div class="form-group"><label>End Time</label><input type="time" id="rl-end_time"></div>
</div>
<div class="form-row">
    <div class="form-group"><label>Starting Page</label><input type="number" id="rl-starting_page" min="0"></div>
    <div class="form-group"><label>Ending Page</label><input type="number" id="rl-ending_page" min="0"></div>
</div>
<div class="form-group"><label>Rating</label>
    <div class="star-rating" id="rl-star-rating">
        <span class="star" data-val="1" onclick="setStarRating('rl',1)">★</span>
        <span class="star" data-val="2" onclick="setStarRating('rl',2)">★</span>
        <span class="star" data-val="3" onclick="setStarRating('rl',3)">★</span>
        <span class="star" data-val="4" onclick="setStarRating('rl',4)">★</span>
        <span class="star" data-val="5" onclick="setStarRating('rl',5)">★</span>
    </div>
    <input type="hidden" id="rl-rating" value="0">
</div>
<div class="form-group"><label>Comments</label><textarea id="rl-comments"></textarea></div>
<div class="modal-footer">
    <button class="btn btn-secondary" onclick="closeModal('modal-reading-log')">Cancel</button>
    <button class="btn btn-primary-book" onclick="saveReadingLog()">Save Entry</button>
</div>
</div>
</div>

<script>
// ======================== UTILITIES ========================
const $ = id => document.getElementById(id);
let currentSeriesId = null, currentSeasonId = null;

function showSection(sec) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(b => b.className = 'nav-btn');
    $('section-' + sec).classList.add('active');
    $('nav' + sec.charAt(0).toUpperCase() + sec.slice(1)).classList.add('active-' + sec);
    if (sec === 'tv') showTVPanel('tv-dashboard');
    if (sec === 'movie') showMoviePanel('movie-dashboard');
    if (sec === 'book') showBookPanel('book-dashboard');
}

function showTVPanel(id) {
    document.querySelectorAll('#section-tv .sub-panel').forEach(p => p.classList.remove('active'));
    $(id).classList.add('active');
    if (id === 'tv-series-list') loadSeriesList();
    if (id === 'tv-watchlog') loadTVWatchlog();
}
function showMoviePanel(id) {
    document.querySelectorAll('#section-movie .sub-panel').forEach(p => p.classList.remove('active'));
    $(id).classList.add('active');
    if (id === 'movie-list') loadMovieList();
    if (id === 'movie-watchlog') loadMovieWatchlog();
}
function showBookPanel(id) {
    document.querySelectorAll('#section-book .sub-panel').forEach(p => p.classList.remove('active'));
    $(id).classList.add('active');
    if (id === 'book-list') loadBookList();
    if (id === 'reading-log') loadReadingLog();
}

function closeModal(id) { $(id).classList.remove('open'); }
function openModal(id) { $(id).classList.add('open'); }

function notify(msg, type='ok') {
    const n = $('notify');
    n.textContent = msg; n.className = 'notify show ' + type;
    setTimeout(() => n.classList.remove('show'), 3000);
}

async function api(action, data=null, files=null) {
    let body;
    if (files) {
        body = new FormData();
        if (data) for (let k in data) body.append(k, data[k] ?? '');
        for (let k in files) if (files[k] && files[k].files && files[k].files[0]) body.append(k, files[k].files[0]);
    } else if (data) {
        body = new FormData();
        for (let k in data) body.append(k, data[k] ?? '');
    }
    const r = await fetch(`?api=${action}`, { method: body ? 'POST' : 'GET', body });
    return await r.json();
}
async function apiGet(action, params='') {
    const r = await fetch(`?api=${action}${params ? '&'+params : ''}`);
    return await r.json();
}

function starsHtml(n) {
    return '★'.repeat(n) + '☆'.repeat(5-n);
}

function setStarRating(prefix, val) {
    $(prefix + '-rating').value = val;
    document.querySelectorAll(`#${prefix}-star-rating .star`).forEach(s => {
        s.classList.toggle('on', parseInt(s.dataset.val) <= val);
    });
}
function renderStarRating(prefix, val) {
    $(prefix + '-rating').value = val || 0;
    document.querySelectorAll(`#${prefix}-star-rating .star`).forEach(s => {
        s.classList.toggle('on', parseInt(s.dataset.val) <= (val||0));
    });
}

function setPreview(elId, url) {
    const el = $(elId);
    el.innerHTML = url ? `<img src="${url}" alt="preview">` : '';
}

// Calc duration from times
function calcDuration(start, end) {
    if (!start || !end) return '–';
    const [sh,sm] = start.split(':').map(Number);
    const [eh,em] = end.split(':').map(Number);
    let mins = (eh*60+em) - (sh*60+sm);
    if (mins < 0) mins += 24*60;
    const h = Math.floor(mins/60), m = mins%60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

// ======================== TV SERIES ========================
function openAddSeries() {
    $('modal-series-title').textContent = 'Add New Series';
    $('series-id').value = '';
    ['series_name','series_synopsis','created_by','genre','premiere_date','series_status','total_seasons','total_episodes','finale_date','original_network','where_to_watch','content_rating'].forEach(f => {
        const el = $('s-'+f);
        if (el) el.value = f === 'series_status' ? 'On Going' : f === 'content_rating' ? 'TV-PG' : '';
    });
    $('s-logo_image').value = '';
    setPreview('s-logo-preview','');
    openModal('modal-series');
}

async function openEditSeries(id) {
    const d = await apiGet('get_series_single', 'id='+id);
    $('modal-series-title').textContent = 'Edit Series';
    $('series-id').value = d.id;
    ['series_name','series_synopsis','created_by','genre','premiere_date','series_status','total_seasons','total_episodes','finale_date','original_network','where_to_watch','content_rating'].forEach(f => {
        const el = $('s-'+f);
        if (el) el.value = d[f] ?? '';
    });
    $('s-logo_image').value = '';
    setPreview('s-logo-preview', d.logo_image);
    openModal('modal-series');
}

function openEditSeriesFromDetail() { openEditSeries(currentSeriesId); }

async function saveSeries() {
    const id = $('series-id').value;
    const data = {};
    ['series_name','series_synopsis','created_by','genre','premiere_date','series_status','total_seasons','total_episodes','finale_date','original_network','where_to_watch','content_rating'].forEach(f => data[f] = $('s-'+f).value);
    if (id) data.id = id;
    const res = await api(id ? 'edit_series' : 'add_series', data, {'logo_image': $('s-logo_image')});
    if (res.error) { notify(res.error, 'err'); return; }
    notify('Series saved!');
    closeModal('modal-series');
    loadSeriesList();
}

async function deleteSeriesFromDetail() {
    if (!confirm('Delete this series and all its seasons and episodes?')) return;
    await api('delete_series', {id: currentSeriesId});
    notify('Series deleted');
    showTVPanel('tv-series-list');
}

async function loadSeriesList() {
    const list = await apiGet('get_series');
    const c = $('series-list-container');
    if (!list.length) { c.innerHTML = '<div class="empty-state"><div class="empty-icon">📺</div><p>No series added yet.</p></div>'; return; }
    c.innerHTML = list.map(s => `
    <div class="item-row" onclick="openSeriesDetail(${s.id})">
        ${s.logo_image ? `<img class="item-thumb" src="${s.logo_image}">` : '<div class="item-thumb-placeholder">📺</div>'}
        <div class="item-info">
            <div class="item-title">${esc(s.series_name)}</div>
            <div class="item-sub">${esc(s.genre||'')} · ${esc(s.series_status)} · ${esc(s.content_rating||'')}</div>
        </div>
        <div class="item-actions" onclick="event.stopPropagation()">
            <button class="btn btn-edit" onclick="openEditSeries(${s.id})">✏️</button>
            <button class="btn btn-delete" onclick="deleteSeries(${s.id})">🗑</button>
        </div>
    </div>`).join('');
}

async function deleteSeries(id) {
    if (!confirm('Delete this series?')) return;
    await api('delete_series', {id});
    notify('Deleted'); loadSeriesList();
}

async function openSeriesDetail(id) {
    currentSeriesId = id;
    const d = await apiGet('get_series_single', 'id='+id);
    $('series-detail-name').textContent = d.series_name;
    $('series-detail-title').textContent = d.series_name + ' — Seasons';
    $('series-delete-btn').dataset.id = id;
    showTVPanel('tv-series-detail');
    loadSeasonsList();
}

// ======================== SEASONS ========================
function openAddSeason() {
    $('modal-season-title').textContent = 'Add Season';
    $('season-id').value = '';
    ['season_number','season_synopsis','premiere_date','total_episodes','finale_date'].forEach(f => $('sn-'+f).value = '');
    $('sn-cover_image').value = '';
    setPreview('sn-cover-preview','');
    openModal('modal-season');
}

async function openEditSeason() {
    const id = currentSeasonId;
    const d = await apiGet('get_season_single', 'id='+id);
    $('modal-season-title').textContent = 'Edit Season';
    $('season-id').value = d.id;
    ['season_number','season_synopsis','premiere_date','total_episodes','finale_date'].forEach(f => $('sn-'+f).value = d[f]??'');
    $('sn-cover_image').value = '';
    setPreview('sn-cover-preview', d.cover_image);
    openModal('modal-season');
}

async function saveSeason() {
    const id = $('season-id').value;
    const data = {series_id: currentSeriesId};
    ['season_number','season_synopsis','premiere_date','total_episodes','finale_date'].forEach(f => data[f] = $('sn-'+f).value);
    if (id) data.id = id;
    const res = await api(id ? 'edit_season' : 'add_season', data, {'cover_image': $('sn-cover_image')});
    if (res.error) { notify(res.error,'err'); return; }
    notify('Season saved!');
    closeModal('modal-season');
    loadSeasonsList();
}

async function deleteCurrentSeason() {
    if (!confirm('Delete this season?')) return;
    await api('delete_season', {id: currentSeasonId});
    notify('Season deleted');
    showTVPanel('tv-series-detail');
    loadSeasonsList();
}

async function loadSeasonsList() {
    const list = await apiGet('get_seasons', 'series_id='+currentSeriesId);
    const c = $('seasons-list-container');
    if (!list.length) { c.innerHTML = '<div class="empty-state"><div class="empty-icon">📂</div><p>No seasons added yet.</p></div>'; return; }
    c.innerHTML = list.map(s => `
    <div class="item-row" onclick="openSeasonDetail(${s.id}, ${s.season_number})">
        ${s.cover_image ? `<img class="item-thumb" src="${s.cover_image}">` : '<div class="item-thumb-placeholder">🎬</div>'}
        <div class="item-info">
            <div class="item-title">Season ${s.season_number}</div>
            <div class="item-sub">Episodes: ${s.total_episodes||0} · Premiere: ${s.premiere_date||'–'}</div>
        </div>
        <div class="item-actions" onclick="event.stopPropagation()">
            <button class="btn btn-delete" onclick="deleteSeasonById(${s.id})">🗑</button>
        </div>
    </div>`).join('');
}

async function deleteSeasonById(id) {
    if (!confirm('Delete this season?')) return;
    await api('delete_season', {id});
    notify('Deleted'); loadSeasonsList();
}

async function openSeasonDetail(id, num) {
    currentSeasonId = id;
    $('season-detail-series-link').textContent = $('series-detail-name').textContent;
    $('season-detail-name').textContent = 'Season ' + num;
    $('season-detail-title').textContent = 'Season ' + num + ' — Episodes';
    showTVPanel('tv-season-detail');
    loadEpisodesList();
}

// ======================== EPISODES ========================
function openAddEpisode() {
    $('modal-episode-title').textContent = 'Add Episode';
    $('episode-id').value = '';
    ['episode_number','episode_title','episode_synopsis','air_date','directed_by','runtime'].forEach(f => $('ep-'+f).value = '');
    $('ep-clip_image').value = '';
    setPreview('ep-clip-preview','');
    openModal('modal-episode');
}

async function openEditEpisodeById(id) {
    const eps = await apiGet('get_episodes', 'season_id='+currentSeasonId);
    const d = eps.find(e => e.id == id);
    if (!d) return;
    $('modal-episode-title').textContent = 'Edit Episode';
    $('episode-id').value = d.id;
    ['episode_number','episode_title','episode_synopsis','air_date','directed_by','runtime'].forEach(f => $('ep-'+f).value = d[f]??'');
    $('ep-clip_image').value = '';
    setPreview('ep-clip-preview', d.clip_image);
    openModal('modal-episode');
}

async function saveEpisode() {
    const id = $('episode-id').value;
    const data = {season_id: currentSeasonId, series_id: currentSeriesId};
    ['episode_number','episode_title','episode_synopsis','air_date','directed_by','runtime'].forEach(f => data[f] = $('ep-'+f).value);
    if (id) data.id = id;
    const res = await api(id ? 'edit_episode' : 'add_episode', data, {'clip_image': $('ep-clip_image')});
    if (res.error) { notify(res.error,'err'); return; }
    notify('Episode saved!');
    closeModal('modal-episode');
    loadEpisodesList();
}

async function loadEpisodesList() {
    const list = await apiGet('get_episodes', 'season_id='+currentSeasonId);
    const c = $('episodes-list-container');
    if (!list.length) { c.innerHTML = '<div class="empty-state"><div class="empty-icon">🎞</div><p>No episodes added yet.</p></div>'; return; }
    c.innerHTML = list.map(e => `
    <div class="item-row">
        ${e.clip_image ? `<img class="item-thumb" src="${e.clip_image}">` : '<div class="item-thumb-placeholder">🎬</div>'}
        <div class="item-info">
            <div class="item-title">Ep ${e.episode_number}: ${esc(e.episode_title||'Untitled')}</div>
            <div class="item-sub">Air: ${e.air_date||'–'} · ${esc(e.runtime||'')} ${e.directed_by ? '· Dir: '+esc(e.directed_by) : ''}</div>
        </div>
        <div class="item-actions">
            <button class="btn btn-edit" onclick="openEditEpisodeById(${e.id})">✏️</button>
            <button class="btn btn-delete" onclick="deleteEpisode(${e.id})">🗑</button>
        </div>
    </div>`).join('');
}

async function deleteEpisode(id) {
    if (!confirm('Delete this episode?')) return;
    await api('delete_episode', {id});
    notify('Deleted'); loadEpisodesList();
}

// ======================== TV WATCHLOG ========================
async function openAddTVLog() {
    $('modal-tv-log-title').textContent = 'Add Watch Log Entry';
    $('tv-log-id').value = '';
    const series = await apiGet('get_series');
    const sel = $('tvl-series_id');
    sel.innerHTML = '<option value="">-- Select Series --</option>' + series.map(s => `<option value="${s.id}">${esc(s.series_name)}</option>`).join('');
    $('tvl-season_id').innerHTML = '<option value="">-- Select Season --</option>';
    $('tvl-episode_id').innerHTML = '<option value="">-- Select Episode --</option>';
    $('tvl-watch_date').value = ''; $('tvl-watch_time').value = ''; $('tvl-comments').value = '';
    renderStarRating('tvl', 0);
    openModal('modal-tv-log');
}

async function loadLogSeasons() {
    const sid = $('tvl-series_id').value;
    $('tvl-season_id').innerHTML = '<option value="">-- Select Season --</option>';
    $('tvl-episode_id').innerHTML = '<option value="">-- Select Episode --</option>';
    if (!sid) return;
    const list = await apiGet('get_seasons_for_series', 'series_id='+sid);
    $('tvl-season_id').innerHTML = '<option value="">-- Select Season --</option>' + list.map(s => `<option value="${s.id}">Season ${s.season_number}</option>`).join('');
}

async function loadLogEpisodes() {
    const sid = $('tvl-season_id').value;
    $('tvl-episode_id').innerHTML = '<option value="">-- Select Episode --</option>';
    if (!sid) return;
    const list = await apiGet('get_episodes_for_season', 'season_id='+sid);
    $('tvl-episode_id').innerHTML = '<option value="">-- Select Episode --</option>' + list.map(e => `<option value="${e.id}">Ep ${e.episode_number}: ${esc(e.episode_title||'Untitled')}</option>`).join('');
}

async function saveTVLog() {
    const id = $('tv-log-id').value;
    const data = {
        series_id: $('tvl-series_id').value,
        season_id: $('tvl-season_id').value,
        episode_id: $('tvl-episode_id').value,
        watch_date: $('tvl-watch_date').value,
        watch_time: $('tvl-watch_time').value,
        rating: $('tvl-rating').value,
        comments: $('tvl-comments').value
    };
    if (id) data.id = id;
    const res = await api(id ? 'edit_tv_watchlog' : 'add_tv_watchlog', data);
    if (res.error) { notify(res.error,'err'); return; }
    notify('Entry saved!');
    closeModal('modal-tv-log');
    loadTVWatchlog();
}

async function loadTVWatchlog() {
    const list = await apiGet('get_tv_watchlog');
    const c = $('tv-watchlog-container');
    if (!list.length) { c.innerHTML = '<div class="empty-state"><div class="empty-icon">📋</div><p>No watch log entries yet.</p></div>'; return; }
    c.innerHTML = list.map(e => `
    <div class="log-row">
        <div style="flex:1">
            <div class="item-title">${esc(e.series_name||'–')} — S${e.season_number||'?'} E${e.episode_number||'?'}: ${esc(e.episode_title||'–')}</div>
            <div class="log-meta">📅 ${e.watch_date||'–'} &nbsp; 🕐 ${e.watch_time||'–'}</div>
            <div class="log-stars">${starsHtml(parseInt(e.rating)||0)}</div>
            ${e.comments ? `<div class="log-comment">"${esc(e.comments)}"</div>` : ''}
        </div>
        <div class="item-actions">
            <button class="btn btn-edit" onclick="editTVLog(${e.id})">✏️</button>
            <button class="btn btn-delete" onclick="deleteTVLog(${e.id})">🗑</button>
        </div>
    </div>`).join('');
}

async function editTVLog(id) {
    const d = await apiGet('get_tv_watchlog_single', 'id='+id);
    $('modal-tv-log-title').textContent = 'Edit Watch Log Entry';
    $('tv-log-id').value = id;
    const series = await apiGet('get_series');
    const sel = $('tvl-series_id');
    sel.innerHTML = '<option value="">-- Select Series --</option>' + series.map(s => `<option value="${s.id}">${esc(s.series_name)}</option>`).join('');
    sel.value = d.series_id;
    await loadLogSeasons();
    $('tvl-season_id').value = d.season_id;
    await loadLogEpisodes();
    $('tvl-episode_id').value = d.episode_id;
    $('tvl-watch_date').value = d.watch_date||'';
    $('tvl-watch_time').value = d.watch_time||'';
    $('tvl-comments').value = d.comments||'';
    renderStarRating('tvl', d.rating);
    openModal('modal-tv-log');
}

async function deleteTVLog(id) {
    if (!confirm('Delete this entry?')) return;
    await api('delete_tv_watchlog', {id});
    notify('Deleted'); loadTVWatchlog();
}

// ======================== MOVIES ========================
function openAddMovie() {
    $('modal-movie-title').textContent = 'Add New Movie';
    $('movie-id').value = '';
    ['movie_title','synopsis','created_by','directed_by','release_date','genre','runtime'].forEach(f => $('mv-'+f).value = '');
    $('mv-cover_image').value = '';
    setPreview('mv-cover-preview','');
    openModal('modal-movie');
}

async function openEditMovie(id) {
    const d = await apiGet('get_movie_single', 'id='+id);
    $('modal-movie-title').textContent = 'Edit Movie';
    $('movie-id').value = id;
    ['movie_title','synopsis','created_by','directed_by','release_date','genre','runtime'].forEach(f => $('mv-'+f).value = d[f]??'');
    $('mv-cover_image').value = '';
    setPreview('mv-cover-preview', d.cover_image);
    openModal('modal-movie');
}

async function saveMovie() {
    const id = $('movie-id').value;
    const data = {};
    ['movie_title','synopsis','created_by','directed_by','release_date','genre','runtime'].forEach(f => data[f] = $('mv-'+f).value);
    if (id) data.id = id;
    const res = await api(id ? 'edit_movie' : 'add_movie', data, {'cover_image': $('mv-cover_image')});
    if (res.error) { notify(res.error,'err'); return; }
    notify('Movie saved!');
    closeModal('modal-movie');
    loadMovieList();
}

async function loadMovieList() {
    const list = await apiGet('get_movies');
    const c = $('movie-list-container');
    if (!list.length) { c.innerHTML = '<div class="empty-state"><div class="empty-icon">🎬</div><p>No movies added yet.</p></div>'; return; }
    c.innerHTML = list.map(m => `
    <div class="item-row" onclick="openEditMovie(${m.id})">
        ${m.cover_image ? `<img class="item-thumb" src="${m.cover_image}">` : '<div class="item-thumb-placeholder">🎬</div>'}
        <div class="item-info">
            <div class="item-title">${esc(m.movie_title)}</div>
            <div class="item-sub">${esc(m.genre||'')} · ${m.release_date||'–'} · ${esc(m.runtime||'–')}</div>
        </div>
        <div class="item-actions" onclick="event.stopPropagation()">
            <button class="btn btn-edit" onclick="openEditMovie(${m.id})">✏️</button>
            <button class="btn btn-delete" onclick="deleteMovie(${m.id})">🗑</button>
        </div>
    </div>`).join('');
}

async function deleteMovie(id) {
    if (!confirm('Delete this movie?')) return;
    await api('delete_movie', {id});
    notify('Deleted'); loadMovieList();
}

async function openAddMovieLog() {
    $('modal-movie-log-title').textContent = 'Add Movie Watch Log';
    $('movie-log-id').value = '';
    const movies = await apiGet('get_movies');
    $('mvl-movie_id').innerHTML = '<option value="">-- Select Movie --</option>' + movies.map(m => `<option value="${m.id}">${esc(m.movie_title)}</option>`).join('');
    $('mvl-watch_date').value = ''; $('mvl-watch_time').value = ''; $('mvl-comments').value = '';
    renderStarRating('mvl', 0);
    openModal('modal-movie-log');
}

async function saveMovieLog() {
    const id = $('movie-log-id').value;
    const data = {
        movie_id: $('mvl-movie_id').value,
        watch_date: $('mvl-watch_date').value,
        watch_time: $('mvl-watch_time').value,
        rating: $('mvl-rating').value,
        comments: $('mvl-comments').value
    };
    if (id) data.id = id;
    const res = await api(id ? 'edit_movie_watchlog' : 'add_movie_watchlog', data);
    if (res.error) { notify(res.error,'err'); return; }
    notify('Entry saved!');
    closeModal('modal-movie-log');
    loadMovieWatchlog();
}

async function loadMovieWatchlog() {
    const list = await apiGet('get_movie_watchlog');
    const c = $('movie-watchlog-container');
    if (!list.length) { c.innerHTML = '<div class="empty-state"><div class="empty-icon">📋</div><p>No watch log entries yet.</p></div>'; return; }
    c.innerHTML = list.map(e => `
    <div class="log-row">
        <div style="flex:1">
            <div class="item-title">${esc(e.movie_title||'–')}</div>
            <div class="log-meta">📅 ${e.watch_date||'–'} &nbsp; 🕐 ${e.watch_time||'–'}</div>
            <div class="log-stars">${starsHtml(parseInt(e.rating)||0)}</div>
            ${e.comments ? `<div class="log-comment">"${esc(e.comments)}"</div>` : ''}
        </div>
        <div class="item-actions">
            <button class="btn btn-edit" onclick="editMovieLog(${e.id})">✏️</button>
            <button class="btn btn-delete" onclick="deleteMovieLog(${e.id})">🗑</button>
        </div>
    </div>`).join('');
}

async function editMovieLog(id) {
    const d = await apiGet('get_movie_watchlog_single', 'id='+id);
    $('modal-movie-log-title').textContent = 'Edit Movie Watch Log';
    $('movie-log-id').value = id;
    const movies = await apiGet('get_movies');
    $('mvl-movie_id').innerHTML = '<option value="">-- Select Movie --</option>' + movies.map(m => `<option value="${m.id}">${esc(m.movie_title)}</option>`).join('');
    $('mvl-movie_id').value = d.movie_id;
    $('mvl-watch_date').value = d.watch_date||'';
    $('mvl-watch_time').value = d.watch_time||'';
    $('mvl-comments').value = d.comments||'';
    renderStarRating('mvl', d.rating);
    openModal('modal-movie-log');
}

async function deleteMovieLog(id) {
    if (!confirm('Delete this entry?')) return;
    await api('delete_movie_watchlog', {id});
    notify('Deleted'); loadMovieWatchlog();
}

// ======================== BOOKS ========================
function openAddBook() {
    $('modal-book-title').textContent = 'Add New Book';
    $('book-id').value = '';
    ['book_title','synopsis','author','release_date','genre','series_name','book_number','num_pages','isbn','publisher'].forEach(f => $('bk-'+f).value = '');
    $('bk-cover_image').value = '';
    setPreview('bk-cover-preview','');
    openModal('modal-book');
}

async function openEditBook(id) {
    const d = await apiGet('get_book_single', 'id='+id);
    $('modal-book-title').textContent = 'Edit Book';
    $('book-id').value = id;
    ['book_title','synopsis','author','release_date','genre','series_name','book_number','num_pages','isbn','publisher'].forEach(f => $('bk-'+f).value = d[f]??'');
    $('bk-cover_image').value = '';
    setPreview('bk-cover-preview', d.cover_image);
    openModal('modal-book');
}

async function saveBook() {
    const id = $('book-id').value;
    const data = {};
    ['book_title','synopsis','author','release_date','genre','series_name','book_number','num_pages','isbn','publisher'].forEach(f => data[f] = $('bk-'+f).value);
    if (id) data.id = id;
    const res = await api(id ? 'edit_book' : 'add_book', data, {'cover_image': $('bk-cover_image')});
    if (res.error) { notify(res.error,'err'); return; }
    notify('Book saved!');
    closeModal('modal-book');
    loadBookList();
}

async function loadBookList() {
    const list = await apiGet('get_books');
    const c = $('book-list-container');
    if (!list.length) { c.innerHTML = '<div class="empty-state"><div class="empty-icon">📚</div><p>No books added yet.</p></div>'; return; }
    c.innerHTML = list.map(b => `
    <div class="item-row" onclick="openEditBook(${b.id})">
        ${b.cover_image ? `<img class="item-thumb" src="${b.cover_image}">` : '<div class="item-thumb-placeholder">📖</div>'}
        <div class="item-info">
            <div class="item-title">${esc(b.book_title)}</div>
            <div class="item-sub">${esc(b.author||'')} ${b.series_name ? '· Series: '+esc(b.series_name) : ''} · ${b.num_pages||0} pages</div>
        </div>
        <div class="item-actions" onclick="event.stopPropagation()">
            <button class="btn btn-edit" onclick="openEditBook(${b.id})">✏️</button>
            <button class="btn btn-delete" onclick="deleteBook(${b.id})">🗑</button>
        </div>
    </div>`).join('');
}

async function deleteBook(id) {
    if (!confirm('Delete this book?')) return;
    await api('delete_book', {id});
    notify('Deleted'); loadBookList();
}

async function openAddReadingLog() {
    $('modal-reading-log-title').textContent = 'Add Reading Log Entry';
    $('rl-id').value = '';
    const books = await apiGet('get_books');
    $('rl-book_id').innerHTML = '<option value="">-- Select Book --</option>' + books.map(b => `<option value="${b.id}">${esc(b.book_title)}</option>`).join('');
    ['start_time','end_time'].forEach(f => $('rl-'+f).value = '');
    ['starting_page','ending_page'].forEach(f => $('rl-'+f).value = '');
    $('rl-comments').value = '';
    renderStarRating('rl', 0);
    openModal('modal-reading-log');
}

async function saveReadingLog() {
    const id = $('rl-id').value;
    const data = {
        book_id: $('rl-book_id').value,
        start_time: $('rl-start_time').value,
        starting_page: $('rl-starting_page').value,
        end_time: $('rl-end_time').value,
        ending_page: $('rl-ending_page').value,
        rating: $('rl-rating').value,
        comments: $('rl-comments').value
    };
    if (id) data.id = id;
    const res = await api(id ? 'edit_reading_log' : 'add_reading_log', data);
    if (res.error) { notify(res.error,'err'); return; }
    notify('Entry saved!');
    closeModal('modal-reading-log');
    loadReadingLog();
}

async function loadReadingLog() {
    const list = await apiGet('get_reading_log');
    const c = $('reading-log-container');
    if (!list.length) { c.innerHTML = '<div class="empty-state"><div class="empty-icon">📒</div><p>No reading log entries yet.</p></div>'; return; }
    c.innerHTML = list.map(e => {
        const pages = (parseInt(e.ending_page)||0) - (parseInt(e.starting_page)||0);
        const dur = calcDuration(e.start_time, e.end_time);
        return `
    <div class="log-row">
        <div style="flex:1">
            <div class="item-title">${esc(e.book_title||'–')}</div>
            <div class="log-meta">📄 Pages ${e.starting_page||0}–${e.ending_page||0} &nbsp;|&nbsp; <strong>${pages} pages read</strong> &nbsp;|&nbsp; ⏱ ${dur}</div>
            <div class="log-meta">🕐 ${e.start_time||'–'} → ${e.end_time||'–'}</div>
            <div class="log-stars">${starsHtml(parseInt(e.rating)||0)}</div>
            ${e.comments ? `<div class="log-comment">"${esc(e.comments)}"</div>` : ''}
        </div>
        <div class="item-actions">
            <button class="btn btn-edit" onclick="editReadingLog(${e.id})">✏️</button>
            <button class="btn btn-delete" onclick="deleteReadingLog(${e.id})">🗑</button>
        </div>
    </div>`}).join('');
}

async function editReadingLog(id) {
    const d = await apiGet('get_reading_log_single', 'id='+id);
    $('modal-reading-log-title').textContent = 'Edit Reading Log Entry';
    $('rl-id').value = id;
    const books = await apiGet('get_books');
    $('rl-book_id').innerHTML = '<option value="">-- Select Book --</option>' + books.map(b => `<option value="${b.id}">${esc(b.book_title)}</option>`).join('');
    $('rl-book_id').value = d.book_id;
    $('rl-start_time').value = d.start_time||'';
    $('rl-end_time').value = d.end_time||'';
    $('rl-starting_page').value = d.starting_page||'';
    $('rl-ending_page').value = d.ending_page||'';
    $('rl-comments').value = d.comments||'';
    renderStarRating('rl', d.rating);
    openModal('modal-reading-log');
}

async function deleteReadingLog(id) {
    if (!confirm('Delete this entry?')) return;
    await api('delete_reading_log', {id});
    notify('Deleted'); loadReadingLog();
}

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

// Init
showSection('tv');
</script>
</body>
</html>
