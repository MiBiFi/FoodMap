<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

function eh($string) { if (is_array($string) || is_object($string)) return ''; return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8'); }

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

$prefs = [];
$pets = [];
$sidebar_route_total_time = '--:--';
$sidebar_route_total_distance = '';
$sidebar_route_maps_link = '#';
$sidebar_route_mode_icon_class = 'fa-car';
$is_google_connected = false;
$google_maps_frontend_js_key = '';
$user_travel_mode_preference_db = 'driving';

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'food_recommendation_db';

if (class_exists('Dotenv\Dotenv') && file_exists(__DIR__ . '/.env')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        $google_maps_frontend_js_key = $_ENV['GOOGLE_MAPS_FRONTEND_JS_KEY'] ?? ($_ENV['GOOGLE_MAPS_API_KEY'] ?? '');
        $dbHost = $_ENV['DB_HOST'] ?? $dbHost;
        $dbUser = $_ENV['DB_USER'] ?? $dbUser;
        $dbPass = $_ENV['DB_PASS'] ?? $dbPass;
        $dbName = $_ENV['DB_NAME'] ?? $dbName;
    } catch (Exception $e) { error_log('[index.php] .env load error: ' . $e->getMessage()); }
}

if ($is_logged_in && $user_id) {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if (!$conn->connect_error) {
        $conn->set_charset('utf8mb4');

        $stmt = $conn->prepare('SELECT google_access_token, google_refresh_token FROM users WHERE id = ?');
        if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $stmt->store_result(); if ($stmt->num_rows) { $stmt->bind_result($a, $r); $stmt->fetch(); if ($a || $r) $is_google_connected = true; } $stmt->close(); }

        $stmt = $conn->prepare('SELECT id, preference_value, type FROM user_preferences WHERE user_id = ?');
        if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $prefs[] = $row; $stmt->close(); }

        $stmt = $conn->prepare('SELECT id, name, details, status, image_url FROM pets WHERE user_id = ? ORDER BY id ASC');
        if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) { $row['image'] = $row['image_url']; $pets[] = $row; } $stmt->close(); }

        $stmt = $conn->prepare('SELECT travel_mode_preference FROM users WHERE id = ?');
        if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $stmt->bind_result($m); if ($stmt->fetch() && $m) $user_travel_mode_preference_db = $m; $stmt->close(); }

        $conn->close();
    } else { error_log('[index.php] DB error: ' . $conn->connect_error); }
}

