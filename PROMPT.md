# Build the NextGenNews website (Phase 1, web only)

You are building **NextGenNews**, an Inshorts-style news website for India: short news cards with depth, Hindi and English, audio, a daily "Morning 5" briefing, a daily quiz, streaks, polls, and pages people can follow. News comes from licensed agency feeds and publisher RSS. AI writes the summaries. Low-risk stories auto-publish, and sensitive stories wait for an editor's approval.

Build it **exactly** to the design described below, using the theme in `./theme/` (copy it into the project as described in "Theme"). Work in milestones and **stop after each milestone** so I can review it.

---

## 1. Tech stack (use exactly this)

- PHP 8.3 + **Laravel** (latest stable)
- **MySQL 8** (use MySQL full-text indexes for search)
- **Blade** templates, server-rendered (SEO matters)
- **Tailwind CSS v4** via Vite (Laravel default)
- **Alpine.js** for interactivity; **Livewire 3** only where it clearly simplifies a page (polls, comments, quiz)
- **Filament 3** for the editor/admin panel at `/admin`
- **Redis** for cache and queues, with **Laravel Horizon** to monitor queues
- **Laravel scheduler** for timed jobs
- File storage: Laravel filesystem with an **S3 disk** (CloudFront URL as `AWS_URL`); use the `public` disk locally
- AI: **Claude API** through Laravel's HTTP client, wrapped in `App\Services\AiWriter`
- Text-to-speech: interface `App\Contracts\TextToSpeech` with a `GoogleTts` implementation and a `FakeTts` for local/testing
- OTP SMS: interface `App\Contracts\SmsSender` with an `Msg91Sender` and a `LogSmsSender` (writes the OTP to the log) for local
- SEO: `spatie/laravel-sitemap`, a Google News sitemap, and `NewsArticle` JSON-LD
- Icons: **Lucide** (outline, 2px stroke) as inline SVG Blade components (`<x-icon name="heart" />`)
- Analytics: Google Analytics 4 (ID from env; skip it when empty). Errors: Sentry (DSN from env)
- Tests: Pest

Don't add React, Vue, Inertia or any SPA framework. Don't build the Android app, push notifications, video or podcasts. Those are later phases.

---

## 2. Theme

The `./theme/` folder contains:
- `theme.css`: Tailwind v4 `@theme` tokens and component classes (`.btn-primary`, `.chip`, `.card`, `.seg`, `.player-bg`, `.logo-tile`, `.why-box`, `.poll-option`, `.sheet`, `.bottom-nav`, …)
- `design-tokens.json`: the same tokens as data (colours, type scale, radii, shadows, gradients, layout)
- `logo.svg`: the red "N" mark

Setup:
1. Copy `theme/theme.css` to `resources/css/theme.css`. In `resources/css/app.css` put `@import "tailwindcss";` and then `@import "./theme.css";`
2. Copy `theme/logo.svg` to `public/images/logo.svg`, and also make it a Blade component `<x-logo />` (inline SVG, red `#D7141E`), followed by the word **NextGenNews** in Inter 900.
3. Use only the theme's colours. Main accent: red `#D7141E`. Text: `#111111`. Page background: `#F7F7F8`. Cards: white, radius 22px, soft shadow.
4. Fonts: Inter for everything. Wherever Hindi text appears, use Noto Sans Devanagari (`lang="hi"` or `.font-hindi`).

Visual rules:
- Rounded everything: cards 22–28px, buttons and chips fully round (pill), inputs 14px.
- Photos fill their cards (`object-cover`). Text on top of photos sits on a dark fade (`.scrim`) and is white.
- Red is used for primary buttons, active tabs, story rings, number badges, check dots, tags and links. Light pink `#FFF0F0` is used for the active nav pill, the Listen button and the "Why it matters" box.
- Dark cards (`#111111`) are used for the Daily quiz and the live-score strip.
- The Morning 5 player uses the dark red radial gradient (`.player-bg`), the tilted white-to-pink logo tile with a red N (`.logo-tile`), waveform bars, a white round play/pause button, and a light pink "In this briefing" list card.
- Minimum touch target: 44px. Text contrast must be at least 4.5:1. Every icon-only button needs an `aria-label`.

---

## 3. Global layout

