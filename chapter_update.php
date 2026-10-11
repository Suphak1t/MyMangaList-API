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
$chapterId = filter_var($data['chapter_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$number = filter_var($data['chapter_number'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$title = trim((string) ($data['title'] ?? ''));
$published = $data['is_published'] ?? null;
if (!$chapterId || !$number || mb_strlen($title) > 255 || !is_bool($published)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ข้อมูลตอนไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare(
    'SELECT chapters.content_type FROM chapters
     JOIN manga ON manga.manga_id = chapters.manga_id
     WHERE chapters.chapter_id = ? AND manga.user_id = ?'
);
$stmt->bind_param('ii', $chapterId, $authenticatedUser['user_id']);
$stmt->execute();
$chapter = $stmt->get_result()->fetch_assoc();
if (!$chapter) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'ไม่พบตอน'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($published && $chapter['content_type'] === 'none') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'กรุณาอัปโหลดไฟล์ก่อนเผยแพร่'], JSON_UNESCAPED_UNICODE);
    exit;
}
try {
    $title = $title === '' ? null : $title;
    $value = $published ? 1 : 0;
    $stmt = $conn->prepare('UPDATE chapters SET chapter_number = ?, title = ?, is_published = ? WHERE chapter_id = ?');
    $stmt->bind_param('isii', $number, $title, $value, $chapterId);
    if (!$stmt->execute()) {
        http_response_code($stmt->errno === 1062 ? 409 : 500);
        echo json_encode(['success' => false, 'message' => $stmt->errno === 1062 ? 'เลขตอนนี้มีแล้ว' : 'แก้ไขตอนไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $error) {
    http_response_code($error->getCode() === 1062 ? 409 : 500);
    echo json_encode(['success' => false, 'message' => $error->getCode() === 1062 ? 'เลขตอนนี้มีแล้ว' : 'แก้ไขตอนไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
