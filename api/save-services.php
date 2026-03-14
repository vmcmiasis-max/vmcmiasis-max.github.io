<?php
/**
 * save-services.php
 * Saves the services.json file via POST request.
 * Works on PHP-capable servers (Apache, Nginx+PHP-FPM, etc.)
 * and for local testing environments with PHP.
 *
 * Usage: POST ../api/save-services.php
 *   Body: raw JSON (application/json)
 *   Password sent as header: X-Admin-Password
 */

// ── Security: change this password ────────────────────────────────
define('ADMIN_PASSWORD', 'admin2024');

// ── CORS: allow requests from the same origin only ────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$hostHeader = $_SERVER['HTTP_HOST'] ?? '';

// Only allow same-origin requests or direct calls without Origin header.
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST) ?: '';
    $originPort = parse_url($origin, PHP_URL_PORT);
    $originScheme = strtolower(parse_url($origin, PHP_URL_SCHEME) ?: 'http');

    $serverHost = preg_replace('/:\\d+$/', '', $hostHeader);
    $serverPort = (int)($_SERVER['SERVER_PORT'] ?? ($originScheme === 'https' ? 443 : 80));

    if ($originPort === null) {
        $originPort = ($originScheme === 'https') ? 443 : 80;
    }

    $sameHost = strcasecmp($originHost, $serverHost) === 0;
    $samePort = ((int)$originPort === (int)$serverPort);

    if (!$sameHost || !$samePort) {
        http_response_code(403);
        jsonOut([
            'error' => 'Origin not allowed',
            'debug' => [
                'origin' => $origin,
                'serverHost' => $serverHost,
                'serverPort' => $serverPort,
            ]
        ]);
    }
}

// ── Only accept POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonOut(['error' => 'Method not allowed']);
}

// ── Check password header ─────────────────────────────────────────
$providedPwd = $_SERVER['HTTP_X_ADMIN_PASSWORD'] ?? '';
if (!hash_equals(ADMIN_PASSWORD, $providedPwd)) {
    http_response_code(401);
    jsonOut(['error' => 'Unauthorized']);
}

// ── Read request body ─────────────────────────────────────────────
$body = file_get_contents('php://input');
if ($body === false || $body === '') {
    http_response_code(400);
    jsonOut(['error' => 'Empty request body']);
}

// ── Validate JSON ─────────────────────────────────────────────────
$data = json_decode($body, true);
if ($data === null) {
    http_response_code(400);
    jsonOut(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
}

// ── Validate basic structure ──────────────────────────────────────
if (!isset($data['categories']) || !is_array($data['categories'])) {
    http_response_code(400);
    jsonOut(['error' => 'JSON must have a "categories" array']);
}
foreach ($data['categories'] as $i => $cat) {
    if (!isset($cat['id'], $cat['title'], $cat['services'])) {
        http_response_code(400);
        jsonOut(['error' => "Category $i is missing required fields (id, title, services)"]);
    }
    if (!is_array($cat['services'])) {
        http_response_code(400);
        jsonOut(['error' => "Category $i: services must be an array"]);
    }
}

// ── Resolve target path ───────────────────────────────────────────
$targetFile = realpath(__DIR__ . '/../services.json');
if ($targetFile === false) {
    // File doesn't exist yet — resolve expected path without realpath
    $targetFile = __DIR__ . '/../services.json';
    $targetDir  = dirname($targetFile);
    if (!is_dir($targetDir)) {
        http_response_code(500);
        jsonOut(['error' => 'Target directory does not exist']);
    }
}

// ── Prevent path traversal ────────────────────────────────────────
$siteRoot = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR;
if ($siteRoot !== false && strpos(realpath(dirname($targetFile)) . DIRECTORY_SEPARATOR, $siteRoot) !== 0) {
    http_response_code(403);
    jsonOut(['error' => 'Path traversal detected']);
}

// ── Write atomically (write to temp, then rename) ─────────────────
$pretty  = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tmpFile = $targetFile . '.tmp.' . getmypid();

if (file_put_contents($tmpFile, $pretty, LOCK_EX) === false) {
    http_response_code(500);
    jsonOut(['error' => 'Failed to write temporary file — check server permissions']);
}

if (!rename($tmpFile, $targetFile)) {
    @unlink($tmpFile);
    http_response_code(500);
    jsonOut(['error' => 'Failed to replace services.json — check server permissions']);
}

// ── Success ───────────────────────────────────────────────────────
jsonOut(['ok' => true, 'saved' => date('c')]);

// ── Helper ────────────────────────────────────────────────────────
function jsonOut(array $data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
