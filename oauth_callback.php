<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

try {
    Dotenv\Dotenv::createImmutable(__DIR__)->load();
} catch (Exception $e) {}

$clientId     = $_ENV['GOOGLE_CLIENT_ID']     ?? $_SERVER['GOOGLE_CLIENT_ID']     ?? null;
$clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? $_SERVER['GOOGLE_CLIENT_SECRET'] ?? null;
$dbHost       = $_ENV['DB_HOST']              ?? 'localhost';
$dbUser       = $_ENV['DB_USERNAME']          ?? 'root';
$dbPass       = $_ENV['DB_PASSWORD']          ?? '';
$dbName       = $_ENV['DB_DATABASE']          ?? 'food_recommendation_db';

if (empty($clientId) || empty($clientSecret)) {
    $_SESSION['google_auth_error'] = 'Google API 憑證未正確設定，請聯絡管理員。';
    header('Location: index.php');
    exit;
}

$client = new Google\Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);

$scheme       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? 'https' : 'http';
$host         = $_SERVER['HTTP_HOST'];
$scriptDir    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$pathPrefix   = ($scriptDir === '' || $scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
$redirectUri  = $scheme . '://' . $host . $pathPrefix . '/oauth_callback.php';
$client->setRedirectUri($redirectUri);
$client->setAccessType('offline');
$client->setPrompt('select_account consent');
$client->setScopes([
    Google\Service\Oauth2::USERINFO_EMAIL,
    Google\Service\Oauth2::USERINFO_PROFILE,
    Google\Service\Calendar::CALENDAR_READONLY,
    'openid'
]);

$conn = null;

try {
    if (isset($_GET['code'])) {
        $accessTokenResponse = $client->fetchAccessTokenWithAuthCode($_GET['code']);

        if (isset($accessTokenResponse['error'])) {
            $_SESSION['google_auth_error'] = 'Google 授權錯誤: ' . htmlspecialchars($accessTokenResponse['error_description'] ?? $accessTokenResponse['error']);
            header('Location: index.php');
            exit;
        }

        if (empty($accessTokenResponse['access_token'])) {
            $_SESSION['google_auth_error'] = '無法從 Google 取得有效的 Access Token。';
            header('Location: index.php');
            exit;
        }

        if (empty($accessTokenResponse['refresh_token'])) {
            $_SESSION['google_auth_error'] = '未能取得 Google 長期授權憑證，請重新連結。';
            header('Location: google_auth_redirect.php?force_consent=1');
            exit;
        }

        if (empty($accessTokenResponse['id_token'])) {
            $_SESSION['google_auth_error'] = '無法從 Google 取得 ID Token。';
            header('Location: index.php');
            exit;
        }

        $client->setAccessToken($accessTokenResponse);
        $idTokenPayload = $client->verifyIdToken($accessTokenResponse['id_token']);

        if (!$idTokenPayload || empty($idTokenPayload['sub'])) {
            $_SESSION['google_auth_error'] = '無法驗證使用者身份。';
            header('Location: index.php');
            exit;
        }

        $google_id              = $idTokenPayload['sub'];
        $google_email           = $idTokenPayload['email'] ?? null;
        $google_name            = $idTokenPayload['name']  ?? null;
        $access_token_to_store  = $accessTokenResponse['access_token'];
        $refresh_token_to_store = $accessTokenResponse['refresh_token'];
        $expires_at_timestamp   = time() + ($accessTokenResponse['expires_in'] ?? 3599);

        $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if ($conn->connect_error) {
            $_SESSION['google_auth_error'] = '資料庫連接失敗。';
            header('Location: index.php');
            exit;
        }
        $conn->set_charset('utf8mb4');

        $stmt = $conn->prepare("SELECT id, username FROM users WHERE google_id = ?");
        $stmt->bind_param('s', $google_id);
        $stmt->execute();
        $stmt->bind_result($existing_user_id, $existing_username);
        $user_found = $stmt->fetch();
        $stmt->close();

        $current_user_id  = null;
        $current_username = null;

        if ($user_found && $existing_user_id) {
            $current_user_id  = $existing_user_id;
            $current_username = $existing_username;

            $stmt = $conn->prepare(
                "UPDATE users
                 SET google_access_token = ?,
                     google_refresh_token = ?,
                     google_token_expires_at = ?,
                     google_email = ?,
                     google_display_name = ?,
                     email = IFNULL(email, ?)
                 WHERE id = ?"
            );
            $stmt->bind_param(
                'ssisssi',
                $access_token_to_store,
                $refresh_token_to_store,
                $expires_at_timestamp,
                $google_email,
                $google_name,
                $google_email,
                $current_user_id
            );
            $stmt->execute();
            $stmt->close();

            $_SESSION['google_auth_success'] = 'Google 帳號登入並更新成功！';
        } else {
            $session_user_id = $_SESSION['user_id'] ?? null;

            if ($session_user_id) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE google_id = ? AND id != ?");
                $stmt->bind_param('si', $google_id, $session_user_id);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $stmt->close();
                    throw new Exception('此 Google 帳號已被其他帳號連結。');
                }
                $stmt->close();

                $current_user_id = $session_user_id;

                $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->bind_param('i', $current_user_id);
                $stmt->execute();
                $stmt->bind_result($current_username);
                $stmt->fetch();
                $stmt->close();

                $stmt = $conn->prepare(
                    "UPDATE users
                     SET google_id = ?,
                         google_access_token = ?,
                         google_refresh_token = ?,
                         google_token_expires_at = ?,
                         google_email = ?,
                         google_display_name = ?,
                         email = IFNULL(email, ?)
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'ssissssi',
                    $google_id,
                    $access_token_to_store,
                    $refresh_token_to_store,
                    $expires_at_timestamp,
                    $google_email,
                    $google_name,
                    $google_email,
                    $current_user_id
                );
                $stmt->execute();
                $stmt->close();

                $_SESSION['google_auth_success'] = 'Google 帳號連結成功！';
            } else {
                $new_username_base = explode('@', $google_email)[0] ?? 'google_user';
                $new_username      = $new_username_base;
                $counter           = 1;

                while (true) {
                    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->bind_param('s', $new_username);
                    $stmt->execute();
                    $stmt->store_result();
                    if ($stmt->num_rows == 0) {
                        $stmt->close();
                        break;
                    }
                    $stmt->close();
                    $new_username = $new_username_base . $counter++;
                    if ($counter > 10) {
                        $new_username = 'google_' . substr(md5(uniqid()), 0, 8);
                        break;
                    }
                }

                $random_password          = bin2hex(random_bytes(16));
                $placeholder_password_hash = password_hash($random_password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare(
                    "INSERT INTO users
                     (username, password_hash, email,
                      google_id, google_email, google_display_name,
                      google_access_token, google_refresh_token, google_token_expires_at,
                      created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())"
                );
                $stmt->bind_param(
                    'ssssssssi',
                    $new_username,
                    $placeholder_password_hash,
                    $google_email,
                    $google_id,
                    $google_email,
                    $google_name,
                    $access_token_to_store,
                    $refresh_token_to_store,
                    $expires_at_timestamp
                );
                if (!$stmt->execute()) {
                    if ($conn->errno == 1062) {
                        $stmt->close();
                        throw new Exception('無法建立新使用者帳號：帳號或 Email 已存在。');
                    }
                    throw new Exception('無法建立新使用者帳號: ' . $stmt->error);
                }

                $current_user_id  = $conn->insert_id;
                $current_username = $new_username;
                $stmt->close();

                $_SESSION['google_auth_success'] = 'Google 帳號註冊並登入成功！';
            }
        }

        session_regenerate_id(true);
        $_SESSION['user_id']                 = $current_user_id;
        $_SESSION['username']                = $current_username;
        $_SESSION['google_id']               = $google_id;
        $_SESSION['google_access_token']     = $access_token_to_store;
        $_SESSION['google_refresh_token']    = $refresh_token_to_store;
        $_SESSION['google_token_expires_at'] = $expires_at_timestamp;

        $service = new Google_Service_Calendar($client);
        $service->events->listEvents('primary', ['maxResults' => 1]);
    } elseif (isset($_GET['error'])) {
        $_SESSION['google_auth_error'] = 'Google 授權失敗: ' . htmlspecialchars($_GET['error_description'] ?? $_GET['error']);
    } else {
        $_SESSION['google_auth_error'] = '無效的授權回應。';
    }
} catch (Exception $e) {
    $_SESSION['google_auth_error'] = '處理授權時發生錯誤，請稍後重試。';
} finally {
    if ($conn instanceof mysqli && $conn->ping()) {
        $conn->close();
    }
}

header('Location: index.php');
exit;
