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
require_once __DIR__ . '/manga_genres.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = [];
$mangaId = filter_var($data['manga_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$title = trim((string) ($data['title'] ?? ''));
$author = trim((string) ($data['author'] ?? ''));
$description = trim((string) ($data['description'] ?? ''));
$status = trim((string) ($data['status'] ?? ''));
$releaseStatus = trim((string) ($data['release_status'] ?? 'ongoing'));
$allowedStatuses = ['private', 'public'];

try {
    $genres = manga_parse_genres(is_array($data) ? $data : []);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!$mangaId || $title === '' || !in_array($status, $allowedStatuses, true) ||
    !in_array($releaseStatus, ['ongoing', 'hiatus', 'completed', 'coming_soon'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ข้อมูล Manga ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
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

$author = $author === '' ? null : $author;
$description = $description === '' ? null : $description;
$genre = $genres[0];
$stmt = $conn->prepare(
    'UPDATE manga SET title = ?, author = ?, description = ?, genre = ?, status = ?, release_status = ?
     WHERE manga_id = ? AND user_id = ?'
);
$stmt->bind_param('ssssssii', $title, $author, $description, $genre, $status, $releaseStatus, $mangaId, $authenticatedUser['user_id']);
try {
    $conn->begin_transaction();
    $stmt->execute();
    manga_save_genres($conn, $mangaId, $genres);
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'แก้ไข Manga ไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['success' => true, 'message' => 'แก้ไข Manga สำเร็จ'], JSON_UNESCAPED_UNICODE);
