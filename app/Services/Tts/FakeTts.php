<?php

namespace App\Services\Tts;

use App\Contracts\TextToSpeech;
use Illuminate\Support\Facades\Storage;

/**
 * Local/testing TTS: writes a small WAV of soft tone pulses whose length matches how long
 * the text would take to read aloud (about 150 words a minute). No network, no keys.
 */
class FakeTts implements TextToSpeech
{
    private const RATE = 8000; // Hz, 8-bit mono keeps files small

    public function synthesize(string $text, string $language, string $path): array
    {
        $words = max(1, count(preg_split('/\s+/u', trim($text))));
        $seconds = (int) max(5, min(120, round($words / 2.5)));

        $file = $path.'.wav';
        Storage::disk(config('filesystems.media'))->put($file, $this->wav($seconds, $language === 'hi' ? 392.0 : 440.0));

        return ['path' => $file, 'seconds' => $seconds];
    }

    private function wav(int $seconds, float $freq): string
    {
        // One second = a quiet 0.25s tone then silence; repeated for the full length.
        $second = '';
        for ($i = 0; $i < self::RATE; $i++) {
            $t = $i / self::RATE;
            $second .= chr((int) (128 + ($t < 0.25 ? sin(2 * M_PI * $freq * $t) * 18 : 0)));
        }
        $samples = str_repeat($second, $seconds);

        $size = strlen($samples);

        return 'RIFF'.pack('V', 36 + $size).'WAVE'
            .'fmt '.pack('VvvVVvv', 16, 1, 1, self::RATE, self::RATE, 1, 8)
            .'data'.pack('V', $size).$samples;
    }
}
