<?php
ini_set('display_errors', 1); // 開發時設為 1，生產環境設為 0
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';


use AssistantEngine\OpenAI\Client as AssistantClient;
use Dotenv\Dotenv;

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    $errorResponse = ['success' => false, 'message' => '需要登入才能使用推薦功能。'];
    echo json_encode($errorResponse);
    exit;
}


// --- 環境變數載入 ---
try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    error_log("FATAL: Could not load .env file. Error: " . $e->getMessage());
    $errorResponse = ['success' => false, 'message' => '伺服器配置錯誤 (env)，請聯絡管理員。'];
    echo json_encode($errorResponse);
    exit;
}

$openaiApiKey = $_ENV['OPEN_AI_KEY'] ?? null;
$googleMapsApiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? null;

if (empty($openaiApiKey)) {
    error_log("FATAL: OpenAI API Key is not set in .env file.");
    $errorResponse = ['success' => false, 'message' => '伺服器缺少 OpenAI API 金鑰，請聯絡管理員。'];
    echo json_encode($errorResponse);
    exit;
}
if (empty($googleMapsApiKey)) {
    error_log("FATAL: Google Maps API Key is not set in .env file.");
    $errorResponse = ['success' => false, 'message' => '伺服器缺少 Google Maps API 金鑰，請聯絡管理員。'];
    echo json_encode($errorResponse);
    exit;
}

$request_body = file_get_contents('php://input');

$input_data = json_decode($request_body, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($input_data['context']) || !is_array($input_data['context'])) {
    error_log("Invalid request data. JSON Error: " . json_last_error_msg() . ". Body: " . $request_body);
    $errorResponse = ['success' => false, 'message' => '無效的請求資料格式。'];
    echo json_encode($errorResponse);
    exit;
}


$context = $input_data['context'];
$userInput = htmlspecialchars($context['userInput'] ?? '');
$userSpecifiedLocation = htmlspecialchars($context['userSpecifiedLocation'] ?? '');
$currentGeoPosition = $context['currentGeoPosition'] ?? null;
$initialGeoPosition = $context['initialGeoPosition'] ?? null;
$calendarEventsContext = $context['calendarEvents'] ?? [];
$weatherContext = $context['weather'] ?? ['description' => '未知', 'temperature' => null];
$preferencesContext = $context['preferences'] ?? ['likes' => [], 'dislikes' => []];


$areaDescription = null;
$effectiveGeoPosition = $currentGeoPosition ?: $initialGeoPosition;

if ($effectiveGeoPosition && isset($effectiveGeoPosition['latitude'])) {
    $areaDescription = getAreaDescriptionFromCoordinates($effectiveGeoPosition['latitude'], $effectiveGeoPosition['longitude'], $googleMapsApiKey);
}

