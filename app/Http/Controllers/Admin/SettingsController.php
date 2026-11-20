<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Features;
use App\Support\Settings;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/** M17 Platform settings (PRD §13). Every save is audit-logged with a diff. */
class SettingsController extends Controller
{
    public const FEATURE_HELP = [
        'ai_autopublish' => 'Stories that pass every automatic check go live without an editor. Off: every AI draft waits in the Approval queue.',
        'listen' => 'Listen buttons and the audio player on stories. Needs a text-to-speech key.',
        'morning5' => 'A spoken briefing of the top 5 stories, made every day at 06:00. Needs Listen.',
        'quiz' => 'Five questions from the news every day, drafted at 21:00.',
        'polls' => 'Polls on stories, pages and the Briefing page.',
        'comments' => 'Readers can comment on stories and reels.',
        'reels' => 'Short vertical news videos. Making reels needs FFmpeg on the server.',
        'otp_login' => 'Readers log in with their mobile number and an SMS code. Needs an SMS provider on a live site.',
    ];

    public static function tabs(): array
    {
        $tzs = ['Asia/Kolkata', 'UTC', 'Asia/Dubai', 'Asia/Singapore', 'Europe/London', 'America/New_York'];

        $features = [];
        foreach (Features::LABELS as $key => $label) {
            $features["features.{$key}"] = ['feature', $label, self::FEATURE_HELP[$key], 'nullable|boolean'];
        }
        $sms = [
            'sms.provider' => ['select', 'SMS provider', 'OTP messages in India must go through a DLT-registered provider.', 'required|in:msg91,log', ['msg91' => 'MSG91 (DLT)', 'log' => 'None: write OTPs to the log file (development only)']],
            'sms.api_key' => ['secret', 'MSG91 auth key', 'MSG91 → API → Auth key.', 'nullable|string|max:300'],
            'sms.sender_id' => ['text', 'DLT sender ID', 'The 6-letter header approved on the DLT portal, e.g. NGNEWS.', 'nullable|string|max:20'],
            'sms.template_id' => ['text', 'DLT template ID', 'The MSG91 template linked to your approved DLT OTP template.', 'nullable|string|max:60'],
        ];
        if (! app()->isProduction()) {
            $sms['sms.dev_otp'] = ['bool', 'Development OTP 123456', 'Every reader can log in with 123456. Only works while APP_ENV is not "production"; it is ignored on a live site.', 'nullable|boolean'];
        }

        return [
            'general' => ['General', [
                'site.name' => ['text', 'Website name', 'Shown in the header, page titles, share images and emails.', 'required|string|max:60'],
                'site.tagline' => ['text', 'Tagline', 'Shown in the browser tab on the home page. Leave empty for the default.', 'nullable|string|max:120'],
                'site.logo' => ['file', 'Logo', 'PNG, JPG or WebP, about 200 × 56 px. Empty = the "N" mark and the website name.', 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048'],
                'site.favicon' => ['file', 'Favicon', 'Square PNG or ICO, 64 × 64 px.', 'nullable|file|mimes:png,ico|max:512'],
                'site.brand_color' => ['color', 'Brand colour', 'Buttons, badges, the logo mark and share images.', ['required', 'regex:/^#[0-9a-fA-F]{6}$/']],
                'site.locale' => ['select', 'Default language', 'For visitors who have not picked a language yet.', 'required|in:en,hi', ['en' => 'English', 'hi' => 'हिंदी (Hindi)']],
                'site.timezone' => ['select', 'Website timezone', 'The daily quiz, Morning 5 and "today" follow this. Set it before you publish: changing it later shifts the times of existing stories.', 'required|timezone', array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers())],
                'site.contact_email' => ['email', 'Contact email', 'Shown on the About page.', 'nullable|email'],
                'general.platform_name' => ['text', 'Admin panel name', 'Shown in the admin header and staff emails. Empty = "'.site_name().' Newsroom".', 'nullable|string|max:60'],
                'general.support_email' => ['email', 'Newsroom contact email', null, 'nullable|email'],
            ]],
            'features' => ['Features', $features],
            'ai' => ['AI', [
                'ai.api_key' => ['secret', 'Claude API key', 'From console.anthropic.com → API keys. Without a key, editors write and publish stories themselves.', 'nullable|string|max:300'],
                'ai.model' => ['text', 'Model', 'Default: claude-opus-5-5.', 'required|string|max:80'],
            ]],
            'tts' => ['Text-to-speech', [
                'tts.provider' => ['select', 'Provider', 'Reads stories aloud for Listen and Morning 5, in English and Hindi.', 'required|in:google,azure,fake', ['google' => 'Google Cloud Text-to-Speech', 'azure' => 'Azure AI Speech', 'fake' => 'None (silent test audio, development only)']],
                'tts.google_key' => ['secret', 'Google API key', 'Google Cloud console → APIs & Services → Credentials. Enable "Cloud Text-to-Speech API".', 'nullable|string|max:300'],
                'tts.azure_key' => ['secret', 'Azure Speech key', 'Azure portal → your Speech resource → Keys and Endpoint.', 'nullable|string|max:300'],
                'tts.azure_region' => ['text', 'Azure region', 'For example centralindia.', 'nullable|string|max:40'],
            ]],
            'sms' => ['SMS', $sms],
            'storage' => ['Storage', [
                'storage.driver' => ['select', 'Where uploads are stored', 'Switching does not move files that are already uploaded.', 'required|in:public,s3', ['public' => 'This server (storage/app/public)', 's3' => 'Amazon S3']],
                'storage.aws_key' => ['secret', 'AWS access key ID', 'An IAM user that can read and write the bucket.', 'nullable|string|max:300'],
                'storage.aws_secret' => ['secret', 'AWS secret access key', null, 'nullable|string|max:300'],
                'storage.aws_bucket' => ['text', 'S3 bucket', null, 'nullable|string|max:120'],
                'storage.aws_region' => ['text', 'S3 region', 'For example ap-south-1 (Mumbai).', 'nullable|string|max:40'],
                'storage.cdn_url' => ['text', 'CloudFront URL', 'Optional. Files are served from here, e.g. https://d1234.cloudfront.net', 'nullable|url|max:255'],
            ]],
            'analytics' => ['Analytics & errors', [
                'analytics.ga4_id' => ['text', 'Google Analytics 4 ID', 'For example G-XXXXXXXXXX. Empty = no Google Analytics.', 'nullable|string|max:40'],
                'analytics.firebase_config' => ['json', 'Firebase web config', 'Firebase console → Project settings → Your apps → Config, as JSON. Needs a measurementId. Empty = Firebase is not loaded.', 'nullable|json|max:4000'],
                'analytics.sentry_dsn' => ['secret', 'Sentry DSN', 'sentry.io → Project settings → Client keys (DSN). Errors are reported there.', 'nullable|string|max:300'],
            ]],
            'legal' => ['Legal', [
                'legal.grievance_name' => ['text', 'Grievance officer name', 'Required for Indian digital news publishers (IT Rules, 2021).', 'required|string|max:120'],
                'legal.grievance_email' => ['email', 'Grievance officer email', null, 'required|email'],
                'legal.grievance_phone' => ['text', 'Grievance officer phone', null, 'nullable|string|max:80'],
                'legal.grievance_address' => ['textarea', 'Grievance officer address', null, 'required|string|max:500'],
                'legal.about_html' => ['html', 'About page', 'HTML. Empty = the built-in About text.', 'nullable|string|max:50000'],
                'legal.privacy_html' => ['html', 'Privacy policy', 'HTML. Empty = the built-in privacy policy.', 'nullable|string|max:50000'],
                'legal.terms_html' => ['html', 'Terms of use', 'HTML. Empty = the built-in terms.', 'nullable|string|max:50000'],
            ]],
            'datetime' => ['Date & time', [
                'datetime.timezone' => ['select', 'Panel timezone', 'Scheduling and reports use this timezone. The website itself always works in IST.', 'required|timezone', array_combine($tzs, $tzs)],
                'datetime.date_format' => ['select', 'Date format', null, 'required|string', ['d M Y' => '05 Nov 2026', 'd/m/Y' => '05/11/2026', 'Y-m-d' => '2026-11-05', 'M j, Y' => 'Nov 5, 2026']],
                'datetime.time_format' => ['select', 'Time format', null, 'required|in:24h,12h', ['24h' => '24-hour (15:30)', '12h' => '12-hour (3:30 PM)']],
            ]],
            'content' => ['Stories', [
                'content.rereview_published_edits' => ['bool', 'Edits to live stories by writers go back through review', 'Editors with "Edit live stories" can always edit directly.', 'nullable|boolean'],
                'content.duplicate_window_days' => ['number', 'Duplicate-headline check window (days)', null, 'required|integer|min:1|max:60'],
                'content.max_image_mb' => ['number', 'Max photo size (MB)', null, 'required|integer|min:1|max:50'],
            ]],
            'editorial' => ['Editorial & moderation', [
                'editorial.guidelines' => ['textarea', 'Editorial guidelines', 'Shown to writers and reviewers.', 'nullable|string|max:20000'],
                'editorial.moderation_guidelines' => ['textarea', 'Moderation guidelines', 'Shown on every report.', 'nullable|string|max:20000'],
                'editorial.rejection_reasons' => ['lines', 'Rejection reasons', 'One per line.', 'nullable|string'],
                'editorial.review_sla_hours' => ['number', 'Review target (hours)', 'Stories waiting longer are highlighted.', 'required|integer|min:1|max:168'],
                'polls.low_participation_ratio' => ['number', 'Low poll participation threshold', 'Fraction of the median, e.g. 0.3 = 30%.', 'required|numeric|min:0.05|max:1', null, '0.05'],
            ]],
            'security' => ['Security', [
                'security.enforce_2fa_roles' => ['roles', 'Require two-factor for these roles', null, 'nullable|array'],
                'security.session_timeout_minutes' => ['number', 'Sign out after inactivity (minutes)', null, 'required|integer|min:5|max:1440'],
                'security.password_min_length' => ['number', 'Minimum password length', null, 'required|integer|min:8|max:64'],
                'security.ip_allowlist' => ['lines', 'Admin IP allowlist', 'One IP per line. Leave empty to allow any network.', 'nullable|string'],
                'retention.login_logs_months' => ['number', 'Keep admin login records (months)', null, 'required|integer|min:1|max:60'],
            ]],
        ];
    }

