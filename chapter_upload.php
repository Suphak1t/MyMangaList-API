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
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/require_auth.php';
require_once __DIR__ . '/manga_storage.php';

$chapterId = filter_var($_POST['chapter_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$type = (string) ($_POST['content_type'] ?? '');
if (!$chapterId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'chapter_id ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$owner = $conn->prepare(
    'SELECT chapters.chapter_id FROM chapters JOIN manga ON manga.manga_id = chapters.manga_id
     WHERE chapters.chapter_id = ? AND manga.user_id = ?'
);
$owner->bind_param('ii', $chapterId, $authenticatedUser['user_id']);
$owner->execute();
if (!$owner->get_result()->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'ไม่พบตอน'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $files = manga_upload_files($type);
    $directory = manga_storage_dir();
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
        throw new RuntimeException('สร้างโฟลเดอร์เก็บไฟล์ไม่สำเร็จ');
    }

    $oldFiles = [];
    $old = $conn->prepare('SELECT filename FROM chapter_files WHERE chapter_id = ?');
    $old->bind_param('i', $chapterId);
    $old->execute();
    foreach ($old->get_result() as $row) $oldFiles[] = $row['filename'];

    $newFiles = [];
    $conn->begin_transaction();
    try {
        $delete = $conn->prepare('DELETE FROM chapter_files WHERE chapter_id = ?');
        $delete->bind_param('i', $chapterId);
        if (!$delete->execute()) throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ');

        $insert = $conn->prepare(
            'INSERT INTO chapter_files (chapter_id, sort_order, filename, mime_type, byte_size)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($files as $index => $file) {
            $filename = bin2hex(random_bytes(16)) . '.' . $file['ext'];
            if (!move_uploaded_file($file['tmp'], $directory . DIRECTORY_SEPARATOR . $filename)) {
                throw new RuntimeException('ย้ายไฟล์อัปโหลดไม่สำเร็จ');
            }
            $newFiles[] = $filename;
            $order = $index + 1;
            $insert->bind_param('iissi', $chapterId, $order, $filename, $file['mime'], $file['size']);
            if (!$insert->execute()) throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ');
        }
        $update = $conn->prepare('UPDATE chapters SET content_type = ? WHERE chapter_id = ?');
        $update->bind_param('si', $type, $chapterId);
        if (!$update->execute()) throw new RuntimeException('บันทึกประเภทไฟล์ไม่สำเร็จ');
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        manga_delete_stored_files($newFiles);
        throw $error;
    }
    manga_delete_stored_files($oldFiles);
    echo json_encode(['success' => true, 'message' => 'อัปโหลดตอนสำเร็จ'], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'อัปโหลดตอนไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
