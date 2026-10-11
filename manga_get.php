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
               manga.author, manga.description, manga.genre, manga.status,
               manga.release_status, manga.cover_filename, manga.is_hidden,
               manga.created_at,
               (SELECT GROUP_CONCAT(genre ORDER BY genre SEPARATOR ",") FROM manga_genres
                WHERE manga_genres.manga_id = manga.manga_id) AS genres_text,
               (SELECT COUNT(*) FROM chapters
                WHERE chapters.manga_id = manga.manga_id AND
                      (manga.user_id = ? OR chapters.is_published = 1)) AS chapter_count,
               (SELECT COUNT(*) FROM manga_views WHERE manga_views.manga_id = manga.manga_id) AS view_count,
               (SELECT COUNT(*) FROM manga_ratings WHERE manga_ratings.manga_id = manga.manga_id) AS rating_count,
               (SELECT COALESCE(AVG(rating), 0) FROM manga_ratings
                WHERE manga_ratings.manga_id = manga.manga_id) AS rating_average,
               (SELECT rating FROM manga_ratings WHERE manga_ratings.manga_id = manga.manga_id
                AND manga_ratings.user_id = ?) AS my_rating
        FROM manga JOIN users ON users.user_id = manga.user_id
        WHERE ' . ($public ? "manga.status = 'public' AND manga.is_hidden = 0" : 'manga.user_id = ?') . '
        ORDER BY manga.created_at DESC, manga.manga_id DESC';
$stmt = $conn->prepare($sql);
if ($public) {
    $stmt->bind_param('ii', $authenticatedUser['user_id'], $authenticatedUser['user_id']);
} else {
    $stmt->bind_param('iii', $authenticatedUser['user_id'], $authenticatedUser['user_id'], $authenticatedUser['user_id']);
}
$stmt->execute();
$result = $stmt->get_result();
$manga = [];

while ($row = $result->fetch_assoc()) {
    $row['manga_id'] = (int) $row['manga_id'];
    $row['user_id'] = (int) $row['user_id'];
    $row['chapter_count'] = (int) $row['chapter_count'];
    $row['view_count'] = (int) $row['view_count'];
    $row['rating_count'] = (int) $row['rating_count'];
    $row['rating_average'] = round((float) $row['rating_average'], 1);
    $row['my_rating'] = $row['my_rating'] === null ? null : (int) $row['my_rating'];
    $row['genres'] = $row['genres_text'] ? explode(',', $row['genres_text']) : ($row['genre'] ? [$row['genre']] : []);
    unset($row['genres_text']);
    $row['is_hidden'] = (bool) $row['is_hidden'];
    $manga[] = $row;
}

echo json_encode([
    'success' => true,
    'manga' => $manga,
], JSON_UNESCAPED_UNICODE);
