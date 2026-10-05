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

if ($userId === (int) $authenticatedUser['user_id']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ไม่สามารถลบบัญชีที่กำลังใช้งานได้',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
$stmt->bind_param('i', $userId);

if ($stmt->execute()) {
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
