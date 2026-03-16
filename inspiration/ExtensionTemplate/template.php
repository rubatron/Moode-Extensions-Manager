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

$extRouteId = preg_replace('/\.php$/', '', basename((string)($_SERVER['SCRIPT_NAME'] ?? 'template-extension.php')));
if (!is_string($extRouteId) || trim($extRouteId) === '') {
    $extRouteId = 'template-extension';
}
$assetBase = '/extensions/installed/' . $extRouteId;

// Read header visibility from ext-mgr registry
$extMgrHideHeader = true; // default: hide header for extensions
$registryPath = '/var/local/www/extensions/registry.json';
if (file_exists($registryPath)) {
    $registryData = @json_decode(@file_get_contents($registryPath), true);
    if (is_array($registryData)) {
        foreach ($registryData as $ext) {
            if (isset($ext['id']) && $ext['id'] === $extRouteId) {
                // headerVisible true = show header, false = hide header
                $extMgrHideHeader = !($ext['headerVisible'] ?? false);
                break;
            }
        }
    }
}

if (function_exists('storeBackLink')) {
    @storeBackLink($section, $extRouteId);
}

if (file_exists('/var/www/header.php')) {
    $usingMoodeShell = true;
    include '/var/www/header.php';
    // Conditionally hide settings tabs based on extension's headerVisible setting
    if ($extMgrHideHeader) {
        echo '<style id="ext-nav-suppress">#config-tabs{display:none!important}</style>' . "\n";
    }
    echo '<link rel="stylesheet" href="' . htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') . '/assets/css/template.css">' . "\n";
} else {
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Template Extension</title>
    <?php echo '<link rel="stylesheet" href="' . htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') . '/assets/css/template.css">'; ?>
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
    <?php // YOUR CODE HERE: add your minimal extension settings UI controls in this page. ?>

    <section class="ext-template-card">
      <h2 class="ext-template-card-title">Icon Picker (Starter)</h2>
      <p class="config-help-static">Pick an icon class and copy it into info.json "iconClass".</p>
      <div class="ext-template-picker-row">
        <label for="ext-template-icon-picker">Icon</label>
        <select id="ext-template-icon-picker"></select>
      </div>
      <div id="ext-template-icon-value" class="ext-template-code">fa-solid fa-sharp fa-puzzle-piece</div>
      <div class="ext-template-code">YOUR CODE HERE: connect controls to backend/api.php and persist extension settings.</div>
    </section>
  </div>
</div>

<?php echo '<script src="' . htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') . '/assets/js/template.js"></script>'; ?>

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
