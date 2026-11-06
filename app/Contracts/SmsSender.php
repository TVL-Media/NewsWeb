<?php

namespace App\Contracts;

interface SmsSender
{
    /** Send a login OTP to an Indian mobile number (10 digits, no country code). */
    public function sendOtp(string $phone, string $code): void;
}
