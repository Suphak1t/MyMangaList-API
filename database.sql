CREATE DATABASE IF NOT EXISTS mymangalist
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE mymangalist;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    is_suspended TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS api_tokens (
    token_hash CHAR(64) PRIMARY KEY,
    user_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS manga (
    manga_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    genre VARCHAR(100) DEFAULT NULL,
    status ENUM('private', 'public') NOT NULL DEFAULT 'private',
    release_status ENUM('ongoing', 'hiatus', 'completed', 'coming_soon') NOT NULL DEFAULT 'ongoing',
    cover_filename VARCHAR(80) DEFAULT NULL,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_manga_user_created (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS manga_genres (
    manga_id INT NOT NULL,
    genre VARCHAR(100) NOT NULL,
    PRIMARY KEY (manga_id, genre),
    FOREIGN KEY (manga_id) REFERENCES manga(manga_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS manga_ratings (
    manga_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    PRIMARY KEY (manga_id, user_id),
    FOREIGN KEY (manga_id) REFERENCES manga(manga_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS manga_views (
    manga_id INT NOT NULL,
    user_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (manga_id, user_id),
    FOREIGN KEY (manga_id) REFERENCES manga(manga_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

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
