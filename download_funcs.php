<?php
// This file contains downloadAndConvert, getConvertFlags, generateOutputFilename
// and the progress-tracking helpers used to update the task JSON while
// download.sh / convert.sh are still running.

define('DOWNLOADS_DIR', __DIR__ . '/downloads');
define('TEMP_DIR', __DIR__ . '/temp');
define('TASKS_DIR', __DIR__ . '/tasks');

if (!is_dir(DOWNLOADS_DIR)) mkdir(DOWNLOADS_DIR, 0755, true);
if (!is_dir(TEMP_DIR)) mkdir(TEMP_DIR, 0755, true);
if (!is_dir(TASKS_DIR)) mkdir(TASKS_DIR, 0755, true);

// Paths to your shell scripts (use absolute)
$DOWNLOAD_SCRIPT = __DIR__ . '/download.sh';
$CONVERT_SCRIPT = __DIR__ . '/convert.sh';

/**
 * Merge fields into a task's JSON file (read-modify-write, atomic-ish via LOCK_EX).
 */
function updateTaskFields($taskId, array $fields) {
    if (!$taskId) return;
    $taskFile = TASKS_DIR . '/' . $taskId . '.json';
    if (!file_exists($taskFile)) return;
    $data = json_decode(file_get_contents($taskFile), true);
    if (!is_array($data)) return;
    $data = array_merge($data, $fields);
    file_put_contents($taskFile, json_encode($data), LOCK_EX);
}

/**
 * Run a shell command, streaming its stdout line-by-line to a callback.
 * Returns ['exit_code' => int, 'output' => string].
 */
function runWithProgress($cmd, callable $onLine) {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    // Explicit cwd so any relative paths inside download.sh / convert.sh
    // resolve to this app's directory, not whatever cwd the web server
    // process (and the detached 'setsid ... &' worker it launches) happens
    // to have.
    $process = proc_open($cmd, $descriptors, $pipes, __DIR__);
    if (!is_resource($process)) {
        return ['exit_code' => -1, 'output' => ''];
    }
    fclose($pipes[0]);

    $fullOutput = '';
    while (!feof($pipes[1])) {
        $line = fgets($pipes[1]);
        if ($line === false) break;
        $fullOutput .= $line;
        $onLine($line);
    }
    fclose($pipes[1]);
    fclose($pipes[2]); // stderr (log file already captures ffmpeg's stderr separately)

    $exitCode = proc_close($process);
    return ['exit_code' => $exitCode, 'output' => $fullOutput];
}

/**
 * Get media duration in seconds using ffprobe, or null if it can't be determined.
 */
function getMediaDuration($file) {
    $cmd = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
    . escapeshellarg($file);
    $out = trim((string) shell_exec($cmd));
    return is_numeric($out) ? (float) $out : null;
}