### Header (desktop ≥ 768px)
White, bottom border `#EDEDED`, container max 1280px, 24px side padding, items in one row:
`<x-logo/> NextGenNews` · nav pills **Home · For You · Briefing · Explore** (the active one uses `.nav-link-active`) · search field (rounded, grey `#F2F2F3`, placeholder "Search news, topics, pages") · **हिं / EN** toggle (outline pill) · bell icon with red dot (show it, but it links nowhere in phase 1) · user avatar (red rounded square with the initial), or a **Log in** red pill for guests.
Below the header there's a row of category chips: `All` (black, active) then India, World, Business, Cricket, Tech, Health, {user city}, Good News.

### Header (mobile web < 768px)
58px tall: logo + "NextGenNews", **हिं/EN** pill, search icon, red **Log in** pill (or avatar). Below it, a dismissible black banner: app icon tile, "**Get the Android app** · Audio, streaks and daily quiz", red "Open" pill and a close ×. Remember the dismissal in localStorage.

### Mobile bottom nav (< 768px only)
Fixed `.bottom-nav` with 4 items: Home (house), For You (zap), Briefing (layers), Explore (layout-grid). The active item gets a pink tinted pill and red colour. Add bottom padding to the page so content isn't hidden behind it.

### Footer
Black `#111111`. Logo in light red, then links About · Grievance officer · Privacy · Terms, and a red pill "Get the Android app".

### Guest login sheet
Guests can read everything. When a guest taps **like, comment, share, save, follow, vote, start quiz, create poll or For You**, open the login sheet (Alpine). Remember the action they tried and finish it right after login.
- **Mobile:** a bottom sheet (`.sheet`) over a dimmed page.
- **Desktop:** a centred modal with the same content.

Sheet content:
- Handle bar, then the title "**Log in to continue**" (26px/800) and a round × close button
- "Log in to like, comment, share, save, follow pages or vote in polls. Reading stays free without an account."
- Phone field: left segment with phone icon, "+91" and a chevron; right segment with the input "Enter mobile number"
- Red **Send OTP →** button (`.btn-primary-lg`)
- "or" divider, then an outline button with the Android icon: "Open in the Android app"
- "By continuing you agree to the Terms and Privacy Policy."

The next step in the same sheet is OTP: 6 boxes (filled boxes get a black border, the current one a red border), "Resend in 0:24", red **Verify**. A **new user** then goes through onboarding (section 5.9).

---

## 4. Data model (MySQL migrations + Eloquent models)

`users` (name, phone unique, language en|hi, city_id, text_size, theme, last_active_at, soft deletes) ·
`otp_requests` (phone, code_hash, expires_at, attempts) ·
`categories` (name_en, name_hi, slug, sort_order, is_sensitive) · `category_user` ·
`cities` (name_en, name_hi, state) ·
`pages` (name, slug, type topic|city|show, category_id, city_id, avatar_path, cover_path, description, desk_label, verified) · `page_follows` ·
`sources` (name, url) ·
`feed_sources` (name, url, type agency|rss, trusted, active, last_fetched_at) ·
`feed_items` (feed_source_id, url unique, title, body, image_url, published_at, cluster_id, processed) ·
`story_clusters` (story_id) ·
`stories` (page_id, category_id, source_id, source_url, status draft|pending_approval|published|rejected|corrected|removed, publish_mode auto|manual, is_breaking, photo_path, photo_credit, read_minutes, published_at, approved_by) ·
`story_versions` (story_id, language en|hi, headline, card_text ≤60 words, key_points json[3], why_it_matters, simple_text, full_text, audio_path, audio_seconds, ai_assisted) — full-text index on headline + card_text ·
`ai_checks` (story_id, risk_level low|sensitive, sensitive_reasons json, confidence, fact_mismatches json, copy_score, decision auto|approval) ·
`approvals` (story_id, editor_id, action approved|edited|rejected, reason, acted_at) ·
`auto_publish_settings` (sensitive_categories json, sensitive_keywords json, confidence_threshold, copy_threshold) ·
`page_stories` (page_id, story_id, image_path, expires_at) — 24-hour story circles ·
`likes`, `reactions` (type surprising|unfair|need_info|good_news), `comments` (status visible|hidden), `saves`, `shares` (channel) ·
`polls` (story_id nullable, created_by, question, status pending|live|closed|rejected, ends_at), `poll_options`, `poll_votes` (unique poll_id+user_id) ·
`briefings` (date unique, story_ids json[5], audio_path_en, audio_path_hi, audio_seconds) ·
`quizzes` (date unique, status), `quiz_questions` (quiz_id, story_id, question, options json[4], correct_index, explanation), `quiz_attempts` (user_id, quiz_id, score, completed_at) ·
`streaks` (user_id, current, longest, last_day, freezes_left) · `reading_events` (user_id, story_id, read_at) ·
`reports` (reporter_id, target_type, target_id, reason, status) ·
`admins` (name, email, password, role editor|chief|admin)

