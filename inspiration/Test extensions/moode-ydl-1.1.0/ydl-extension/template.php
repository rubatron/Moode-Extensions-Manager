<?php
header('Content-Type: text/html; charset=utf-8');

$usingMoodeShell = false;
$section = 'extensions';

if (file_exists('/var/www/inc/common.php'))  require_once '/var/www/inc/common.php';
if (file_exists('/var/www/inc/session.php')) require_once '/var/www/inc/session.php';

if (function_exists('sqlConnect') && function_exists('phpSession')) {
    @sqlConnect();
    @phpSession('open');
}
if (function_exists('storeBackLink')) {
    @storeBackLink($section, 'moode-ydl');
}

$ROOT    = __DIR__;
$API_URL = 'backend/api.php';

if (file_exists('/var/www/header.php')) {
    $usingMoodeShell = true;
    $API_URL = '/extensions/installed/moode-ydl/backend/api.php';
    include '/var/www/header.php';
    // Settings nav suppression — back / home / M only
    echo '<style id="ext-nav-suppress">
#navbar-settings,.navbar-settings,nav.moode-settings-nav,
#header .nav-tabs,#header ul.nav,#header .navbar-collapse,
#header .navbar-nav,.settings-nav,#topnav .nav{display:none!important}
</style>' . "\n";
    echo '<link rel="stylesheet" href="/extensions/installed/moode-ydl/assets/css/template.css">' . "\n";
} else {
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>YouTube Audioplayback — moOde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/template.css">
</head>
<body data-standalone="true">
<?php } ?>

