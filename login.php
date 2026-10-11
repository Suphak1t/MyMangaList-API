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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$username = trim((string) ($data['username'] ?? ''));
$password = trim((string) ($data['password'] ?? ''));

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'กรุณากรอก Username และ Password',
    ]);
    exit;
}

require_once __DIR__ . '/config.php';

$stmt = $conn->prepare('SELECT user_id, username, password, role, is_suspended FROM users WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {
    if ((int) $user['is_suspended'] === 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'บัญชีนี้ถูกพักการใช้งาน'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $accessToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $accessToken);
    $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));

    $tokenStmt = $conn->prepare('INSERT INTO api_tokens (token_hash, user_id, expires_at) VALUES (?, ?, ?)');
    $tokenStmt->bind_param('sis', $tokenHash, $user['user_id'], $expiresAt);
    $tokenStmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Login สำเร็จ',
        'access_token' => $accessToken,
        'user' => [
            'user_id' => (int) $user['user_id'],
            'username' => $user['username'],
            'role' => $user['role'],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Username หรือ Password ไม่ถูกต้อง',
], JSON_UNESCAPED_UNICODE);
