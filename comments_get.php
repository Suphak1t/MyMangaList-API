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
$chapterId = isset($_GET['chapter_id'])
    ? filter_var($_GET['chapter_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
if (!$mangaId || (isset($_GET['chapter_id']) && !$chapterId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
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
$sql = 'SELECT comments.comment_id, comments.user_id, users.username, comments.body, comments.created_at
        FROM comments JOIN users ON users.user_id = comments.user_id
        WHERE comments.manga_id = ? AND comments.chapter_id ' . ($chapterId ? '= ?' : 'IS NULL') . '
        ORDER BY comments.created_at ASC, comments.comment_id ASC';
$stmt = $conn->prepare($sql);
if ($chapterId) $stmt->bind_param('ii', $mangaId, $chapterId);
else $stmt->bind_param('i', $mangaId);
$stmt->execute();
$comments = [];
foreach ($stmt->get_result() as $row) {
    $row['comment_id'] = (int) $row['comment_id'];
    $row['user_id'] = (int) $row['user_id'];
    $row['can_delete'] = $row['user_id'] == $authenticatedUser['user_id'] ||
        $manga['user_id'] == $authenticatedUser['user_id'] || $authenticatedUser['role'] === 'admin';
    $comments[] = $row;
}
echo json_encode(['success' => true, 'comments' => $comments], JSON_UNESCAPED_UNICODE);
