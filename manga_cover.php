<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit; }
require_once __DIR__ . '/require_auth.php';
require_once __DIR__ . '/manga_access.php';
require_once __DIR__ . '/manga_storage.php';

$mangaId = filter_var($_GET['manga_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$mangaId) { http_response_code(400); exit; }
$stmt = $conn->prepare('SELECT user_id, status, is_hidden, cover_filename FROM manga WHERE manga_id = ?');
$stmt->bind_param('i', $mangaId);
$stmt->execute();
$manga = $stmt->get_result()->fetch_assoc();
if (!$manga || !$manga['cover_filename'] || !manga_can_view($conn, $authenticatedUser, $mangaId, $manga) ||
    !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $manga['cover_filename'])) {
    http_response_code(404);
    exit;
}
$path = manga_storage_dir() . DIRECTORY_SEPARATOR . $manga['cover_filename'];
if (!is_file($path)) { http_response_code(404); exit; }
$extension = pathinfo($path, PATHINFO_EXTENSION);
$mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$extension];
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