Write seeders with realistic demo content: 8 categories, 6 cities (Lucknow, Delhi, Mumbai, Jaipur, Patna, Bhopal), 10 pages, 30 published stories in both languages with photos from `storage/app/demo/`, today's briefing, today's quiz and 3 polls. Use placeholder images if real ones aren't available.

---

## 5. Pages (build each exactly like this)

Desktop container: max 1280px (story page 1200px), 24px gutters. Two-column pages use flex-wrap: the main column grows (`flex: 999 1 640px`) and the right rail is 300–340px, stacking under the main column on mobile.

### 5.1 Home `/`
1. Greeting row: "**Good Morning, {name}**" (32px/900; "Good Afternoon"/"Good Evening" by IST time; "Today's top news" for guests) and a sub-line "Here's what's happening today · {weekday}, {date}". On the right: a row of story circles (`.story-ring`, 60px). The first circle is a solid red circle with a white "5" linking to Morning 5, then followed pages, or top categories for guests.
2. Hero row:
   - **Left (wide):** the top story as a hero card, min-height 420px, photo with scrim. Top-left `.photo-pill` "Cricket · Top story". Headline 34px/900 white at the bottom-left, meta "25 min ago · 3 min read · Source: X", and a white pill "▶ Listen" at the bottom-right.
   - **Right column:**
     - A **Morning 5** card: `.player-bg`, tilted logo tile, "Morning 5 / Top 5 stories in 5 minutes", a waveform, "2 of 5 read today" and a white round play button.
     - A black **Daily quiz** card: yellow ? icon tile, "Daily quiz / 5 questions · keeps your {n}-day streak" and a white "Start" pill.
3. Two columns:
   - **Main:** "Latest News" (22px/900) and "See more ›". A grid of white cards (`auto-fill, minmax(240px,1fr)`), each with a photo (160px), a red uppercase tag, a 16px/800 headline and meta. Infinite scroll or a "Load more" button.
   - **Right rail:**
     - **Trending Now** card: red flame icon, then 5 rows with a red number badge, title and a red ↗ icon.
     - **Pages to follow** card: 44px rounded-square avatar, name, one-line description and an outline "Follow" pill.
     - **Poll** card: title and "+ Create poll" outline pill, the question, and option buttons (`.poll-option`).
- **Mobile web version (same route):** greeting smaller, story circles, chips row, hero card 260px tall, then a slim red Morning 5 strip (logo tile, title, play button), Latest News as horizontal cards (96×80 photo left, text right), and the Trending Now card.

### 5.2 For You `/for-you` (login required)
Only stories from the user's chosen categories and followed pages. **No story circles.**
- **Desktop:** a centred column (max 560px) of large swipe-style cards. Each card has:
  - a photo (300px) with a red category pill and a white "🔊 Listen 0:40" pill on it
  - a page row (avatar, name, "Source · time", "Following" outline pill)
  - a headline (21px/800), the 60-word card text and "Read more ›"
  - an action row: heart+count (red when liked), comment+count, share, then **हिं/EN** pill and save
- Keyboard ↑/↓ and swipe/scroll snapping move between cards. Below the last new card, show "**You're all caught up ✓**".
- **Mobile:** one card fills the screen and snaps on vertical scroll.

