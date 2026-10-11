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
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/require_auth.php';
require_once __DIR__ . '/genre_options.php';

$data = json_decode(file_get_contents('php://input'), true);
$title = trim((string) ($data['title'] ?? ''));
$author = trim((string) ($data['author'] ?? ''));
$description = trim((string) ($data['description'] ?? ''));
$genre = trim((string) ($data['genre'] ?? ''));
$status = trim((string) ($data['status'] ?? 'private'));
$allowedStatuses = ['private', 'public'];

if ($title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อเรื่อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($status, $allowedStatuses, true) || !in_array($genre, MANGA_GENRES, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'แนวเรื่องหรือสถานะไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$author = $author === '' ? null : $author;
$description = $description === '' ? null : $description;
$stmt = $conn->prepare(
    'INSERT INTO manga (user_id, title, author, description, genre, status)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('isssss', $authenticatedUser['user_id'], $title, $author, $description, $genre, $status);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'บันทึก Manga ไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'เพิ่ม Manga สำเร็จ',
    'manga_id' => $stmt->insert_id,
], JSON_UNESCAPED_UNICODE);