$finalOpenAiPrompt = "你是一位專業的美食推薦助手，擅長利用網路搜尋提供精確的餐廳資訊。\n\n";
$finalOpenAiPrompt .= "請嚴格依照以下描述的 JSON Array of Objects 格式回覆。你的回答必須是一個可以直接被 JSON.parse() 解析的有效 JSON 陣列。不要包含任何在此 JSON 陣列之外的任何開頭文字、結尾文字、解釋、介紹、註解或任何 Markdown 標記 (例如 ```json ... ```)。\n";
$finalOpenAiPrompt .= "\n【使用者主要需求】\n想吃：「" . $userInput . "」\n";
if (!empty($userSpecifiedLocation)) {
    $finalOpenAiPrompt .= "指定地點/區域（主要搜索範圍）：「" . $userSpecifiedLocation . "」\n";
}
$finalOpenAiPrompt .= "\n【使用者參考位置資訊】\n";
if (!empty($areaDescription)) {
    $finalOpenAiPrompt .= "使用者目前大約在：「" . $areaDescription . "」";
    if ($effectiveGeoPosition) $finalOpenAiPrompt .= "（精確座標參考：緯度 " . number_format($effectiveGeoPosition['latitude'], 4) . "，經度 " . number_format($effectiveGeoPosition['longitude'], 4) . "）\n";
    else $finalOpenAiPrompt .= "\n";
} elseif ($effectiveGeoPosition) {
    $finalOpenAiPrompt .= "使用者目前位置座標：緯度 " . number_format($effectiveGeoPosition['latitude'], 4) . "，經度 " . number_format($effectiveGeoPosition['longitude'], 4) . "\n";
} else {
    $finalOpenAiPrompt .= "目前無法獲取使用者精確位置資訊。\n";
}
if (!empty($calendarEventsContext)) {
    $finalOpenAiPrompt .= "\n【今日行程參考】\n";
    foreach ($calendarEventsContext as $event) {
        $finalOpenAiPrompt .= "- " . htmlspecialchars($event['summary'] ?? '活動') . " ";
        if (!empty($event['startTime'])) $finalOpenAiPrompt .= htmlspecialchars($event['startTime']);
        if (!empty($event['endTime'])) $finalOpenAiPrompt .= " 至 " . htmlspecialchars($event['endTime']);
        if (!empty($event['location'])) $finalOpenAiPrompt .= " 在「" . htmlspecialchars($event['location']) . "」";
        $finalOpenAiPrompt .= "\n";
    }
}
if (isset($weatherContext['temperature']) && $weatherContext['temperature'] !== null) {
    $finalOpenAiPrompt .= "\n【目前天氣狀況】\n";
    $finalOpenAiPrompt .= "描述：「" . htmlspecialchars($weatherContext['description']) . "」，氣溫：" . htmlspecialchars($weatherContext['temperature']) . "\n";
}
if (!empty($preferencesContext['likes']) || !empty($preferencesContext['dislikes'])) {
    $finalOpenAiPrompt .= "\n【飲食偏好】\n";
    if (!empty($preferencesContext['likes'])) {
        $finalOpenAiPrompt .= "喜歡：" . htmlspecialchars(implode('、', $preferencesContext['likes'])) . "\n";
    }
    if (!empty($preferencesContext['dislikes'])) {
        $finalOpenAiPrompt .= "不喜歡（請避免）：" . htmlspecialchars(implode('、', $preferencesContext['dislikes'])) . "\n";
    }
}
$finalOpenAiPrompt .= "\n【任務說明】\n";
$searchContext = "";
if (!empty($userSpecifiedLocation)) {
    $searchContext = "以使用者指定的「" . $userSpecifiedLocation . "」為主要搜索目標區域。";
} elseif (!empty($areaDescription)) {
    $searchContext = "以使用者目前所在的「" . $areaDescription . "」附近（例如方圓30公里內）為主要搜索區域。";
} elseif ($effectiveGeoPosition) {
    $searchContext = "以使用者目前位置 (緯度 " . number_format($effectiveGeoPosition['latitude'], 4) . "，經度 " . number_format($effectiveGeoPosition['longitude'], 4) . ") 附近為主要搜索區域。";
} else {
    $searchContext = "由於無法確定使用者位置，請在台北市的熱門商圈或交通樞紐，";
}
$finalOpenAiPrompt .= "請根據以上所有情境資訊，並利用你的網路搜尋能力，{$searchContext}推薦 3 家最符合「" . $userInput . "」的餐廳。\n";
$finalOpenAiPrompt .= "對於每家餐廳，請務必提供以下資訊：\n";
$finalOpenAiPrompt .= "  - `name`: 餐廳的官方、完整名稱。\n";
$finalOpenAiPrompt .= "  - `address`: 餐廳的完整地址。\n";
$finalOpenAiPrompt .= "  - `place_id`: (非常重要！) 該餐廳在 Google Maps 上的 Place ID。如果你能查詢到，請務必提供。如果查詢不到，則此欄位值為空字串 \"\"。\n";
$finalOpenAiPrompt .= "  - `ai_rating`: 你查詢到的餐廳評分（例如 Google Maps 評分或其他來源的評分，如果是你自己評估的請註明），若無則為 null。\n";
$finalOpenAiPrompt .= "  - `ai_image_url`: 你查詢到的代表該餐廳的一張公開圖片的URL（例如來自餐廳官網、Google Maps、社群媒體等）。如果查詢不到，則此欄位值為空字串 \"\"。\n";
$finalOpenAiPrompt .= "  - `reason`: 詳細的推薦原因（2-3句話），說明為何這家餐廳符合使用者需求、考慮到行程或天氣（若相關）、以及它的特色。\n";
$finalOpenAiPrompt .= "  - `cost`: 單人平均消費的大致範圍 (例如：約 NT$300-500)。\n";
$finalOpenAiPrompt .= "\n【回應格式】請嚴格以 JSON 陣列格式回覆，每個物件代表一家餐廳：\n";
$finalOpenAiPrompt .= "[\n";
$finalOpenAiPrompt .= "  {\n";
$finalOpenAiPrompt .= "    \"name\": \"<餐廳名稱>\",\n";
$finalOpenAiPrompt .= "    \"address\": \"<完整地址>\",\n";
$finalOpenAiPrompt .= "    \"place_id\": \"<Google Maps Place ID 或空字串>\",\n";
$finalOpenAiPrompt .= "    \"ai_rating\": <數字評分 或 null>,\n";
$finalOpenAiPrompt .= "    \"ai_image_url\": \"<圖片 URL 或空字串>\",\n";
$finalOpenAiPrompt .= "    \"reason\": \"<推薦理由>\",\n";
$finalOpenAiPrompt .= "    \"cost\": \"<消費範圍>\"\n";
$finalOpenAiPrompt .= "  }\n";
$finalOpenAiPrompt .= "  // (如果有多家，繼續列出，格式相同)\n";
$finalOpenAiPrompt .= "]\n";
$finalOpenAiPrompt .= "如果經過網路搜尋後，在指定條件和區域內確實找不到任何合適的餐廳推薦，請回傳一個包含單一物件的 JSON 陣列，格式如下：\n";
$finalOpenAiPrompt .= "[{\n";
$finalOpenAiPrompt .= "  \"status\": \"NO_RESULTS\",\n";
$finalOpenAiPrompt .= "  \"message\": \"抱歉，在您指定的條件和區域內，目前找不到合適的餐廳推薦。\"\n";
$finalOpenAiPrompt .= "}]\n";
$finalOpenAiPrompt .= "再次強調，你的整個回應必須是一個合法的、純粹的 JSON 陣列，不包含任何其他文字或標記。";


