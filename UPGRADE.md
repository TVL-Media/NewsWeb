# Updating NextGenNews

An update replaces the program files and applies any database changes. It never deletes or changes your stories, readers, comments, settings or uploaded photos and videos.

## Before you start: back up

1. **Database:** phpMyAdmin → your database → *Export* → *Quick* → *Go*. Or, with SSH:
   `mysqldump -u USER -p DATABASE > nextgennews-backup.sql`
2. **Files:** download your **`.env`** file and the **`storage/app`** folder (your uploads).

If anything goes wrong, you can put these back.

## Update in three steps

1. **Upload the new files** over the old ones. Unzip the new version and upload everything inside it, replacing what is there.
   - **Do not** delete or replace your `.env` file or the `storage/` folder.
   - The new download does not contain those, so uploading over the old files is safe.
2. **Open Admin → System update.** It shows *Running now* (your old version) and *Files on the server* (the new version).
3. **Tick "I have a backup", then press Run update** (it asks for your password). The update then:
   1. switches the site to maintenance mode (visitors see a short "back soon" page);
   2. applies the database changes;
   3. rebuilds the caches;
   4. records the new version;
   5. brings the site back online, even if a step failed.

It usually takes under a minute.

**With SSH**, you can run the same update from the command line:

```bash
cd /path/to/nextgennews
php artisan nextgennews:update
php artisan queue:restart      # if you use a queue worker (Supervisor)
```

## If the update stops

- The page lists each step and why it stopped. Details are in `storage/logs/laravel.log`.
- Fix the cause, then press **Run update** again. Changes that already worked are not repeated.
- The site is never left in maintenance mode by the updater. If it ever is, run `php artisan up`.
- If you cannot fix it: restore the old files, import your database backup, and contact support with the log.

## Version history

### 1.0.0 (2026-11-19)

The first installable release.

- Web installer at `/install` and `php artisan nextgennews:install`.
- Admin → Settings for the site name, logo, colour, languages, timezone, feature switches, API keys (stored encrypted), storage, analytics and legal pages.
- Admin → System update and `php artisan nextgennews:update`.

<!--
For maintainers, with every release:
1. Bump `version` (and `released`) in config/app-version.php.
2. Put database changes in new migrations only. Never edit an old one, and never seed data the site owner may have changed.
3. Add a section above: what is new, and any manual steps (new .env values, new cron lines).
-->
