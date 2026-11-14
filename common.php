<?php
session_start();

// ---------- Subscriptions (cookie) ----------
$subscriptions = isset($_COOKIE['dlp_subs']) ? json_decode($_COOKIE['dlp_subs'], true) : [];
if (!is_array($subscriptions)) $subscriptions = [];

// ---------- Configuration ----------
define('YOUTUBE_API_KEY', getenv('YOUTUBE_API_KEY') ?: '');
define('DOWNLOADS_DIR', './downloads');
define('TEMP_DIR', './temp');

if (!is_dir(DOWNLOADS_DIR)) mkdir(DOWNLOADS_DIR, 0755, true);
if (!is_dir(TEMP_DIR)) mkdir(TEMP_DIR, 0755, true);

// ---------- Request vars ----------
$action = $_GET['action'] ?? null;
$videoId = $_GET['videoId'] ?? null;
$format = $_GET['format'] ?? null;
$query = $_POST['query'] ?? $_GET['query'] ?? null;
$pageToken = $_GET['pageToken'] ?? null;
$apiKey = $_POST['apiKey'] ?? $_GET['apiKey'] ?? YOUTUBE_API_KEY;

$message = '';
$messageType = '';
$searchResults = [];

// ---------- Handle Subscribe/Unsubscribe Actions ----------
// (moved here so $action/$message/$messageType are already defined)
if ($action === 'subscribe' && isset($_GET['channelId']) && isset($_GET['channelTitle'])) {
    $subscriptions[$_GET['channelId']] = $_GET['channelTitle'];
    setcookie('dlp_subs', json_encode($subscriptions), time() + (86400 * 365), "/");
    $message = 'Subscribed to ' . htmlspecialchars($_GET['channelTitle']);
    $messageType = 'ok';
    $action = 'subscriptions';
} elseif ($action === 'unsubscribe' && isset($_GET['channelId'])) {
    unset($subscriptions[$_GET['channelId']]);
    setcookie('dlp_subs', json_encode($subscriptions), time() + (86400 * 365), "/");
    $message = 'Unsubscribed';
    $messageType = 'ok';
    $action = 'subscriptions';
}

// ---------- Search / Channel view logic ----------
// (moved outside the if block to avoid duplication)
function searchYouTube($query, $apiKey, $pageToken = null, $channelId = null) {
    if (!$apiKey) return 'YouTube API key not configured';
    $url = 'https://www.googleapis.com/youtube/v3/search';
    $params = [
        'part' => 'snippet',
        'type' => 'video',
        'maxResults' => 10,
        'key' => $apiKey
    ];
    if ($query) $params['q'] = $query;
    if ($pageToken) $params['pageToken'] = $pageToken;
    if ($channelId) {
        $params['channelId'] = $channelId;
        $params['order'] = 'date';
    }
    $fullUrl = $url . '?' . http_build_query($params);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) return 'Failed to search videos. Check API key.';
    $data = json_decode($response, true);
    if (isset($data['error'])) return $data['error']['message'] ?? 'API Error';
    return [
        'items' => $data['items'] ?? [],
        'nextPageToken' => $data['nextPageToken'] ?? null,
        'prevPageToken' => $data['prevPageToken'] ?? null
    ];
}

// Handle search (initial or pagination)
if (isset($_POST['search']) || isset($_GET['search']) || $action === 'channel') {
    $query = trim($_POST['query'] ?? $_GET['query'] ?? '');
    $apiKey = trim($_POST['apiKey'] ?? $_GET['apiKey'] ?? '') ?: YOUTUBE_API_KEY;
    if ($query || $action === 'channel') {
        $_SESSION['current_query'] = $query;
        $_SESSION['current_api_key'] = $apiKey;
        // Perform the search (or channel view)
        $channelId = ($action === 'channel') ? ($_GET['channelId'] ?? null) : null;
        $searchResults = searchYouTube($query, $apiKey, $pageToken, $channelId);
        if (!is_array($searchResults)) {
            $message = $searchResults;
            $messageType = 'err';
            $searchResults = [];
        }
    } else {
        $message = 'Please enter a search query';
        $messageType = 'err';
    }
}

// Handle pagination (next/prev) – already called above when action is next/prev,
// but we keep it here for clarity (now redundant but safe)
if (($action === 'next' || $action === 'prev') && $pageToken) {
    $query = $_GET['query'] ?? $_SESSION['current_query'] ?? '';
    $apiKey = $_SESSION['current_api_key'] ?? YOUTUBE_API_KEY;
    $channelId = $_GET['channelId'] ?? null;
    $searchResults = searchYouTube($query, $apiKey, $pageToken, $channelId);
    if (!is_array($searchResults)) {
        $message = $searchResults;
        $messageType = 'err';
        $searchResults = [];
    }
}

// Handle channel view (if not already handled above)
if ($action === 'channel' && isset($_GET['channelId'])) {
    // If we didn't get results yet (e.g., when coming from a link without search parameter)
    if (empty($searchResults)) {
        $channelId = $_GET['channelId'];
        $apiKey = $_SESSION['current_api_key'] ?? YOUTUBE_API_KEY;
        $searchResults = searchYouTube('', $apiKey, null, $channelId);
        if (!is_array($searchResults)) {
            $message = $searchResults;
            $messageType = 'err';
            $searchResults = [];
        }
    }
}

// ---------- Download handling ----------
if ($action === 'download' && $videoId && $format) {
    $tasksDir = __DIR__ . '/tasks';
    if (!is_dir($tasksDir)) mkdir($tasksDir, 0755, true);
    $taskId = bin2hex(random_bytes(16));
    $taskFile = "{$tasksDir}/{$taskId}.json";
    $taskData = [
        'id' => $taskId,
        'videoId' => $videoId,
        'format' => $format,
        'status' => 'queued',
        'message' => 'Queued',
        'filepath' => null,
        'filename' => null,
        'created_at' => date('c')
    ];
    file_put_contents($taskFile, json_encode($taskData), LOCK_EX);
    $php = '/usr/bin/php';
    $worker = escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/worker.php') . ' ' . escapeshellarg($taskId);
    shell_exec('setsid ' . $worker . ' > /dev/null 2>&1 &');
    header('Location: waiting.php?task=' . urlencode($taskId));
    exit;
}

// ---------- Helper functions ----------
function getVideoDetails($videoId, $apiKey) {
    if (!$apiKey) return 'YouTube API key not configured';
    $url = 'https://www.googleapis.com/youtube/v3/videos';
    $params = [
        'part' => 'snippet',
        'id' => $videoId,
        'key' => $apiKey
    ];
    $fullUrl = $url . '?' . http_build_query($params);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) return 'Failed to fetch video details.';
    $data = json_decode($response, true);
    if (isset($data['error'])) return $data['error']['message'] ?? 'API Error';
    return $data['items'][0] ?? null;
}

function downloadAndConvert($videoId, $format) {
    // ... (unchanged, keep your existing implementation)
}

function getConvertFlags($format) {
    // ... (unchanged)
}

function generateOutputFilename($videoId, $format) {
    // ... (unchanged)
}

$currentQuery = $_SESSION['current_query'] ?? '';
$currentApiKey = $_SESSION['current_api_key'] ?? YOUTUBE_API_KEY;
$carryQueryString = $_SERVER['QUERY_STRING'] ?? '';
