<?php
ini_set('display_errors', 0); // Production: 0, Development: 1
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;
use Google\Client as GoogleClient; // Using an alias for Google Client
use Google\Service\Calendar as GoogleCalendarService;
use Google\Service\Calendar\Event as GoogleCalendarEvent;

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '需要登入才能儲存行事曆事件。']);
    exit;
}
$user_id = $_SESSION['user_id'];

// --- .env 載入 ---
try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    error_log("[save_gcal_event.php] .env loading error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '伺服器配置錯誤 (env)。']);
    exit;
}

$clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? null;
$clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? null;
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';
$dbName = $_ENV['DB_NAME'] ?? 'food_recommendation_db';

if (empty($clientId) || empty($clientSecret)) {
    error_log("[save_gcal_event.php] Google Client ID or Secret is missing from .env.");
    echo json_encode(['success' => false, 'message' => 'Google API 憑證未設定。']);
    exit;
}

// --- 從前端請求中獲取數據 ---
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => '無效的 JSON 請求體。']);
    exit;
}

$summary = $input['summary'] ?? null;
$date = $input['date'] ?? null;
$startTime = $input['startTime'] ?? null;
$location = $input['location'] ?? '';
$descriptionFromForm = $input['description'] ?? ''; // 這是包含用戶備註和路線摘要的完整描述

// 從前端獲取結構化的路線詳情
$routeDetails = $input['routeDetails'] ?? null;
$restaurantName = $input['restaurantName'] ?? (explode('：', $summary)[1] ?? '未知餐廳'); // 從摘要中嘗試提取

if (empty($summary) || empty($date) || empty($startTime)) {
    echo json_encode(['success' => false, 'message' => '事件名稱、日期和開始時間為必填。']);
    exit;
}

