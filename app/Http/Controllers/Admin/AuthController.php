<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminLoginLog;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $admin = Admin::where('email', $data['email'])->first();
        $log = fn (bool $ok, ?string $why = null) => AdminLoginLog::create(['admin_id' => $admin?->id, 'email' => $data['email'], 'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500), 'success' => $ok, 'failure_reason' => $why]);

        if (! $admin || ! $admin->password || ! Hash::check($data['password'], $admin->password)) {
            $log(false, 'bad_credentials');

            return back()->withInput($request->only('email'))->withErrors(['email' => 'Email or password is incorrect.']);
        }
        if ($admin->status !== 'active') {
            $log(false, 'inactive');

            return back()->withInput($request->only('email'))->withErrors(['email' => 'This account is inactive. Contact a Super Admin.']);
        }
        if (! empty(setting('security.ip_allowlist')) && ! in_array($request->ip(), (array) setting('security.ip_allowlist'), true)) {
            $log(false, 'ip_not_allowed');

            return back()->withErrors(['email' => 'Sign-in is not allowed from this network.']);
        }

        if ($admin->hasTwoFactor()) {
            $request->session()->put('login.2fa_id', $admin->id);
            $request->session()->put('login.remember', $request->boolean('remember'));

            return redirect()->route('admin.2fa.challenge');
        }

        return $this->completeLogin($request, $admin, $request->boolean('remember'));
    }

    private function completeLogin(Request $request, Admin $admin, bool $remember)
    {
        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();
        $request->session()->put('last_activity_at', time());
        $admin->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        AdminLoginLog::create(['admin_id' => $admin->id, 'email' => $admin->email, 'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500), 'success' => true]);
        Audit::log('auth.login', $admin);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function twoFactorForm(Request $request)
    {
        if (! $request->session()->has('login.2fa_id')) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.two-factor');
    }

    public function twoFactorVerify(Request $request)
    {
        $id = $request->session()->get('login.2fa_id');
        $admin = $id ? Admin::find($id) : null;
        if (! $admin) {
            return redirect()->route('admin.login');
        }
        $request->validate(['code' => 'required|string']);
        $code = preg_replace('/\s+/', '', $request->input('code'));

        $ok = false;
        if (preg_match('/^\d{6}$/', $code)) {
            $ok = (new Google2FA)->verifyKey($admin->two_factor_secret, $code, 1);
        } else {
            $codes = $admin->two_factor_recovery_codes ?? [];
            if (in_array($code, $codes, true)) {
                $admin->two_factor_recovery_codes = array_values(array_diff($codes, [$code]));
                $admin->save();
                $ok = true;
            }
        }
        if (! $ok) {
            AdminLoginLog::create(['admin_id' => $admin->id, 'email' => $admin->email, 'ip' => $request->ip(), 'success' => false, 'failure_reason' => 'bad_2fa']);

            return back()->withErrors(['code' => 'That code is not valid. Try again or use a recovery code.']);
        }
        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget('login.2fa_id');

        return $this->completeLogin($request, $admin, $remember);
    }

    public function logout(Request $request)
    {
        Audit::log('auth.logout', $request->user('admin'));
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function forgotForm()
    {
        return view('admin.auth.forgot');
    }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        Password::broker('admins')->sendResetLink($request->only('email'));

        // Same message either way so the form does not reveal which emails exist.
        return back()->with('status', 'If that email belongs to an admin, a reset link is on its way.');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('admin.auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min((int) setting('security.password_min_length', 10))->mixedCase()->numbers()]]);
        $status = Password::broker('admins')->reset($request->only('email', 'password', 'password_confirmation', 'token'), function (Admin $admin, string $password) {
            $admin->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('admin.login')->with('status', 'Password updated. You can sign in now.')
            : back()->withErrors(['email' => __($status)]);
    }

    public function inviteForm(string $token)
    {
        $admin = Admin::where('invite_token', hash('sha256', $token))->where('invite_expires_at', '>', now())->first();
        if (! $admin) {
            return redirect()->route('admin.login')->withErrors(['email' => 'This invitation link is invalid or has expired. Ask a Super Admin to resend it.']);
        }

        return view('admin.auth.invite', compact('admin', 'token'));
    }

    public function acceptInvite(Request $request, string $token)
    {
        $admin = Admin::where('invite_token', hash('sha256', $token))->where('invite_expires_at', '>', now())->firstOrFail();
        $data = $request->validate(['name' => 'required|string|max:100', 'password' => ['required', 'confirmed', PasswordRule::min((int) setting('security.password_min_length', 10))->mixedCase()->numbers()]]);
        $admin->forceFill(['name' => $data['name'], 'password' => $data['password'], 'status' => 'active', 'invite_token' => null, 'invite_expires_at' => null])->save();
        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        Audit::log('admin.invite_accepted', $admin);

        return redirect()->route('admin.dashboard')->with('ok', 'Welcome! Your account is ready.');
    }
}
