<?php require __DIR__ . '/common.php'; ?>
<html>
<head>
<title>DLP TUBE</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Sixtyfour&display=swap">
<style>
/* Additional style for channel header */
.channel-header { background: var(--bg-raised); border: 1px solid var(--border); border-radius: 8px; padding: 20px; margin-bottom: 20px; }
.channel-header h2 { margin-top: 0; }
.channel-header .sub-actions { font-size: 0.9rem; }
</style>
</head>
<body>
<div class="page">

<div class="mode-switch">
<a href="lite.php<?php echo $carryQueryString ? '?' . htmlspecialchars($carryQueryString) : ''; ?>">Switch to Lite Mode</a>
</div>

<header class="site-header">
<span class="brand">DLP TUBE</span>
<span class="tagline" id="tagline"><span class="cursor"></span></span>
</header>
<hr>

<form method="GET" action="" class="search-form">
<label for="q">Search:</label>
<input id="q" type="text" name="query" value="<?php echo htmlspecialchars($currentQuery); ?>" size="30" required>
<input type="submit" name="search" value="Search" class="btn">
</form>
<hr>

<?php if ($message): ?>
<div class="banner <?php echo $messageType === 'err' ? 'banner-err' : 'banner-ok'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<hr>
<?php endif; ?>

<?php if ($action === 'view' && $videoId): ?>
<!-- Video detail view (unchanged) -->
<?php
$videoDetails = getVideoDetails($videoId, $currentApiKey);
if (is_array($videoDetails)):
    $title = htmlspecialchars($videoDetails['snippet']['title']);
$channel = htmlspecialchars($videoDetails['snippet']['channelTitle']);
$description = htmlspecialchars($videoDetails['snippet']['description']);
$channelId = htmlspecialchars($videoDetails['snippet']['channelId']);
$thumbUrl = $videoDetails['snippet']['thumbnails']['medium']['url'] ?? $videoDetails['snippet']['thumbnails']['default']['url'];
?>
<div class="video-view">
<a class="back-link" href="?search=1&query=<?php echo urlencode($currentQuery); ?>">&laquo; Back to results</a>
<h2><?php echo $title; ?></h2>
<span class="channel">By <a href="?action=channel&channelId=<?php echo urlencode($channelId); ?>"><?php echo $channel; ?></a>
<?php if (isset($subscriptions[$channelId])): ?>
[<a href="?action=unsubscribe&channelId=<?php echo urlencode($channelId); ?>">Unsubscribe</a>]
<?php else: ?>
[<a href="?action=subscribe&channelId=<?php echo urlencode($channelId); ?>&channelTitle=<?php echo urlencode($channel); ?>">Subscribe</a>]
<?php endif; ?>
</span>
<img src="<?php echo htmlspecialchars($thumbUrl); ?>" width="320" alt="Thumb">
<span class="section-label">Download Video (3GP)</span>
<div class="dl-group">
<a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=3gp-low">Low</a>
<a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=3gp-mid">Mid</a>
<a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=3gp-high">High</a>
<a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=3gp-ultra">Ultra</a>
</div>
<span class="section-label">Download Audio</span>
<div class="dl-group">
<a href="?action=download&videoId=<?php echo urlencode($videoId); ?>&format=mp3">MP3</a>
</div>
<span class="section-label">Description</span>
<div class="description"><?php echo nl2br($description); ?></div>
</div>
<hr>
<?php else: ?>
<div class="banner banner-err"><?php echo is_string($videoDetails) ? htmlspecialchars($videoDetails) : 'Video not found'; ?></div>
<?php endif; ?>

