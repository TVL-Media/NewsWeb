# Installing NextGenNews

NextGenNews is a short-news website in English and Hindi, with a newsroom admin panel at `/admin`.
This guide takes you from the download to a live site. Most people finish in 15–30 minutes.

**Contents**

1. [What you need](#1-what-you-need)
2. [Shared hosting or VPS?](#2-shared-hosting-or-vps)
3. [Upload the files](#3-upload-the-files)
4. [Create the database](#4-create-the-database)
5. [Run the installer](#5-run-the-installer)
6. [Cron job and queue worker](#6-cron-job-and-queue-worker)
7. [HTTPS](#7-https)
8. [Email](#8-email)
9. [API keys: where to get each one](#9-api-keys-where-to-get-each-one)
10. [Turning features on and off](#10-turning-features-on-and-off)
11. [Common errors](#11-common-errors)
12. [Installing on your own computer (Laragon)](#12-installing-on-your-own-computer-laragon)
13. [For developers: building the download](#13-for-developers-building-the-download)

---

## 1. What you need

| | Minimum |
|---|---|
| PHP | **8.3** or newer |
| PHP extensions | bcmath, ctype, curl, dom, fileinfo, filter, gd, json, mbstring, openssl, pdo, pdo_mysql, tokenizer, xml |
| Recommended extensions | intl, imagick (share images show Hindi letters perfectly), zip |
| Database | **MySQL 8.0+** or **MariaDB 10.4+**, with an empty database |
| Web server | Apache (with `mod_rewrite`) or Nginx |
| Disk | About 300 MB, plus your photos and videos |
| Optional | **FFmpeg** on the server, to make Reels (short videos) |
| Optional | Redis, for big traffic (not needed to start) |

The installer checks all of this for you on its **Requirements** step.

## 2. Shared hosting or VPS?

| | Shared hosting (cPanel, Hostinger, …) | VPS (DigitalOcean, AWS Lightsail, …) |
|---|---|---|
| Good for | Getting started, small traffic | Growing sites, reels, many editors |
| Background work | A cron job runs it every minute | A Supervisor queue worker runs all the time |
| Reels | Usually not (no FFmpeg) | Yes: `apt install ffmpeg` |
| Setup effort | Low | Medium (you manage the server) |

Everything else works the same on both.

## 3. Upload the files

1. Unzip the download. Upload **everything inside the `nextgennews` folder** to your server, for example to `/home/you/nextgennews`.
2. Point your domain at the **`public`** folder, not at the main folder. This keeps your `.env` and code private.
   - **cPanel:** Domains → your domain → *Document root* → `nextgennews/public`.
   - **Nginx:** `root /var/www/nextgennews/public;` and `try_files $uri $uri/ /index.php?$query_string;`.
   - **Apache vhost:** `DocumentRoot /var/www/nextgennews/public` with `AllowOverride All`.
   - If your host does not let you change the document root, put the project one level above `public_html`, copy the contents of `public/` into `public_html/`, and in `public_html/index.php` change the three `__DIR__.'/../…'` paths to point to the project folder.
3. Make these folders writable by the web server (permission **775**): `storage/` (and everything inside it) and `bootstrap/cache/`. The main folder must be writable once so the installer can create `.env`.

On a VPS:

```bash
sudo chown -R www-data:www-data /var/www/nextgennews
sudo chmod -R 775 /var/www/nextgennews/storage /var/www/nextgennews/bootstrap/cache
```

## 4. Create the database

Create an **empty** database and a user with **all privileges** on it.

- **cPanel:** MySQL® Databases → create the database, create a user, *Add user to database* → All privileges.
- **VPS:**

  ```sql
  CREATE DATABASE nextgennews CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'nextgennews'@'localhost' IDENTIFIED BY 'a-strong-password';
  GRANT ALL PRIVILEGES ON nextgennews.* TO 'nextgennews'@'localhost';
  ```

The installer refuses a database that already has tables, so it can never overwrite another site's data.

## 5. Run the installer

Open your website address. Until the site is installed, every page opens the installer at **`/install`**.

| Step | What you do |
|---|---|
| Welcome | Read what you need. |
| Requirements | Green = OK. Red must be fixed (turn the PHP extension on in your hosting panel, then *Check again*). Orange is optional. |
| Permissions | Fix any red folder (see step 3), then *Check again*. |
| Database | Enter host (usually `127.0.0.1` or `localhost`), port `3306`, database name, user and password. Press **Test connection**. |
| Purchase code | Only shown when the seller turned licence checks on. |
| Your site | Name, tagline, address (use `https://` once SSL works), language, timezone, *live website* or *testing*, and whether to add sample data. |
| Admin account | Your name, email and a password (10+ characters, letters and numbers). |
| API keys | Optional. Paste the keys you have, or press **Skip**. Add them later in **Admin → Settings**. |
| Install | Press **Install now** and watch the list. |

If a step fails, the installer shows why. Fix it and press **Retry**: finished steps are not repeated, and your site stays on the installer until every step has worked, so it is never half installed.

When it finishes, the installer locks itself (`storage/installed` is written and `/install` no longer exists). Open **`/admin`** and sign in. On a live website the Super Admin is asked to set up two-step sign-in (an authenticator app such as Google Authenticator) on first login; a "testing on my computer" install skips this.

**No browser access?** Install from SSH instead:

```bash
cd /var/www/nextgennews
php artisan nextgennews:install
```

It asks the same questions. For an unattended install, pass every option:

```bash
php artisan nextgennews:install -n --db-database=nextgennews --db-username=nextgennews --db-password=secret \
  --url=https://news.example.com --name="My News" --admin-name="Your Name" --admin-email=you@example.com \
  --admin-password=ChooseAStrong1 [--sample] [--environment=local]
```

## 6. Cron job and queue worker

Two things must run in the background. The installer's last screen shows both lines with **Copy** buttons.

**Cron job (required).** Runs the scheduler every minute: news feeds, scheduled stories, the daily quiz, Morning 5, polls closing, streaks.

```
* * * * * cd /path/to/nextgennews && php artisan schedule:run >> /dev/null 2>&1
```

- **cPanel:** Cron Jobs → *Once per minute* → paste the line (use the full path to PHP if your host says so, e.g. `/usr/local/bin/php`).
- **VPS:** `crontab -u www-data -e` and paste it.

**Queue worker (required).** Does the slow work: AI drafts, translations, audio, reels, notifications.

- **VPS, with Supervisor (recommended).** Create `/etc/supervisor/conf.d/nextgennews.conf`:

  ```ini
  [program:nextgennews-queue]
  command=php /var/www/nextgennews/artisan queue:work --tries=3 --timeout=900
  user=www-data
  numprocs=1
  autostart=true
  autorestart=true
  stopwaitsecs=910
  redirect_stderr=true
  stdout_logfile=/var/www/nextgennews/storage/logs/queue.log
  ```

  Then `sudo supervisorctl reread && sudo supervisorctl update`. After every update, run `php artisan queue:restart`.

- **Shared hosting (no Supervisor).** Add a second cron job, every minute:

  ```
  * * * * * cd /path/to/nextgennews && php artisan queue:work --stop-when-empty --tries=3 --timeout=900 >> /dev/null 2>&1
  ```

**Bigger sites:** install Redis, then set `CACHE_STORE=redis` and `QUEUE_CONNECTION=redis` in `.env` (and use Horizon if you like: `php artisan horizon`).

## 7. HTTPS

Use HTTPS on a live site: logins, the admin panel and phone OTPs need it.

- **cPanel:** SSL/TLS Status → *Run AutoSSL* (free).
- **VPS with Nginx:** `sudo apt install certbot python3-certbot-nginx && sudo certbot --nginx -d news.example.com`.

Then make sure `APP_URL` in `.env` starts with `https://` and run `php artisan config:clear`.

## 8. Email

Staff invitations and password resets are sent by email. The installer leaves email in *log* mode (emails are written to `storage/logs/laravel.log`). To really send them, set these in `.env`, using your host's or provider's SMTP details (Gmail, Zoho, Amazon SES, Brevo, …):

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=noreply@your-domain.com
```

## 9. API keys: where to get each one

Every key is optional and is pasted in **Admin → Settings** (or on the installer's API keys step). Keys are stored **encrypted** and shown masked (`••••abcd`). On a live site, a feature whose key is missing hides itself, and **Settings → Features** says what is missing.

| Feature | Setting | Where to get it |
|---|---|---|
| AI drafts and auto-publishing | **AI → Claude API key** | [console.anthropic.com](https://console.anthropic.com) → API keys → *Create key*. Billing must be set up. |
| Listen, Morning 5, reel voice-over | **Text-to-speech → Google API key** | [console.cloud.google.com](https://console.cloud.google.com) → *APIs & Services* → enable **Cloud Text-to-Speech API** → *Credentials* → *Create API key* (restrict it to that API). |
| (or) | **Text-to-speech → Azure key + region** | [portal.azure.com](https://portal.azure.com) → create a **Speech** resource → *Keys and Endpoint*. Region e.g. `centralindia`. |
| Phone OTP login | **SMS → MSG91 auth key, DLT sender ID, DLT template ID** | [msg91.com](https://msg91.com) → *API* → auth key. In India, OTP SMS needs **DLT registration**: register your entity, a 6-letter sender ID and an OTP template on your operator's DLT portal (e.g. Jio, Vodafone Idea), then add that template in MSG91. |
| Photos and videos on Amazon S3 | **Storage → AWS key, secret, bucket, region, CloudFront URL** | [AWS console](https://console.aws.amazon.com) → S3 → create a bucket; IAM → a user with access to that bucket → *Access keys*. CloudFront is optional. |
| Google Analytics | **Analytics → GA4 ID** | [analytics.google.com](https://analytics.google.com) → Admin → Data streams → Measurement ID (`G-…`). |
| Firebase Analytics | **Analytics → Firebase web config** | [console.firebase.google.com](https://console.firebase.google.com) → Project settings → *Your apps* → *Config* (paste the JSON). |
| Error reports | **Analytics → Sentry DSN** | [sentry.io](https://sentry.io) → Project settings → *Client Keys (DSN)*. |

## 10. Turning features on and off

**Admin → Settings → Features** has a switch for each of: AI auto-publishing, Listen, Morning 5, Daily quiz, Polls, Comments, Reels and Phone OTP login.

- **Off:** the feature disappears from the website (buttons, cards, menus) and its pages return *not found*.
- **Needs setup:** the switch is on but something is missing (a key, or Listen for Morning 5). The reason is shown under the switch.
- Changes take effect immediately.

Other settings, such as the name, logo, colour, timezone and legal pages, are on the other Settings tabs. The **General** tab sets the website name, tagline, logo, favicon, brand colour, default language, timezone and contact email. The **Legal** tab holds the grievance officer details (required for Indian digital news publishers) and your own About, Privacy and Terms pages.

## 11. Common errors

| You see | Do this |
|---|---|
| **500 error / blank page** right after upload | Check `storage/` and `bootstrap/cache/` are writable (step 3). The real error is in `storage/logs/laravel.log`. |
| **"No application encryption key"** | The installer could not write `.env`. Make the main folder writable, or copy `.env.example` to `.env` and run `php artisan key:generate`. |
| **404 on every page except the home page** | Apache: `mod_rewrite` is off or `AllowOverride` is not `All`. Nginx: add `try_files $uri $uri/ /index.php?$query_string;`. |
| **The site shows a folder list or the code** | The domain points at the main folder. Point it at `public/` (step 3). |
| **"The username or password is wrong"** (database) | Re-check the user and password; on cPanel the names usually have a prefix like `cpuser_`. |
| **"That database does not exist"** | Create it first (step 4) and check the exact name. |
| **"This database already has … tables"** | Use a new, empty database. The installer never changes existing data. |
| **Photos do not show** | The `public/storage` link is missing. Run `php artisan storage:link`. If your host blocks links, ask support to create a symlink from `public/storage` to `storage/app/public`. |
| **AI drafts, audio or reels never appear** | The queue worker is not running (step 6). Check `php artisan queue:work` runs without errors. |
| **Nothing happens at 06:00 / 21:00, feeds never update** | The cron job is not running (step 6). Check its path to PHP. |
| **"419 Page expired"** in the installer | The session expired. Reload the page and continue. |
| **"Too many requests"** | Wait a minute: the database test and purchase-code check are rate-limited. |
| **Readers cannot log in** | Phone OTP login needs an SMS provider on a live site. Add MSG91 in Settings → SMS. |
| **Reels: "FFmpeg not found"** | Install FFmpeg (`sudo apt install ffmpeg`) and set `FFMPEG_PATH` in `.env` if it is not on the PATH. |

## 12. Installing on your own computer (Laragon)

1. Copy the project into `C:\laragon\www\nextgennews` and start Laragon (*Start All*).
2. Laragon → *Database* → create an empty database, e.g. `nextgennews`.
3. Open `http://nextgennews.test`. The installer opens.
4. Database: host `127.0.0.1`, port `3306`, user `root`, **no password**.
5. On *Your site*, choose **For testing on my computer**. This shows error details, uses the test OTP `123456` and makes sample AI drafts without a key.
6. For background work, open two Laragon terminals and run `php artisan schedule:work` and `php artisan queue:work`.

## 13. For developers: building the download

The download must contain `vendor/` and the built front-end (`public/build/`), because most hosts cannot run Composer or npm.

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan test            # optional
```

Then zip the project **without**: `.env`, `storage/installed`, `storage/logs/*`, `storage/app/public/*` (keep the folders and their `.gitignore` files), `public/storage`, `node_modules/`, `.git/`.

To show a purchase-code step in the installer, ship the `.env.example` with `ENVATO_LICENSE_CHECK=true` (and `ENVATO_API_TOKEN` + `ENVATO_ITEM_ID` to check codes with Envato's API; without a token, only the format is checked).

Updating a site is covered in [UPGRADE.md](../UPGRADE.md).
