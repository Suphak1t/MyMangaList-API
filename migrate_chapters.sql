-- Use after migrate_manga_content.sql on an existing installation.
-- The previous upload data in this workspace was test data and has been removed.
-- This migration leaves the unused manga_files table and manga.content_type column
-- in place so it does not delete any data from another installation.
USE mymangalist;

CREATE TABLE IF NOT EXISTS chapters (
    chapter_id INT AUTO_INCREMENT PRIMARY KEY,
    manga_id INT NOT NULL,
    chapter_number INT NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    content_type ENUM('none', 'pdf', 'images') NOT NULL DEFAULT 'none',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_chapter_number (manga_id, chapter_number),
    FOREIGN KEY (manga_id) REFERENCES manga(manga_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chapter_files (
    file_id INT AUTO_INCREMENT PRIMARY KEY,
    chapter_id INT NOT NULL,
    sort_order INT NOT NULL,
    filename VARCHAR(80) NOT NULL,
    mime_type VARCHAR(30) NOT NULL,
    byte_size INT NOT NULL,
    INDEX idx_chapter_files_order (chapter_id, sort_order),
    FOREIGN KEY (chapter_id) REFERENCES chapters(chapter_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    manga_id INT NOT NULL,
    chapter_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    body VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_comments_manga_chapter (manga_id, chapter_id, created_at),
    FOREIGN KEY (manga_id) REFERENCES manga(manga_id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapters(chapter_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
