<?php
require_once __DIR__ . '/config.php';

$headers = function_exists('getallheaders') ? getallheaders() : [];
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $authorization, $matches)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาเข้าสู่ระบบก่อน',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenHash = hash('sha256', $matches[1]);
$stmt = $conn->prepare(
    'SELECT users.user_id, users.role, users.is_suspended
     FROM api_tokens
     JOIN users ON users.user_id = api_tokens.user_id
     WHERE api_tokens.token_hash = ? AND api_tokens.expires_at > NOW()
     LIMIT 1'
);
$stmt->bind_param('s', $tokenHash);
$stmt->execute();
$authenticatedUser = $stmt->get_result()->fetch_assoc();

if (!$authenticatedUser) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Token ไม่ถูกต้องหรือหมดอายุ',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((int) $authenticatedUser['is_suspended'] === 1) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'บัญชีนี้ถูกพักการใช้งาน',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
