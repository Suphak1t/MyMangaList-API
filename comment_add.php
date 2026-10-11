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

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = [];
$mangaId = filter_var($data['manga_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$chapterId = isset($data['chapter_id'])
    ? filter_var($data['chapter_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
$body = trim((string) ($data['body'] ?? ''));
if (!$mangaId || (isset($data['chapter_id']) && !$chapterId) || $body === '' || mb_strlen($body) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ความคิดเห็นต้องมี 1–1000 ตัวอักษร'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare('SELECT user_id, status, is_hidden FROM manga WHERE manga_id = ?');
$stmt->bind_param('i', $mangaId);
$stmt->execute();
$manga = $stmt->get_result()->fetch_assoc();
if (!$manga || ($manga['user_id'] != $authenticatedUser['user_id'] &&
    ($manga['status'] !== 'public' || $manga['is_hidden']))) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'ไม่พบ Manga'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($chapterId) {
    $stmt = $conn->prepare('SELECT is_published FROM chapters WHERE chapter_id = ? AND manga_id = ?');
    $stmt->bind_param('ii', $chapterId, $mangaId);
    $stmt->execute();
    $chapter = $stmt->get_result()->fetch_assoc();
    if (!$chapter || (!$chapter['is_published'] && $manga['user_id'] != $authenticatedUser['user_id'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'ไม่พบตอน'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
$stmt = $conn->prepare('INSERT INTO comments (manga_id, chapter_id, user_id, body) VALUES (?, ?, ?, ?)');
$stmt->bind_param('iiis', $mangaId, $chapterId, $authenticatedUser['user_id'], $body);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'บันทึกความคิดเห็นไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['success' => true, 'comment_id' => $stmt->insert_id], JSON_UNESCAPED_UNICODE);
