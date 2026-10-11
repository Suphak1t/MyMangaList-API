<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_once __DIR__ . '/require_auth.php';

$data = json_decode(file_get_contents('php://input'), true);
$chapterId = filter_var($data['chapter_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$chapterId) { http_response_code(400); exit; }
$stmt = $conn->prepare("SELECT manga.manga_id, manga.user_id FROM chapters
    JOIN manga ON manga.manga_id = chapters.manga_id
    WHERE chapters.chapter_id = ? AND
    (manga.user_id = ? OR (chapters.is_published = 1 AND manga.status = 'public' AND manga.is_hidden = 0))");
$stmt->bind_param('ii', $chapterId, $authenticatedUser['user_id']);
$stmt->execute();
$manga = $stmt->get_result()->fetch_assoc();
if (!$manga) { http_response_code(404); exit; }
if ($manga['user_id'] != $authenticatedUser['user_id']) {
    $stmt = $conn->prepare('INSERT IGNORE INTO manga_views (manga_id, user_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $manga['manga_id'], $authenticatedUser['user_id']);
    $stmt->execute();
}
echo json_encode(['success' => true]);
