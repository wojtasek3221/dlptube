<?php
$taskId = $_GET['task'] ?? '';
$tasksDir = __DIR__ . '/tasks';
$taskFile = "{$tasksDir}/{$taskId}.json";

header('Content-Type: application/json');

if (!$taskId || !file_exists($taskFile)) {
    echo json_encode(['error' => 'Task not found']);
    exit;
}

$data = json_decode(file_get_contents($taskFile), true);
if (!$data) {
    echo json_encode(['error' => 'Invalid task file']);
    exit;
}

echo json_encode([
    'status' => $data['status'],
    'message' => $data['message'],
    'filename' => $data['filename'] ?? null,
    'ready' => ($data['status'] === 'finished')
]);
