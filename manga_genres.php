<?php
require_once __DIR__ . '/genre_options.php';

function manga_parse_genres(array $data): array
{
    $genres = $data['genres'] ?? [$data['genre'] ?? null];
    if (!is_array($genres) || count($genres) < 1 || count($genres) > 5) {
        throw new InvalidArgumentException('เลือกแนวเรื่องได้ 1–5 แนว');
    }
    $genres = array_map(fn($value) => is_string($value) ? trim($value) : '', $genres);
    if (count(array_unique($genres)) !== count($genres)) {
        throw new InvalidArgumentException('แนวเรื่องซ้ำกัน');
    }
    foreach ($genres as $genre) {
        if (!in_array($genre, MANGA_GENRES, true)) {
            throw new InvalidArgumentException('แนวเรื่องไม่ถูกต้อง');
        }
    }
    return $genres;
}

function manga_save_genres(mysqli $conn, int $mangaId, array $genres): void
{
    $delete = $conn->prepare('DELETE FROM manga_genres WHERE manga_id = ?');
    $delete->bind_param('i', $mangaId);
    $delete->execute();
    $insert = $conn->prepare('INSERT INTO manga_genres (manga_id, genre) VALUES (?, ?)');
    foreach ($genres as $genre) {
        $insert->bind_param('is', $mangaId, $genre);
        $insert->execute();
    }
}
