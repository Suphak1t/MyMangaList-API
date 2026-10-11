-- Run once after migrate_moderation.sql on an existing database.
USE mymangalist;

ALTER TABLE manga
    ADD COLUMN release_status ENUM('ongoing', 'hiatus', 'completed') NOT NULL DEFAULT 'ongoing',
    ADD COLUMN cover_filename VARCHAR(80) DEFAULT NULL;

CREATE TABLE manga_genres (
    manga_id INT NOT NULL,
    genre VARCHAR(100) NOT NULL,
    PRIMARY KEY (manga_id, genre),
    FOREIGN KEY (manga_id) REFERENCES manga(manga_id) ON DELETE CASCADE
);

INSERT INTO manga_genres (manga_id, genre)
SELECT manga_id, genre FROM manga WHERE genre IS NOT NULL AND genre <> '';

CREATE TABLE manga_ratings (
    manga_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    PRIMARY KEY (manga_id, user_id),
    FOREIGN KEY (manga_id) REFERENCES manga(manga_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE manga_views (
    manga_id INT NOT NULL,
    user_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (manga_id, user_id),
    FOREIGN KEY (manga_id) REFERENCES manga(manga_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
