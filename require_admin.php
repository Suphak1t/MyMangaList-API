<?php
require_once __DIR__ . '/require_auth.php';

if ($authenticatedUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ไม่มีสิทธิ์ใช้งานส่วนนี้',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
