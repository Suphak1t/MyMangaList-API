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
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/require_auth.php';
require_once __DIR__ . '/manga_genres.php';

$data = json_decode(file_get_contents('php://input'), true);
$title = trim((string) ($data['title'] ?? ''));
$author = trim((string) ($data['author'] ?? ''));
$description = trim((string) ($data['description'] ?? ''));
$status = trim((string) ($data['status'] ?? 'private'));
$releaseStatus = trim((string) ($data['release_status'] ?? 'ongoing'));
$allowedStatuses = ['private', 'public'];

if ($title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อเรื่อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $genres = manga_parse_genres(is_array($data) ? $data : []);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!in_array($status, $allowedStatuses, true) ||
    !in_array($releaseStatus, ['ongoing', 'coming_soon'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'แนวเรื่องหรือสถานะไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$author = $author === '' ? null : $author;
$description = $description === '' ? null : $description;
$genre = $genres[0];
$stmt = $conn->prepare(
    'INSERT INTO manga (user_id, title, author, description, genre, status, release_status)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('issssss', $authenticatedUser['user_id'], $title, $author, $description, $genre, $status, $releaseStatus);

try {
    $conn->begin_transaction();
    $stmt->execute();
    $mangaId = (int) $stmt->insert_id;
    manga_save_genres($conn, $mangaId, $genres);
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'บันทึก Manga ไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'เพิ่ม Manga สำเร็จ',
    'manga_id' => $mangaId,
], JSON_UNESCAPED_UNICODE);
