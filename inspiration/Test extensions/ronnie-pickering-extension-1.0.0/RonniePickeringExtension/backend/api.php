<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'extension' => 'template-extension',
    'message' => 'Starter backend endpoint is reachable.',
], JSON_UNESCAPED_SLASHES);
