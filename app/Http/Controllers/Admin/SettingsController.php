<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/** M17 Platform settings (PRD §13). Every save is audit-logged with a diff. */
class SettingsController extends Controller
{
    public static function tabs(): array
    {
        $tzs = ['Asia/Kolkata', 'UTC', 'Asia/Dubai', 'Asia/Singapore', 'Europe/London', 'America/New_York'];

        return [
            'general' => ['General', [
                'general.platform_name' => ['text', 'Panel name', 'Shown in the admin header and staff emails.', 'required|string|max:60'],
                'general.support_email' => ['email', 'Newsroom contact email', null, 'nullable|email'],
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
        $before = [];
        $after = [];
        foreach ($fields as $key => $f) {
            $in = str_replace('.', '__', $key);
            $value = match ($f[0]) {
                'bool' => $r->boolean($in),
                'number' => is_numeric($data[$in] ?? null) ? $data[$in] + 0 : null,
                'lines' => collect(preg_split('/\r?\n/', (string) ($data[$in] ?? '')))->map('trim')->filter()->values()->all(),
                'roles' => array_values((array) ($data[$in] ?? [])),
                'file' => $r->hasFile($in) ? $r->file($in)->store('branding', 'public') : setting($key),
                'secret' => ($data[$in] ?? '') === '' ? setting($key) : $data[$in],
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