### 5.3 Story `/story/{slug}`
Main column:
- Breadcrumb "Home › Sports › Cricket"
- H1 (40px/900, mobile 25px)
- Meta row: page avatar (rounded square), page name, "Source: X · time · n min read", "Follow +" outline pill; on the right a **Listen · 2 min** tint pill and a "Read in हिंदी" ghost pill (switches the language of every text block, without reloading)
- Big photo (440px, radius 26px)
- Segmented control (`.seg`): **Key Points** (default) / **Explain Simply** / **Full Story**, switching the content below with Alpine
- Key Points: white card with 3 bullets, each with a red `.check-dot`
- `.why-box` with an "i" icon, "Why it matters" and one sentence
- Action row: like pill with count, reaction pills (Surprising, Unfair, Need more info, Good news), and on the right a black "Share as image" pill and a save icon
- Comments card: "Comments · 96", an input (or "Log in to comment…" for guests), the list, and "Like · Reply · Report"
- Footer note: "Summary by our editors, AI-assisted. Source: X · Read original · Report this story"

Right rail:
- Morning 5 mini card (`.player-bg`)
- Related stories (86×66 photo, tag, title)
- Poll card with results bars (`.poll-result`)
- One dashed "AD · 300×250" placeholder slot

On mobile web, a fixed bottom bar shows the like count, save, and a red **Share to WhatsApp** pill.

The Listen button plays `story_versions.audio_path` in a mini player docked at the bottom (Alpine plus the HTML5 `<audio>` element), with a speed control of 1x/1.25x/1.5x.

Story pages need full SEO: title, meta description from the card text, canonical, Open Graph image, and `NewsArticle` JSON-LD.

### 5.4 Topic / page `/page/{slug}` (for example Cricket)
- Wide banner (320px, radius 30px) with the cover photo and a left-to-right dark fade. Over it:
  - "SPORTS DESK" (pink, small caps)
  - "Cricket" (52px/900)
  - the description
  - "[n] followers · Updated 25 min ago"
  - a red **Follow +** pill and an outline "Share" pill
- Chips: Latest (red, active), Explainers, Live scores, Polls, …
- **Main column:**
  - a black result strip ("RESULT" red label, score line, "Match report ›"), shown only when the page has a pinned item
  - a TOP STORY horizontal card (photo left, text right)
  - a grid of more stories (104×84 photo, tag, title, meta)
- **Right rail:** "Upcoming" (date tiles in pink tint with a red day number), then "Related topics" with Follow pills.
- **Mobile:** the banner is 250px and everything stacks.
- This page must **not** look like an Instagram profile: no avatar-plus-stats header and no square photo grid.

### 5.5 Explore `/explore`
- Title "Explore", with "Discover news by topics" under it
- 2×2 (desktop: 4-across) photo tiles for categories, with a scrim, the white name and a white round → button
- A **Trending Now** card
- **Pages to follow:** cards with an avatar, the name and a red "Follow" button

### 5.6 Briefing `/briefing`
- **Full-width player section** (`.player-bg`, radius 32px). It wraps into a stack on mobile.
  - **Left:**
    - calendar icon, "Today's Briefing" and the date
    - tilted logo tile (120px) next to "Morning 5" (46px/900) and "Top 5 stories in 5 minutes"
    - waveform bars
    - progress bar with 0:00 / 5:00
    - controls: 1x, previous, big white play/pause, next, list
  - **Right:** the `.briefing-list` card "In this briefing", 5 rows (number badge, 52×40 thumbnail, title, chevron). The playing row is highlighted.
- Below the player:
  - a black Daily quiz card ("Start quiz" pill)
  - a "This week" streak card (7 day circles: red ✓ for done, pink dot for today)
- **Polls:** "+ Create poll" outline pill and a grid of poll cards. Open polls show option buttons; closed or voted polls show `.poll-result` bars with percentages.
- Playing Morning 5 plays the joined briefing audio and moves the highlight through the list using the timestamps.

### 5.7 Daily quiz `/quiz` (login required)
- Top: round × button, "Question 2 of 5" with 5 progress segments (red for done), and a pink timer pill "⏱ 0:18"
- A photo (190px, radius 22px), a red tag, and the question (22px/800)
- 4 option buttons (56px, radius 16px) with letter badges A–D
- After answering:
  - **Correct:** green border, a green tint and the word "Correct"
  - **Wrong:** red border on the chosen option, and the correct one shown in green