    public function show(?string $tab = 'general')
    {
        $tabs = self::tabs();
        $tab ??= 'general';
        abort_unless(isset($tabs[$tab]), 404);

        return view('admin.settings.index', ['tabs' => $tabs, 'tab' => $tab, 'roles' => Role::where('guard_name', 'admin')->pluck('name')]);
    }

    public function update(Request $r, string $tab)
    {
        $fields = self::tabs()[$tab][1] ?? abort(404);
        $rules = [];
        foreach ($fields as $key => $f) {
            $rules[str_replace('.', '__', $key)] = $f[3];
        }
        $data = $r->validate($rules);

        if ($tab === 'storage' && $data['storage__driver'] === 's3') {
            foreach (['storage.aws_key' => 'AWS access key ID', 'storage.aws_secret' => 'AWS secret access key', 'storage.aws_bucket' => 'S3 bucket', 'storage.aws_region' => 'S3 region'] as $key => $label) {
                $in = str_replace('.', '__', $key);
                if (blank($data[$in] ?? null) && (blank(setting($key)) || $r->boolean($in.'__clear'))) {
                    return back()->withInput()->withErrors([$in => "{$label} is needed to store files on S3."]);
                }
            }
        }

        $before = [];
        $after = [];
        foreach ($fields as $key => $f) {
            $in = str_replace('.', '__', $key);
            $value = match ($f[0]) {
                'bool', 'feature' => $r->boolean($in),
                'number' => is_numeric($data[$in] ?? null) ? $data[$in] + 0 : null,
                'lines' => collect(preg_split('/\r?\n/', (string) ($data[$in] ?? '')))->map('trim')->filter()->values()->all(),
                'roles' => array_values((array) ($data[$in] ?? [])),
                'file' => $r->hasFile($in) ? $r->file($in)->store('branding', 'public') : ($r->boolean($in.'__clear') ? null : setting($key)),
                // Empty = keep the saved key; the "Remove" tick clears it.
                'secret' => $r->boolean($in.'__clear') ? null : (($data[$in] ?? '') === '' ? setting($key) : trim($data[$in])),
                default => $data[$in] ?? null,
            };
            $old = setting($key);
            if (json_encode($old) !== json_encode($value)) {
                $before[$key] = $f[0] === 'secret' ? '••••' : $old;
                $after[$key] = $f[0] === 'secret' ? '•••• (changed)' : $value;
                Settings::set($key, $value, auth('admin')->id());
            }
        }
        if ($after) {
            Audit::log('settings.updated', null, $before, $after, null, self::tabs()[$tab][0].' settings');
        }

        return back()->with('ok', $after ? count($after).' setting(s) saved.' : 'No changes.');
    }

}
