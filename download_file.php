<?php
$taskId = $_GET['task'] ?? '';
$tasksDir = __DIR__ . '/tasks';
$taskFile = "{$tasksDir}/{$taskId}.json";

if (!$taskId || !file_exists($taskFile)) {
    http_response_code(404);
    exit('Not found');
}

$data = json_decode(file_get_contents($taskFile), true);
if (!$data || $data['status'] !== 'finished' || empty($data['filepath'])) {
    http_response_code(404);
    exit('Not ready');
}

$path = $data['filepath'];
if (!file_exists($path)) {
    http_response_code(404);
    exit('File missing');
}

$basename = $data['filename'] ?? basename($path);
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($basename) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
