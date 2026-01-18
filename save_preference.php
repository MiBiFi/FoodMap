<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '需要登入']);
    exit;
}
$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
    echo json_encode(['success' => false, 'message' => '無效的請求資料']);
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    error_log("Warning: Could not load .env file in save_preference.php. Error: " . $e->getMessage());
}

$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';
$dbName = $_ENV['DB_NAME'] ?? 'food_recommendation_db';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    error_log("DB Connect Error (save_preference.php): " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => '資料庫連接錯誤']);
    exit;
}
$conn->set_charset("utf8mb4");

// Determine action: saving a preference or a user setting
$action_type = $data['action_type'] ?? 'preference'; // 'preference' or 'user_setting'

if ($action_type === 'preference') {
    $preference_value = $data['preference'] ?? null;
    $preference_type = isset($data['type']) && in_array($data['type'], ['like', 'dislike']) ? $data['type'] : 'like';

    if (empty($preference_value) || mb_strlen($preference_value, 'UTF-8') > 100) {
        echo json_encode(['success' => false, 'message' => '偏好不能為空或過長']);
        $conn->close();
        exit;
    }

    $sql = "INSERT INTO user_preferences (user_id, preference_value, type) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("iss", $user_id, $preference_value, $preference_type);
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            echo json_encode([
                'success' => true,
                'newPreference' => [
                    'id' => $new_id,
                    'value' => $preference_value,
                    'type' => $preference_type
                ]
            ]);
        } else {
            error_log("DB Execute Error (save_preference.php - preference): " . $stmt->error);
            echo json_encode(['success' => false, 'message' => '無法儲存偏好']);
        }
        $stmt->close();
    } else {
        error_log("DB Prepare Error (save_preference.php - preference): " . $conn->error);
        echo json_encode(['success' => false, 'message' => '資料庫查詢準備失敗 (preference)']);
    }

} elseif ($action_type === 'user_setting') {
    $setting_key = $data['setting_key'] ?? null;
    $setting_value = $data['setting_value'] ?? null;

    if (empty($setting_key) || mb_strlen($setting_key, 'UTF-8') > 50) {
        echo json_encode(['success' => false, 'message' => '無效的設定鍵']);
        $conn->close();
        exit;
    }
    if ($setting_value === null || mb_strlen($setting_value, 'UTF-8') > 255) {
        echo json_encode(['success' => false, 'message' => '設定值不能為空或過長']);
        $conn->close();
        exit;
    }

    $allowed_settings = ['travel_mode_preference', 'another_allowed_setting']; // Add other allowed settings here
    if (!in_array($setting_key, $allowed_settings)) {
        echo json_encode(['success' => false, 'message' => '不允許的設定鍵']);
        $conn->close();
        exit;
    }

    if ($setting_key === 'travel_mode_preference') {
        $valid_travel_modes = ['driving', 'motorcycle', 'walking', 'bicycling', 'transit'];
        if (!in_array($setting_value, $valid_travel_modes)) {
            echo json_encode(['success' => false, 'message' => '無效的交通方式']);
            $conn->close();
            exit;
        }
    }

    $escaped_setting_key = $conn->real_escape_string($setting_key);
    $sql = "UPDATE users SET `{$escaped_setting_key}` = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("si", $setting_value, $user_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                 if ($setting_key === 'travel_mode_preference') {
                     $_SESSION['user_travel_mode_preference'] = $setting_value;
                 }
                echo json_encode(['success' => true, 'message' => '設定已儲存。', 'setting_key' => $setting_key, 'setting_value' => $setting_value]);
            } else {
                echo json_encode(['success' => true, 'message' => '設定未變更或已是最新。', 'setting_key' => $setting_key, 'setting_value' => $setting_value]);
            }
        } else {
            error_log("DB Execute Error (save_preference.php - user_setting): " . $stmt->error);
            echo json_encode(['success' => false, 'message' => '儲存設定失敗 (DB execute)。']);
        }
        $stmt->close();
    } else {
        error_log("DB Prepare Error (save_preference.php - user_setting): " . $conn->error . " SQL: UPDATE users SET `{$escaped_setting_key}` = ? WHERE id = ?");
        echo json_encode(['success' => false, 'message' => '儲存設定失敗 (DB prepare)。']);
    }

} else {
    echo json_encode(['success' => false, 'message' => '無效的操作類型']);
}

$conn->close();
?>