<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
require_once __DIR__ . '/require_admin.php';

$data = json_decode(file_get_contents('php://input'), true);
$userId = filter_var(is_array($data) ? ($data['user_id'] ?? null) : null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$suspended = is_array($data) ? ($data['is_suspended'] ?? null) : null;
if (!$userId || !is_bool($suspended) || $userId === (int) $authenticatedUser['user_id']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'user'");
$stmt->bind_param('i', $userId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'ไม่พบผู้ใช้ที่จัดการได้'], JSON_UNESCAPED_UNICODE);
    exit;
}
$value = $suspended ? 1 : 0;
$conn->begin_transaction();
try {
    $stmt = $conn->prepare('UPDATE users SET is_suspended = ? WHERE user_id = ?');
    $stmt->bind_param('ii', $value, $userId);
    if (!$stmt->execute()) throw new RuntimeException('บันทึกไม่สำเร็จ');
    if ($suspended) {
        $stmt = $conn->prepare('DELETE FROM api_tokens WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) throw new RuntimeException('ยกเลิก token ไม่สำเร็จ');
    }
    $conn->commit();
    echo json_encode(['success' => true, 'message' => $suspended ? 'พักบัญชีแล้ว' : 'ปลดพักบัญชีแล้ว'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'จัดการบัญชีไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
