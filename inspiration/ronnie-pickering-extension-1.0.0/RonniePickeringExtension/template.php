<?php
header('Content-Type: text/html; charset=utf-8');

$usingMoodeShell = false;
$section = 'extensions';

if (file_exists('/var/www/inc/common.php')) {
    require_once '/var/www/inc/common.php';
}
if (file_exists('/var/www/inc/session.php')) {
    require_once '/var/www/inc/session.php';
}

if (function_exists('sqlConnect') && function_exists('phpSession')) {
    @sqlConnect();
    @phpSession('open');
}

if (function_exists('storeBackLink')) {
    @storeBackLink($section, 'ronnie-pickering-extension');
}

$videoId   = 'r0dcv6GKNNw';
$embedUrl  = 'https://www.youtube.com/embed/' . $videoId;
$iframeSrc = htmlspecialchars($embedUrl) . '?rel=0&modestbranding=1&color=white';
$embedCode = htmlspecialchars(
    '<iframe src="' . $embedUrl . '?rel=0&modestbranding=1" ' .
    'title="YouTube video" ' .
    'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" ' .
    'allowfullscreen></iframe>'
);

if (file_exists('/var/www/header.php')) {
    $usingMoodeShell = true;
    include '/var/www/header.php';
    // ── Settings nav suppression ──────────────────────────────────
    // Extension detail pages must only show back (<), home and M buttons.
    // The settings nav (Library/Audio/Network/…) belongs to moOde top-level
    // pages only and must not appear inside extension views.
    //
    // Primary:  hide via CSS — works across all moOde versions
    // Failsafe: data attribute signals intent to any future JS layer
    // ── Settings nav suppression ─────────────────────────────────
    // moOde's settings tabs live in #config-tabs (confirmed via ext-mgr source).
    // Hiding this element removes Library|Audio|Network|System|Renderers|…
    // while leaving the back (<), home and M buttons untouched — they are
    // rendered outside #config-tabs by header.php.
    echo '<style id="ext-nav-suppress">#config-tabs{display:none!important}</style>' . "\n";
    echo '<link rel="stylesheet" href="/extensions/installed/ronnie-pickering-extension/assets/css/template.css">' . "\n";
} else {
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ronnie Pickering's Extension</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/template.css">
</head>
<body data-standalone="true">
<?php
}
?>

<div id="container">
  <div class="container ext-template-shell">

    <?php if (!$usingMoodeShell): ?>
    <!-- Standalone only: branded title (hidden in moOde shell) -->
    <div class="ext-rp-page-title">
      <div class="ext-rp-title-text">
        <i class="fa-brands fa-youtube ext-rp-yt-icon" aria-hidden="true"></i>
        <h1 class="ext-rp-h1">Ronnie Pickering's Extension</h1>
      </div>
      <span class="ext-rp-badge">YouTube</span>
    </div>
    <?php endif; ?>

    <!-- Standard ext-template-header (moOde shell mode) -->
    <div class="ext-template-header">
      <i class="fa-brands fa-youtube" aria-hidden="true"></i>
      <span class="config-title">Ronnie Pickering's Extension</span>
    </div>

    <section class="ext-template-card ext-rp-video-card">
      <h2 class="ext-template-card-title">Now Playing</h2>
      <div class="ext-rp-video-wrapper">
        <div class="ext-rp-video-glow"></div>
        <iframe
          src="<?php echo $iframeSrc; ?>"
          title="Ronnie Pickering – YouTube video"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          allowfullscreen>
        </iframe>
      </div>
    </section>

    <section class="ext-template-card ext-rp-meta-card">
      <h2 class="ext-template-card-title">Video Info</h2>
      <div class="ext-template-picker-row">
        <span class="ext-rp-label">Video ID</span>
        <span class="ext-rp-value ext-rp-mono"><?php echo htmlspecialchars($videoId); ?></span>
      </div>
      <div class="ext-template-picker-row">
        <span class="ext-rp-label">Platform</span>
        <span class="ext-rp-value"><i class="fa-brands fa-youtube" style="color:var(--accentxts);margin-right:0.35rem;"></i>YouTube</span>
      </div>
      <div class="ext-template-picker-row">
        <span class="ext-rp-label">Aspect ratio</span>
        <span class="ext-rp-value">16 : 9</span>
      </div>
      <div class="ext-template-picker-row">
        <span class="ext-rp-label">Privacy</span>
        <span class="ext-rp-value ext-rp-pill">No related videos</span>
      </div>
    </section>

    <section class="ext-template-card ext-rp-code-card">
      <h2 class="ext-template-card-title">Embed Code</h2>
      <div id="ext-rp-embed-code" class="ext-template-code ext-rp-code-block"><?php echo $embedCode; ?></div>
      <div class="ext-rp-btn-row">
        <button id="ext-rp-copy-btn" class="ext-rp-btn" type="button">
          <i class="fa-regular fa-copy"></i>
          <span>Copy embed</span>
        </button>
        <a href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($videoId); ?>"
           target="_blank" rel="noopener noreferrer" class="ext-rp-btn ext-rp-btn-ghost">
          <i class="fa-solid fa-arrow-up-right-from-square"></i>
          <span>Open on YouTube</span>
        </a>
      </div>
    </section>

  </div>
</div>

<script src="<?php echo $usingMoodeShell
    ? '/extensions/installed/ronnie-pickering-extension/assets/js/template.js'
    : 'assets/js/template.js'; ?>"></script>

<?php
if ($usingMoodeShell) {
    if (file_exists('/var/www/footer.min.php')) {
        include '/var/www/footer.min.php';
    } elseif (file_exists('/var/www/footer.php')) {
        include '/var/www/footer.php';
    } else {
        include '/var/www/inc/footer.php';
    }
} else {
?>
</body>
</html>
<?php
}