<div id="container">
<div class="container ext-template-shell">

    <?php if (!$usingMoodeShell): ?>
    <div class="ext-ydl-title">
        <i class="fa-brands fa-youtube"></i>
        <h1>YouTube Audioplayback</h1>
    </div>
    <?php endif; ?>

    <div class="ext-template-header">
        <i class="fa-brands fa-youtube"></i>
        <span class="config-title">YouTube Audioplayback</span>
        <span id="ydl-version-badge" class="ext-ydl-version-badge">checking…</span>
        <span id="ydl-nowplaying-badge" class="ext-ydl-nowplaying-badge" style="display:none"></span>
    </div>

    <!-- ── Single video ──────────────────────────────────────── -->
    <section class="ext-template-card">
        <h2 class="ext-template-card-title">
            <i class="fa-solid fa-circle-play"></i> Single video
        </h2>
        <div class="ext-ydl-input-row">
            <input type="text" id="singleUrl" placeholder="YouTube video URL" autocomplete="off">
            <button id="singleSubmit" class="ext-rp-btn" disabled>
                <i class="fa-solid fa-circle-plus"></i> Add
            </button>
        </div>
        <div class="ext-ydl-radio-row">
            <label><input type="radio" name="plopts" value="add"    checked> add to youtube-playlist</label>
            <label><input type="radio" name="plopts" value="clear">          clear &amp; add</label>
            <label><input type="radio" name="plopts" value="append"><em>     append to MPD queue</em></label>
            <label><input type="radio" name="plopts" value="insert"><em>     insert after current</em></label>
        </div>
    </section>

    <!-- ── Playlist ──────────────────────────────────────────── -->
    <section class="ext-template-card">
        <h2 class="ext-template-card-title">
            <i class="fa-solid fa-list"></i> YouTube playlist
        </h2>
        <div class="ext-ydl-input-row">
            <input type="text" id="plUrl" placeholder="YouTube playlist URL" autocomplete="off">
            <button id="plSubmit" class="ext-rp-btn" disabled>
                <i class="fa-solid fa-magnifying-glass"></i> Fetch
            </button>
        </div>
        <div id="plItems" style="display:none" class="ext-ydl-pl-items">
            <div class="ext-ydl-pl-found">Items: <strong id="plCount">0</strong></div>
            <div id="plList" class="ext-ydl-pl-list"></div>
            <div class="ext-ydl-radio-row" style="margin-top:.5rem">
                <label><input type="radio" name="plplopts" value="add" checked> add to youtube-playlist</label>
                <label><input type="radio" name="plplopts" value="clear">       clear &amp; add</label>
                <label><input type="radio" name="plplopts" value="append"><em>  append to MPD queue</em></label>
                <label><input type="radio" name="plplopts" value="insert"><em>  insert after current</em></label>
            </div>
            <button id="plCreate" class="ext-rp-btn" style="margin-top:.6rem">
                <i class="fa-solid fa-circle-play"></i> Create playlist
            </button>
        </div>
        <!-- Saved playlists -->
        <div class="ext-ydl-saved-row">
            <select id="selectPL" class="ext-ydl-select"></select>
            <button class="ext-ydl-btn-sm" onclick="loadPLentry()">Load</button>
            <button class="ext-ydl-btn-sm" onclick="delPLentry()">Del</button>
            <button class="ext-ydl-btn-sm" onclick="openPLentry()">Open</button>
            <input type="text" id="ytplTitle" placeholder="Title for new entry">
            <button id="savePLentry" class="ext-ydl-btn-sm" disabled onclick="savePLentry()">Save</button>
        </div>
    </section>

    <!-- ── Search ────────────────────────────────────────────── -->
    <section class="ext-template-card">
        <h2 class="ext-template-card-title">
            <i class="fa-solid fa-magnifying-glass"></i> Search YouTube
        </h2>
        <div class="ext-ydl-input-row">
            <input type="text" id="ytQuery" placeholder="Search query" autocomplete="off">
            <button id="ytQuerySubmit" class="ext-rp-btn" disabled>
                <i class="fa-brands fa-youtube"></i> Search
            </button>
        </div>
    </section>

    <!-- ── Activity log ──────────────────────────────────────── -->
    <section class="ext-template-card">
        <h2 class="ext-template-card-title">
            <i class="fa-solid fa-terminal"></i> Activity
        </h2>
        <div class="ext-ydl-log-row">
            <div id="log"    class="ext-ydl-log"></div>
            <div id="mpdlog" class="ext-ydl-log"></div>
        </div>
    </section>

    <!-- ── youtube.m3u ───────────────────────────────────────── -->
    <section class="ext-template-card">
        <h2 class="ext-template-card-title">
            <i class="fa-solid fa-film"></i> youtube.m3u
            <button class="ext-ydl-btn-sm" onclick="loadnplay()" style="margin-left:auto;display:inline-flex;align-items:center;gap:.3em">
                <i class="fa-solid fa-play"></i> Load &amp; play
            </button>
        </h2>
        <div id="playlist" class="ext-ydl-playlist"></div>
    </section>

    <!-- ── Coverart injection info ───────────────────────────── -->
    <section class="ext-template-card ext-ydl-info-card">
        <h2 class="ext-template-card-title">
            <i class="fa-solid fa-video"></i> Coverart video
        </h2>
        <p class="ext-ydl-info-text">
            When a YouTube stream is playing, a
            <strong style="color:var(--accentxts)">Video</strong> toggle appears
            in the coverart area on the main moOde player page.
            Tap it to switch between the YouTube video and the album art.
            The video auto-activates when playback starts.
        </p>
        <div class="ext-ydl-info-row">
            <i class="fa-brands fa-youtube" style="color:var(--accentxts)"></i>
            <span>Requires the Stephanowicz addon injection active in moOde header.php</span>
        </div>
    </section>

</div>
</div>

<script>
var YDL_API  = '<?= htmlspecialchars($usingMoodeShell
    ? '/extensions/installed/moode-ydl/backend/api.php'
    : 'backend/api.php') ?>';
var plArray  = [];
</script>
<script src="<?= $usingMoodeShell
    ? '/extensions/installed/moode-ydl/assets/js/template.js'
    : 'assets/js/template.js' ?>"></script>

<?php if ($usingMoodeShell): ?>
    <?php if (file_exists('/var/www/footer.min.php')): include '/var/www/footer.min.php';
          elseif (file_exists('/var/www/footer.php')): include '/var/www/footer.php';
          else: include '/var/www/inc/footer.php'; endif; ?>
<?php else: ?>
</body></html>
<?php endif; ?>
