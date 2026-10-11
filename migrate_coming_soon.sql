-- Run once after migrate_discovery.sql on an existing database.
USE mymangalist;

ALTER TABLE manga
    MODIFY COLUMN release_status ENUM('ongoing', 'hiatus', 'completed', 'coming_soon')
    NOT NULL DEFAULT 'ongoing';
