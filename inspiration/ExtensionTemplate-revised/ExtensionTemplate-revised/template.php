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
    @storeBackLink($section, 'template-extension');
}

if (file_exists('/var/www/header.php')) {
    $usingMoodeShell = true;
    include '/var/www/header.php';
    // ── Settings nav suppression ─────────────────────────────────
    // Selector confirmed from ext-mgr source: moOde settings tabs
    // (#config-tabs) is the only element to hide. Back/home/M are
    // outside #config-tabs and are not affected.
    echo '<style id="ext-nav-suppress">#config-tabs{display:none!important}</style>' . "\n";
    echo '<link rel="stylesheet" href="/extensions/installed/template-extension/assets/css/template.css">' . "\n";
} else {
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Template Extension</title>
    <link rel="stylesheet" href="/extensions/installed/template-extension/assets/css/template.css">
</head>
<body>
    <?php
}
?>

<div id="container">
  <div class="container ext-template-shell">
    <div class="ext-template-header">
      <i id="ext-template-icon" class="fa-solid fa-sharp fa-puzzle-piece" aria-hidden="true"></i>
      <h1 class="config-title">Template Extension</h1>
    </div>
    <p class="config-help-static">Template extension page. The moOde shell (back, home and M menu) is provided via header.php.</p>

    <section class="ext-template-card">
      <h2 class="ext-template-card-title">Icon Picker (Starter)</h2>
      <p class="config-help-static">Pick an icon class and copy it into info.json "iconClass".</p>
      <div class="ext-template-picker-row">
        <label for="ext-template-icon-picker">Icon</label>
        <select id="ext-template-icon-picker"></select>
      </div>
      <div id="ext-template-icon-value" class="ext-template-code">fa-solid fa-sharp fa-puzzle-piece</div>
    </section>
  </div>
</div>

<script src="/extensions/installed/template-extension/assets/js/template.js"></script>

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
