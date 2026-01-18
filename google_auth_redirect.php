<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    error_log("Google Auth Redirect: Could not load .env file - " . $e->getMessage());

}


$clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? $_SERVER['GOOGLE_CLIENT_ID'] ?? null;
$clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? $_SERVER['GOOGLE_CLIENT_SECRET'] ?? null;

if (empty($clientId) || empty($clientSecret)) {
    error_log("Google Auth Redirect: Google Client ID or Secret is not configured.");
    $_SESSION['google_auth_error'] = 'Google API 憑證未正確設定，請聯絡管理員。';
    header('Location: index.php'); // 或者一個專門的錯誤頁面
    exit;
}

$client = new Google\Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);


$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$pathPrefix = ($scriptDir === '' || $scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
$redirectUri = $scheme . '://' . $host . $pathPrefix . '/oauth_callback.php';

$client->setRedirectUri($redirectUri);
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

$client->setScopes([
    Google\Service\Oauth2::USERINFO_EMAIL,    // 'email'
    Google\Service\Oauth2::USERINFO_PROFILE,  // 'profile'
    Google\Service\Calendar::CALENDAR_EVENTS, // 'https://www.googleapis.com/auth/calendar.readonly'
    'openid'                                  // 'openid'
]);

try {
    $authUrl = $client->createAuthUrl();
} catch (Exception $e) {
    error_log("Google Auth Redirect: Error creating auth URL - " . $e->getMessage());
    $_SESSION['google_auth_error'] = '無法產生 Google 授權連結，請稍後再試。';
    header('Location: index.php');
    exit;
}


error_log("Google Auth Redirect: Redirecting to Google Auth URL: " . $authUrl);
header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;

?>