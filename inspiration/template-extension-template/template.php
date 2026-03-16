<?php

/**
 * Template Extension
 *
 * This is a minimal moOde extension template with proper shell integration.
 * Replace "template-extension" with your extension ID and customize as needed.
 */
header('Content-Type: text/html; charset=utf-8');

$usingMoodeShell = false;
$section = 'extensions';

// Load moOde common includes if available
if (file_exists('/var/www/inc/common.php')) {
  require_once '/var/www/inc/common.php';
}
if (file_exists('/var/www/inc/session.php')) {
  require_once '/var/www/inc/session.php';
}

// Read extension visibility from registry (dynamic header control)
$showHeaderTabs = true;
$registryPath = '/var/www/extensions/registry.json';
if (file_exists($registryPath)) {
  $registry = json_decode(file_get_contents($registryPath), true);
  if (is_array($registry) && isset($registry['extensions'])) {
    foreach ($registry['extensions'] as $ext) {
      // Replace 'template-extension' with your extension ID
      if (($ext['id'] ?? '') === 'template-extension') {
        $showHeaderTabs = !empty($ext['headerVisible']) || !empty($ext['menuVisibility']['header']);
        break;
      }
    }
  }
}

// Include moOde header if available
if (file_exists('/var/www/header.php')) {
  $usingMoodeShell = true;
  include '/var/www/header.php';

  // Dynamic header visibility - only hide if extension is configured to hide header tabs
  if (!$showHeaderTabs) {
    echo '<style id="ext-nav-suppress">#config-tabs{display:none!important}</style>' . "\n";
  }

  // Extension assets
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
      <h1 class="config-title">Template Extension</h1>
      <p class="config-help-static">Template extension page. Customize this file.</p>
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
