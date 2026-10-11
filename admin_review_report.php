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
require_once __DIR__ . '/manga_storage.php';

$data = json_decode(file_get_contents('php://input'), true);
$reportId = filter_var(is_array($data) ? ($data['report_id'] ?? null) : null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = is_array($data) ? ($data['action'] ?? null) : null;
if (!$reportId || !in_array($action, ['dismissed', 'hidden', 'manga_deleted', 'user_suspended'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ข้อมูลการตัดสินใจไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$files = [];
$conn->begin_transaction();
try {
    $stmt = $conn->prepare('SELECT manga_id, uploader_user_id, status FROM manga_reports WHERE report_id = ? FOR UPDATE');
    $stmt->bind_param('i', $reportId);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    if (!$report || $report['status'] !== 'pending') {
        throw new InvalidArgumentException('รีพอร์ตนี้ไม่มีหรือดำเนินการแล้ว');
    }

    if ($action === 'hidden') {
        if (!$report['manga_id']) throw new InvalidArgumentException('Manga ถูกลบไปแล้ว');
        $stmt = $conn->prepare('UPDATE manga SET is_hidden = 1 WHERE manga_id = ?');
        $stmt->bind_param('i', $report['manga_id']);
        if (!$stmt->execute()) throw new RuntimeException('ซ่อน Manga ไม่สำเร็จ');
    } elseif ($action === 'user_suspended') {
        if (!$report['uploader_user_id']) throw new InvalidArgumentException('ผู้ใช้นี้ไม่มีแล้ว');
        $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $report['uploader_user_id']);
        $stmt->execute();
        $uploader = $stmt->get_result()->fetch_assoc();
        if (!$uploader || $uploader['role'] !== 'user') throw new InvalidArgumentException('พักบัญชีนี้ไม่ได้');
        $stmt = $conn->prepare('UPDATE users SET is_suspended = 1 WHERE user_id = ?');
        $stmt->bind_param('i', $report['uploader_user_id']);
        if (!$stmt->execute()) throw new RuntimeException('พักบัญชีไม่สำเร็จ');
        $stmt = $conn->prepare('DELETE FROM api_tokens WHERE user_id = ?');
        $stmt->bind_param('i', $report['uploader_user_id']);
        if (!$stmt->execute()) throw new RuntimeException('ยกเลิก token ไม่สำเร็จ');
    } elseif ($action === 'manga_deleted') {
        if (!$report['manga_id']) throw new InvalidArgumentException('Manga ถูกลบไปแล้ว');
        $stmt = $conn->prepare(
            'SELECT chapter_files.filename FROM chapter_files
             JOIN chapters ON chapters.chapter_id = chapter_files.chapter_id
             WHERE chapters.manga_id = ?'
        );
        $stmt->bind_param('i', $report['manga_id']);
        $stmt->execute();
        foreach ($stmt->get_result() as $row) $files[] = $row['filename'];
        $stmt = $conn->prepare('DELETE FROM manga WHERE manga_id = ?');
        $stmt->bind_param('i', $report['manga_id']);
        if (!$stmt->execute() || $stmt->affected_rows === 0) throw new RuntimeException('ลบ Manga ไม่สำเร็จ');
    }

    $stmt = $conn->prepare(
        "UPDATE manga_reports SET status = 'resolved', action = ?, reviewed_by = ?, reviewed_at = NOW()
         WHERE report_id = ?"
    );
    $stmt->bind_param('sii', $action, $authenticatedUser['user_id'], $reportId);
    if (!$stmt->execute()) throw new RuntimeException('บันทึกผลรีพอร์ตไม่สำเร็จ');
    $conn->commit();
    manga_delete_stored_files($files);
    echo json_encode(['success' => true, 'message' => 'ดำเนินการรีพอร์ตแล้ว'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code($error instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode([
        'success' => false,
        'message' => $error instanceof InvalidArgumentException ? $error->getMessage() : 'ดำเนินการรีพอร์ตไม่สำเร็จ',
    ], JSON_UNESCAPED_UNICODE);
}
