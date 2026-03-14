<?php

define('ADMIN_PASSWORD', 'admin2024');

$providedPwd = $_SERVER['HTTP_X_ADMIN_PASSWORD'] ?? '';
if (!hash_equals(ADMIN_PASSWORD, $providedPwd)) {
    http_response_code(401);
    jsonOut(['error' => 'Unauthorized']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonOut(['error' => 'Method not allowed']);
}

if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
    http_response_code(400);
    jsonOut(['error' => 'No uploaded file']);
}

$imageRoot = realpath(__DIR__ . '/../image');
if ($imageRoot === false || !is_dir($imageRoot)) {
    http_response_code(500);
    jsonOut(['error' => 'Image directory not found']);
}

$folder = trim((string)($_POST['folder'] ?? 'image'));
$folder = str_replace('\\', '/', $folder);
$folder = preg_replace('#/+#', '/', $folder);
$folder = trim($folder, '/');
if ($folder === '') {
    $folder = 'image';
}
if (strpos($folder, 'image') !== 0) {
    $folder = 'image';
}

$targetDir = realpath(__DIR__ . '/../' . $folder);
if ($targetDir === false) {
    $targetDir = __DIR__ . '/../' . $folder;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
        http_response_code(500);
        jsonOut(['error' => 'Failed to create target directory']);
    }
    $targetDir = realpath($targetDir);
}

if ($targetDir === false || strpos(str_replace('\\', '/', $targetDir), str_replace('\\', '/', $imageRoot)) !== 0) {
    http_response_code(400);
    jsonOut(['error' => 'Invalid target directory']);
}

$originalName = (string)$_FILES['image']['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
if (!in_array($extension, $allowed, true)) {
    http_response_code(400);
    jsonOut(['error' => 'Unsupported file type']);
}

$baseName = pathinfo($originalName, PATHINFO_FILENAME);
$baseName = preg_replace('/[^\p{L}\p{N}_\-\s]/u', '', $baseName);
$baseName = preg_replace('/\s+/u', '_', trim($baseName));
if ($baseName === '') {
    $baseName = 'image';
}

$fileName = $baseName . '.' . $extension;
$targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
$counter = 1;
while (file_exists($targetPath)) {
    $fileName = $baseName . '_' . $counter . '.' . $extension;
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
    $counter++;
}

if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
    http_response_code(500);
    jsonOut(['error' => 'Failed to move uploaded file']);
}

$relativePath = str_replace('\\', '/', substr($targetPath, strlen(realpath(__DIR__ . '/..')) + 1));

jsonOut([
    'ok' => true,
    'path' => $relativePath,
]);

function jsonOut(array $data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}