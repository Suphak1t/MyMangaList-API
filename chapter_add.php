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
$number = filter_var($data['chapter_number'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$title = trim((string) ($data['title'] ?? ''));
if (!$mangaId || !$number || mb_strlen($title) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ข้อมูลตอนไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}
$owner = $conn->prepare('SELECT manga_id FROM manga WHERE manga_id = ? AND user_id = ?');
$owner->bind_param('ii', $mangaId, $authenticatedUser['user_id']);
$owner->execute();
if (!$owner->get_result()->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'ไม่พบ Manga'], JSON_UNESCAPED_UNICODE);
    exit;
}
try {
    $title = $title === '' ? null : $title;
    $stmt = $conn->prepare('INSERT INTO chapters (manga_id, chapter_number, title) VALUES (?, ?, ?)');
    $stmt->bind_param('iis', $mangaId, $number, $title);
    if (!$stmt->execute()) {
        http_response_code($stmt->errno === 1062 ? 409 : 500);
        echo json_encode(['success' => false, 'message' => $stmt->errno === 1062 ? 'เลขตอนนี้มีแล้ว' : 'เพิ่มตอนไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true, 'chapter_id' => $stmt->insert_id], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $error) {
    http_response_code($error->getCode() === 1062 ? 409 : 500);
    echo json_encode(['success' => false, 'message' => $error->getCode() === 1062 ? 'เลขตอนนี้มีแล้ว' : 'เพิ่มตอนไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
