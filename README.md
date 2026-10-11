# MyMangaList API

1. Start Apache and MySQL in XAMPP.
2. For a new database, import `database.sql`. For an existing database, run the migrations not yet applied in order: `migrate_manga_content.sql`, `migrate_chapters.sql`, `migrate_moderation.sql`, `migrate_discovery.sql`, `migrate_coming_soon.sql`.
3. Copy `config.example.php` to `config.php` and set the local database connection values.

The Flutter app uses `http://localhost/mymangalist_api` on Windows Desktop. Its source is in the separate [MyMangaList Flutter repository](https://github.com/Suphak1t/MyMangaList-Flutter).

Registration creates a `user` account. To give an existing account admin access, change its role in MySQL:

```sql
UPDATE users SET role = 'admin' WHERE username = 'your_username';
```

The server validates bearer tokens and reads the role from the database. Manga endpoints use the authenticated user's `user_id`.

Manga stores metadata, up to five genres, an optional JPG/PNG/WebP cover (up to 5 MB), and many chapters. Each chapter may contain one PDF (up to 20 MB) or up to 15 JPG, PNG, or WebP images (up to 5 MB each, 30 MB total). Files are stored outside `htdocs` in `C:\xampp\mymangalist_uploads` and are served only by authenticated API requests. `private` Manga is readable only by its owner; published chapters in `public` Manga are readable by other signed-in users. Draft chapters are owner-only. Editing and deleting always require ownership. Visibility and release status are separate: new Manga can start as `ongoing` or `coming_soon`; its owner can later select `hiatus` or `completed` as well.

`manga_get.php` returns chapter count, genres, cover filename, release status, unique reader count, and average rating. `manga_view.php` records one unique reader per public Manga after a published chapter opens; owner views do not count. `manga_rate.php` lets a signed-in non-owner give a public Manga one rating from 1 to 5 and update it later. The existing database's single genre is copied into `manga_genres` by `migrate_discovery.sql`.

Manga-level and chapter-level comments are stored separately by a nullable `chapter_id`. Signed-in readers can comment on visible content. Comment authors, the Manga owner, and admins can delete a comment. The migration keeps the old `manga_files` table and `manga.content_type` column unused to avoid deleting previous data; this workspace had no old Manga files to move.

Signed-in users may report a public Manga with a written reason. The admin-only report queue records a resolution: dismiss, hide Manga, delete Manga, or suspend its uploader. Admins can inspect a reported Manga and its published chapters even if it was hidden or later made private; unrelated private Manga and draft chapters remain owner-only. Hiding a Manga blocks public list, chapter, file, and comment access while leaving owner access; an admin can restore visibility. Suspension blocks login and every token-protected API and deletes existing tokens. The user-management panel shows Manga and comment counts and can suspend, unsuspend, or delete ordinary users. It cannot promote an account to admin; use the SQL role update above.

The genre list is a fixed snapshot of AniList's genre categories. Existing Manga without files can still be edited and given files later.
