<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 基本的登入檢查
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '需要登入才能操作寵物資料。']);
    exit;
}
$user_id = $_SESSION['user_id'];

// 資料庫連接參數
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'food_recommendation_db';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    error_log("[save_pet.php] DB Connect Error: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => '資料庫連接錯誤，請稍後再試。']);
    exit;
}
$conn->set_charset("utf8mb4");

// 從 POST 取得欄位
$pet_id    = isset($_POST['pet_id']) && $_POST['pet_id'] !== ''
             ? filter_var($_POST['pet_id'], FILTER_VALIDATE_INT)
             : null;
$pet_name    = trim($_POST['pet_name'] ?? '');
$pet_details = trim($_POST['pet_details'] ?? '');
$pet_status  = trim($_POST['pet_status'] ?? '');
$image_action = $_POST['pet_image_action'] ?? 'keep';
$current_image_url_from_form_or_db = $_POST['current_image_url'] ?? null;

if (empty($pet_name)) {
    echo json_encode(['success' => false, 'message' => '寵物名稱不能為空。']);
    $conn->close();
    exit;
}

$final_image_db_path = null;
$image_removed_flag = false;
$old_image_to_delete_on_server = null;
$current_image_url_from_db = null;

// 若為更新，先抓原本的 image_url
if ($pet_id) {
    $stmt = $conn->prepare("SELECT image_url FROM pets WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $pet_id, $user_id);
        $stmt->execute();
        $stmt->bind_result($current_image_url_from_db);
        $stmt->fetch();
        $stmt->close();
    }
}

// 處理圖片上傳 / 移除
if ($image_action === 'new'
    && isset($_FILES['pet_image_file'])
    && $_FILES['pet_image_file']['error'] === UPLOAD_ERR_OK) {

    $upload_dir_relative = 'uploads/pet_images/';
    $upload_dir_absolute = __DIR__ . '/' . $upload_dir_relative;
    if (!is_dir($upload_dir_absolute)) {
        mkdir($upload_dir_absolute, 0775, true);
    }

    $tmp = $_FILES['pet_image_file']['tmp_name'];
    $orig = basename($_FILES['pet_image_file']['name']);
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif'];
    if (!in_array($ext, $allowed) || $_FILES['pet_image_file']['size'] > 2*1024*1024) {
        echo json_encode(['success'=>false,'message'=>'圖片格式或大小不符（限 JPG/PNG/GIF，2MB 內）。']);
        $conn->close();
        exit;
    }

    $newname = uniqid("pet_{$user_id}_", true) . ".$ext";
    $dst_abs = $upload_dir_absolute . $newname;
    $dst_rel = $upload_dir_relative . $newname;
    if (move_uploaded_file($tmp, $dst_abs)) {
        $final_image_db_path = $dst_rel;
        if ($pet_id && $current_image_url_from_db) {
            $old_image_to_delete_on_server = __DIR__ . '/' . $current_image_url_from_db;
        }
    } else {
        echo json_encode(['success'=>false,'message'=>'無法儲存上傳圖片。']);
        $conn->close();
        exit;
    }

} elseif ($image_action === 'remove') {
    $final_image_db_path = null;
    $image_removed_flag = true;
    if ($pet_id && $current_image_url_from_db) {
        $old_image_to_delete_on_server = __DIR__ . '/' . $current_image_url_from_db;
    }

} else {
    // keep 或其他
    $final_image_db_path = $pet_id ? $current_image_url_from_db : null;
}

// 資料庫寫入
if ($pet_id) {
    // 更新
    $sql = "UPDATE pets
            SET name = ?, details = ?, status = ?, image_url = ?
            WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssii",
        $pet_name,
        $pet_details,
        $pet_status,
        $final_image_db_path,
        $pet_id,
        $user_id
    );
    if ($stmt->execute()) {
        if ($old_image_to_delete_on_server
            && $old_image_to_delete_on_server !== __DIR__.'/'.$final_image_db_path
            && file_exists($old_image_to_delete_on_server)
        ) {
            @unlink($old_image_to_delete_on_server);
        }
        echo json_encode([
            'success'       => true,
            'pet_id'        => $pet_id,
            'message'       => '寵物資料更新成功。',
            'new_image_url'=> $final_image_db_path,
            'image_removed'=> $image_removed_flag
        ]);
    } else {
        echo json_encode(['success'=>false,'message'=>'更新寵物失敗。']);
    }
    $stmt->close();

} else {
    // 新增
    $sql = "INSERT INTO pets (user_id, name, details, status, image_url)
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "issss",
        $user_id,
        $pet_name,
        $pet_details,
        $pet_status,
        $final_image_db_path
    );
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        echo json_encode([
            'success'       => true,
            'pet_id'        => $new_id,
            'message'       => '寵物新增成功。',
            'new_image_url'=> $final_image_db_path
        ]);
    } else {
        echo json_encode(['success'=>false,'message'=>'新增寵物失敗。']);
    }
    $stmt->close();
}

$conn->close();
?>
