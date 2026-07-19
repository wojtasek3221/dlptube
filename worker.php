<?php
// Usage: php worker.php <taskId>
if (PHP_SAPI !== 'cli') {
    // For safety, allow only CLI run
    exit("This script must be run from CLI\n");
}

$taskId = $argv[1] ?? null;
if (!$taskId) exit("No task id\n");

$tasksDir = __DIR__ . '/tasks';
$taskFile = "{$tasksDir}/{$taskId}.json";
if (!file_exists($taskFile)) exit("Task file not found\n");

// helper to atomically update task
function update_task($file, $data) {
    file_put_contents($file, json_encode($data), LOCK_EX);
}

$taskData = json_decode(file_get_contents($taskFile), true);
if (!$taskData) exit("Invalid task file\n");

// mark processing
$taskData['status'] = 'processing';
$taskData['message'] = 'Starting download';
$taskData['started_at'] = date('c');
update_task($taskFile, $taskData);

// include download functions
require_once __DIR__ . '/download_funcs.php';

// ensure using absolute script paths inside functions
try {
    $result = downloadAndConvert($taskData['videoId'], $taskData['format'], $taskId);
    if (isset($result['error'])) {
        $taskData['status'] = 'error';
        $taskData['message'] = $result['error'];
    } else {
        $taskData['status'] = 'finished';
        $taskData['message'] = 'Ready';
        $taskData['filepath'] = $result['filepath'];
        $taskData['filename'] = $result['filename'];
        $taskData['finished_at'] = date('c');
    }
} catch (Exception $e) {
    $taskData['status'] = 'error';
    $taskData['message'] = 'Exception: ' . $e->getMessage();
}

update_task($taskFile, $taskData);
