<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

if (!function_exists('eh')) {
    function eh($string) {
        if (is_array($string) || is_object($string)) { return ''; }
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

$content_params = [];
$requested_content = '';
$is_post_request = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_post_request = true;
    $json_data = file_get_contents('php://input');
    $decoded_data = json_decode($json_data, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_data)) {
        $requested_content = $decoded_data['content'] ?? '';
        $content_params = $decoded_data;
        unset($content_params['content']);
    } else {
        error_log("[content_loader] Failed to decode POST JSON data. Error: " . json_last_error_msg() . " Raw: " . $json_data);
        $requested_content = '';
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $requested_content_full = $_GET['content'] ?? '';
    $parts = explode('&', $requested_content_full, 2);
    $requested_content = $parts[0];
    $query_params_str = $parts[1] ?? '';

    if (!empty($query_params_str)) {
        parse_str($query_params_str, $get_params);
        $content_params = array_merge($content_params, $get_params);
    }
}

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

if (empty($requested_content)) {
    $requested_content = $is_logged_in ? 'recommendations' : 'food-diary';
}

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'food_recommendation_db';

if (class_exists('Dotenv\Dotenv') && file_exists(__DIR__ . '/.env')) {
    try {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        $dbHost = $_ENV['DB_HOST'] ?? $dbHost;
        $dbUser = $_ENV['DB_USER'] ?? $dbUser;
        $dbPass = $_ENV['DB_PASS'] ?? $dbPass;
        $dbName = $_ENV['DB_NAME'] ?? $dbName;
    } catch (Exception $e) {
        error_log("[content_loader.php] Error loading .env file: " . $e->getMessage());
    }
}

function getDbConnection($host, $user, $pass, $db) {
    static $connection = null;
    if ($connection === null || !$connection->ping()) { // Add ping to check connection liveness
        $connection = new mysqli($host, $user, $pass, $db);
        if ($connection->connect_error) {
            error_log("資料庫連接失敗: " . $connection->connect_error);
            echo "<p style='color:red;text-align:center;'>系統暫時無法提供服務，請稍後再試。</p>";
            return null;
        }
        $connection->set_charset("utf8mb4");
    }
    return $connection;
}

$restricted_content_array = ['recommendations', 'user-profile', 'save-gcal-event'];
if (in_array($requested_content, $restricted_content_array) && !$is_logged_in) {
    echo "<p style='text-align: center; color: orange; padding: 20px;'>請先登入以查看或編輯此內容。</p>";
    exit;
}

$user_prefs = [];
$user_pets = [];
$user_schedule_items = [];
$user_travel_mode_preference = 'driving';
$prefill_event_name = $content_params['event_name'] ?? '';
$prefill_event_date = $content_params['event_date'] ?? '';
$prefill_event_time = $content_params['event_time'] ?? '';
$prefill_event_location = $content_params['event_location'] ?? '';
$prefill_event_description = $content_params['event_description'] ?? '';


if ($is_logged_in && $user_id && ($requested_content === 'user-profile' || $requested_content === 'food-diary' || $requested_content === 'recommendations')) {
    $conn = getDbConnection($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn) {
        $sqlPrefs = "SELECT id, preference_value, type FROM user_preferences WHERE user_id = ?";
        $stmtPrefs = $conn->prepare($sqlPrefs);
        if ($stmtPrefs) {
            $stmtPrefs->bind_param("i", $user_id); $stmtPrefs->execute();
            $resultPrefs = $stmtPrefs->get_result();
            while ($row = $resultPrefs->fetch_assoc()) { $user_prefs[] = $row; }
            $stmtPrefs->close();
        } else { error_log("Content Loader Prefs SQL Prepare Error: " . $conn->error); }

        $sqlPets = "SELECT id, name, details, status, image_url FROM pets WHERE user_id = ? ORDER BY id ASC";
        $stmtPets = $conn->prepare($sqlPets);
        if ($stmtPets) {
            $stmtPets->bind_param("i", $user_id); $stmtPets->execute();
            $resultPets = $stmtPets->get_result();
            while ($row = $resultPets->fetch_assoc()) { $user_pets[] = $row; }
            $stmtPets->close();
        } else { error_log("Content Loader Pets SQL Prepare Error: " . $conn->error); }

        $sqlTravelMode = "SELECT travel_mode_preference FROM users WHERE id = ?";
        $stmtTravelMode = $conn->prepare($sqlTravelMode);
        if ($stmtTravelMode) {
            $stmtTravelMode->bind_param("i", $user_id);
            $stmtTravelMode->execute();
            $stmtTravelMode->bind_result($db_travel_mode);
            if ($stmtTravelMode->fetch() && !empty($db_travel_mode)) {
                $user_travel_mode_preference = $db_travel_mode;
            }
            $stmtTravelMode->close();
        } else { error_log("Content Loader Travel Mode SQL Prepare Error: " . $conn->error); }
    }
}


switch ($requested_content) {
    case 'food-diary':
        $foodDiaryEntries = [];
        $conn = getDbConnection($dbHost, $dbUser, $dbPass, $dbName);
        if ($conn) {
             $sqlDiary = "";
            if ($is_logged_in && $user_id) {
                 $sqlDiary = "SELECT fde.id, fde.entry_date, fde.restaurant_name, fde.content, fde.image_path, fde.image_caption, u.username FROM food_diary_entries fde JOIN users u ON fde.user_id = u.id WHERE fde.user_id = ? ORDER BY fde.entry_date DESC, fde.created_at DESC";
                 $stmtDiary = $conn->prepare($sqlDiary);
                 if ($stmtDiary) $stmtDiary->bind_param("i", $user_id);
             } else {
                 $sqlDiary = "SELECT fde.id, fde.entry_date, fde.restaurant_name, fde.content, fde.image_path, fde.image_caption, u.username FROM food_diary_entries fde JOIN users u ON fde.user_id = u.id ORDER BY fde.entry_date DESC, fde.created_at DESC LIMIT 10";
                 $stmtDiary = $conn->prepare($sqlDiary);
            }
             if ($stmtDiary) {
                 $stmtDiary->execute(); $resultDiary = $stmtDiary->get_result();
                 while ($row = $resultDiary->fetch_assoc()) {
                     if (!empty($row['entry_date'])) { try { $dateObj = new DateTime($row['entry_date']); $row['display_date'] = $dateObj->format('m/d'); } catch (Exception $e) { $row['display_date'] = '日期無效'; } } else { $row['display_date'] = '未記錄'; }
                     $foodDiaryEntries[] = $row;
                 }
                 $stmtDiary->close();
             } else { error_log("Food Diary SQL Prepare Error: " . ($conn->error ?: "Unknown error") . " SQL: " . $sqlDiary); }
        }
        include '_partial_food_diary.php';
        break;

    case 'recommendations':
        include '_partial_recommendations.php';
        break;

    case 'user-profile':
        if ($is_logged_in) {
             include '_partial_user_profile.php';
        } else { echo "<p>請先登入。</p>"; }
        break;

    default:
        if ($is_logged_in) {
            include '_partial_recommendations.php';
        } else {
             $foodDiaryEntries = [];
             $conn = getDbConnection($dbHost, $dbUser, $dbPass, $dbName);
             if ($conn) {
                 $sqlDiary = "SELECT fde.id, fde.entry_date, fde.restaurant_name, fde.content, fde.image_path, fde.image_caption, u.username FROM food_diary_entries fde JOIN users u ON fde.user_id = u.id ORDER BY fde.entry_date DESC, fde.created_at DESC LIMIT 10";
                 $stmtDiary = $conn->prepare($sqlDiary);
                 if ($stmtDiary) {
                     $stmtDiary->execute(); $resultDiary = $stmtDiary->get_result();
                     while ($row = $resultDiary->fetch_assoc()) {
                         if (!empty($row['entry_date'])) { try { $dateObj = new DateTime($row['entry_date']); $row['display_date'] = $dateObj->format('m/d'); } catch (Exception $e) { $row['display_date'] = '日期無效'; } } else { $row['display_date'] = '未記錄'; }
                         $foodDiaryEntries[] = $row;
                     }
                     $stmtDiary->close();
                 } else { error_log("Food Diary SQL Prepare Error (Default): " . ($conn->error ?: "Unknown error") . " SQL: " . $sqlDiary); }
             }
             include '_partial_food_diary.php';
        }
        break;
}
?>