- An explanation box with "Read the story ›", then a red "Next question →" button
- End screen: score, streak +1 animation, and "Share result" (generates an image)

### 5.8 Account `/account` (login required)
This is an account page, **not an Instagram profile**:
- Account card: red rounded-square avatar with the initial, name, chips for city and language, and an "Edit" pill
- 3 stat tiles: **red** "12 · day streak" (flame), white "4/5 · today's quiz", white "38 · read this week"
- "YOUR LIBRARY": a white list card with rows for Saved stories, Pages you follow, Liked and Reading history (pink icon square, label, count, chevron)
- "PREFERENCES": Topics, City, Language, Notifications (max per day), Text size, and a Dark mode switch
- Bottom links: "Help and grievance", plus a red "Log out"

### 5.9 Onboarding (shown once after the first login)
A centred card on desktop, full screen on mobile. A 3-step progress bar (red segments) and "1 of 3":
1. **Language:** two large cards (हिंदी with a red "अ" tile, English with a grey "A" tile). The selected card gets a red border, pink tint and red check.
2. **Categories (pick at least 1):** photo tiles in 2 columns (4 on desktop) with a scrim and white name. Selected tiles get a 3px red border and a red check badge. The button reads "Continue · 3 selected" and is disabled at 0.
3. **City:** search field, a pink "Use my current location" row, then a "POPULAR CITIES" list with a building icon, city name, state and a round selector. Then "Save and start reading".

### 5.10 Search `/search?q=`
Results as horizontal cards, with filters for type (stories, pages) and category, a no-results state, and "Recent searches" stored in localStorage.

### 5.11 Static pages
About, Grievance officer (name, email, phone, address and response times, as required by IT Rules 2021), Privacy, Terms and Community rules.

---

## 6. Features and behaviour

- **Language:** the हिं/EN toggle sets the user preference (a cookie for guests). Each story renders the `story_versions` row for that language, falling back to the other language if one is missing.
- **Likes, reactions, saves, follows, votes, comments:** AJAX (Alpine + `fetch`), optimistic UI, rate limited. Guests get the login sheet.
- **Share as image:** a server-generated 1080×1350 image of the card (Intervention Image or Browsershot), with the logo, headline and card text, sized for WhatsApp. Also offer the share link.
- **Streaks:** a day counts when the user reads 3 stories (`reading_events`) **or** finishes the quiz. Each user gets 2 freezes per month, used automatically. Days are measured in IST.
- **Polls:**
  - one vote per user
  - results show after voting
  - user-created polls start `pending` and go live after editor approval
  - polls close at `ends_at`
- **Comments:** profanity and abuse filter, plus a report button. Accounts younger than 24 hours can post at most 3 comments a day.
- **Reports:** for stories, comments and polls, going to the moderation queue.
- **Page stories:** 24-hour circles on Home. Tapping one opens a full-screen story viewer (Alpine) with progress bars, tap left/right, and swipe up to open the story.
- **Performance:** cache the home, topic and story pages in Redis (1–5 minutes; clear on publish), lazy-load images, use responsive `srcset`, and aim for under 2 seconds on 4G.
- **Accessibility:** semantic HTML, focus rings, every control reachable by keyboard, `aria-label` on icon buttons, and `prefers-reduced-motion` respected.

---

## 7. Auto-publishing newsroom (backend)

Pipeline (queued jobs):
1. **FetchFeeds** (every 5 min): pull active feed sources and save new `feed_items` (deduplicated by URL).
2. **ClusterItems:** group items about the same event using title similarity plus a time window. One cluster becomes one story.
3. **GenerateDrafts:** `AiWriter` asks Claude for strict JSON:
   - EN + HI headline
   - 60-word card text
   - 3 key points
   - why it matters
   - simple explanation
   - full story
   - category, page and city suggestions
   - risk flags

   Write in **our own words; never copy sentences from the source**.
4. **RunChecks:**
   - fact check: compare names, numbers and dates in the draft with the source
   - copy check: n-gram overlap with the source, scored as `copy_score`
   - confidence score
   - sensitive check: category in the sensitive list, keyword match, or AI risk flags (politics, elections, crime, courts, religion, communal issues, health claims, allegations against named people, deaths, disasters, children, breaking news)