try {
    $client = AssistantClient::make($openaiApiKey);

    $openAiApiParams = [
        'model' => 'gpt-4o',
        'tools' => [['type' => 'web_search_preview']],
        'input' => [[
            "role" => "user",
            "content" => [["type" => "input_text", "text" => $finalOpenAiPrompt]]
        ]],
        "truncation" => "auto"
    ];

    $responseObject = $client->responses()->create($openAiApiParams);

    $rawResponseString = json_encode($responseObject, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (strlen($rawResponseString) > 50000) { // 限制記錄長度
        $rawResponseString = substr($rawResponseString, 0, 50000) . "\n... (response truncated due to length)";
    }

    $aiMessageContent = null;
    if (isset($responseObject->output) && is_array($responseObject->output)) {
        foreach ($responseObject->output as $outputItem) {
            if (is_object($outputItem) && isset($outputItem->type) && $outputItem->type === 'message' &&
                isset($outputItem->content) && is_array($outputItem->content) &&
                isset($outputItem->content[0]) && is_object($outputItem->content[0]) &&
                isset($outputItem->content[0]->type) && $outputItem->content[0]->type === 'output_text' &&
                isset($outputItem->content[0]->text)
            ) {
                $aiMessageContent = $outputItem->content[0]->text;
                break;
            }
        }
    }

    if (empty($aiMessageContent)) {
        $apiErrorDetails = "";
        if(!empty($responseObject->error)){
            $errorObj = $responseObject->error;
            // 檢查 error 是否是物件且有 message 屬性
            if (is_object($errorObj) && property_exists($errorObj, 'message')) {
                 $apiErrorDetails = " 錯誤詳情: " . $errorObj->message;
                 if (property_exists($errorObj, 'type')) $apiErrorDetails .= " (Type: " . $errorObj->type . ")";
                 if (property_exists($errorObj, 'code')) $apiErrorDetails .= " (Code: " . $errorObj->code . ")";
            } elseif (is_string($errorObj)) {
                 $apiErrorDetails = " 錯誤詳情: " . $errorObj;
            } else {
                 $apiErrorDetails = " 錯誤詳情: " . json_encode($errorObj, JSON_UNESCAPED_UNICODE);
            }
        }
        error_log("OpenAI API did not return valid content." . $apiErrorDetails . " Prompt hash: " . md5($finalOpenAiPrompt));
        $errorResponse = ['success' => false, 'message' => 'OpenAI API 未返回有效內容。' . $apiErrorDetails, 'final_prompt_to_openai_hash' => md5($finalOpenAiPrompt)];
        echo json_encode($errorResponse);
        exit;
    }

    // --- 清理並解析 AI 回應的 JSON ---
    $cleanedJsonText = preg_replace('/^```json\s*|\s*```\s*$/s', '', $aiMessageContent);
    $cleanedJsonText = trim($cleanedJsonText);

    $aiRecommendedRestaurants = json_decode($cleanedJsonText, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($aiRecommendedRestaurants)) {
        error_log("AI did not return valid JSON. JSON Parse Error: " . json_last_error_msg() . ". Raw AI Response: " . $cleanedJsonText . ". Prompt hash: " . md5($finalOpenAiPrompt));
        $errorResponse = [
            'success' => false,
            'message' => 'AI 未能返回有效的 JSON 格式推薦。解析錯誤: ' . json_last_error_msg(),
            'openai_response_text' => $cleanedJsonText,
            'final_prompt_to_openai_hash' => md5($finalOpenAiPrompt)
        ];
        echo json_encode($errorResponse);
        exit;
    }
    if (isset($aiRecommendedRestaurants[0]['status']) && $aiRecommendedRestaurants[0]['status'] === 'NO_RESULTS') {
        $finalResponse = [
            'success' => true,
            'recommendations' => $aiRecommendedRestaurants,
            'final_prompt_to_openai_hash' => md5($finalOpenAiPrompt)
        ];
        echo json_encode($finalResponse);
        exit;
    }

    $refinedRestaurants = [];
    foreach ($aiRecommendedRestaurants as $idx => $aiRestaurant) {

        if (!isset($aiRestaurant['name']) || !isset($aiRestaurant['address'])) {
            error_log("AI recommendation #{$idx} missing name or address: " . json_encode($aiRestaurant, JSON_UNESCAPED_UNICODE));
            continue;
        }

        $placeId = $aiRestaurant['place_id'] ?? null;
        $googleData = null;

        if (!empty($placeId) && $googleMapsApiKey) {
            $googleData = getGooglePlaceDetails($placeId, $googleMapsApiKey);
             if ($googleData && isset($googleData['permanently_closed']) && $googleData['permanently_closed'] === true) {
                continue; // 跳過永久關閉的店家
            }
        } elseif (!empty($aiRestaurant['name']) && !empty($aiRestaurant['address']) && $googleMapsApiKey) {
            $candidatePlaceId = findGooglePlaceId($aiRestaurant['name'], $aiRestaurant['address'], $googleMapsApiKey);
            if ($candidatePlaceId) {
                $placeId = $candidatePlaceId;
                $googleData = getGooglePlaceDetails($candidatePlaceId, $googleMapsApiKey);
                 if ($googleData && isset($googleData['permanently_closed']) && $googleData['permanently_closed'] === true) {
                    continue; // 跳過永久關閉的店家
                }
            } else {
                error_log("Google Places API could not find Place ID for AI suggestion: Name='{$aiRestaurant['name']}', Address='{$aiRestaurant['address']}'");
            }
        }


        $finalRestaurant = [
            'name' => $googleData['name'] ?? $aiRestaurant['name'],
            'address' => $googleData['address'] ?? $aiRestaurant['address'],
            'place_id' => $placeId, // 應是 $googleData['place_id'] ?? $placeId
            'rating' => $googleData['rating'] ?? $aiRestaurant['ai_rating'] ?? null,
            'user_ratings_total' => $googleData['user_ratings_total'] ?? null,
            'photo_reference' => $googleData['photo_reference'] ?? null,
            'google_image_url' => null,
            'ai_image_url' => $aiRestaurant['ai_image_url'] ?? null,
            'reason' => $aiRestaurant['reason'] ?? '推薦（AI未提供詳細理由）',
            'cost' => $aiRestaurant['cost'] ?? '價格未知',
            'source' => $googleData && !$googleData['permanently_closed'] ? 'google_places_api_enhanced' : 'ai_only' // 修正 source 判斷
        ];
         // 修正 place_id 的獲取
        if ($googleData && isset($googleData['place_id'])) {
            $finalRestaurant['place_id'] = $googleData['place_id'];
        }


        if ($finalRestaurant['photo_reference'] && $googleMapsApiKey) {
            $finalRestaurant['google_image_url'] = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference={$finalRestaurant['photo_reference']}&key={$googleMapsApiKey}";
        }
        
        $refinedRestaurants[] = $finalRestaurant;
    }


    if (empty($refinedRestaurants) && !empty($aiRecommendedRestaurants)) {
        error_log("All AI recommendations failed Google Places API enhancement or were filtered. Returning AI data with 'ai_only_fallback' source.");
         foreach ($aiRecommendedRestaurants as $idx => $origAiRestaurant) {
             if (isset($origAiRestaurant['name']) && isset($origAiRestaurant['address'])) {
                 $refinedRestaurants[] = [
                     'name' => $origAiRestaurant['name'],
                     'address' => $origAiRestaurant['address'],
                     'place_id' => $origAiRestaurant['place_id'] ?? null,
                     'rating' => $origAiRestaurant['ai_rating'] ?? null,
                     'user_ratings_total' => null,
                     'photo_reference' => null,
                     'google_image_url' => null,
                     'ai_image_url' => $origAiRestaurant['ai_image_url'] ?? null,
                     'reason' => $origAiRestaurant['reason'] ?? '推薦（AI未提供詳細理由）',
                     'cost' => $origAiRestaurant['cost'] ?? '價格未知',
                     'source' => 'ai_only_fallback'
                 ];
             }
         }
    }


    $finalResponseToFrontend = [
        'success' => true,
        'recommendations' => $refinedRestaurants,
        'final_prompt_to_openai_hash' => md5($finalOpenAiPrompt)
    ];
    echo json_encode($finalResponseToFrontend);

} catch (Exception $e) {
    $promptHashForError = isset($finalOpenAiPrompt) ? md5($finalOpenAiPrompt) : 'Prompt not yet constructed';
    error_log("FATAL General Exception in call_AI.php: " . $e->getMessage() . ". Trace: " . $e->getTraceAsString() . ". Prompt hash: " . $promptHashForError);
    $errorResponse = [
        'success' => false,
        'message' => '伺服器在處理 AI 請求時發生嚴重錯誤，請稍後再試。錯誤：' . $e->getMessage(),
        'final_prompt_to_openai_hash' => $promptHashForError
    ];
    echo json_encode($errorResponse);
}

function makeCurlRequest($url, $timeout = 10) {
    global $googleMapsApiKey; // 為了安全移除 key
    $urlForLog = preg_replace('/key=[^&]+/', 'key=GOOGLE_API_KEY_REDACTED', $url);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT, 'FoodMapApp/1.0 (PHP cURL)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $responseJson = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $logResponse = $responseJson;
    if (strlen($logResponse) > 1000) { // 限制記錄長度
        $logResponse = substr($logResponse, 0, 1000) . "... (response truncated)";
    }


    if ($curlError) {
        error_log("cURL Error for {$urlForLog}: " . $curlError);
        return null;
    }
    if ($httpCode !== 200) {
        error_log("HTTP Error {$httpCode} for {$urlForLog}. Response: " . $responseJson); // 完整錯誤回應記錄到 php_errors.log
        return null;
    }
    return $responseJson;
}

function getAreaDescriptionFromCoordinates($latitude, $longitude, $apiKey) {
    $geocodeUrl = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$latitude},{$longitude}&key={$apiKey}&language=zh-TW&result_type=administrative_area_level_2|locality|sublocality_level_1";
    $responseJson = makeCurlRequest($geocodeUrl, 5);
    if (!$responseJson) return null;

    $geocodeData = json_decode($responseJson, true);
    if ($geocodeData && $geocodeData['status'] === 'OK' && !empty($geocodeData['results'])) {
        $components = $geocodeData['results'][0]['address_components'];
        $city = ''; $district = '';
        foreach ($components as $component) {
            if (in_array('locality', $component['types'])) $city = $component['long_name'];
            if (in_array('administrative_area_level_1', $component['types']) && empty($city)) $city = $component['long_name']; // 例如直轄市
            if (in_array('administrative_area_level_2', $component['types']) && empty($district)) $district = $component['long_name']; // 例如縣轄市或區
             if (in_array('sublocality_level_1', $component['types']) && empty($district) && $city !== $component['long_name'] ) $district = $component['long_name']; // 例如鄉鎮市區底下的區，避免與市重複
        }
        if ($city && $district && $city !== $district) return $city . $district;
        if ($city) return $city;
        if ($district) return $district;
        return $geocodeData['results'][0]['formatted_address'] ?? null;
    } else {
        $errorMsg = ($geocodeData['error_message'] ?? ($geocodeData['status'] ?? 'Unknown error'));
        error_log("Geocode API error for {$latitude},{$longitude}: " . $errorMsg);
    }
    return null;
}