// --- 處理日期和時間 ---
$timezone = 'Asia/Taipei'; // 或者從使用者設定獲取
try {
    $startDateTimeStr = $date . 'T' . $startTime . ':00'; // ISO 8601 format
    $startDateTime = new DateTime($startDateTimeStr, new DateTimeZone($timezone));
    $endDateTime = clone $startDateTime;
    $endDateTime->add(new DateInterval('PT1H30M')); // 預設 1.5 小時用餐時間
} catch (Exception $e) {
    error_log("[save_gcal_event.php] Date/Time parsing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '日期或時間格式無效。']);
    exit;
}

// --- 資料庫連接 ---
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    error_log("[save_gcal_event.php] DB Connect Error: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => '資料庫連接錯誤。']);
    exit;
}
$conn->set_charset("utf8mb4");

// --- Google OAuth Token 獲取和刷新 ---
$g_access_token = null; $g_refresh_token = null; $g_expires_at = null;
$sql_token_fetch = "SELECT google_access_token, google_refresh_token, google_token_expires_at FROM users WHERE id = ?";
$stmt_token_fetch = $conn->prepare($sql_token_fetch);
if (!$stmt_token_fetch) {
    error_log("[save_gcal_event.php] DB Prepare Error (Fetch Token): " . $conn->error);
    echo json_encode(['success' => false, 'message' => '無法讀取使用者Google授權 (DB Prepare)。']); $conn->close(); exit;
}
$stmt_token_fetch->bind_param("i", $user_id);
$stmt_token_fetch->execute();
$stmt_token_fetch->bind_result($g_access_token, $g_refresh_token, $g_expires_at);
if (!$stmt_token_fetch->fetch()) {
    $stmt_token_fetch->close();
    error_log("[save_gcal_event.php] No Google token found in DB for user {$user_id}.");
    echo json_encode(['success' => false, 'message' => '找不到Google授權記錄。請重新連接Google帳號。']); $conn->close(); exit;
}
$stmt_token_fetch->close();

if (empty($g_access_token)) {
    error_log("[save_gcal_event.php] Google Access Token is empty in DB for user {$user_id}.");
    echo json_encode(['success' => false, 'message' => '尚未授權Google行事曆，或授權已失效。請重新連接Google帳號。']);
    $conn->close(); exit;
}

$client = new GoogleClient();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setAccessType('offline');
$client->setAccessToken([
    'access_token' => $g_access_token,
    'refresh_token' => $g_refresh_token,
    'expires_in' => $g_expires_at ? max(0, $g_expires_at - time()) : 0
]);

if ($client->isAccessTokenExpired()) {
    if ($g_refresh_token) {
        try {
            $newAccessToken = $client->fetchAccessTokenWithRefreshToken($g_refresh_token);
            if (isset($newAccessToken['error'])) {
                throw new Exception("無法更新 Google 授權: " . ($newAccessToken['error_description'] ?? $newAccessToken['error']));
            }
            $client->setAccessToken($newAccessToken); // Update client with new token

            $updated_access_token = $newAccessToken['access_token'];
            $updated_refresh_token = $newAccessToken['refresh_token'] ?? $g_refresh_token; // Use new refresh token if provided
            $updated_expires_at = time() + ($newAccessToken['expires_in'] ?? 3599);

            $updateSql = "UPDATE users SET google_access_token = ?, google_refresh_token = ?, google_token_expires_at = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt) {
                $updateStmt->bind_param("ssii", $updated_access_token, $updated_refresh_token, $updated_expires_at, $user_id);
                if(!$updateStmt->execute()){
                    error_log("[save_gcal_event.php] Failed to update refreshed token in DB for user {$user_id}. Error: " . $updateStmt->error);
                }
                $updateStmt->close();
            } else {
                error_log("[save_gcal_event.php] Failed to prepare statement for updating refreshed token for user {$user_id}. Error: " . $conn->error);
            }
        } catch (Exception $e) {
            error_log("[save_gcal_event.php] Token refresh failed for user {$user_id} - " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Google 授權已過期且更新失敗: ' . $e->getMessage() . ' 請嘗試重新連接Google帳號。']);
            $conn->close(); exit;
        }
    } else {
        error_log("[save_gcal_event.php] Token expired and no refresh token for user {$user_id}.");
        echo json_encode(['success' => false, 'message' => 'Google 授權已過期且無更新權杖。請重新連接Google帳號。']);
        $conn->close(); exit;
    }
}
$service = new GoogleCalendarService($client);
$event = new GoogleCalendarEvent([
    'summary' => $summary,
    'location' => $location,
    'description' => $descriptionFromForm, // 包含用戶備註和路線的完整描述
    'start' => ['dateTime' => $startDateTime->format(DateTime::RFC3339), 'timeZone' => $timezone],
    'end' => ['dateTime' => $endDateTime->format(DateTime::RFC3339), 'timeZone' => $timezone],
    'reminders' => ['useDefault' => false, 'overrides' => [['method' => 'popup', 'minutes' => 30]]],
]);
$calendarId = 'primary';
$gcal_event_id_db = null;
$html_link_db = null;
$db_save_message = "";

try {
    $createdEvent = $service->events->insert($calendarId, $event);
    $gcal_event_id_db = $createdEvent->getId();
    $html_link_db = $createdEvent->getHtmlLink();

    // --- 將事件和路線資訊儲存到 meal_arrangements ---
    $sql_insert_arrangement = "INSERT INTO meal_arrangements (
        user_id, gcal_event_id, event_summary, event_start_datetime, event_end_datetime, 
        event_location_text, event_description_full, selected_restaurant_name, selected_restaurant_address, 
        route_origin, route_destination, route_mode, 
        route_duration_text, route_duration_value, route_distance_text, 
        route_google_maps_url, route_summary_text, route_steps_json
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt_arrangement = $conn->prepare($sql_insert_arrangement);
    if ($stmt_arrangement) {
        // 從 $routeDetails (由前端傳入) 中獲取結構化路線資訊
        $db_route_origin = $routeDetails['origin'] ?? null;
        $db_route_destination = $routeDetails['destination'] ?? $location; // 終點通常是餐廳地點
        $db_route_mode = $routeDetails['mode'] ?? 'driving';
        $db_route_duration_text = $routeDetails['duration_text'] ?? null;
        $db_route_duration_value = isset($routeDetails['duration_value']) ? (int)$routeDetails['duration_value'] : null;
        $db_route_distance_text = $routeDetails['distance_text'] ?? null;
        $db_route_google_maps_url = $routeDetails['google_maps_url'] ?? null;
        $db_route_summary_text = $routeDetails['summary'] ?? null;
        $db_route_steps_json = isset($routeDetails['steps']) ? json_encode($routeDetails['steps']) : null;

        $stmt_arrangement->bind_param(
            "isssssssssssisssss", // 18 個 's' 或 'i'
            $user_id,
            $gcal_event_id_db,
            $summary,
            $startDateTime->format('Y-m-d H:i:s'),
            $endDateTime->format('Y-m-d H:i:s'),
            $location,
            $descriptionFromForm, // 完整的描述 (包含路線)
            $restaurantName,      // 確保前端傳來了 restaurantName
            $location,            // selected_restaurant_address 即事件地點
            $db_route_origin,
            $db_route_destination,
            $db_route_mode,
            $db_route_duration_text,
            $db_route_duration_value,
            $db_route_distance_text,
            $db_route_google_maps_url,
            $db_route_summary_text,
            $db_route_steps_json
        );

        if ($stmt_arrangement->execute()) {
            $db_save_message = "用餐安排已記錄到資料庫。";
            error_log("[save_gcal_event.php] Meal arrangement saved to DB for user {$user_id}, GCal ID: {$gcal_event_id_db}");
        } else {
            $db_save_message = "儲存到 Google 日曆成功，但記錄到本地資料庫失敗: " . $stmt_arrangement->error;
            error_log("[save_gcal_event.php] Failed to save meal arrangement to DB: " . $stmt_arrangement->error);
        }
        $stmt_arrangement->close();
    } else {
        $db_save_message = "儲存到 Google 日曆成功，但準備本地資料庫記錄時失敗: " . $conn->error;
        error_log("[save_gcal_event.php] Failed to prepare statement for meal_arrangements: " . $conn->error);
    }
    // --- meal_arrangements 儲存結束 ---

    echo json_encode([
        'success' => true,
        'message' => '事件已成功新增至 Google 行事曆!',
        'eventId' => $gcal_event_id_db,
        'htmlLink' => $html_link_db,
        'dbMessage' => $db_save_message
    ]);

} catch (Exception $e) {
    $errorMsg = '新增事件至 Google 行事曆時發生錯誤。';
    $googleApiError = json_decode($e->getMessage(), true); // 嘗試解析Google返回的錯誤
    if (is_array($googleApiError) && isset($googleApiError['error']['message'])) {
        $errorMsg .= ' Google 回應: ' . $googleApiError['error']['message'];
    } else {
        $errorMsg .= ' 訊息: ' . $e->getMessage();
    }
    error_log("[save_gcal_event.php] Error inserting GCal event: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $errorMsg, 'google_error_raw' => $e->getMessage()]);
}

$conn->close();
?>