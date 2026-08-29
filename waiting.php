<?php
$taskId = $_GET['task'] ?? '';
$tasksDir = __DIR__ . '/tasks';
$taskFile = "{$tasksDir}/{$taskId}.json";

$status = null;
$message = 'Queued...';
$progress = 0;
$ready = false;
$error = null;

if (!$taskId) {
    $error = 'No task specified';
} elseif (!file_exists($taskFile)) {
    $error = 'Task not found';
} else {
    $data = json_decode(file_get_contents($taskFile), true);
    if (!$data) {
        $error = 'Invalid task file';
    } else {
        $status = $data['status'] ?? 'queued';
        $message = $data['message'] ?? $status;
        $progress = (int) ($data['progress'] ?? 0);
        $progress = max(0, min(100, $progress));
        $ready = ($status === 'finished');
    }
}

$showRefresh = !$error && !$ready && $status !== 'error';
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Waiting Room</title>
<?php if ($showRefresh): ?>
<meta http-equiv="refresh" content="3;url=waiting.php?task=<?php echo urlencode($taskId); ?>">
<?php endif; ?>
</head>
<body>
<h2>Your download is being prepared</h2>

<?php if ($error): ?>
<p>Error: <?php echo htmlspecialchars($error); ?></p>

<?php elseif ($ready): ?>
<!-- Full bar, old-school table cells so it renders on virtually any browser -->
<table width="200" border="1" cellpadding="0" cellspacing="0"><tr>
<td bgcolor="#28a745" width="100%">&nbsp;</td>
</tr></table>
<p>100% &mdash; Ready!</p>
<p><a href="download_file.php?task=<?php echo urlencode($taskId); ?>">Download ready — click to download</a></p>

<?php elseif ($status === 'error'): ?>
<p>Error: <?php echo htmlspecialchars($message); ?></p>

<?php else: ?>
<table width="200" border="1" cellpadding="0" cellspacing="0"><tr>
<?php if ($progress > 0): ?>
<td bgcolor="#007bff" width="<?php echo $progress; ?>%">&nbsp;</td>
<?php endif; ?>
<?php if ($progress < 100): ?>
<td bgcolor="#eeeeee" width="<?php echo (100 - $progress); ?>%">&nbsp;</td>
<?php endif; ?>
</tr></table>
<p id="status"><?php echo $progress; ?>% &mdash; <?php echo htmlspecialchars($message); ?></p>
<p>This page will refresh automatically. If it doesn't,
<a href="waiting.php?task=<?php echo urlencode($taskId); ?>">click here to check again</a>.</p>
<?php endif; ?>

</body>
</html>