function getGooglePlaceDetails($placeId, $apiKey) {
    $fields = "name,formatted_address,rating,user_ratings_total,photos,place_id,permanently_closed";
    $detailsUrl = "https://maps.googleapis.com/maps/api/place/details/json?place_id=" . urlencode($placeId) . "&fields=" . urlencode($fields) . "&key=" . $apiKey . "&language=zh-TW";
    
    $responseJson = makeCurlRequest($detailsUrl);
    if (!$responseJson) return null;

    $data = json_decode($responseJson, true);
    if ($data && $data['status'] === 'OK' && isset($data['result'])) {
        $result = $data['result'];
        if (isset($result['permanently_closed']) && $result['permanently_closed'] === true) {
            return ['permanently_closed' => true, 'name' => $result['name'] ?? $placeId];
        }
        return [
            'name' => $result['name'] ?? null,
            'address' => $result['formatted_address'] ?? null,
            'rating' => $result['rating'] ?? null,
            'user_ratings_total' => $result['user_ratings_total'] ?? null,
            'photo_reference' => $result['photos'][0]['photo_reference'] ?? null,
            'place_id' => $result['place_id'] ?? $placeId,
            'permanently_closed' => false
        ];
    } else {
        $errorMsg = ($data['error_message'] ?? ($data['status'] ?? 'Unknown error'));
        error_log("Place Details API error for Place ID '{$placeId}'. Status: " . ($data['status'] ?? 'N/A') . ". Error Message: " . $errorMsg);
    }
    return null;
}

function findGooglePlaceId($name, $address, $apiKey) {
    $queryInput = urlencode($name . " " . $address);
    $findUrl = "https://maps.googleapis.com/maps/api/place/findplacefromtext/json?inputtype=textquery&input={$queryInput}&fields=place_id&key={$apiKey}&language=zh-TW";

    $responseJson = makeCurlRequest($findUrl);
    if (!$responseJson) return null;

    $data = json_decode($responseJson, true);
    if ($data && $data['status'] === 'OK' && !empty($data['candidates'])) {
        return $data['candidates'][0]['place_id'] ?? null;
    } else {
        $errorMsg = ($data['error_message'] ?? ($data['status'] ?? 'Unknown error'));
        error_log("Find Place API error for query '{$name} {$address}'. Status: " . ($data['status'] ?? 'N/A') . ". Error Message: " . $errorMsg);
      }
    return null;
}
?>