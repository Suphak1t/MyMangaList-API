<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
require_once __DIR__ . '/require_auth.php';
require_once __DIR__ . '/manga_storage.php';

$data = json_decode(file_get_contents('php://input'), true);
$chapterId = filter_var(is_array($data) ? ($data['chapter_id'] ?? null) : null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$chapterId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'chapter_id ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare(
    'SELECT chapters.chapter_id FROM chapters JOIN manga ON manga.manga_id = chapters.manga_id
     WHERE chapters.chapter_id = ? AND manga.user_id = ?'
);
$stmt->bind_param('ii', $chapterId, $authenticatedUser['user_id']);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'ไม่พบตอน'], JSON_UNESCAPED_UNICODE);
    exit;
}
$files = [];
$stmt = $conn->prepare('SELECT filename FROM chapter_files WHERE chapter_id = ?');
$stmt->bind_param('i', $chapterId);
$stmt->execute();
foreach ($stmt->get_result() as $row) $files[] = $row['filename'];
$stmt = $conn->prepare('DELETE FROM chapters WHERE chapter_id = ?');
$stmt->bind_param('i', $chapterId);
$stmt->execute();
manga_delete_stored_files($files);
echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
