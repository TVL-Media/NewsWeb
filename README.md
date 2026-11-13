# NextGenNews (website, phase 1)

Inshorts-style Indian news site: 60-word cards with depth, Hindi and English, audio, Morning 5, daily quiz, streaks, polls and followable pages. Laravel 13 · PHP 8.3 · MySQL 8 · Blade + Tailwind v4 + Alpine · Livewire 3. Newsroom admin panel at `/admin` (Blade + Alpine, roles & permissions).

**Status:** Milestones 1 (setup) and 2 (reading) are done. See [Milestones](#milestones).

## ⚠️ Replace before launch

- **Grievance officer** (`resources/views/static/grievance.blade.php`): the name, email (`grievance@nextgennews.in`), phone and Lucknow address are **placeholders**. IT Rules 2021 require real details.
- **About** (`static/about.blade.php`): uses `hello@nextgennews.in` as a general contact address. It is made up.
- **Privacy / Terms** (`static/privacy.blade.php`, `static/terms.blade.php`): check these against the real setup before publishing:
  - data held outside India by GA4 and Sentry;
  - the "for example, 180 days" log retention;
  - account deletion from settings (built in Milestone 3);
  - courts at Lucknow;
  - users must be 18+ or have verifiable parental consent.

  Have a lawyer review both pages.
- **Demo photos** in `storage/app/demo/` are placeholders. The demo stories are fictional.

## Run it locally (Windows)

Prerequisites: PHP 8.3 (with `pdo_mysql`, `intl`, `gd`, `zip`, `fileinfo`, `mbstring`, `openssl`, `curl`, `sodium`, `pdo_sqlite`), Composer, Node 20+, MySQL 8.

```powershell
# 1. Start MySQL (this machine uses a user-level data dir, not a Windows service)
Start-Process "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" -ArgumentList "--defaults-file=`"$env:LOCALAPPDATA\nextgennews-mysql\my.ini`"" -WindowStyle Hidden

# 2. First time only
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
npm install
copy .env.example .env      # then set CACHE_STORE=database and QUEUE_CONNECTION=database if you have no Redis
php artisan key:generate
php artisan storage:link

# 3. Database + demo content (safe to re-run; it rebuilds everything)
php artisan migrate:fresh --seed

# 4. Run
npm run build               # or: npm run dev (hot reload)
php artisan serve           # http://127.0.0.1:8000
```

Checks: `php artisan test` (Pest, in-memory SQLite) and `npm run build`.

> Composer: on Windows, a `.bat` wrapper eats `^` in version constraints. Use `php path\to\composer.phar require "vendor/pkg:^1.0"` when adding packages.

### Demo accounts

| Who | Login |
|---|---|
| Reader "Riya Sharma" (Lucknow, 12-day streak) | phone `9876543210`, OTP **`123456`**. This is the development OTP from `OTP_DEV_CODE` in `.env`; it is ignored when `APP_ENV=production`. With `OTP_DEV_CODE` empty, the OTP is written to `storage/logs/laravel.log` (look for `[OTP]`). No real SMS is sent until `SMS_DRIVER=msg91` is set. Any other 10-digit number starting 6–9 creates a new account and goes through onboarding. |
| Newsroom admin / chief / editor | `admin@` / `chief@` / `editor@nextgennews.test`, password `password` at `/admin` (Super Admin / Chief Editor / Editor) |

### Local vs production

| | Local (this machine) | Production |
|---|---|---|
| Cache + queues | `database` (no Redis on Windows) | `redis` + Horizon |
| Media | `public` disk (`MEDIA_DISK=public`) | `s3` disk, `AWS_URL` = CloudFront |
| TTS / SMS / AI | `TTS_DRIVER=fake`, `SMS_DRIVER=log`, empty `ANTHROPIC_API_KEY` | `google`, `msg91`, real key |

Horizon needs the `pcntl`/`posix` extensions, which don't exist on Windows. That's why Composer runs with `--ignore-platform-req` locally; Horizon runs on the Linux server.

## Project map

- `resources/css/theme.css`: the supplied theme (see "Theme changes" below); `app.css` imports Tailwind and the theme.
- `resources/views/components/`: `x-logo`, `x-icon` (Lucide), `x-story-card`, `x-hero-card`, `x-poll-card`, `x-login-sheet`, plus layout parts (`layouts/app`, `site-header`, `app-banner`, `category-chips`, `site-footer`, `bottom-nav`, `lang-toggle`).
- `resources/js/app.js`: Alpine (from Livewire's bundle), `$store.auth` (guest gate + remembered action), the login sheet, and the `ngnFetch` helper.
- `database/migrations/`: all tables from the brief. `database/seeders/`: demo content (`data/stories_*.php` holds the 30 bilingual stories).
- `storage/app/demo/`: demo photos (Picsum/Unsplash placeholders). Replace any file with a real photo of the same name and re-seed.

## Decisions and notes

Where the brief was silent, I picked the simplest option:

**Theme changes** (`resources/css/theme.css`, same class names and values):
- Tailwind v4 can't `@apply` a custom class (`@apply btn`), so base classes are grouped selectors (`.btn, .btn-primary, …`) followed by each variant.
- The global `body`, `a` and `:lang(hi)` rules moved into `@layer base`. Unlayered rules beat every Tailwind utility in v4, so `text-white` on a link would have stayed red.
- The Google Fonts `@import` was moved to `<link>` tags in the layout, because browsers ignore an `@import` that ends up mid-file after bundling.
- `.font-hindi` was added next to `:lang(hi)`, as the brief mentions both.

**Data model additions** (fields the screens need but the brief didn't list):
- `stories.slug`: for `/story/{slug}`.
- `stories.city_id`, `importance` (Morning 5 and the hero), `correction_label`, and `likes_count`/`comments_count`/`shares_count` counters.
- `users.onboarded_at`: drives "onboarding once after first login".
- `categories.image_path`: photo tiles on Explore and onboarding.
- `pages.name_hi`, `pinned_label`/`pinned_text`/`pinned_story_id` (the black RESULT strip) and `upcoming` (JSON for the "Upcoming" tiles).
- `briefings.chapter_offsets`: per-story timestamps for the player highlight.
- `streaks.freezes_month`: monthly freeze reset.
- `reading_events.read_on`: the IST day.
- `comments.parent_id` (Reply) and `likes` as polymorphic (story and comment likes).
- `poll_options.votes_count`.
- `quiz_questions.sort_order`, `quiz_attempts.answers`.

**Other:**
- Readers sign in with phone + OTP only, so `users` has no email or password. Newsroom staff are a separate `admins` table and `admin` guard.
- Guests on login-only pages are redirected to `/?login=1`, which opens the login sheet.
- The `{user city}` chip shows only for signed-in users with a city; guests have no city.
- The bell is shown but does nothing (as specified). The "Get the Android app" links use `ANDROID_APP_URL` (defaults to the Play Store home).
- Quiz questions and polls are single-language (the tables have no language column).
- The Hindi UI chrome is translated through `lang/hi.json`, and story text comes from `story_versions`.
- Laravel 13's cache won't unserialize objects by default, so cached data is stored as plain arrays.
- The demo stories, people, companies, films and figures are fictional. Real institutions and places appear for realism.

**Milestone 2 (reading):**
- **Listen audio:** `App\Contracts\TextToSpeech` has two implementations. `FakeTts` writes a quiet tone WAV whose length matches the reading time (about 150 words a minute). `GoogleTts` uses Google Cloud TTS (en-IN/hi-IN, MP3). The audio reads the headline plus the 60-word card. The seeder generates audio for every story version.
- **Home story circles:** signed-in readers see 24-hour stories from pages they follow, opened in the full-screen viewer. Guests see the top categories (as specified). The red "5" circle opens `/briefing`.
- **Morning 5 and quiz cards:** they link to `/briefing` and `/quiz`, which are built in Milestone 4. Guests see "5 stories · 5 min" in place of "n of 5 read today".
- **Hero:** the most important story of the last 24 hours. **Trending:** the last 3 days, ranked by likes + 2×shares + 3×comments + importance.
- **Load more:** a button, not infinite scroll. It fetches `/?page=N&fragment=1`.
- **Story page:**
  - Both language versions are in the page, so "Read in हिंदी" switches every block without reloading.
  - Breadcrumb: Home › desk (from the page's desk label, e.g. "Sports") › page.
  - The rail poll always shows result bars.
  - Likes, reactions, saves, follows, comments, share image and report already have their UI and optimistic updates. Guests get the login sheet. The endpoints arrive in Milestone 3.
- **Topic page tabs:** Latest; Explainers (stories with a read time of 4+ minutes); Live scores (jumps to the result strip and only shows when the page has one); Polls (polls on the page's stories). The "Upcoming" tiles come from `pages.upcoming`.
- **Explore tiles:** each opens the category's topic page, or Home filtered by that category if it has none.
- **Search:**
  - Uses MySQL full-text (natural language) on story headlines and card text, falling back to `LIKE` for short words and for SQLite in tests.
  - Results appear in the reader's language, even when the match was in the other language.
  - Recent searches are saved in this browser's localStorage only.
- **Mobile story bar:** the like/save/WhatsApp bar sits above the bottom nav, so both stay visible.
- **Blade gotcha:** `@error` is a Blade directive, so the audio player uses `x-on:error` instead of `@error`.

**Milestone 3 (accounts):**
- **OTP:**
  - 6 digits, valid for 10 minutes; only a bcrypt hash is stored.
  - 5 wrong tries lock the code.
  - One SMS per 30 seconds and 5 per hour per number.
  - Route throttles: 5 sends a minute per IP, 10 verifies a minute per IP.
  - `SmsSender`: `LogSmsSender` locally; `Msg91Sender` uses the MSG91 Flow API, and its DLT template needs an `##otp##` variable.
- **After login:**
  - New users go to `/onboarding`. Returning users go back to the page they were on.
  - The action a guest tried (like, save, follow, vote, react, comment, share, report or opening For You) is kept in `sessionStorage` and replayed once they're signed in. After onboarding, the replay happens on the page they land on.
  - Readers who haven't finished onboarding are sent back to it from any page (`TrackReader` middleware), which also updates `last_active_at`.
- **Onboarding:**
  - Saves language, topics (at least 1) and city.
  - Automatically follows the chosen city's page, if one exists.
  - "Use my current location" picks the nearest of the listed cities, using the browser's location only.
- **For You:**
  - Shows stories from the last 7 days in the reader's topics and followed pages, skipping stories they have already read (max 30). "You're all caught up ✓" comes after them.
  - A card counts as read after 3 seconds on screen. On the story page, a read counts after 8 seconds.
- **Comments:**
  - Replies are one level deep.
  - The profanity filter covers English, romanised Hindi and Devanagari, including masked letters such as `f*ck` or `sh1t`.
  - Accounts under 24 hours old can post 3 comments a day.
  - Rate limits: 5 comments a minute and 90 engagement actions a minute per reader.
- **Reports:** one per reader per item, with reasons spam / abusive / misinformation / other. They go to the moderation queue (admin panel → Reports).
- **Share as image:**
  - A 1080×1350 JPEG made with Intervention Image: photo, red band with the logo, tag, headline, card text and site name. Cached per story + language and rebuilt when the story changes.
  - The sheet offers WhatsApp, Download, Copy link and the phone's native share.
  - **Hindi:** GD can't shape Devanagari. A pre-pass fixes the ि vowel sign, but conjuncts still show a visible halant (e.g. क्र). For production, set `IMAGE_DRIVER=imagick` with ImageMagick built with libraqm.
  - The fonts (Inter and Noto Sans Devanagari, OFL) are in `resources/fonts/`.
- **Account:**
  - Text size scales story reading text.
  - **Dark mode:** the theme has no dark palette, so dark mode inverts the light theme and re-inverts photos. Treat it as a stopgap until designed dark tokens exist.
  - **Notifications:** "Notifications (max per day)" is saved in a new `users.notifications_per_day` column, for the Android app.
  - **Delete account:** added (soft delete), because the Privacy page promises erasure.
  - Library pages are `/account/saved|following|liked|history`.

**Milestone 4 (habit features):**
- **Morning 5:**
  - `PickMorning5` runs at 06:00 IST. It ranks stories from the last 24 hours by importance × 10 + reads in the last 24 hours + likes ÷ 50.
  - Editor override: if today's briefing already exists (an editor edited it in `/admin`), its stories are kept and only the audio is rebuilt.
  - `BuildBriefingAudio` joins the 5 stories' audio (headline + card) into one EN and one HI track and stores each story's start second (`chapter_offsets`, per language).
  - The track lasts as long as the five stories take to read. With FakeTts that's about 2–3 minutes; "5 minutes" is the tagline.
  - Manual runs: `php artisan briefing:pick` and `php artisan briefing:audio`.
- **Briefing player:**
  - HTML5 audio with 1x/1.25x/1.5x, previous/next story (jumping by timestamp), and a seekable progress bar (←/→ keys = 10 s).
  - Played waveform bars turn white.
  - The "In this briefing" row for the story that's playing is highlighted; tapping a row jumps to it, and the chevron opens the story.
- **Daily quiz:**
  - 30 seconds a question; a timed-out question counts as wrong.
  - Correct answers and explanations never reach the browser before the reader answers: each answer is checked by `POST /quiz/answers`, and answers can't be changed.
  - The end screen shows the score, the streak with a "+1" pop, and "Share result" (a server-made 1080×1350 image with WhatsApp and Download).
  - Quizzes need status `approved` or `published`; `DraftQuiz` comes in Milestone 5.
- **Streaks (IST):**
  - A day counts at 3 reads or a finished quiz; the reader's streak is updated the moment that happens.
  - `UpdateStreaks` at 00:05 IST spends a freeze on a missed day (2 per calendar month, reset on the 1st) or resets the streak.
  - The "This week" card works out the last 7 days from reads and quiz attempts.
- **Polls:**
  - One vote per reader (unique index). A second try returns 409 with their existing vote. Closed polls refuse votes. Results appear after voting.
  - Readers' polls need a question (10–150 chars), 2–4 options and a run of 1, 3 or 7 days. They pass the profanity filter, are limited to 3 a day, and start as `pending` until an editor approves them (Milestone 5).
  - `ClosePolls` and `ExpirePageStories` run every 15 minutes.
- **Scheduler:** run `php artisan schedule:work` (plus `php artisan queue:work` for queued jobs) locally. In production, a cron runs `schedule:run` every minute, with Horizon running the queue.

**Milestone 5 (newsroom):**
- **Pipeline** (queued jobs):
  1. `FetchFeeds`, every 5 minutes: RSS 2.0, Atom or JSON Feed, deduplicated by URL.
  2. `ClusterItems`: same event = title similarity (Jaccard ≥ 0.45 on meaningful words) within 12 hours.
  3. `GenerateDrafts`: one story per cluster. The photo comes from the feed's image, else the category photo.
  4. `RunChecks`, then `RouteStory`: auto-publish, or `pending_approval` plus `NotifyEditor` (admin panel bell + email).
  5. On publish: `GenerateAudio`, page-cache flush, and the `StoryPublished` event (sitemaps hook in Milestone 6).
  - `DraftQuiz` at 21:00 IST writes tomorrow's quiz from today's top 5 stories. It's approved automatically only if every source story is low-risk.
  - Manual run of the whole pipeline: `php artisan newsroom:run` (prints each new story's decision and why). Tomorrow's quiz by hand: `php artisan quiz:draft`.
- **AiWriter** (`app/Services/AiWriter.php`), Claude via Laravel's HTTP client:
  - Model `claude-opus-5-5` (`ANTHROPIC_MODEL`), effort `medium` (`ANTHROPIC_EFFORT`), API version `2026-10-21`.
  - Strict JSON through structured outputs (`output_config.format` = `json_schema`).
  - **Refusal fallback on:** `fallbacks: "default"` (beta `server-side-fallback-2026-07-01`) re-runs a safety-declined request on Anthropic's recommended fallback model. If the whole chain still refuses, the cluster goes to editors as a notice. Remove `fallbacks` and the beta header in `AiWriter::call()` to opt out.
  - Retries 429/5xx/connection errors 3 times with backoff; `max_tokens` cut-offs and invalid JSON are errors.
  - Prompt rule: our own words, facts only from the sources, never copy sentences.
  - With `ANTHROPIC_API_KEY` empty it returns seeded sample drafts, and the quiz falls back to "Which page covered…?" questions. The sample text doesn't match real sources, so the checks route those drafts to the approval queue.
- **Checks** (`app/Services/Newsroom/StoryChecks.php`):
  - **Fact check:** every number, month/weekday and multi-word name in the English draft must appear in the sources.
  - **Copy check:** the share of the draft's 5-word sequences found in the sources (`copy_score`).
  - **Sensitive check:** sensitive category, keyword match (EN + HI + source), any AI risk flag, or breaking news.
  - **Confidence:** the AI's confidence minus 0.15 per missing fact (max 3), minus 0.3 when over the copy threshold.
  - **Auto-publish** only when low risk, no missing facts, copy < threshold, confidence ≥ threshold, **and every source feed is marked trusted**. "Trusted" is my reading of the Feeds screen's toggle; untrusted feeds always go to an editor.
  - Every decision and its reasons are stored in `ai_checks` (new `reasons` and `risk_flags` columns) and shown in the Auto-publish log.
- **Admin panel** at `/admin`: see [Admin panel](#admin-panel) below. (It replaced the Filament screens; everything they did is in the panel.)

## Reels (short vertical news videos)

Settings in `.env`: `REELS_ENABLED`, `FFMPEG_PATH`, `REEL_MAX_SECONDS` (default 60, a hard cap for every reel). Details in `config/reels.php`.

### Install FFmpeg (the only extra tool)

FFprobe ships with FFmpeg and must sit next to it (or set `FFPROBE_PATH`).

- **Windows, winget (what this machine uses):**
  ```powershell
  winget install --id Gyan.FFmpeg -e
  ```
  Then set `FFMPEG_PATH=C:/Users/<you>/AppData/Local/Microsoft/WinGet/Links/ffmpeg.exe`.
- **Windows, Laragon:** download the "release essentials" zip from https://www.gyan.dev/ffmpeg/builds/. Extract it to `C:\laragon\bin\ffmpeg` so that `C:\laragon\bin\ffmpeg\bin\ffmpeg.exe` exists. Then set `FFMPEG_PATH=C:/laragon/bin/ffmpeg/bin/ffmpeg.exe`.
- **Linux server (Ubuntu/Debian):**
  ```bash
  sudo apt update && sudo apt install -y ffmpeg
  ```
  Then set `FFMPEG_PATH=/usr/bin/ffmpeg`. On distributions without an `ffmpeg` package (for example Amazon Linux), use a static build from https://johnvansickle.com/ffmpeg/ and point `FFMPEG_PATH` at it.

Check it with `ffmpeg -version`. The build needs `libx264` and `aac`; the packages above have both.

### How a reel is made

- **Auto reels:** `StoryPublished` → `QueueStoryReels` → `GenerateReel` (queued, once per language version) → `App\Services\Reels\ReelBuilder`.
  - **Voice-over:** our TTS reads each segment in turn: headline, key point 1, 2, 3, then "Read the full story on NextGenNews". FFprobe measures each clip.
  - **Stills:** `ReelSlides` draws them at 720×1280 with GD/Intervention, using the same fonts as the share card (Inter / Noto Sans Devanagari). There is one transparent overlay per segment (logo, dark fade, red #D7141E accent bar, label pill, big white text), plus the end card and a JPG thumbnail.
  - **Video:** one FFmpeg run applies a slow zoom/pan to the story photo, shows each overlay for exactly as long as its segment is spoken, adds the end card, and outputs 720×1280 (~1.5 Mbps) and 480×854 (~700 kbps) H.264/AAC MP4s with `+faststart`.
  - **Storage:** files go to the media disk under `reels/{id}/` with a fresh random prefix on every build, so CloudFront never serves a stale file.
- **Length 20–45 s:** if the voice-over runs long, trailing key points are dropped (the headline and point 1 always stay). If it runs short, the end card is held longer.
- **Uploads:** `ProcessUploadedReel` turns an editor upload into the same two MP4s plus a thumbnail. The video is fitted over a blurred copy of itself, so landscape agency footage isn't cropped. A silent track is added if the upload has no audio. Uploads longer than `REEL_MAX_SECONDS` fail with a clear message. The upload form in the `/admin` Reels section comes in Reels Milestone 2.
- **Status:** a low-risk story → `published`; a sensitive story (`ai_checks.risk_level = sensitive`) or any upload → `pending_approval`; an error → `failed`, with the FFmpeg/FFprobe message in `error_message`. Editor alerts and the review screens come in Reels Milestone 2.
- **Re-runs:** a story that is published again with the same headline, key points and photo doesn't re-render (`source_hash`). Pass `force: true` to rebuild anyway.
- **Engagement:** likes (already polymorphic) and reports (`target_type = reel`) needed no schema change. Comments, saves and shares got a nullable `reel_id` next to `story_id`.
- **Sample data:** `ReelSeeder` renders 10 English reels from low-risk seeded stories. It runs synchronously at the end of `migrate:fresh --seed` (a few minutes). It is skipped when `REELS_ENABLED=false` or FFmpeg is missing. Tests set `REELS_ENABLED=false`.

### Reels decisions and limits

- **Text drawing:** text is drawn with PHP/GD, not FFmpeg's `drawtext`, so fonts render the same on Windows and Linux without fontconfig.
- **Captions:** they are synced per sentence. Each headline or key point is on screen exactly while it is spoken. Word-by-word captions would need word timings, and our TTS doesn't return them.
- **Photos:** stories have one photo, so the zoom/pan runs over that single photo. A multi-photo slideshow needs a story-gallery field first.
- **Music:** none. `REEL_MUSIC_DIR` is reserved for the licensed music folder.
- **Hindi in reels:** Hindi has the same GD shaping caveat as the share card. Set `IMAGE_DRIVER=imagick` (with libraqm) in production for perfect conjuncts.

## Milestones

1. ✅ **Setup:** Laravel, theme, layout (header, footer, mobile nav, app banner, chips), components, migrations, seeders. Home shows a first version (greeting, hero, Latest News, poll). Other routes render a placeholder naming their milestone.
2. ✅ **Reading:** Home (full, desktop + mobile), Story (3 tabs, language switch, Listen mini player with fake audio, SEO), Topic page, Explore, Search, static pages.
3. ✅ **Accounts:** OTP login sheet, onboarding, account page, For You, likes, reactions, saves, follows, comments, reports, share image.
4. ✅ **Habit features:** Briefing page with the Morning 5 player, daily quiz, streaks (with freezes), polls (vote, create, results), scheduled jobs.
5. ✅ **Newsroom:** feeds, clustering, AI drafts (Claude), fact/copy/sensitivity checks, auto-publish routing, approval queue, admin panel, scheduler.
6. Launch prep: SEO/sitemaps, caching, GA4/Sentry, accessibility, tests, final README.

**Reels:**

1. ✅ Database, reel generation and video processing (FFmpeg), sample reels (10 stories × English + Hindi).
2. Reels section in the `/admin` panel (see "Admin panel" below), auto-publish rules + editor alerts, the `/api/v1` endpoints. `ReelResource` is already shaped for the API.
3. ✅ Website pages:
   - `/reels` and `/reels/{slug}` (mobile snap-scroll, desktop centred player, comments and share sheets, SEO + VideoObject JSON-LD)
   - Reels in the header and the 5-item bottom nav
   - the Home reels row, the Story "Watch reel" pill, the topic Reels tab and the Explore tile

**Reels screens: notes**

- **Stack:** the site already uses Blade + Tailwind + Alpine (from Livewire), so the player is an Alpine component (`reelFeed` in `resources/js/app.js`). No new npm packages.
- **Feed order:** reels from followed pages first, then the newest day, and the most watched within each day. It uses page numbers (8 reels per page), not a cursor.
- **Language:** one reel per story, in the reader's language. If a story only has a reel in the other language, that one is shown.
- **Views:** a view is counted when the reader leaves a reel after watching at least 1 second (`sendBeacon`).
- **Shares:** guests can share; shares are counted but not gated.
- **Burned-in text:** it stays out of the right 140 px and a 58 px side margin. On tall phones, `object-fit: cover` trims the sides, and the like/comment column covers the right edge.

## Admin panel

The newsroom admin panel at `/admin` runs on this app's own tables. Staff sign in with email + password (`admins` table, `admin` guard); readers never can.

**Screens**

| Area | What it does |
|---|---|
| Dashboard | Stories published (auto vs editor), approval queue, reads, active readers, likes/comments/shares, poll votes, streaks, open reports, the newsroom pipeline, and a "needs your attention" list (failed publishing, reader polls to approve, translations to review, live stories without Hindi, today's Morning 5, tomorrow's quiz, stale feeds). |
| Approval queue | AI drafts that failed a check and stories submitted by writers. AI checks and the source reports sit next to the English and Hindi draft. Approve, approve and schedule, request changes, reject (reason kept in `approvals`). Assign / take, SLA highlight, no self-approval (override is password-confirmed and audit-logged). |
| Stories | Every story with status tabs and filters (category, page, writer, AI vs editor, breaking, no Hindi yet). The editor has English, हिंदी, Photo & source, and AI checks tabs: 60-word card check, 3 key points, why it matters, explain simply, full story, photo upload or media library, correction label, page/city/importance/read time, tags. Save, submit, publish now, schedule. Live edits rebuild the audio and clear page caches. Version history with restore, preview in both languages, bulk actions, permanent delete (type DELETE + password). |
| Schedule | Calendar (drag to reschedule) and list. `stories:publish-due` runs every minute, retries 3 times, then tells editors. |
| Feeds & auto-publish | Feeds (add, trusted/active, fetch now), sources, auto-publish rules (sensitive categories and keywords, thresholds) and the auto-publish log with one-click unpublish. |
| Translations | AI translation English ↔ Hindi using the newsroom's Claude key. The text waits for review and is only written to the story when an editor approves it. Side-by-side review, re-run one field, glossary, monthly cap, "translate recent stories into Hindi". |
| Daily | Morning 5 (pick or reorder five stories, rebuild audio, "pick today's now") and daily quiz (write/edit questions, approve drafts, "draft tomorrow's now"). |
| Polls | Editorial polls and reader polls. Approve / reject reader polls (they keep their chosen run length), close, duplicate, results with percentages that add to 100, votes over time, CSV, low-participation flag. |
| Categories, Pages, Tags | Bilingual categories (photo tile or icon, colour, hidden/shown, sensitive), followable pages (result strip, upcoming tiles, avatar/cover), tags. |
| Readers | Search and filters (activity, engagement, language, city), masked phone numbers, reads/comments/likes, this week's streak, restrict (with an end date) / suspend / reinstate with a reason, streak recalculation and extra freeze, data export (JSON), erasure. The website enforces these on the reader's next request (`EnforceReaderStatus`). |
| Comments, Reports | Reported comments first, hide/restore (keeps comment counts right). Reports grouped per item: take down, take down + restrict/suspend the author, dismiss, escalate; repeat-offender warning; decision log with CSV. |
| Streaks | Reads per day and freezes per month (used by `StreakService` on the website), leaderboards, distribution. |
| Analytics | Stories, Newsroom & AI, Readers, Polls/quiz/streaks, Moderation: charts, comparisons with the previous period or last year, CSV export. Computed from `reading_events`, likes, comments, shares, poll votes, quiz attempts, reports and `ai_checks`. |
| Administration | Admin team (invites, two-factor, deactivate), roles & permissions editor, settings (general, date & time, stories, editorial, security), audit log, profile, notifications, Ctrl+K search. |

**Roles** (seeded by `PermissionSeeder`; editable in the panel): Super Admin (everything), Chief Editor (newsroom + feeds + auto-publish rules + delete + readers), Editor (newsroom, daily, polls, moderation), Writer (write and submit own stories), Community Moderator, Analyst. Staff who existed before the panel get Super Admin / Chief Editor / Editor from their old `role` (migration `2026_11_10_110000`).

**Database:** the panel only adds columns and tables (`2026_11_10_100000_add_admin_panel_tables`); status columns on `stories`, `polls`, `reports` and `approvals.action` became strings so the workflow can add `changes_requested` and `scheduled`. Nothing the website reads was renamed.

**Local demo:** two-factor is not forced for the seeded demo staff (Settings → Security turns it back on).

**Tests:** `tests/Feature/AdminPanelTest.php` opens every admin screen and covers the workflow (write → submit → approve → live on the site, self-approval block, scheduling, version restore, unpublish, delete), translation approval, reader restrictions, report take-down, category hiding and streak rules.

**Not carried over from the standalone admin panel** (the website has no screens that would use them yet): content formats other than stories (video, podcast, web-story slides, image posts), languages other than English and Hindi, badges & achievements, featured placements / collections / trending, announcements and notification categories, the mobile-app API, and "duplicate as new version" (live stories are edited in place and every change is kept in version history).
