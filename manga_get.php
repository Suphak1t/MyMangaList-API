<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/require_auth.php';

$public = ($_GET['scope'] ?? '') === 'public';
$sql = 'SELECT manga.manga_id, manga.user_id, users.username, manga.title,
               manga.author, manga.description, manga.genre, manga.status, manga.is_hidden,
               manga.created_at,
               (SELECT COUNT(*) FROM chapters
                WHERE chapters.manga_id = manga.manga_id AND
                      (manga.user_id = ? OR chapters.is_published = 1)) AS chapter_count
        FROM manga JOIN users ON users.user_id = manga.user_id
        WHERE ' . ($public ? "manga.status = 'public' AND manga.is_hidden = 0" : 'manga.user_id = ?') . '
        ORDER BY manga.created_at DESC, manga.manga_id DESC';
$stmt = $conn->prepare($sql);
if ($public) {
    $stmt->bind_param('i', $authenticatedUser['user_id']);
} else {
    $stmt->bind_param('ii', $authenticatedUser['user_id'], $authenticatedUser['user_id']);
}
$stmt->execute();
$result = $stmt->get_result();
$manga = [];

while ($row = $result->fetch_assoc()) {
    $row['manga_id'] = (int) $row['manga_id'];
    $row['user_id'] = (int) $row['user_id'];
    $row['chapter_count'] = (int) $row['chapter_count'];
    $row['is_hidden'] = (bool) $row['is_hidden'];
    $manga[] = $row;
}

echo json_encode([
    'success' => true,
    'manga' => $manga,
], JSON_UNESCAPED_UNICODE);