5. **RouteStory:**
   - **Auto-publish** if every check passes and confidence is at or above the threshold.
   - Otherwise set `pending_approval`, which triggers **NotifyEditor**: a notice in Filament plus an email (WhatsApp later).
6. **On publish:**
   - GenerateAudio (both languages)
   - clear the caches
   - update the sitemaps
7. **Daily jobs:**
   - PickMorning5 at 06:00 IST (top stories by importance and reads, with an editor override)
   - BuildBriefingAudio
   - DraftQuiz at 21:00 (auto-approved when every source story is low-risk)
   - ExpirePageStories and ClosePolls every 15 min
   - UpdateStreaks at 00:05 IST

**Filament admin** (`/admin`, roles editor / chief / admin):
- **Approval queue:** the story with its AI check results side by side with the source. Actions: Approve, Edit then publish, Reject with a reason.
- **Stories:** list, edit both language versions, upload a photo, unpublish in one click, add a correction label.
- **Feeds:** add, enable or disable sources, and mark them as trusted.
- **Auto-publish settings:** sensitive categories, keywords, confidence and copy thresholds.
- **Auto-publish log:** what was published automatically and why.
- **Briefing and quiz:** view and override today's Morning 5 and quiz.
- **Moderation:** reported comments, pending user polls and reports.
- **Pages and categories:** full CRUD.

---

## 8. Routes (web)

```
GET  /                          home
GET  /for-you                   auth
GET  /briefing                  briefing + polls
GET  /explore
GET  /search
GET  /story/{slug}
GET  /page/{slug}
GET  /quiz                      auth
GET  /account                   auth
GET  /onboarding                auth, first login only
POST /auth/otp/send             throttle
POST /auth/otp/verify
POST /logout
POST /stories/{story}/like      auth   (DELETE to unlike)
PUT  /stories/{story}/reaction  auth
POST /stories/{story}/save      auth   (DELETE to unsave)
POST /stories/{story}/share     auth   -> share image url
POST /stories/{story}/read      auth   (streak)
GET|POST /stories/{story}/comments
POST /pages/{page}/follow       auth   (DELETE to unfollow)
POST /polls/{poll}/vote         auth
POST /polls                     auth   (user poll -> pending)
POST /quiz/answers              auth
POST /reports                   auth
POST /preferences/language
GET  /sitemap.xml, /news-sitemap.xml
```

---

## 9. Environment variables (put placeholders in `.env.example`)

`ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `TTS_DRIVER` (fake|google), `GOOGLE_TTS_KEY`, `SMS_DRIVER` (log|msg91), `MSG91_AUTH_KEY`, `MSG91_TEMPLATE_ID`, `MSG91_SENDER_ID`, `AWS_*`, `GA4_ID`, `SENTRY_LARAVEL_DSN`, `REDIS_*`, `TIMEZONE=Asia/Kolkata`.

With `TTS_DRIVER=fake` and `SMS_DRIVER=log`, everything must run locally without paid services. When `ANTHROPIC_API_KEY` is empty, `AiWriter` returns seeded sample drafts.

---

## 10. Milestones (stop after each and show me how to run it)

1. **Setup:** Laravel project, theme installed, layout (header, footer, mobile nav), Blade components (`x-logo`, `x-icon`, `x-story-card`, `x-hero-card`, `x-poll-card`, `x-login-sheet`), migrations, seeders.
2. **Reading:** Home, Story (all 3 tabs, language switch, Listen with fake audio), Topic page, Explore, Search. Mobile web and desktop both pixel-close to the design.
3. **Accounts:** OTP login sheet, onboarding, account page, For You, likes, reactions, saves, follows, comments, share image.
4. **Habit features:** Briefing page with the player, Morning 5, daily quiz, streaks, polls (vote, create, results).
5. **Newsroom:** feeds, clustering, AI drafts, checks, auto-publish routing, approval queue, Filament admin, scheduler.
6. **Launch prep:** SEO and sitemaps, caching, GA4 and Sentry, the accessibility pass, Pest tests for the login, streaks, poll voting, auto-publish routing and the copy check, and a README with setup steps.

At each milestone:
- run `php artisan test`
- make sure `npm run build` passes
- list anything you couldn't finish

Don't invent features beyond this document. Where something is unclear, pick the simplest option and note it in the README.
