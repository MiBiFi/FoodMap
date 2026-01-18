<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '需要登入']);
    exit;
}
$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);

// 1. Check if keys exist and type is valid first
$item_type = $data['type'] ?? null;
$allowed_types = ['preference', 'pet', 'schedule']; // Define allowed types

if ($item_type === null || !in_array($item_type, $allowed_types)) {
    error_log("Delete Error: Invalid or missing type. Received: " . print_r($item_type, true));
    echo json_encode(['success' => false, 'message' => '無效或缺少項目類型']);
    exit;
}

// 2. Check if ID key exists and validate its value
if (!isset($data['id'])) { // Check if 'id' key exists at all
    error_log("Delete Error: Missing ID key. Received data: " . print_r($data, true));
    echo json_encode(['success' => false, 'message' => '缺少項目 ID']);
    exit;
}

// Use filter_var AFTER confirming the key exists
$item_id = filter_var($data['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]); // Allow 0 if valid

if ($item_id === false) { // Check specifically for filter failure (null is handled by isset above)
    error_log("Delete Error: Invalid integer ID. Received ID: " . print_r($data['id'], true));
    echo json_encode(['success' => false, 'message' => '項目 ID 格式無效']);
    exit;
}
// Now $item_id is a valid integer (including 0)

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'food_recommendation_db';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    error_log("DB Connect Error: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => '資料庫連接錯誤']);
    exit;
}
$conn->set_charset("utf8mb4");

$sql = "";
switch ($item_type) {
    case 'preference':
        $sql = "DELETE FROM user_preferences WHERE id = ? AND user_id = ?";
        break;
    case 'pet':
        $sql = "DELETE FROM pets WHERE id = ? AND user_id = ?";
        break;
     case 'schedule':
         $sql = "DELETE FROM schedule WHERE id = ? AND user_id = ?";
         break;
    // No default needed due to earlier type check
}

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $item_id, $user_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // --- Return ID and Type on success ---
            echo json_encode([
                'success' => true,
                'deletedItem' => [
                    'id' => $item_id,
                    'type' => $item_type
                    ]
                ]);
        } else {
            echo json_encode(['success' => false, 'message' => '項目未找到或無刪除權限']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '無法刪除項目']);
    }
    $stmt->close();
} else {
     echo json_encode(['success' => false, 'message' => '資料庫查詢準備失敗']);
}

$conn->close();
?>