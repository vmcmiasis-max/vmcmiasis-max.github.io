<?php
// Simple router for built-in PHP server
$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

switch ($uri) {
    case 'main':
        readfile('index.html');
        break;
    case 'prices':
        readfile('prices.html');
        break;
    case 'admin':
        readfile('admin/admin.html');
        break;
    default:
        // Serve static files if they exist
        if ($uri && file_exists($uri)) {
            readfile($uri);
        } else {
            // Fallback: show index.html or 404
            http_response_code(404);
            echo 'Not found';
        }
        break;
}
