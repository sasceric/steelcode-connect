<?php

// Optional local-only listener for a primary php -S server started without a
// router. Production uses its ordinary front controller/public HTTPS origin.
// Do not serve arbitrary static files or expose a second general-purpose API.
if (!str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/api/v1/catalogue-media/')) {
    http_response_code(404);
    exit;
}

$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__.'/index.php';