function downloadAndConvert($videoId, $format, $taskId = null) {
    global $DOWNLOAD_SCRIPT, $CONVERT_SCRIPT;

    if (!in_array($format, ['3gp-low', '3gp-mid', '3gp-high', '3gp-ultra', 'mp3'])) {
        return ['error' => 'Invalid format'];
    }
    if (!file_exists($DOWNLOAD_SCRIPT)) return ['error' => 'Download script not found'];
    if (!file_exists($CONVERT_SCRIPT)) return ['error' => 'Convert script not found'];

    $prefix = $videoId;
    if ($taskId) $prefix .= '_' . $taskId;

    // ---------- Step 1: download (progress 0-50%) ----------
    updateTaskFields($taskId, ['status' => 'processing', 'message' => 'Downloading: 0%', 'progress' => 0]);

    $downloadCmd = 'PYTHONUNBUFFERED=1 stdbuf -oL -eL '
    . escapeshellcmd($DOWNLOAD_SCRIPT) . ' '
    . escapeshellarg($videoId) . ' '
    . escapeshellarg(TEMP_DIR . '/' . $prefix) . ' 2>&1';

    $lastDownloadPct = -1;
    $downloadResult = runWithProgress($downloadCmd, function ($line) use ($taskId, &$lastDownloadPct) {
        // yt-dlp --newline output looks like:
        // [download]  42.7% of   9.10MiB at    1.05MiB/s ETA 00:04
        if (preg_match('/\[download\]\s+([\d.]+)%/', $line, $m)) {
            $pct = (float) $m[1];
            $overall = (int) round($pct / 2); // scale 0-100 -> 0-50
            if ($overall !== $lastDownloadPct) {
                $lastDownloadPct = $overall;
                updateTaskFields($taskId, [
                    'message' => 'Downloading: ' . round($pct) . '%',
                                 'progress' => $overall,
                ]);
            }
        }
    });

    // Try to find downloaded file using glob pattern (don't use preg_quote)
    $pattern = TEMP_DIR . '/' . $prefix . '*';
    $files = glob($pattern);
    if (empty($files)) {
        // fallback: search recent files in TEMP_DIR
        $all = glob(TEMP_DIR . '/*');
        if (!empty($all)) {
            usort($all, function($a, $b){ return filemtime($b) - filemtime($a); });
            $files = $all;
        }
    }

    if (empty($files)) {
        return ['error' => 'Failed to download video. Download output: ' . trim($downloadResult['output'])];
    }

    $downloadedFile = $files[0];
    updateTaskFields($taskId, ['message' => 'Download complete, starting conversion', 'progress' => 50]);

    // ---------- Step 2: convert (progress 50-100%) ----------
    $outputFile = generateOutputFilename($videoId, $format);
    $outputPath = DOWNLOADS_DIR . '/' . $outputFile;
    $convertFlags = getConvertFlags($format);

    $duration = getMediaDuration($downloadedFile); // seconds, or null if unknown

    $convertCmd = escapeshellcmd($CONVERT_SCRIPT) . ' ' . $convertFlags . ' '
    . escapeshellarg($downloadedFile) . ' ' . escapeshellarg($outputPath);

    $lastConvertPct = -1;
    $convertResult = runWithProgress($convertCmd, function ($line) use ($taskId, $duration, &$lastConvertPct) {
        // ffmpeg -progress pipe:1 output includes lines like: out_time=00:00:12.345678
        if ($duration && preg_match('/^out_time=(\d+):(\d+):(\d+(?:\.\d+)?)/', trim($line), $m)) {
            $seconds = ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (float) $m[3];
            $pct = min(100, ($seconds / $duration) * 100);
            $overall = 50 + (int) round($pct / 2); // scale 0-100 -> 50-100
            if ($overall !== $lastConvertPct) {
                $lastConvertPct = $overall;
                updateTaskFields($taskId, [
                    'message' => 'Converting: ' . round($pct) . '%',
                                 'progress' => $overall,
                ]);
            }
        }
    });

    if (!file_exists($outputPath)) {
        return ['error' => 'Failed to convert video (exit ' . $convertResult['exit_code'] . '). '
        . 'Output: ' . trim($convertResult['output'])];
    }

    // cleanup temp file
    @unlink($downloadedFile);

    updateTaskFields($taskId, ['progress' => 100]);

    return ['filepath' => $outputPath, 'filename' => basename($outputPath)];
}

function getConvertFlags($format) {
    switch ($format) {
        case '3gp-low': return '-l';
        case '3gp-mid': return '-m';
        case '3gp-high': return '-h';
        case '3gp-ultra': return '-u';
        case 'mp3': return '-s';
        default: return '-m';
    }
}

function generateOutputFilename($videoId, $format) {
    $timestamp = time();
    $formatExt = ($format === 'mp3') ? 'mp3' : '3gp';
    $cleanFormat = str_replace('-', '_', $format);
    return $videoId . '_' . $cleanFormat . '_' . $timestamp . '.' . $formatExt;
}
