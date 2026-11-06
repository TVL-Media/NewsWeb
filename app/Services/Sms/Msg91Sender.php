<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Http;

/** MSG91 Flow API. The DLT-approved template must contain an ##otp## variable. */
class Msg91Sender implements SmsSender
{
    public function __construct(
        private readonly ?string $authKey,
        private readonly ?string $templateId,
        private readonly ?string $senderId,
    ) {}

    public function sendOtp(string $phone, string $code): void
    {
        Http::timeout(10)->retry(2, 300)
            ->withHeaders(['authkey' => (string) $this->authKey, 'accept' => 'application/json'])
            ->post('https://control.msg91.com/api/v5/flow', [
                'template_id' => $this->templateId,
                'sender' => $this->senderId,
                'short_url' => '0',
                'recipients' => [['mobiles' => '91'.$phone, 'otp' => $code]],
            ])
            ->throw();
    }
}
