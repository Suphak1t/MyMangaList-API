-- Run once after migrate_chapters.sql on an existing MyMangaList database.
USE mymangalist;

ALTER TABLE users ADD COLUMN is_suspended TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE manga ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS manga_reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    manga_id INT DEFAULT NULL,
    uploader_user_id INT DEFAULT NULL,
    reporter_user_id INT DEFAULT NULL,
    manga_title VARCHAR(255) NOT NULL,
    uploader_username VARCHAR(100) NOT NULL,
    reporter_username VARCHAR(100) NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    status ENUM('pending', 'resolved') NOT NULL DEFAULT 'pending',
    action ENUM('none', 'dismissed', 'hidden', 'manga_deleted', 'user_suspended') NOT NULL DEFAULT 'none',
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reports_status_created (status, created_at),
    FOREIGN KEY (manga_id) REFERENCES manga(manga_id) ON DELETE SET NULL,
    FOREIGN KEY (uploader_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (reporter_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
);
