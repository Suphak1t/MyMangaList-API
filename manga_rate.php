<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_once __DIR__ . '/require_auth.php';

$data = json_decode(file_get_contents('php://input'), true);
$mangaId = filter_var($data['manga_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$rating = filter_var($data['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
if (!$mangaId || !$rating) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'คะแนนไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare("SELECT user_id FROM manga WHERE manga_id = ? AND status = 'public' AND is_hidden = 0");
$stmt->bind_param('i', $mangaId);
$stmt->execute();
$manga = $stmt->get_result()->fetch_assoc();
if (!$manga || $manga['user_id'] == $authenticatedUser['user_id']) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'ให้คะแนนเรื่องนี้ไม่ได้'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare('INSERT INTO manga_ratings (manga_id, user_id, rating) VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE rating = VALUES(rating)');
$stmt->bind_param('iii', $mangaId, $authenticatedUser['user_id'], $rating);
$stmt->execute();
$stmt = $conn->prepare('SELECT COUNT(*) AS rating_count, ROUND(AVG(rating), 1) AS rating_average
    FROM manga_ratings WHERE manga_id = ?');
$stmt->bind_param('i', $mangaId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
echo json_encode(['success' => true, 'my_rating' => $rating,
    'rating_count' => (int) $result['rating_count'],
    'rating_average' => (float) $result['rating_average']], JSON_UNESCAPED_UNICODE);
