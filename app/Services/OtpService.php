<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Models\OtpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Phone OTP: 6 digits, valid 10 minutes, 5 wrong attempts max, one SMS per 30 seconds
 * and 5 per hour per number. Only a hash of the code is stored.
 */
class OtpService
{
    public const TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_SECONDS = 30;
    public const MAX_PER_HOUR = 5;

    public function __construct(private readonly SmsSender $sms) {}

    public function send(string $phone): void
    {
        $recent = OtpRequest::where('phone', $phone)->where('created_at', '>=', now()->subHour());

        if ((clone $recent)->where('created_at', '>=', now()->subSeconds(self::RESEND_SECONDS))->exists()) {
            throw ValidationException::withMessages(['phone' => __('Please wait a few seconds before asking for another OTP.')]);
        }
        if ($recent->count() >= self::MAX_PER_HOUR) {
            throw ValidationException::withMessages(['phone' => __('Too many OTP requests. Please try again in an hour.')]);
        }

        $code = self::devCode() ?? (string) random_int(100000, 999999);

        OtpRequest::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->sms->sendOtp($phone, $code);
    }

    /** OTP_DEV_CODE (6 digits) outside production, so local logins don't need a real SMS. */
    public static function devCode(): ?string
    {
        $code = (string) config('services.sms.dev_code');

        return ! app()->isProduction() && preg_match('/^\d{6}$/', $code) ? $code : null;
    }

    /** True when the code matches the newest unexpired OTP for this phone. Consumes it on success. */
    public function verify(string $phone, string $code): bool
    {
        $otp = OtpRequest::where('phone', $phone)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $otp || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return false;
        }

        OtpRequest::where('phone', $phone)->delete();

        return true;
    }
}
