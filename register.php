<?php
// Register API สำหรับ MyMangaList
// Flutter ส่ง username, password, confirm_password
// PHP ตรวจสอบข้อมูล และบันทึกลง users table

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
$username = trim((string) ($data['username'] ?? ''));
$password = trim((string) ($data['password'] ?? ''));
$confirmPassword = trim((string) ($data['confirm_password'] ?? ''));

if ($username === '' || $password === '' || $confirmPassword === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน',
    ]);
    exit;
}

if (strpos($username, ' ') !== false) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username ต้องไม่มีช่องว่าง',
    ]);
    exit;
}

if ($password !== $confirmPassword) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Password กับ Confirm Password ต้องตรงกัน',
    ]);
    exit;
}

require_once __DIR__ . '/config.php';

$checkSql = 'SELECT user_id FROM users WHERE username = ? LIMIT 1';
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param('s', $username);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Username นี้มีคนใช้งานแล้ว',
    ]);
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$insertSql = 'INSERT INTO users (username, password) VALUES (?, ?)';
$insertStmt = $conn->prepare($insertSql);
$insertStmt->bind_param('ss', $username, $hashedPassword);

if ($insertStmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'สมัครสมาชิกสำเร็จ',
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'สมัครสมาชิกไม่สำเร็จ',
]);
