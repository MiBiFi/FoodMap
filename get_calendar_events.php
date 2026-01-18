<?php
/**
 * get_calendar_events.php
 * 讀取使用者「今天起 7 天內」Google 行事曆事件
 * 回傳 JSON：{ success: bool, events: [], message?: string }
 */

session_start();
require_once __DIR__ . '/vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');

/* ---------- A. 基本驗證 ---------- */
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => '需要登入才能讀取行事曆']); exit;
}

/* ---------- B. 載入環境設定 ---------- */
try { Dotenv\Dotenv::createImmutable(__DIR__)->load(); } catch (Exception $e) {}

$dbHost = $_ENV['DB_HOST']       ?? 'localhost';
$dbUser = $_ENV['DB_USERNAME']   ?? 'root';
$dbPass = $_ENV['DB_PASSWORD']   ?? '';
$dbName = $_ENV['DB_DATABASE']   ?? 'food_recommendation_db';

$clientId     = $_ENV['GOOGLE_CLIENT_ID']     ?? null;
$clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? null;
if (!$clientId || !$clientSecret) {
    echo json_encode(['success' => false, 'message' => 'Google API 憑證未設定']); exit;
}

/* ---------- C. 取出 DB 中的 Token ---------- */
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => '資料庫錯誤']); exit;
}
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare(
    "SELECT google_access_token, google_refresh_token, google_token_expires_at
     FROM users WHERE id = ?"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($accessToken, $refreshToken, $expiresAt);
$stmt->fetch();
$stmt->close();

if (!$accessToken) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => '尚未連結 Google 帳號']); exit;
}

/* ---------- D. 建立 Google Client ---------- */
$client = new Google\Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setAccessType('offline');          // 允許取得 refresh_token
$client->setPrompt('consent');              // 之後若需強制重新同意
$client->setScopes([
    Google\Service\Oauth2::USERINFO_EMAIL,
    Google\Service\Oauth2::USERINFO_PROFILE,
    Google\Service\Calendar::CALENDAR_READONLY,
    'openid'
]);

/* 這裡**使用 `expires_at`**（UNIX timestamp），
   比只給 `expires_in` 更穩定；Google 官方程式庫亦支援。 */
$client->setAccessToken([
    'access_token'  => $accessToken,
    'refresh_token' => $refreshToken,
    'expires_at'    => (int)$expiresAt        // 直接存絕對時間
]);

/* ---------- E. 若過期就自動 refresh ---------- */
try {
    if ($client->isAccessTokenExpired()) {
        if (!$refreshToken) {
            throw new Exception('Google 授權已過期且無法自動更新，請重新授權。');
        }

        $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (isset($newToken['error'])) {
            // 常見：invalid_grant（使用者撤銷權限或 refresh_token 失效）
            throw new Exception('無法更新 Google 授權: ' .
                                ($newToken['error_description'] ?? $newToken['error']));
        }

        // 立即更新 Client 狀態
        $client->setAccessToken($newToken);

        // 寫回 DB（refresh_token 幾乎不會改變，但保險起見帶回存）
        $stmt = $conn->prepare(
            "UPDATE users
             SET google_access_token = ?,
                 google_refresh_token = IFNULL(?, google_refresh_token),
                 google_token_expires_at = ?
             WHERE id = ?"
        );
        $newAccess  = $newToken['access_token'];
        $newRefresh = $newToken['refresh_token'] ?? $refreshToken;
        $newExpAt   = time() + ($newToken['expires_in'] ?? 3599);
        $stmt->bind_param('ssii', $newAccess, $newRefresh, $newExpAt, $userId);
        $stmt->execute();
        $stmt->close();
    }
} catch (Exception $e) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit;
}

/* ---------- F. 呼叫 Calendar API ---------- */
try {
    $service = new Google_Service_Calendar($client);

    $opt = [
        'singleEvents' => true,
        'orderBy'      => 'startTime',
        'timeMin'      => (new DateTime('today', new DateTimeZone('Asia/Taipei')))
                            ->format(DateTime::RFC3339),
        'timeMax'      => (new DateTime('today +7 days', new DateTimeZone('Asia/Taipei')))
                            ->format(DateTime::RFC3339),
        'maxResults'   => 20,
        'timeZone'     => 'Asia/Taipei'
    ];
    $events = $service->events->listEvents('primary', $opt);

    $out = [];
    foreach ($events->getItems() as $ev) {
        $start = $ev->getStart();
        $end   = $ev->getEnd();
        $isAllDay = !empty($start->getDate());

        $out[] = [
            'id'        => $ev->getId(),
            'summary'   => $ev->getSummary() ?: '(無標題)',
            'location'  => $ev->getLocation() ?: '',
            'isAllDay'  => $isAllDay,
            'startDate' => $isAllDay
                            ? $start->getDate()
                            : (new DateTime($start->getDateTime()))->format('Y-m-d'),
            'startTime' => $isAllDay ? null
                            : (new DateTime($start->getDateTime()))->format('H:i'),
            'endTime'   => $isAllDay ? null
                            : (new DateTime($end->getDateTime()))->format('H:i')
        ];
    }

    echo json_encode(['success' => true, 'events' => $out]);
} catch (Exception $e) {
    echo json_encode(['success' => false,
                      'message' => '讀取 Google 行事曆時發生錯誤：' . $e->getMessage()]);
} finally {
    $conn->close();
}
