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

$mangaId = filter_var($_GET['manga_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$mangaId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'manga_id ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare('SELECT user_id, status, is_hidden FROM manga WHERE manga_id = ?');
$stmt->bind_param('i', $mangaId);
$stmt->execute();
$manga = $stmt->get_result()->fetch_assoc();
if (!$manga || !manga_can_view($conn, $authenticatedUser, $mangaId, $manga)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'ไม่พบ Manga'], JSON_UNESCAPED_UNICODE);
    exit;
}
$isOwner = $manga['user_id'] == $authenticatedUser['user_id'];
$sql = 'SELECT chapter_id, chapter_number, title, is_published, content_type, created_at
        FROM chapters WHERE manga_id = ?' . ($isOwner ? '' : ' AND is_published = 1') . '
        ORDER BY chapter_number ASC';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $mangaId);
$stmt->execute();
$chapters = [];
foreach ($stmt->get_result() as $row) {
    $row['chapter_id'] = (int) $row['chapter_id'];
    $row['chapter_number'] = (int) $row['chapter_number'];
    $row['is_published'] = (bool) $row['is_published'];
    $chapters[] = $row;
}
echo json_encode(['success' => true, 'chapters' => $chapters], JSON_UNESCAPED_UNICODE);
