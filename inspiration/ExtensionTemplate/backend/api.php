<?php
header('Content-Type: application/json; charset=utf-8');

// YOUR CODE HERE: implement extension-specific API actions and persistence.
echo json_encode([
    'ok' => true,
    'extension' => 'template-extension',
    'message' => 'Starter backend endpoint is reachable.',
], JSON_UNESCAPED_SLASHES);
