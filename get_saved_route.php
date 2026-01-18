<?php // get_saved_route.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/vendor/autoload.php'; 

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '使用者未登入。']);
    exit;
}
$user_id = $_SESSION['user_id'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    error_log("[get_saved_route.php] .env 載入錯誤: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '伺服器設定錯誤 (env)。']);
    exit;
}

$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbUser = $_ENV['DB_USER'] ?? 'root'; // 從 .env 讀取資料庫使用者名稱
$dbPass = $_ENV['DB_PASS'] ?? '';   // 從 .env 讀取資料庫密碼
$dbName = $_ENV['DB_NAME'] ?? 'food_recommendation_db';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    error_log("[get_saved_route.php] 資料庫連線錯誤: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => '資料庫連線錯誤。']);
    exit;
}
$conn->set_charset("utf8mb4");

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => '無效的 JSON 請求。']);
    $conn->close();
    exit;
}

$gcal_event_id = $input['gcal_event_id'] ?? null;
// 前端目前主要透過 gcal_event_id 查詢，以下參數保留彈性
// $event_summary = $input['event_summary'] ?? null;
// $event_start_time_str = $input['event_start_time_str'] ?? null;

$routeData = null;
$sql = "";
$stmt = null;

if ($gcal_event_id) {
    // 確保查詢的欄位與前端 displaySidebarDetailedRoute 所需的鍵名匹配
    // 或與 meal_arrangements 資料表中的實際欄位名一致
    $sql = "SELECT 
                gcal_event_id, 
                route_duration_text, 
                route_distance_text, 
                route_google_maps_url, 
                route_steps_json, 
                route_mode,
                route_summary_text, /* 路線摘要，如果有的話 */
                route_origin,       /* 路線起點 */
                route_destination   /* 路線終點 */
                /* 您可能還需要 event_summary 或 selected_restaurant_name 等欄位 */
            FROM meal_arrangements 
            WHERE user_id = ? AND gcal_event_id = ? 
            ORDER BY event_start_datetime DESC 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $gcal_event_id);
    } else {
        error_log("[get_saved_route.php] 準備 gcal_event_id 查詢陳述式失敗: " . $conn->error . " SQL: " . $sql);
    }
} 
/* 
// 備用查詢邏輯 (目前前端主要使用 GCal Event ID)
elseif ($event_summary && $event_start_time_str) {
    $today_date = date('Y-m-d');
    $sql = "SELECT * FROM meal_arrangements 
            WHERE user_id = ? 
              AND event_summary = ? 
              AND DATE(event_start_datetime) = ? 
              AND TIME(STR_TO_DATE(event_start_datetime, '%Y-%m-%d %H:%i:%s')) = STR_TO_DATE(?, '%H:%i:%s') 
            ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $time_to_compare = $event_start_time_str; 
        $stmt->bind_param("isss", $user_id, $event_summary, $today_date, $time_to_compare);
    } else {
        error_log("[get_saved_route.php] 準備 summary/time 查詢陳述式失敗: " . $conn->error . " SQL: " . $sql);
    }
}
*/

if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $routeData = $row; // 直接使用從資料庫查詢到的行作為回傳資料
        }
    } else {
        error_log("[get_saved_route.php] 陳述式執行失敗: " . $stmt->error);
    }
    $stmt->close();
}

if ($routeData) {
    echo json_encode(['success' => true, 'route' => $routeData]);
} else {
    $message = '未找到與此事件相關的已儲存路線。';
    if ($gcal_event_id) {
        $message .= " (GCal Event ID: " . htmlspecialchars($gcal_event_id) . ")";
    }
    error_log("[get_saved_route.php] " . $message . " for user " . $user_id);
    echo json_encode(['success' => false, 'message' => $message]);
}
$conn->close();
?>