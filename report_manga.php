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
require_once __DIR__ . '/require_auth.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = [];
$mangaId = filter_var($data['manga_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$reason = trim((string) ($data['reason'] ?? ''));
if (!$mangaId || mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'กรุณาระบุเหตุผล 10–1000 ตัวอักษร'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare(
    "SELECT manga.user_id, manga.title, users.username
     FROM manga JOIN users ON users.user_id = manga.user_id
     WHERE manga.manga_id = ? AND manga.status = 'public' AND manga.is_hidden = 0"
);
$stmt->bind_param('i', $mangaId);
$stmt->execute();
$manga = $stmt->get_result()->fetch_assoc();
if (!$manga || $manga['user_id'] == $authenticatedUser['user_id']) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'ไม่พบ Manga ที่รีพอร์ตได้'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare("SELECT report_id FROM manga_reports WHERE manga_id = ? AND reporter_user_id = ? AND status = 'pending'");
$stmt->bind_param('ii', $mangaId, $authenticatedUser['user_id']);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'คุณรีพอร์ตเรื่องนี้ไว้แล้วและกำลังรอตรวจ'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare('SELECT username FROM users WHERE user_id = ?');
$stmt->bind_param('i', $authenticatedUser['user_id']);
$stmt->execute();
$reporter = $stmt->get_result()->fetch_assoc();
$stmt = $conn->prepare(
    'INSERT INTO manga_reports (manga_id, uploader_user_id, reporter_user_id,
     manga_title, uploader_username, reporter_username, reason)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('iiissss', $mangaId, $manga['user_id'], $authenticatedUser['user_id'],
    $manga['title'], $manga['username'], $reporter['username'], $reason);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'ส่งรีพอร์ตไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['success' => true, 'message' => 'ส่งรีพอร์ตให้ผู้ดูแลแล้ว'], JSON_UNESCAPED_UNICODE);
