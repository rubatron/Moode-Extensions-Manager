<?php
/*
 * ext-mgr shell bridge
 *
 * This bridge is included from moOde shell files (header/footer) and only
 * injects ext-mgr frontend wiring. It must not mutate native modal behavior.
 */

if (defined('EXT_MGR_SHELL_BRIDGE_LOADED')) {
    return;
}
define('EXT_MGR_SHELL_BRIDGE_LOADED', true);

echo '<script src="/extensions/sys/assets/js/ext-mgr-hover-menu.js" defer></script>' . "\n";
