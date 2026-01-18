<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 檢查使用者是否登入
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '請先登入才能新增美食日誌。']);
    exit;
}
$user_id = $_SESSION['user_id'];

// 資料庫連接設定
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = ''; // 您的資料庫密碼
$dbName = 'food_recommendation_db';

// 圖片上傳路徑設定
define('UPLOAD_DIR', __DIR__ . '/uploads/diary_images/'); // 確保這個路徑相對於此腳本是正確的
define('UPLOAD_URL_PREFIX', 'uploads/diary_images/');    // 相對於網站根目錄的路徑，用於儲存到資料庫

if (!is_dir(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0775, true)) { // 嘗試建立目錄，0775 權限
        error_log("無法建立上傳目錄: " . UPLOAD_DIR);
        echo json_encode(['success' => false, 'message' => '伺服器錯誤：無法設定圖片上傳路徑。']);
        exit;
    }
}
if (!is_writable(UPLOAD_DIR)) {
    error_log("上傳目錄不可寫: " . UPLOAD_DIR);
    echo json_encode(['success' => false, 'message' => '伺服器錯誤：圖片上傳路徑不可寫。']);
    exit;
}


$response = ['success' => false, 'message' => '未知錯誤。'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entry_date_str = $_POST['diary_date'] ?? null;
    $restaurant_name = $_POST['diary_restaurant'] ?? null;
    $content = $_POST['diary_content'] ?? null;
    $image_caption = $_POST['diary_caption'] ?? null; // 可選

    // 基本驗證
    if (empty($entry_date_str) || empty($restaurant_name) || empty($content)) {
        $response['message'] = '日期、餐廳名稱和心得為必填項目。';
        echo json_encode($response);
        exit;
    }

    // 轉換日期格式
    $entry_date = null;
    try {
        $dateObj = new DateTime($entry_date_str);
        $entry_date = $dateObj->format('Y-m-d'); // 儲存為 MySQL DATE 格式
    } catch (Exception $e) {
        $response['message'] = '日期格式無效。';
        echo json_encode($response);
        exit;
    }

    $image_path_for_db = null; // 資料庫中儲存的圖片相對路徑

    // 處理圖片上傳
    if (isset($_FILES['diary_image']) && $_FILES['diary_image']['error'] == UPLOAD_ERR_OK) {
        $image_file = $_FILES['diary_image'];
        $file_name = $image_file['name'];
        $file_tmp_name = $image_file['tmp_name'];
        $file_size = $image_file['size'];
        $file_type = $image_file['type'];
        $file_ext_arr = explode('.', $file_name);
        $file_ext = strtolower(end($file_ext_arr));

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file_ext, $allowed_extensions)) {
            $response['message'] = '不支援的圖片格式。僅允許 JPG, JPEG, PNG, GIF。';
            echo json_encode($response);
            exit;
        }

        if ($file_size > $max_file_size) {
            $response['message'] = '圖片檔案過大，請上傳小於 5MB 的圖片。';
            echo json_encode($response);
            exit;
        }

        // 產生唯一檔案名稱以避免衝突 (user_id + timestamp + random_string)
        $unique_file_name = $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
        $destination_path = UPLOAD_DIR . $unique_file_name;

        if (move_uploaded_file($file_tmp_name, $destination_path)) {
            $image_path_for_db = UPLOAD_URL_PREFIX . $unique_file_name;
        } else {
            error_log("圖片上傳失敗: " . $file_name);
            $response['message'] = '圖片上傳失敗，請重試。';
            echo json_encode($response);
            exit;
        }
    }

    // 連接資料庫並儲存資料
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        error_log("Save Diary DB Connect Error: " . $conn->connect_error);
        $response['message'] = '資料庫連接錯誤。';
        echo json_encode($response);
        exit;
    }
    $conn->set_charset("utf8mb4");

    $sql = "INSERT INTO food_diary_entries (user_id, entry_date, restaurant_name, content, image_path, image_caption) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("isssss", $user_id, $entry_date, $restaurant_name, $content, $image_path_for_db, $image_caption);
        if ($stmt->execute()) {
            $new_entry_id = $conn->insert_id;
            $response['success'] = true;
            $response['message'] = '美食日誌已成功新增！';
            $response['new_entry'] = [ // 回傳新條目的資料，方便前端更新
                'id' => $new_entry_id,
                'user_id' => $user_id,
                'display_date' => (new DateTime($entry_date))->format('m/d'), // 格式化日期以便顯示
                'entry_date' => $entry_date,
                'restaurant_name' => htmlspecialchars($restaurant_name),
                'content' => nl2br(htmlspecialchars($content)),
                'image_path' => $image_path_for_db ? htmlspecialchars($image_path_for_db) : null,
                'image_caption' => $image_caption ? htmlspecialchars($image_caption) : null
            ];
        } else {
            error_log("Save Diary SQL Execute Error: " . $stmt->error);
            $response['message'] = '儲存日誌失敗：' . $stmt->error;
        }
        $stmt->close();
    } else {
        error_log("Save Diary SQL Prepare Error: " . $conn->error);
        $response['message'] = '資料庫查詢準備失敗。';
    }
    $conn->close();

} else {
    $response['message'] = '無效的請求方式。';
}

echo json_encode($response);
?>