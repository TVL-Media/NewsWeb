<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Phone + OTP login for readers (POST /auth/otp/send, /auth/otp/verify, /logout). */
class OtpController extends Controller
{
    private const PHONE_RULE = ['required', 'string', 'regex:/^[6-9]\d{9}$/'];

    public function send(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate(['phone' => self::PHONE_RULE], [
            'phone.regex' => __('Enter a valid 10-digit mobile number.'),
        ]);

        $otp->send($data['phone']);

        return response()->json(['sent' => true, 'resend_in' => OtpService::RESEND_SECONDS]);
    }

    public function verify(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => self::PHONE_RULE,
            'code' => ['required', 'digits:6'],
        ]);

        if (! $otp->verify($data['phone'], $data['code'])) {
            throw ValidationException::withMessages(['code' => __('That OTP is wrong or has expired.')]);
        }

        $user = User::withTrashed()->firstOrNew(['phone' => $data['phone']]);
        if ($user->exists && $user->effectiveStatus() === 'suspended') {
            // Set in the admin panel (Readers → Account action).
            throw ValidationException::withMessages(['code' => __('Your account is suspended. Contact the grievance officer if you think this is a mistake.')]);
        }
        if ($user->trashed()) {
            $user->restore();
        }
        if (! $user->exists) {
            // New reader: start with the language they were browsing in.
            $user->fill(['language' => app()->getLocale()])->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_active_at' => now()])->save();

        return response()->json([
            'new' => ! $user->onboarded_at,
            'redirect' => $user->onboarded_at ? url()->previous(route('home')) : route('onboarding'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
