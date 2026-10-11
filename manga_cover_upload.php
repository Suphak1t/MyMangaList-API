<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_once __DIR__ . '/require_auth.php';
require_once __DIR__ . '/manga_storage.php';

$mangaId = filter_var($_POST['manga_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$file = $_FILES['cover'] ?? null;
if (!$mangaId || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
    is_array($file['tmp_name'] ?? null) || (int) ($file['size'] ?? 0) < 1 ||
    (int) $file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'เลือกภาพปก JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt = $conn->prepare('SELECT cover_filename FROM manga WHERE manga_id = ? AND user_id = ?');
$stmt->bind_param('ii', $mangaId, $authenticatedUser['user_id']);
$stmt->execute();
$manga = $stmt->get_result()->fetch_assoc();
if (!$manga) { http_response_code(404); exit; }
$tmp = $file['tmp_name'];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$size = @getimagesize($tmp);
if (!isset($extensions[$mime]) || !$size || $size[0] > 4000 || $size[1] > 6000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ภาพปกไม่ถูกต้องหรือใหญ่เกินไป'], JSON_UNESCAPED_UNICODE);
    exit;
}
$directory = manga_storage_dir();
if (!is_dir($directory) && !mkdir($directory, 0700, true)) { http_response_code(500); exit; }
$filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
if (!move_uploaded_file($tmp, $directory . DIRECTORY_SEPARATOR . $filename)) { http_response_code(500); exit; }
try {
    $stmt = $conn->prepare('UPDATE manga SET cover_filename = ? WHERE manga_id = ? AND user_id = ?');
    $stmt->bind_param('sii', $filename, $mangaId, $authenticatedUser['user_id']);
    if (!$stmt->execute() || $stmt->affected_rows === 0) throw new RuntimeException('บันทึกภาพปกไม่สำเร็จ');
    manga_delete_stored_files($manga['cover_filename'] ? [$manga['cover_filename']] : []);
    echo json_encode(['success' => true, 'cover_filename' => $filename], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    manga_delete_stored_files([$filename]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'บันทึกภาพปกไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
