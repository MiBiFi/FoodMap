<?php
session_start(); 
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

ini_set('log_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '需要登入才能規劃路線。']);
    exit;
}
$user_id = $_SESSION['user_id'];

$googleMapsApiKey = null;
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'food_recommendation_db';

try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    $googleMapsApiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? null;
    $dbHost = $_ENV['DB_HOST'] ?? $dbHost;
    $dbUser = $_ENV['DB_USER'] ?? $dbUser;
    $dbPass = $_ENV['DB_PASS'] ?? $dbPass;
    $dbName = $_ENV['DB_NAME'] ?? $dbName;

} catch (Exception $e) {
    error_log("Warning: Could not load .env file in get_route_directions.php. Error: " . $e->getMessage());
}

if (empty($googleMapsApiKey)) {
    echo json_encode(['success' => false, 'message' => '伺服器配置錯誤 (Google API Key missing)。']);
    exit;
}

$request_body = file_get_contents('php://input');
$input_data = json_decode($request_body, true);

if (json_last_error() !== JSON_ERROR_NONE ||
    !isset($input_data['origin']) ||
    !isset($input_data['destination'])) {
    echo json_encode(['success' => false, 'message' => '無效的請求資料：缺少出發地或目的地。']);
    exit;
}

$origin_text = $input_data['origin'];
$destination_text = $input_data['destination'];

$user_db_travel_mode = 'driving'; 
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
     error_log("[get_route_directions.php] DB Connect Error: " . $conn->connect_error);
} else {
     $conn->set_charset("utf8mb4");
     $sqlTravelMode = "SELECT travel_mode_preference FROM users WHERE id = ?";
     $stmtTravelMode = $conn->prepare($sqlTravelMode);
      if ($stmtTravelMode) {
            $stmtTravelMode->bind_param("i", $user_id);
            $stmtTravelMode->execute();
            $stmtTravelMode->bind_result($db_mode);
            if ($stmtTravelMode->fetch() && !empty($db_mode)) {
                $user_db_travel_mode = $db_mode;
            }
            $stmtTravelMode->close();
        } else {
             error_log("[get_route_directions.php] DB Prepare Error (User Travel Mode): " . $conn->error);
        }
     $conn->close();
}
error_log("[get_route_directions.php] Using Travel Mode from DB: " . $user_db_travel_mode);

$mode_from_db = $user_db_travel_mode;
$mode_for_google = $mode_from_db; 

$validGoogleModes = ['driving', 'walking', 'bicycling', 'transit'];
if (!in_array($mode_for_google, $validGoogleModes)) {
    if ($mode_for_google === 'motorcycle') {
        $mode_for_google = 'driving';
    } else {
         $mode_for_google = 'driving';
    }
}

$origin_encoded = urlencode($origin_text);
$destination_encoded = urlencode($destination_text);

$directionsUrl = "https://maps.googleapis.com/maps/api/directions/json" .
                 "?origin={$origin_encoded}" .
                 "&destination={$destination_encoded}" .
                 "&mode={$mode_for_google}" .
                 "&language=zh-TW" .
                 "&key={$googleMapsApiKey}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $directionsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'FoodMapApp/1.0 (PHP cURL)');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
$responseJson = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'message' => 'cURL 錯誤：' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    $errorData = json_decode($responseJson, true);
    $googleMessage = $errorData['error_message'] ?? ('請求 Google Directions API 失敗 (HTTP ' . $httpCode . ')');
    echo json_encode(['success' => false, 'message' => $googleMessage, 'google_response' => $errorData ?? null]);
    exit;
}

$directionsData = json_decode($responseJson, true);

if ($directionsData && $directionsData['status'] === 'OK' && !empty($directionsData['routes'])) {
    $route = $directionsData['routes'][0];
    $leg = $route['legs'][0];

    $stepsOutput = [];
    if (isset($leg['steps']) && is_array($leg['steps'])) {
        foreach ($leg['steps'] as $step) {
            $stepsOutput[] = [
                'html_instructions' => $step['html_instructions'] ?? '',
                'distance_text' => $step['distance']['text'] ?? '',
                'duration_text' => $step['duration']['text'] ?? '',
                'maneuver' => $step['maneuver'] ?? null
            ];
        }
    }
    
    $googleMapsUrl = "https://www.google.com/maps/dir/?api=1&origin=" . urlencode($origin_text) . "&destination=" . urlencode($destination_text) . "&travelmode=" . $mode_from_db;

    echo json_encode([
        'success' => true,
        'mode' => $mode_from_db, 
        'duration_text' => $leg['duration']['text'] ?? '未知',
        'duration_value' => $leg['duration']['value'] ?? null,
        'distance_text' => $leg['distance']['text'] ?? '未知',
        'distance_value' => $leg['distance']['value'] ?? null,
        'summary' => $route['summary'] ?? '',
        'google_maps_url' => $googleMapsUrl, 
        'steps' => $stepsOutput
    ]);
} else {
    $statusMessage = $directionsData['status'] ?? '未知錯誤';
    if ($statusMessage === 'ZERO_RESULTS') {
        $statusMessage = '找不到路線。';
    } elseif (isset($directionsData['error_message'])) {
        $statusMessage = $directionsData['error_message'];
    }
     echo json_encode(['success' => false, 'message' => '路線規劃失敗：' . $statusMessage, 'google_status' => $directionsData['status'] ?? null, 'returned_mode' => $mode_from_db]);
}
?>