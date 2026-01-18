<?php
header('Content-Type: application/json; charset=utf-8');

// --- Load API Key from .env ---
$apiKey = null;
$dotenvPath = __DIR__ . '/.env'; // Assumes .env is in the same directory

if (file_exists($dotenvPath)) {
    // Simple parse_ini_file method (adjust if using phpdotenv package)
    $env = parse_ini_file($dotenvPath);
    if (isset($env['CWB_API'])) {
        $apiKey = $env['CWB_API'];
    }
}

// Fallback or error if key not found
if (empty($apiKey)) {
    error_log("Error: CWB_API key not found or empty in .env file at " . $dotenvPath);
    echo json_encode(['success' => false, 'message' => '伺服器天氣 API 配置錯誤。']);
    exit;
}

// --- Build CWA API URL ---
// Note: O-A0003-001 doesn't take location params directly, we fetch all stations
$apiUrl = sprintf(
    "https://opendata.cwa.gov.tw/api/v1/rest/datastore/O-A0003-001?Authorization=%s&format=JSON",
    $apiKey
);

// --- Make API Call using cURL ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Increased timeout slightly
curl_setopt($ch, CURLOPT_USERAGENT, 'MyFoodApp/1.0 (php-curl)');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Keep SSL verification
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// --- Process Response ---
if ($curlError) {
     error_log("cURL Error fetching CWA weather: " . $curlError);
     echo json_encode(['success' => false, 'message' => '無法連接天氣服務。']);
     exit;
}

if ($httpcode !== 200 || empty($response)) {
     error_log("CWA Weather API Error: HTTP Code {$httpcode}. Response: " . $response);
     echo json_encode(['success' => false, 'message' => '無法取得天氣資訊 (Code: ' . $httpcode . ')']);
     exit;
}

$weatherData = json_decode($response, true);

// Basic check if the expected data structure exists
if (json_last_error() !== JSON_ERROR_NONE || !isset($weatherData['success']) || $weatherData['success'] !== 'true' || !isset($weatherData['records']['Station'])) {
    error_log("CWA Weather API JSON Error or unexpected format: " . json_last_error_msg() . ". Response: " . $response);
    echo json_encode(['success' => false, 'message' => '無法解析天氣資訊。']);
    exit;
}

// --- Send the full records back to the frontend for processing ---
// Frontend will calculate the nearest station
echo json_encode([
    'success' => true,
    'records' => $weatherData['records'] // Send the whole records object
]);
?>