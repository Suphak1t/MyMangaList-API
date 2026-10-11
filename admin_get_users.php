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
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed',
    ]);
    exit;
}

require_once __DIR__ . '/require_admin.php';

$sql = "SELECT users.user_id, users.username, users.role, users.is_suspended, users.created_at,
               (SELECT COUNT(*) FROM manga WHERE manga.user_id = users.user_id) AS manga_count,
               (SELECT COUNT(*) FROM comments WHERE comments.user_id = users.user_id) AS comment_count,
               (SELECT COUNT(*) FROM manga_reports
                WHERE manga_reports.uploader_user_id = users.user_id
                  AND manga_reports.status = 'pending') AS pending_report_count
        FROM users ORDER BY users.user_id DESC";
$result = $conn->query($sql);

$users = [];
while ($row = $result->fetch_assoc()) {
    $row['user_id'] = (int) $row['user_id'];
    $row['is_suspended'] = (bool) $row['is_suspended'];
    $row['manga_count'] = (int) $row['manga_count'];
    $row['comment_count'] = (int) $row['comment_count'];
    $row['pending_report_count'] = (int) $row['pending_report_count'];
    $users[] = $row;
}

echo json_encode([
    'success' => true,
    'message' => 'ดึงข้อมูลผู้ใช้สำเร็จ',
    'users' => $users,
], JSON_UNESCAPED_UNICODE);
