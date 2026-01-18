<?php
session_start();

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'food_recommendation_db';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? null;

    if (empty($username) || empty($password)) {
        $_SESSION['login_error_message'] = '請輸入帳號和密碼。';
        header("Location: index.php?login_error=1");
        exit;
    }

    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        error_log("Login DB Connect Error: " . $conn->connect_error);
        $_SESSION['login_error_message'] = '資料庫連接錯誤，請稍後再試。';
        header("Location: index.php?login_error=1");
        exit;
    }
    $conn->set_charset("utf8mb4");

    $sql = "SELECT id, password_hash FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($user_db_id, $hashed_password);
            $stmt->fetch();

            // --- Verify the password ---
            if ($hashed_password !== null && password_verify($password, $hashed_password)) {
                // Password is correct
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_db_id;
                $_SESSION['username'] = $username;
                $stmt->close();
                $conn->close();
                header("Location: index.php");
                exit;
            } else {
                // Invalid password (or password hash is null for Google-only users)
                 $_SESSION['login_error_message'] = '帳號或密碼錯誤。';
            }
        } else {
            // User not found
            $_SESSION['login_error_message'] = '帳號或密碼錯誤。';
        }
        $stmt->close();
    } else {
        error_log("Login DB Prepare Error: " . $conn->error);
        $_SESSION['login_error_message'] = '登入時發生錯誤，請稍後再試。';
    }

    $conn->close();
    header("Location: index.php?login_error=1");
    exit;

} else {
    header("Location: index.php");
    exit;
}
?>