<?php

function manga_can_view(mysqli $conn, array $user, int $mangaId, array $manga): bool
{
    if ((int) $manga['user_id'] === (int) $user['user_id']) return true;
    if ($manga['status'] === 'public' && (int) $manga['is_hidden'] === 0) return true;
    if ($user['role'] !== 'admin') return false;

    // Admins can inspect a reported Manga even after it is hidden or made private.
    $stmt = $conn->prepare('SELECT report_id FROM manga_reports WHERE manga_id = ? LIMIT 1');
    $stmt->bind_param('i', $mangaId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}
