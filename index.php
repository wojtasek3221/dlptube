<?php
session_start();

// Configuration
define('YOUTUBE_API_KEY', ''); // Fill in youtube API key
define('DOWNLOADS_DIR', './downloads');
define('TEMP_DIR', './temp');

// Ensure directories exist
if (!is_dir(DOWNLOADS_DIR)) {
    mkdir(DOWNLOADS_DIR, 0755, true);
}
if (!is_dir(TEMP_DIR)) {
    mkdir(TEMP_DIR, 0755, true);
}

$action = $_GET['action'] ?? null;
$videoId = $_GET['videoId'] ?? null;
$format = $_GET['format'] ?? null;
$query = $_POST['query'] ?? $_GET['query'] ?? null;
$pageToken = $_GET['pageToken'] ?? null;
$apiKey = $_POST['apiKey'] ?? $_GET['apiKey'] ?? YOUTUBE_API_KEY;

$message = '';
$messageType = '';
$searchResults = [];
$nextPageToken = null;

// Handle search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $query = trim($_POST['query']);
    $apiKey = trim($_POST['apiKey']) ?: YOUTUBE_API_KEY;

    if ($query) {
        $searchResults = searchYouTube($query, $apiKey, null);
        if (!is_array($searchResults)) {
            $message = $searchResults;
            $messageType = 'error';
            $searchResults = [];
        } else {
            $_SESSION['current_query'] = $query;
            $_SESSION['current_api_key'] = $apiKey;
        }
    } else {
        $message = 'Please enter a search query';
        $messageType = 'error';
    }
}

// Handle pagination
if ($action === 'next' && $pageToken) {
    $query = $_SESSION['current_query'] ?? '';
    $apiKey = $_SESSION['current_api_key'] ?? YOUTUBE_API_KEY;

    if ($query) {
        $searchResults = searchYouTube($query, $apiKey, $pageToken);
        if (!is_array($searchResults)) {
            $message = $searchResults;
            $messageType = 'error';
            $searchResults = [];
        }
    }
}

// Handle download
if ($action === 'download' && $videoId && $format) {
    // create tasks dir
    $tasksDir = __DIR__ . '/tasks';
    if (!is_dir($tasksDir)) mkdir($tasksDir, 0755, true);

    // create unique task id
    $taskId = bin2hex(random_bytes(16));
    $taskFile = "{$tasksDir}/{$taskId}.json";

    $taskData = [
        'id' => $taskId,
        'videoId' => $videoId,
        'format' => $format,
        'status' => 'queued', // queued | processing | finished | error
        'message' => 'Queued',
        'filepath' => null,
        'filename' => null,
        'created_at' => date('c')
    ];
    file_put_contents($taskFile, json_encode($taskData), LOCK_EX);

    $php = '/usr/bin/php';

    $worker = escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/worker.php') . ' ' . escapeshellarg($taskId);

    // Fully detach so it survives the request ending
    shell_exec('setsid ' . $worker . ' > /dev/null 2>&1 &');

    // redirect user to waiting room
    header('Location: waiting.php?task=' . urlencode($taskId));
    exit;
}

