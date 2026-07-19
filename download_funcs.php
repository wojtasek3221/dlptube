<?php
// This file contains downloadAndConvert, getConvertFlags, generateOutputFilename

define('DOWNLOADS_DIR', __DIR__ . '/downloads');
define('TEMP_DIR', __DIR__ . '/temp');

if (!is_dir(DOWNLOADS_DIR)) mkdir(DOWNLOADS_DIR, 0755, true);
if (!is_dir(TEMP_DIR)) mkdir(TEMP_DIR, 0755, true);

// Paths to shell scripts
$DOWNLOAD_SCRIPT = __DIR__ . '/download.sh';
$CONVERT_SCRIPT = __DIR__ . '/convert.sh';

function downloadAndConvert($videoId, $format, $taskId = null) {
    global $DOWNLOAD_SCRIPT, $CONVERT_SCRIPT;

    if (!in_array($format, ['3gp-low', '3gp-mid', '3gp-high', 'mp3'])) {
        return ['error' => 'Invalid format'];
    }
    if (!file_exists($DOWNLOAD_SCRIPT)) return ['error' => 'Download script not found'];
    if (!file_exists($CONVERT_SCRIPT)) return ['error' => 'Convert script not found'];

    // Create a task-specific temp prefix to avoid collisions
    $prefix = $videoId;
    if ($taskId) $prefix .= '_' . $taskId;

    //call download script
    $downloadCmd = escapeshellcmd($DOWNLOAD_SCRIPT) . ' ' . escapeshellarg($videoId) . ' ' . escapeshellarg(TEMP_DIR . '/' . $prefix);
    // Note: modify download.sh to accept optional output prefix/dir or print final file path
    $downloadOutput = shell_exec($downloadCmd . ' 2>&1');

    // Try to find downloaded file using glob pattern
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
        return ['error' => 'Failed to download video. Download output: ' . trim($downloadOutput)];
    }

    $downloadedFile = $files[0];

    //Convert
    $outputFile = generateOutputFilename($videoId, $format);
    $outputPath = DOWNLOADS_DIR . '/' . $outputFile;
    $convertFlags = getConvertFlags($format);
    $convertCmd = escapeshellcmd($CONVERT_SCRIPT) . ' ' . $convertFlags . ' ' . escapeshellarg($downloadedFile) . ' ' . escapeshellarg($outputPath);
    $convertOutput = shell_exec($convertCmd . ' 2>&1');

    if (!file_exists($outputPath)) {
        return ['error' => 'Failed to convert video. Convert output: ' . trim($convertOutput)];
    }

    //cleanup temp file
    @unlink($downloadedFile);

    return ['filepath' => $outputPath, 'filename' => basename($outputPath)];
}

function getConvertFlags($format) {
    switch ($format) {
        case '3gp-low': return '-l';
        case '3gp-mid': return '-m';
        case '3gp-high': return '-h';
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
