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
$commentId = filter_var(is_array($data) ? ($data['comment_id'] ?? null) : null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$commentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'comment_id ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare('SELECT comments.user_id, manga.user_id AS owner_id
    FROM comments JOIN manga ON manga.manga_id = comments.manga_id WHERE comments.comment_id = ?');
$stmt->bind_param('i', $commentId);
$stmt->execute();
$comment = $stmt->get_result()->fetch_assoc();
if (!$comment || ($comment['user_id'] != $authenticatedUser['user_id'] &&
    $comment['owner_id'] != $authenticatedUser['user_id'] && $authenticatedUser['role'] !== 'admin')) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'ไม่พบความคิดเห็น'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare('DELETE FROM comments WHERE comment_id = ?');
$stmt->bind_param('i', $commentId);
$stmt->execute();
echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
