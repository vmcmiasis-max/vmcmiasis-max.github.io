<?php

define('ADMIN_PASSWORD', 'admin2024');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonOut(['error' => 'Method not allowed']);
}

$providedPwd = $_SERVER['HTTP_X_ADMIN_PASSWORD'] ?? '';
if (!hash_equals(ADMIN_PASSWORD, $providedPwd)) {
    http_response_code(401);
    jsonOut(['error' => 'Unauthorized']);
}

$body = file_get_contents('php://input');
if ($body === false || $body === '') {
    http_response_code(400);
    jsonOut(['error' => 'Empty request body']);
}

$data = json_decode($body, true);
if (!is_array($data)) {
    http_response_code(400);
    jsonOut(['error' => 'Invalid JSON']);
}

if (!isset($data['hero']) || !is_array($data['hero'])) {
    http_response_code(400);
    jsonOut(['error' => 'Missing hero object']);
}
if (!isset($data['aboutCards']) || !is_array($data['aboutCards'])) {
    http_response_code(400);
    jsonOut(['error' => 'Missing aboutCards array']);
}
if (!isset($data['directions']) || !is_array($data['directions'])) {
    http_response_code(400);
    jsonOut(['error' => 'Missing directions array']);
}
if (!isset($data['contacts']) || !is_array($data['contacts'])) {
    http_response_code(400);
    jsonOut(['error' => 'Missing contacts object']);
}

$targetFile = realpath(__DIR__ . '/../site-content.json');
if ($targetFile === false) {
    $targetFile = __DIR__ . '/../site-content.json';
}

$siteRoot = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR;
$targetDir = realpath(dirname($targetFile));
if ($targetDir === false || strpos($targetDir . DIRECTORY_SEPARATOR, $siteRoot) !== 0) {
    http_response_code(403);
    jsonOut(['error' => 'Path traversal detected']);
}

$pretty = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tmpFile = $targetFile . '.tmp.' . getmypid();

if (file_put_contents($tmpFile, $pretty, LOCK_EX) === false) {
    http_response_code(500);
    jsonOut(['error' => 'Failed to write temporary file']);
}

if (!rename($tmpFile, $targetFile)) {
    @unlink($tmpFile);
    http_response_code(500);
    jsonOut(['error' => 'Failed to replace site-content.json']);
}

jsonOut(['ok' => true, 'saved' => date('c')]);

function jsonOut(array $data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