function get_travel_mode_display($m) {
    $d = ['icon' => 'fa-question-circle', 'text' => '未知'];
    if ($m === 'driving') $d = ['icon' => 'fa-car', 'text' => '開車'];
    elseif ($m === 'motorcycle') $d = ['icon' => 'fa-motorcycle', 'text' => '機車'];
    elseif ($m === 'walking') $d = ['icon' => 'fa-person-walking', 'text' => '步行'];
    elseif ($m === 'bicycling') $d = ['icon' => 'fa-bicycle', 'text' => '自行車'];
    elseif ($m === 'transit') $d = ['icon' => 'fa-bus-simple', 'text' => '大眾運輸'];
    return $d;
}
$current_travel_mode_display = get_travel_mode_display($user_travel_mode_preference_db);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>今天吃蝦米</title>
<link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script>
const IS_LOGGED_IN=<?php echo json_encode($is_logged_in); ?>;
const IS_GOOGLE_CONNECTED=<?php echo json_encode($is_google_connected); ?>;
const GOOGLE_MAPS_FRONTEND_JS_KEY="<?php echo eh($google_maps_frontend_js_key); ?>";
const USER_PETS_DATA=<?php echo json_encode($pets); ?>;
const USER_TRAVEL_PREFERENCE=<?php echo json_encode($user_travel_mode_preference_db); ?>;
let gFetchedCalendarEvents=[],currentUserPosition=null,gUserTravelPreference=USER_TRAVEL_PREFERENCE;
</script>
</head>
<body>
<div class="app-container">
<header class="app-header">
<div class="logo">今天吃蝦米</div>
<nav class="main-nav">
<a href="#" id="home-link" data-content="recommendations">主頁</a>
<a href="#" id="profile-link" data-content="user-profile" class="<?php echo $is_logged_in ? '' : 'requires-login'; ?>">使用者頁面</a>
<?php echo $is_logged_in ? '<a href="logout.php" style="margin-left:10px;">登出</a>' : '<a href="#" id="login-trigger-button">登入</a>'; ?>
</nav>
</header>
<div class="main-layout">
<aside class="sidebar left-sidebar"><div class="sidebar-scroll-content">
<nav class="sidebar-nav">
<section class="widget"><h3 class="widget-header sidebar-link <?php echo $is_logged_in ? '' : 'requires-login'; ?>" data-content="recommendations"><span><i class="fas fa-utensils nav-icon"></i> 美食推薦</span></h3></section>
<section class="widget collapsible-widget">
<h3 class="widget-header collapsible-header <?php echo $is_logged_in ? '' : 'requires-login'; ?>" data-target="#user-info-content"><span><i class="fas fa-user nav-icon"></i> 使用者資訊</span><span class="arrow"><?php echo ($is_logged_in && ($prefs || $user_travel_mode_preference_db)) ? 'V' : '>'; ?></span></h3>
<div class="collapsible-content" id="user-info-content" <?php if (!($is_logged_in && ($prefs || $user_travel_mode_preference_db))) echo 'style="display:none"'; ?>>
<?php if ($is_logged_in): ?>
<div class="preferences"><h4>飲食偏好</h4><ul id="sidebar-preference-list">
<?php if ($prefs) foreach ($prefs as $p): $s = $p['type'] === 'like' ? '✔' : '✖'; $c = $p['type'] === 'like' ? 'pref-like-symbol' : 'pref-dislike-symbol'; ?>
<li class="<?php echo eh($p['type']); ?>" data-pref-id="<?php echo eh($p['id']); ?>"><span class="pref-text"><?php echo eh($p['preference_value']); ?></span><span class="pref-symbol <?php echo eh($c); ?>"><?php echo $s; ?></span></li>
<?php endforeach; if (!$prefs): ?><p id="sidebar-no-prefs-message" class="no-prefs-message">尚無飲食偏好設定</p><?php endif; ?>
</ul></div>
<hr class="sidebar-divider">
<div class="travel-mode-sidebar-display"><h4>交通方式</h4><p id="sidebar-travel-mode-display"><i class="fas <?php echo eh($current_travel_mode_display['icon']); ?>"></i><?php echo eh($current_travel_mode_display['text']); ?></p></div>
<?php endif; ?>
</div>
</section>
<section class="widget collapsible-widget">
<h3 class="widget-header collapsible-header <?php echo $is_logged_in ? '' : 'requires-login'; ?>" data-target="#pet-info-content"><span><i class="fas fa-dog nav-icon"></i> 寵物資訊</span><span class="arrow" data-pet-count="<?php echo count($pets); ?>">></span></h3>
<div class="collapsible-content" id="pet-info-content" style="display:none">
<?php if ($is_logged_in): if ($pets): ?>
<div class="pets-container">
<?php foreach ($pets as $pet): ?>
<div class="pet-card" data-pet-id="<?php echo eh($pet['id']); ?>">
<h4><?php echo eh($pet['name'] ?: '未知名稱'); ?></h4>
<?php if ($pet['details']) echo '<p>' . eh($pet['details']) . '</p>'; ?>
<?php if ($pet['status']) echo '<p class="pet-status">' . eh($pet['status']) . '</p>'; ?>
<?php if ($pet['image']) echo '<img src="' . eh($pet['image']) . '" alt="' . eh($pet['name']) . '" class="pet-image">'; else echo '<div class="pet-image-placeholder"><i class="fas fa-paw"></i></div>'; ?>
</div>
<?php endforeach; ?>
</div>
<?php else: ?><p id="sidebar-no-pets-message" class="no-prefs-message">尚無寵物資訊</p><?php endif; endif; ?>
</div>
</section>
<section class="widget"><h3 class="widget-header sidebar-link" data-content="food-diary"><span><i class="fas fa-book-open nav-icon"></i> 美食日誌</span></h3></section>
</nav>
<?php if ($is_logged_in): ?>
<section class="widget route-preview-widget" id="left-sidebar-route-widget" style="display:block">
<h4><span id="route-preview-mode-icon"><i class="fas <?php echo eh($sidebar_route_mode_icon_class); ?>"></i></span> 預覽路線 <span id="route-preview-total-time-wrapper" style="display:none">(<span id="route-preview-total-time"><?php echo eh($sidebar_route_total_time); ?></span>)</span></h4>
<div class="route-image-placeholder" id="route-preview-placeholder-default" style="display:block"><span class="pin-icon">📍</span></div>
<div id="route-preview-summary-details" style="display:none"><p id="route-preview-total-distance" class="route-detail-info" style="margin-bottom:5px"></p><a id="route-preview-maps-link" href="<?php echo eh($sidebar_route_maps_link); ?>" target="_blank" rel="noopener noreferrer" class="route-detail-info">在 Google Maps 中開啟</a></div>
<div id="sidebar-route-steps-container" class="sidebar-route-steps-list"><p class="no-steps-message" id="sidebar-route-initial-message">選擇餐廳並規劃行程後，此處將顯示詳細路線步驟。</p></div>
<div id="route-error" class="route-error-message" style="display:none"></div>
</section>
<?php endif; ?>
</div></aside>
<main class="content-area" id="main-content-area"><div class="loading-placeholder"><p>正在載入內容...</p><i class="fas fa-spinner fa-spin"></i></div></main>
<aside class="sidebar right-sidebar <?php echo $is_logged_in ? '' : 'hidden-section'; ?>">
<?php if ($is_logged_in): ?>
<section class="widget schedule"><h3 class="widget-header"><span><i class="far fa-calendar-alt nav-icon"></i> 行事曆</span><span class="edit-icon" id="edit-schedule-icon"><i class="fas fa-pencil-alt"></i></span></h3>
<div id="schedule-content-area"><div id="schedule-events-list" style="display:none"><p id="schedule-loading-message" style="display:none">正在載入行程...</p></div><div id="schedule-connect-prompt" style="display:none"><p class="schedule-prompt-message">連接 Google 帳號以顯示今日行程。</p><button id="connect-google-button" class="connect-google-btn">連接 Google 帳號</button></div><div id="schedule-error" class="schedule-error-message" style="display:none"></div></div>
</section>
<section class="widget weather"><h3 class="widget-header"><span><i class="fas fa-cloud-sun nav-icon"></i> 天氣</span></h3>
<div class="weather-info"><span class="icon"><i class="fas fa-question-circle weather-status-icon"></i></span><span id="weather-temp-display" class="weather-temp-value">--°C</span></div><div id="weather-error" class="weather-error-message" style="display:none"></div>
</section>
<section class="widget chatgpt-response"><h3 class="widget-header"><span><i class="fas fa-robot nav-icon"></i>CHATGPT 回應</span></h3><pre id="chatgpt-response-content">AI 回應將顯示於此處...</pre></section>
<?php endif; ?>
</aside>
</div>
</div>
<div id="login-modal" class="modal"><div class="modal-content"><span class="close-button">×</span><h2>使用者登入</h2>
<?php if (isset($_SESSION['login_error_message'])) { echo '<p class="login-error-message">' . eh($_SESSION['login_error_message']) . '</p>'; unset($_SESSION['login_error_message']); } elseif (isset($_SESSION['google_auth_error'])) { echo '<p class="login-error-message">' . eh($_SESSION['google_auth_error']) . '</p>'; unset($_SESSION['google_auth_error']); } elseif (isset($_GET['login_error']) && $_GET['login_error'] === 'google_auth_requires_login') { echo '<p class="login-error-message">請先登入您的帳號，才能連接 Google 行事曆。</p>'; } if (isset($_SESSION['google_auth_success'])) { echo '<p class="login-success-message">' . eh($_SESSION['google_auth_success']) . '</p>'; unset($_SESSION['google_auth_success']); } ?>
<form action="login_process.php" method="post" id="standard-login-form"><div class="form-group"><label for="username_login">帳號名：</label><input type="text" id="username_login" name="username" required></div><div class="form-group"><label for="password_login">密碼：</label><input type="password" id="password_login" name="password" required></div><button type="submit" class="login-submit-button">登入 / 創建帳號</button></form>
<div class="login-divider">或</div><button type="button" id="google-login-button" class="connect-google-btn google-login"><i class="fab fa-google"></i> 使用 Google 登入</button>
</div></div>
<script src="js/main.js?v=<?php echo time(); ?>" type="module"></script>
</body>
</html>
