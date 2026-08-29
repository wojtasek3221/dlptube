<?php require __DIR__ . '/common.php'; ?>
<html>
<head>
<title>DLP TUBE</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<style type="text/css">
body { margin: 4px; overflow-x: hidden; word-wrap: break-word; background: #000; color: #fff; }
.btn { background: #d00; color: #fff; border: 1px solid #f00; padding: 3px 6px; }
input[type="text"] { width: 100%; max-width: 200px; box-sizing: border-box; }
table { width: 100%; table-layout: fixed; }
td.thumb { width: 120px; }
img { max-width: 100%; height: auto; }
a { color: #0ff; }
hr { border: 1px solid #333; }
</style>
</head>
<body>

<font color="#ff0000" size="+2"><b>DLP TUBE</b></font><br>
<font size="-2"><a href="index.php<?php echo $carryQueryString ? '?' . htmlspecialchars($carryQueryString) : ''; ?>">[ Switch to Fancy Mode ]</a></font>
<hr>
<a href="?action=subscriptions">My Subscriptions</a>
<form method="GET" action="">
<b>Search:</b><br>
<input type="text" name="query" value="<?php echo htmlspecialchars($currentQuery); ?>" size="30" required>
<br>
<input type="submit" name="search" value="Search" class="btn">
</form>
<hr>

<?php if ($message): ?>
<font color="<?php echo $messageType === 'err' ? '#ff5555' : '#55ff55'; ?>"><b>
<?php echo htmlspecialchars($message); ?>
</b></font>
<hr>
<?php endif; ?>

<?php if ($action === 'view' && $videoId): ?>
<!-- Video detail (unchanged) -->
<?php
$videoDetails = getVideoDetails($videoId, $currentApiKey);
if (is_array($videoDetails)):
    $title = htmlspecialchars($videoDetails['snippet']['title']);
$channel = htmlspecialchars($videoDetails['snippet']['channelTitle']);
$description = htmlspecialchars($videoDetails['snippet']['description']);
$thumbUrl = $videoDetails['snippet']['thumbnails']['medium']['url'] ?? $videoDetails['snippet']['thumbnails']['default']['url'];
?>
<a href="?search=1&query=<?php echo urlencode($currentQuery); ?>">&laquo; Back</a><br><br>
<b><?php echo $title; ?></b><br>
<font size="-1">By: <?php echo $channel; ?></font><br><br>
<img src="<?php echo htmlspecialchars($thumbUrl); ?>" width="320" alt="Thumb"><br><br>
<b>Download Video (3GP):</b><br>
[ <a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=3gp-low">Low</a> ]
[ <a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=3gp-mid">Mid</a> ]
[ <a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=3gp-high">High</a> ]
[ <a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=3gp-ultra">Ultra</a> ]<br><br>
<b>Download Audio:</b><br>
[ <a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=mp3">MP3</a> ]<br><br>
<b>Description:</b><br>
<font size="-1"><?php echo nl2br($description); ?></font>
<hr>
<?php else: ?>
<font color="#ff5555"><?php echo is_string($videoDetails) ? htmlspecialchars($videoDetails) : 'Video not found'; ?></font>
<?php endif; ?>

<?php elseif ($action === 'subscriptions'): ?>
<!-- Subscriptions list (NEW in Lite) -->
<b>Your Subscriptions</b><br><br>
<?php if (empty($subscriptions)): ?>
<font size="-1">No subscriptions found.</font>
<?php else: ?>
<?php foreach ($subscriptions as $id => $title): ?>
<b><?php echo htmlspecialchars($title); ?></b><br>
[ <a href="?action=channel&channelId=<?php echo urlencode($id); ?>">View Channel</a> ]
[ <a href="?action=unsubscribe&channelId=<?php echo urlencode($id); ?>" style="color:#f55;">Unsubscribe</a> ]<br><br>
<?php endforeach; ?>
<?php endif; ?>
<hr>

<?php elseif ($action === 'channel'): ?>
<!-- Channel view (NEW in Lite) -->
<?php
$channelTitle = '';
$channelId = $_GET['channelId'] ?? '';
if (!empty($searchResults['items'])) {
    $first = $searchResults['items'][0];
    $channelTitle = htmlspecialchars($first['snippet']['channelTitle']);
    $channelId = htmlspecialchars($first['snippet']['channelId']);
}
?>
<b><?php echo $channelTitle ?: 'Channel'; ?></b><br>
<?php if ($channelId): ?>
<?php if (isset($subscriptions[$channelId])): ?>
[ <a href="?action=unsubscribe&channelId=<?php echo urlencode($channelId); ?>" style="color:#f55;">Unsubscribe</a> ]
<?php else: ?>
[ <a href="?action=subscribe&channelId=<?php echo urlencode($channelId); ?>&channelTitle=<?php echo urlencode($channelTitle); ?>">Subscribe</a> ]
<?php endif; ?>
<?php endif; ?>
<br><br>

<?php if (!empty($searchResults['items'])): ?>
<?php foreach ($searchResults['items'] as $video):
$videoId = $video['id']['videoId'];
$title = htmlspecialchars($video['snippet']['title']);
$thumb = $video['snippet']['thumbnails']['default']['url'] ?? '';
?>
<table border="0" cellpadding="0" cellspacing="0">
<tr>
<?php if ($thumb): ?>
<td valign="top"><img src="<?php echo htmlspecialchars($thumb); ?>" width="120" height="90" alt="Thumb"></td>
<?php endif; ?>
<td valign="top">
<b><?php echo $title; ?></b><br>
[ <a href="?action=view&videoId=<?php echo urlencode($videoId); ?>">View &amp; Download</a> ]
</td>
</tr>
</table>
<hr>
<?php endforeach; ?>
<!-- Pagination for channel -->
<?php if (isset($searchResults['prevPageToken']) || isset($searchResults['nextPageToken'])): ?>
<?php if (isset($searchResults['prevPageToken'])): ?>
[ <a href="?action=prev&channelId=<?php echo urlencode($channelId); ?>&pageToken=<?php echo urlencode($searchResults['prevPageToken']); ?>">&laquo; Prev</a> ]
<?php endif; ?>
<?php if (isset($searchResults['nextPageToken'])): ?>
[ <a href="?action=next&channelId=<?php echo urlencode($channelId); ?>&pageToken=<?php echo urlencode($searchResults['nextPageToken']); ?>">Next &raquo;</a> ]
<?php endif; ?>
<?php endif; ?>
<?php else: ?>
<font size="-1">This channel has no public videos.</font>
<?php endif; ?>
<hr>

<?php elseif (!empty($searchResults['items'])): ?>
<!-- Search results (unchanged) -->
<?php foreach ($searchResults['items'] as $video): ?>
<?php
$videoId = $video['id']['videoId'];
$title = htmlspecialchars($video['snippet']['title']);
$channel = htmlspecialchars($video['snippet']['channelTitle']);
$thumb = $video['snippet']['thumbnails']['default']['url'] ?? '';
?>
<table border="0" cellpadding="0" cellspacing="0">
<tr>
<?php if ($thumb): ?>
<td valign="top"><img src="<?php echo htmlspecialchars($thumb); ?>" width="120" height="90" alt="Thumb"></td>
<?php endif; ?>
<td valign="top">
<b><?php echo $title; ?></b><br>
<font size="-1"><?php echo $channel; ?> | ID: <?php echo $videoId; ?></font><br>
[ <a href="?action=view&videoId=<?php echo urlencode($videoId); ?>">View &amp; Download</a> ]
</td>
</tr>
</table>
<hr>
<?php endforeach; ?>

<?php if (isset($searchResults['prevPageToken']) || isset($searchResults['nextPageToken'])): ?>
<?php if (isset($searchResults['prevPageToken'])): ?>
[ <a href="?action=prev&query=<?php echo urlencode($currentQuery); ?>&pageToken=<?php echo urlencode($searchResults['prevPageToken']); ?>">&laquo; Prev</a> ]
<?php endif; ?>
<?php if (isset($searchResults['nextPageToken'])): ?>
[ <a href="?action=next&query=<?php echo urlencode($currentQuery); ?>&pageToken=<?php echo urlencode($searchResults['nextPageToken']); ?>">Next &raquo;</a> ]
<?php endif; ?>
<?php endif; ?>

<?php elseif ($currentQuery): ?>
<font size="-1">No videos found. Try a different search.</font>
<?php endif; ?>

</body>
</html>
