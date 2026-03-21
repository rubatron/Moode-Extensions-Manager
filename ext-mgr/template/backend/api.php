<?php
/**
 * Extension backend API endpoint
 *
 * Use the ext-mgr broker API for safe moOde operations.
 * This avoids database locks and uses proper moOde APIs.
 *
 * Example broker calls:
 *   GET /ext-mgr-api.php?action=moode_radio&cmd=list
 *   GET /ext-mgr-api.php?action=moode_playback&cmd=play
 *   GET /ext-mgr-api.php?action=variables&scope=system
 *
 * See /extensions/sys/docs/API.md for full broker API reference.
 */

header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? 'status';

switch ($action) {
    case 'status':
        // Get system variables including boot_config feature status
        $response = @file_get_contents('http://localhost/ext-mgr-api.php?action=variables&scope=system');
        $vars = $response ? json_decode($response, true) : null;
        echo json_encode([
            'ok' => true,
            'extension' => 'template-extension',
            'message' => 'Backend endpoint is reachable.',
            'bootConfigEnabled' => $vars['data']['system']['features']['bootConfig']['enabled'] ?? false,
        ], JSON_UNESCAPED_SLASHES);
        break;

    case 'get_boot_config_status':
        // Check boot_config status via broker API
        $response = @file_get_contents('http://localhost/ext-mgr-api.php?action=boot_config_status');
        echo $response ?: json_encode(['ok' => false, 'error' => 'Failed to check boot_config status']);
        break;

    // YOUR CODE HERE: Add your extension-specific actions
    // Use the broker API pattern above for moOde operations

    default:
        echo json_encode([
            'ok' => false,
            'error' => 'Unknown action: ' . $action,
        ], JSON_UNESCAPED_SLASHES);
}
