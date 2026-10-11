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
            manga.genre, manga.status AS manga_status,
            manga.user_id AS manga_owner_id, manga.created_at AS manga_created_at,
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
    $row['is_hidden'] = (bool) $row['is_hidden'];
    $reports[] = $row;
}
echo json_encode(['success' => true, 'reports' => $reports], JSON_UNESCAPED_UNICODE);
