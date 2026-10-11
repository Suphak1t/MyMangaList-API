<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/require_auth.php';
require_once __DIR__ . '/manga_storage.php';

$data = json_decode(file_get_contents('php://input'), true);
$mangaId = filter_var(is_array($data) ? ($data['manga_id'] ?? null) : null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$mangaId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'manga_id ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$files = [];
$lookup = $conn->prepare(
    'SELECT chapter_files.filename FROM chapter_files
     JOIN chapters ON chapters.chapter_id = chapter_files.chapter_id
     JOIN manga ON manga.manga_id = chapters.manga_id
     WHERE manga.manga_id = ? AND manga.user_id = ?'
);
$lookup->bind_param('ii', $mangaId, $authenticatedUser['user_id']);
$lookup->execute();
foreach ($lookup->get_result() as $row) $files[] = $row['filename'];
$cover = $conn->prepare('SELECT cover_filename FROM manga WHERE manga_id = ? AND user_id = ?');
$cover->bind_param('ii', $mangaId, $authenticatedUser['user_id']);
$cover->execute();
$coverRow = $cover->get_result()->fetch_assoc();
if ($coverRow && $coverRow['cover_filename']) $files[] = $coverRow['cover_filename'];

$stmt = $conn->prepare('DELETE FROM manga WHERE manga_id = ? AND user_id = ?');
$stmt->bind_param('ii', $mangaId, $authenticatedUser['user_id']);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'ลบ Manga ไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($stmt->affected_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'ไม่พบ Manga'], JSON_UNESCAPED_UNICODE);
    exit;
}
manga_delete_stored_files($files);
echo json_encode(['success' => true, 'message' => 'ลบ Manga สำเร็จ'], JSON_UNESCAPED_UNICODE);
