<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed',
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง',
    ]);
    exit;
}

require_once __DIR__ . '/require_admin.php';
require_once __DIR__ . '/manga_storage.php';

if ($userId === (int) $authenticatedUser['user_id']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ไม่สามารถลบบัญชีที่กำลังใช้งานได้',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$target = $conn->prepare('SELECT role FROM users WHERE user_id = ?');
$target->bind_param('i', $userId);
$target->execute();
$targetUser = $target->get_result()->fetch_assoc();
if (!$targetUser || $targetUser['role'] === 'admin') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ลบผู้ใช้นี้ไม่ได้'], JSON_UNESCAPED_UNICODE);
    exit;
}

$files = [];
$lookup = $conn->prepare(
    'SELECT chapter_files.filename FROM chapter_files
     JOIN chapters ON chapters.chapter_id = chapter_files.chapter_id
     JOIN manga ON manga.manga_id = chapters.manga_id
     WHERE manga.user_id = ?'
);
$lookup->bind_param('i', $userId);
$lookup->execute();
foreach ($lookup->get_result() as $row) $files[] = $row['filename'];
$covers = $conn->prepare('SELECT cover_filename FROM manga WHERE user_id = ? AND cover_filename IS NOT NULL');
$covers->bind_param('i', $userId);
$covers->execute();
foreach ($covers->get_result() as $row) $files[] = $row['cover_filename'];

$stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
$stmt->bind_param('i', $userId);

if ($stmt->execute()) {
    manga_delete_stored_files($files);
    echo json_encode([
        'success' => true,
        'message' => 'ลบผู้ใช้สำเร็จ',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'ลบผู้ใช้ไม่สำเร็จ',
], JSON_UNESCAPED_UNICODE);
