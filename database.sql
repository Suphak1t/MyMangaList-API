CREATE DATABASE IF NOT EXISTS mymangalist
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE mymangalist;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
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
    status ENUM('Reading', 'Completed', 'Plan to Read', 'On Hold', 'Dropped') NOT NULL DEFAULT 'Plan to Read',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_manga_user_created (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
INSERT IGNORE INTO users (username, password, role) VALUES
('admin', '$2y$10$ki0Jg6l94.unH9bI8TcVTeE/sPGjj3EQ7XQLruzfs9FLod4TTPQy.', 'admin');