function searchYouTube($query, $apiKey, $pageToken = null) {
    if (!$apiKey) {
        return 'YouTube API key not configured';
    }

    $url = 'https://www.googleapis.com/youtube/v3/search';
    $params = array(
        'part' => 'snippet',
        'q' => $query,
        'type' => 'video',
        'maxResults' => 10,
        'key' => $apiKey
    );

    if ($pageToken) {
        $params['pageToken'] = $pageToken;
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

    if ($httpCode !== 200) {
        return 'Failed to search videos. Check API key.';
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        return $data['error']['message'] ?? 'API Error';
    }

    return array(
        'items' => $data['items'] ?? [],
        'nextPageToken' => $data['nextPageToken'] ?? null
    );
}

function downloadAndConvert($videoId, $format) {
    // Validate format
    if (!in_array($format, ['3gp-low', '3gp-mid', '3gp-high', 'mp3'])) {
        return ['error' => 'Invalid format'];
    }

    // Check if download script exists
    if (!file_exists('./download.sh')) {
        return ['error' => 'Download script not found'];
    }

    // Check if convert script exists
    if (!file_exists('./convert.sh')) {
        return ['error' => 'Convert script not found'];
    }

    try {
        // Download video

        $downloadCmd = escapeshellcmd(__DIR__ . '/download.sh') . ' ' . escapeshellarg($videoId);
        $downloadOutput = shell_exec($downloadCmd . ' 2>&1');

        // Try to find the downloaded file (look for files starting with videoId)
        $pattern = TEMP_DIR . '/' . $videoId . '*';
        $files = glob($pattern);

        // Fallback: if nothing found, try most recent file in temp dir
        if (empty($files)) {
            $all = glob(TEMP_DIR . '/*');
            if (!empty($all)) {
                usort($all, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                $files = $all;
            }
        }

        if (empty($files)) {
            // Include script output to help debug what went wrong
            return ['error' => 'Failed to download video. Download script output: ' . trim($downloadOutput)];
        }

        $downloadedFile = $files[0];

        // Convert based on format
        $outputFile = generateOutputFilename($videoId, $format);
        $outputPath = DOWNLOADS_DIR . '/' . $outputFile;

        $convertFlags = getConvertFlags($format);
        $convertCmd = './convert.sh ' . $convertFlags . ' ' . escapeshellarg($downloadedFile) . ' ' . escapeshellarg($outputPath);

        $convertOutput = shell_exec($convertCmd . ' 2>&1');

        // Check if conversion was successful
        if (!file_exists($outputPath)) {
            return ['error' => 'Failed to convert video'];
        }

        // Clean up temp file
        @unlink($downloadedFile);

        return [
            'filepath' => $outputPath,
            'filename' => $outputFile
        ];

    } catch (Exception $e) {
        return ['error' => 'Conversion error: ' . $e->getMessage()];
    }
}

function getConvertFlags($format) {
    switch ($format) {
        case '3gp-low':
            return '-l';
        case '3gp-mid':
            return '-m';
        case '3gp-high':
            return '-h';
        case 'mp3':
            return '-s';
        default:
            return '-m';
    }
}

function generateOutputFilename($videoId, $format) {
    $timestamp = time();
    $formatExt = ($format === 'mp3') ? 'mp3' : '3gp';
    $cleanFormat = str_replace('-', '_', $format);
    return $videoId . '_' . $cleanFormat . '_' . $timestamp . '.' . $formatExt;
}

$currentQuery = $_SESSION['current_query'] ?? '';
$currentApiKey = $_SESSION['current_api_key'] ?? YOUTUBE_API_KEY;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>YouTube Opera Mini Client</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background-color: black;
    color: white;
    padding: 10px;
    font-size: 14px;
}

.container {
    max-width: 400px;
    margin: 0 auto;
    background-color: #420000;
    border-radius: 4px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

header {
    text-align: center;
    margin-bottom: 20px;
    border-bottom: 2px solid #ff0000;
    padding-bottom: 10px;
}

h1 {
    font-size: 18px;
    color: red;
}

.search-box {
    margin-bottom: 15px;
}

.form-group {
    margin-bottom: 10px;
}

label {
    display: block;
    font-size: 12px;
    color: red;
    margin-bottom: 3px;
}

input[type="text"],
input[type="password"] {
    width: 100%;
    padding: 8px;
    border: 1px solid red;
    background-color: #420000;
    color: red;
    border-radius: 3px;
    font-size: 12px;
    box-sizing: border-box;
}

button {
    width: 100%;
    padding: 8px 12px;
    background-color: #ff0000;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
}

button:hover {
    background-color: #cc0000;
}

button:active {
    background-color: #990000;
}

.message {
    padding: 10px;
    border-radius: 3px;
    margin: 10px 0;
    font-size: 12px;
}

.error {
    color: #d32f2f;
    background-color: #ffebee;
    border: 1px solid #ef5350;
}

.success {
    color: #388e3c;
    background-color: #e8f5e9;
    border: 1px solid #81c784;
}

.results {
    margin-top: 15px;
}

.video-item {
    background-color: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 10px;
    margin-bottom: 10px;
}

.video-title {
    font-weight: bold;
    color: #222;
    margin-bottom: 5px;
    font-size: 13px;
    word-wrap: break-word;
}

.video-channel {
    color: #666;
    font-size: 11px;
    margin-bottom: 5px;
}

.video-id {
    font-size: 10px;
    color: #999;
    margin-bottom: 8px;
    word-break: break-all;
    background-color: #f0f0f0;
    padding: 3px 5px;
    border-radius: 2px;
}

.download-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 5px;
}

.download-btn {
    padding: 6px 4px;
    font-size: 11px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 2px;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    display: inline-block;
    width: 100%;
}

.download-btn:hover {
    background-color: #0056b3;
}

.download-btn.mp3 {
    background-color: #28a745;
    grid-column: span 2;
}

.download-btn.mp3:hover {
    background-color: #1e7e34;
}

.pagination {
    text-align: center;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}

.pagination button {
    margin: 0 3px;
    padding: 6px 12px;
    font-size: 11px;
    width: auto;
    display: inline-block;
}

.info-box {
    background-color: #e3f2fd;
    border: 1px solid #90caf9;
    border-radius: 3px;
    padding: 10px;
    margin-bottom: 15px;
    font-size: 12px;
    color: #1565c0;
}

.no-results {
    text-align: center;
    color: #666;
    padding: 20px 10px;
    font-size: 12px;
}
</style>
</head>
<body>
<div class="container">
<header>
<h1>DLP TUBE</h1>
</header>


<form method="POST" class="search-box">
<div class="form-group">
<label for="query">Search Videos:</label>
<input
type="text"
id="query"
name="query"
placeholder="Search..."
value="<?php echo htmlspecialchars($currentQuery); ?>"
required
>
</div>

<div class="form-group">
</div>

<button type="submit" name="search">Search</button>
</form>

<?php if ($message): ?>
<div class="message <?php echo $messageType; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if (!empty($searchResults['items'])): ?>
<div class="results">
<?php foreach ($searchResults['items'] as $video): ?>
<?php
$videoId = $video['id']['videoId'];
$title = htmlspecialchars($video['snippet']['title']);
$channel = htmlspecialchars($video['snippet']['channelTitle']);
?>
<div class="video-item">
<div class="video-title"><?php echo $title; ?></div>
<div class="video-channel">Channel: <?php echo $channel; ?></div>
<div class="video-id">ID: <?php echo $videoId; ?></div>
<div class="download-buttons">
<a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=3gp-low" class="download-btn">3GP Low</a>
<a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=3gp-mid" class="download-btn">3GP Mid</a>
<a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=3gp-high" class="download-btn">3GP High</a>
<a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=mp3" class="download-btn mp3">MP3 Audio</a>
</div>
</div>
<?php endforeach; ?>
</div>

<?php if (isset($searchResults['nextPageToken'])): ?>
<div class="pagination">
<form method="GET" style="display: inline;">
<input type="hidden" name="action" value="next">
<input type="hidden" name="pageToken" value="<?php echo htmlspecialchars($searchResults['nextPageToken']); ?>">
<button type="submit">Next Page</button>
</form>
</div>
<?php endif; ?>
<?php elseif ($currentQuery): ?>
<div class="no-results">
No videos found. Try a different search.
</div>
<?php endif; ?>
</div>
</body>
</html>