<?php elseif ($action === 'subscriptions'): ?>
<!-- Subscription list (unchanged) -->
<h2>Your Subscriptions</h2>
<?php if (empty($subscriptions)): ?>
<p class="empty-note">No subscriptions found.</p>
<?php else: ?>
<div class="results-grid">
<?php foreach ($subscriptions as $id => $title): ?>
<div class="result-card">
<div class="meta">
<b><?php echo htmlspecialchars($title); ?></b>
<a href="?action=channel&channelId=<?php echo urlencode($id); ?>">View Channel</a> &middot;
<a href="?action=unsubscribe&channelId=<?php echo urlencode($id); ?>" style="color:var(--red);">Unsubscribe</a>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($action === 'channel'): ?>
<!-- CHANNEL VIEW (NEW) -->
<?php
// Extract channel info from first result
$channelTitle = '';
$channelId = $_GET['channelId'] ?? '';
if (!empty($searchResults['items'])) {
    $first = $searchResults['items'][0];
    $channelTitle = htmlspecialchars($first['snippet']['channelTitle']);
    $channelId = htmlspecialchars($first['snippet']['channelId']);
}
?>
<div class="channel-header">
<h2><?php echo $channelTitle ?: 'Channel'; ?></h2>
<div class="sub-actions">
<?php if ($channelId): ?>
<?php if (isset($subscriptions[$channelId])): ?>
<a href="?action=unsubscribe&channelId=<?php echo urlencode($channelId); ?>" style="color:var(--red);">Unsubscribe</a>
<?php else: ?>
<a href="?action=subscribe&channelId=<?php echo urlencode($channelId); ?>&channelTitle=<?php echo urlencode($channelTitle); ?>">Subscribe</a>
<?php endif; ?>
<?php endif; ?>
<span style="margin-left:15px;color:var(--text-dim);">Recent uploads</span>
</div>
</div>

<?php if (!empty($searchResults['items'])): ?>
<div class="results-grid">
<?php foreach ($searchResults['items'] as $video):
$videoId = $video['id']['videoId'];
$title = htmlspecialchars($video['snippet']['title']);
$thumb = $video['snippet']['thumbnails']['default']['url'] ?? '';
?>
<div class="result-card">
<?php if ($thumb): ?>
<img src="<?php echo htmlspecialchars($thumb); ?>" width="120" height="90" alt="Thumb">
<?php endif; ?>
<div class="meta">
<b><?php echo $title; ?></b>
<a href="?action=view&videoId=<?php echo urlencode($videoId); ?>">View &amp; Download</a>
</div>
</div>
<?php endforeach; ?>
</div>
<!-- Pagination for channel -->
<?php if (isset($searchResults['prevPageToken']) || isset($searchResults['nextPageToken'])): ?>
<div class="pagination">
<?php if (isset($searchResults['prevPageToken'])): ?>
<a href="?action=prev&channelId=<?php echo urlencode($channelId); ?>&pageToken=<?php echo urlencode($searchResults['prevPageToken']); ?>">&laquo; Prev</a>
<?php endif; ?>
<?php if (isset($searchResults['nextPageToken'])): ?>
<a href="?action=next&channelId=<?php echo urlencode($channelId); ?>&pageToken=<?php echo urlencode($searchResults['nextPageToken']); ?>">Next &raquo;</a>
<?php endif; ?>
</div>
<?php endif; ?>
<?php else: ?>
<p class="empty-note">This channel has no public videos.</p>
<?php endif; ?>
<hr>

<?php elseif (!empty($searchResults['items'])): ?>
<!-- Normal search results (unchanged) -->
<div class="results-grid">
<?php foreach ($searchResults['items'] as $video): ?>
<?php
$videoId = $video['id']['videoId'];
$title = htmlspecialchars($video['snippet']['title']);
$channel = htmlspecialchars($video['snippet']['channelTitle']);
$thumb = $video['snippet']['thumbnails']['default']['url'] ?? '';
?>
<div class="result-card">
<?php if ($thumb): ?>
<img src="<?php echo htmlspecialchars($thumb); ?>" width="120" height="90" alt="Thumb">
<?php endif; ?>
<div class="meta">
<b><?php echo $title; ?></b>
<span class="sub"><?php echo $channel; ?> &middot; ID: <?php echo $videoId; ?></span>
<a href="?action=view&videoId=<?php echo urlencode($videoId); ?>">View &amp; Download</a>
</div>
</div>
<?php endforeach; ?>
</div>

<?php if (isset($searchResults['prevPageToken']) || isset($searchResults['nextPageToken'])): ?>
<div class="pagination">
<?php if (isset($searchResults['prevPageToken'])): ?>
<a href="?action=prev&query=<?php echo urlencode($currentQuery); ?>&pageToken=<?php echo urlencode($searchResults['prevPageToken']); ?>">&laquo; Prev</a>
<?php endif; ?>
<?php if (isset($searchResults['nextPageToken'])): ?>
<a href="?action=next&query=<?php echo urlencode($currentQuery); ?>&pageToken=<?php echo urlencode($searchResults['nextPageToken']); ?>">Next &raquo;</a>
<?php endif; ?>
</div>
<?php endif; ?>

<?php elseif ($currentQuery): ?>
<p class="empty-note">No videos found. Try a different search.</p>
<?php endif; ?>

</div>
<script>
</script>
</body>
</html>
