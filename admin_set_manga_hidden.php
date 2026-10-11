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
$mangaId = filter_var(is_array($data) ? ($data['manga_id'] ?? null) : null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$hidden = is_array($data) ? ($data['is_hidden'] ?? null) : null;
if (!$mangaId || !is_bool($hidden)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ข้อมูล Manga ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}
$value = $hidden ? 1 : 0;
$stmt = $conn->prepare('UPDATE manga SET is_hidden = ? WHERE manga_id = ?');
$stmt->bind_param('ii', $value, $mangaId);
if (!$stmt->execute() || $stmt->affected_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'ไม่พบ Manga หรือสถานะไม่เปลี่ยน'], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['success' => true, 'message' => $hidden ? 'ซ่อน Manga แล้ว' : 'คืนการแสดง Manga แล้ว'], JSON_UNESCAPED_UNICODE);
