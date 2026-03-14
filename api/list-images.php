<?php

define('ADMIN_PASSWORD', 'admin2024');

$providedPwd = $_SERVER['HTTP_X_ADMIN_PASSWORD'] ?? '';
if (!hash_equals(ADMIN_PASSWORD, $providedPwd)) {
    http_response_code(401);
    jsonOut(['error' => 'Unauthorized']);
}

$root = realpath(__DIR__ . '/../image');
if ($root === false || !is_dir($root)) {
    http_response_code(500);
    jsonOut(['error' => 'Image directory not found']);
}

$images = [];
$folders = ['image'];
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        continue;
    }

    $absolutePath = $file->getPathname();
    $relativePath = 'image/' . str_replace('\\', '/', substr($absolutePath, strlen($root) + 1));
    $images[] = $relativePath;

    $folder = dirname($relativePath);
    if ($folder && !in_array($folder, $folders, true)) {
        $folders[] = $folder;
    }
}

sort($images, SORT_NATURAL | SORT_FLAG_CASE);
sort($folders, SORT_NATURAL | SORT_FLAG_CASE);

jsonOut([
    'ok' => true,
    'images' => $images,
    'folders' => $folders,
]);

function jsonOut(array $data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}