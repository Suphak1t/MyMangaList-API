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
require_once __DIR__ . '/require_admin.php';

$result = $conn->query(
    "SELECT manga_reports.report_id, manga_reports.manga_id,
            manga_reports.uploader_user_id, manga_reports.manga_title,
            manga_reports.uploader_username, manga_reports.reporter_username,
            manga_reports.reason, manga_reports.status, manga_reports.action,
            manga_reports.created_at, manga_reports.reviewed_at,
            reviewer.username AS reviewer_username,
            manga.title AS current_title, manga.author, manga.description,
            manga.genre, manga.release_status, manga.cover_filename,
            manga.status AS manga_status,
            manga.user_id AS manga_owner_id, manga.created_at AS manga_created_at,
            (SELECT GROUP_CONCAT(genre ORDER BY genre SEPARATOR ',') FROM manga_genres
             WHERE manga_genres.manga_id = manga.manga_id) AS genres_text,
            (SELECT COUNT(*) FROM chapters WHERE chapters.manga_id = manga.manga_id
             AND chapters.is_published = 1) AS chapter_count,
            (SELECT COUNT(*) FROM manga_views WHERE manga_views.manga_id = manga.manga_id) AS view_count,
            (SELECT COUNT(*) FROM manga_ratings WHERE manga_ratings.manga_id = manga.manga_id) AS rating_count,
            (SELECT COALESCE(AVG(rating), 0) FROM manga_ratings
             WHERE manga_ratings.manga_id = manga.manga_id) AS rating_average,
            COALESCE(manga.is_hidden, 0) AS is_hidden
     FROM manga_reports LEFT JOIN manga ON manga.manga_id = manga_reports.manga_id
     LEFT JOIN users AS reviewer ON reviewer.user_id = manga_reports.reviewed_by
     ORDER BY (manga_reports.status = 'pending') DESC,
              manga_reports.created_at DESC, manga_reports.report_id DESC"
);
$reports = [];
foreach ($result as $row) {
    $row['report_id'] = (int) $row['report_id'];
    $row['manga_id'] = $row['manga_id'] === null ? null : (int) $row['manga_id'];
    $row['uploader_user_id'] = $row['uploader_user_id'] === null ? null : (int) $row['uploader_user_id'];
    $row['manga_owner_id'] = $row['manga_owner_id'] === null ? null : (int) $row['manga_owner_id'];
    $row['genres'] = $row['genres_text'] ? explode(',', $row['genres_text']) : ($row['genre'] ? [$row['genre']] : []);
    unset($row['genres_text']);
    $row['chapter_count'] = (int) $row['chapter_count'];
    $row['view_count'] = (int) $row['view_count'];
    $row['rating_count'] = (int) $row['rating_count'];
    $row['rating_average'] = round((float) $row['rating_average'], 1);
    $row['is_hidden'] = (bool) $row['is_hidden'];
    $reports[] = $row;
}
echo json_encode(['success' => true, 'reports' => $reports], JSON_UNESCAPED_UNICODE);
