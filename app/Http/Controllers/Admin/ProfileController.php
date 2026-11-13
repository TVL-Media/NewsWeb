<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Support\Audit;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use PragmaRX\Google2FA\Google2FA;

class ProfileController extends Controller
{
    public function edit(Request $r)
    {
        $a = $r->user('admin');
        $qr = null;
        $secret = $r->session()->get('2fa.pending_secret');
        if ($secret) {
            $url = (new Google2FA)->getQRCodeUrl(setting('general.platform_name', 'Newsroom').' Admin', $a->email, $secret);
            $qr = (new Writer(new ImageRenderer(new RendererStyle(180, 1), new SvgImageBackEnd)))->writeString($url);
        }

        return view('admin.profile', ['admin' => $a, 'qr' => $qr, 'secret' => $secret, 'codes' => $r->session()->get('2fa.codes')]);
    }

    public function update(Request $r)
    {
        $a = $r->user('admin');
        $data = $r->validate(['name' => 'required|string|max:100', 'phone' => 'nullable|string|max:30', 'timezone' => 'nullable|timezone', 'avatar' => 'nullable|image|max:2048']);
        if ($r->hasFile('avatar')) {
            $data['avatar_path'] = $r->file('avatar')->store('avatars', config('filesystems.media'));
        }
        unset($data['avatar']);
        $a->update($data);

        return back()->with('ok', 'Profile saved.');
    }

    public function password(Request $r)
    {
        $a = $r->user('admin');
        $r->validate(['current_password' => 'required', 'password' => ['required', 'confirmed', Password::min((int) setting('security.password_min_length', 10))->mixedCase()->numbers()]]);
        if (! Hash::check($r->current_password, $a->password)) {
            return back()->withErrors(['current_password' => 'Current password is not correct.']);
        }
        $a->update(['password' => $r->password]);
        Auth::guard('admin')->logoutOtherDevices($r->password);
        Audit::log('auth.password_changed', $a);

        return back()->with('ok', 'Password changed. Other sessions were signed out.');
    }

    public function twoFactorStart(Request $r)
    {
        $r->session()->put('2fa.pending_secret', (new Google2FA)->generateSecretKey(32));

        return back();
    }

    public function twoFactorConfirm(Request $r)
    {
        $secret = $r->session()->get('2fa.pending_secret');
        $r->validate(['code' => 'required|digits:6']);
        if (! $secret || ! (new Google2FA)->verifyKey($secret, $r->code, 1)) {
            return back()->withErrors(['code' => 'That code did not match. Check your authenticator app’s time and try again.']);
        }
        $codes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
        $a = $r->user('admin');
        $a->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => $codes, 'two_factor_confirmed_at' => now()])->save();
        $r->session()->forget('2fa.pending_secret');
        $r->session()->flash('2fa.codes', $codes);
        Audit::log('auth.2fa_enabled', $a);

        return back()->with('ok', 'Two-factor authentication is on. Save your recovery codes.');
    }

    public function twoFactorDisable(Request $r)
    {
        $a = $r->user('admin');
        $r->validate(['confirm_password' => 'required']);
        if (! Hash::check($r->confirm_password, $a->password)) {
            return back()->withErrors(['confirm_password' => 'That password is not correct.']);
        }
        $roles = (array) setting('security.enforce_2fa_roles', ['Super Admin']);
        if ($a->roles->pluck('name')->intersect($roles)->isNotEmpty()) {
            return back()->with('error', 'Your role requires two-factor authentication, so it cannot be turned off.');
        }
        $a->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        Audit::log('auth.2fa_disabled', $a);

        return back()->with('ok', 'Two-factor authentication turned off.');
    }

    public function regenerateCodes(Request $r)
    {
        $a = $r->user('admin');
        $codes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
        $a->forceFill(['two_factor_recovery_codes' => $codes])->save();
        $r->session()->flash('2fa.codes', $codes);

        return back()->with('ok', 'New recovery codes generated. The old ones no longer work.');
    }

    public function logoutOthers(Request $r)
    {
        $r->validate(['confirm_password' => 'required']);
        if (! Hash::check($r->confirm_password, $r->user('admin')->password)) {
            return back()->withErrors(['confirm_password' => 'That password is not correct.']);
        }
        Auth::guard('admin')->logoutOtherDevices($r->confirm_password);
        DB::table('sessions')->where('user_id', $r->user('admin')->id)->where('id', '!=', $r->session()->getId())->delete();

        return back()->with('ok', 'Signed out of all other devices.');
    }

    public function notifications(Request $r)
    {
        $items = AdminNotification::where('admin_id', $r->user('admin')->id)->latest()->paginate(30);

        return view('admin.notifications', compact('items'));
    }

    public function readAll(Request $r)
    {
        AdminNotification::where('admin_id', $r->user('admin')->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function openNotification(Request $r, AdminNotification $notification)
    {
        abort_unless($notification->admin_id === $r->user('admin')->id, 404);
        $notification->update(['read_at' => now()]);

        return redirect($notification->url ?: route('admin.dashboard'));
    }
}
