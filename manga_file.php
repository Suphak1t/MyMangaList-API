<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

require_once __DIR__ . '/require_auth.php';
require_once __DIR__ . '/manga_storage.php';
require_once __DIR__ . '/manga_access.php';
$fileId = filter_var($_GET['file_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$fileId) {
    http_response_code(400);
    exit;
}
$stmt = $conn->prepare(
    "SELECT chapter_files.filename, chapter_files.mime_type,
            chapters.manga_id, chapters.is_published,
            manga.user_id, manga.status, manga.is_hidden
     FROM chapter_files
     JOIN chapters ON chapters.chapter_id = chapter_files.chapter_id
     JOIN manga ON manga.manga_id = chapters.manga_id
     WHERE chapter_files.file_id = ?"
);
$stmt->bind_param('i', $fileId);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
if (!$file || !manga_can_view($conn, $authenticatedUser, (int) $file['manga_id'], $file) ||
    (!$file['is_published'] && $file['user_id'] != $authenticatedUser['user_id']) ||
    !preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png|webp)$/', $file['filename'])) {
    http_response_code(404);
    exit;
}
$path = manga_storage_dir() . DIRECTORY_SEPARATOR . $file['filename'];
if (!is_file($path)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $file['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');
readfile($path);
