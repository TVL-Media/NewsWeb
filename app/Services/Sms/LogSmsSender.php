<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/** Local development: writes the OTP to storage/logs/laravel.log instead of sending an SMS. */
class LogSmsSender implements SmsSender
{
    public function sendOtp(string $phone, string $code): void
    {
        Log::info("[OTP] {$code} for +91 {$phone}");
    }
}
