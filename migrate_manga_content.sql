-- Run once on an existing MyMangaList database before using the new app.
USE mymangalist;

ALTER TABLE manga
    MODIFY status ENUM('Reading', 'Completed', 'Plan to Read', 'On Hold', 'Dropped', 'private', 'public')
    NOT NULL DEFAULT 'private';
UPDATE manga SET status = 'private' WHERE status NOT IN ('private', 'public');
ALTER TABLE manga
    MODIFY status ENUM('private', 'public') NOT NULL DEFAULT 'private',
    ADD COLUMN content_type ENUM('none', 'pdf', 'images') NOT NULL DEFAULT 'none';

CREATE TABLE manga_files (
    file_id INT AUTO_INCREMENT PRIMARY KEY,
    manga_id INT NOT NULL,
    sort_order INT NOT NULL,
    filename VARCHAR(80) NOT NULL,
    mime_type VARCHAR(30) NOT NULL,
    byte_size INT NOT NULL,
    INDEX idx_manga_files_order (manga_id, sort_order),
    FOREIGN KEY (manga_id) REFERENCES manga(manga_id) ON DELETE CASCADE
);
