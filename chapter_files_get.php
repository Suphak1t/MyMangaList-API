<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}
require_once __DIR__ . '/require_auth.php';
require_once __DIR__ . '/manga_access.php';

$chapterId = filter_var($_GET['chapter_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$chapterId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'chapter_id ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare(
    "SELECT chapters.content_type, chapters.is_published, chapters.manga_id,
            manga.user_id, manga.status, manga.is_hidden FROM chapters
     JOIN manga ON manga.manga_id = chapters.manga_id
     WHERE chapters.chapter_id = ?"
);
$stmt->bind_param('i', $chapterId);
$stmt->execute();
$chapter = $stmt->get_result()->fetch_assoc();
if (!$chapter || !manga_can_view($conn, $authenticatedUser, (int) $chapter['manga_id'], $chapter) ||
    (!$chapter['is_published'] && $chapter['user_id'] != $authenticatedUser['user_id'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'ไม่พบตอน'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare('SELECT file_id, sort_order, mime_type FROM chapter_files WHERE chapter_id = ? ORDER BY sort_order');
$stmt->bind_param('i', $chapterId);
$stmt->execute();
$files = [];
foreach ($stmt->get_result() as $row) {
    $row['file_id'] = (int) $row['file_id'];
    $row['sort_order'] = (int) $row['sort_order'];
    $files[] = $row;
}
echo json_encode(['success' => true, 'content_type' => $chapter['content_type'], 'files' => $files], JSON_UNESCAPED_UNICODE);
