<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/require_auth.php';

$stmt = $conn->prepare(
    'SELECT manga_id, title, author, description, genre, status, created_at
     FROM manga
     WHERE user_id = ?
     ORDER BY created_at DESC, manga_id DESC'
);
$stmt->bind_param('i', $authenticatedUser['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$manga = [];

while ($row = $result->fetch_assoc()) {
    $row['manga_id'] = (int) $row['manga_id'];
    $manga[] = $row;
}

echo json_encode([
    'success' => true,
    'manga' => $manga,
], JSON_UNESCAPED_UNICODE);