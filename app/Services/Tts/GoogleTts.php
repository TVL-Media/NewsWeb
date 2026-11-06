<?php

namespace App\Services\Tts;

use App\Contracts\TextToSpeech;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Google Cloud Text-to-Speech (REST, API key). Indian English and Hindi voices, MP3 output. */
class GoogleTts implements TextToSpeech
{
    private const VOICES = [
        'en' => ['languageCode' => 'en-IN', 'name' => 'en-IN-Neural2-A'],
        'hi' => ['languageCode' => 'hi-IN', 'name' => 'hi-IN-Neural2-A'],
    ];

    public function __construct(private readonly ?string $key) {}

    public function synthesize(string $text, string $language, string $path): array
    {
        if (! $this->key) {
            throw new RuntimeException('GOOGLE_TTS_KEY is not set.');
        }

        $response = Http::timeout(30)->retry(2, 500)
            ->post('https://texttospeech.googleapis.com/v1/text:synthesize?key='.$this->key, [
                'input' => ['text' => $text],
                'voice' => self::VOICES[$language] ?? self::VOICES['en'],
                'audioConfig' => ['audioEncoding' => 'MP3', 'speakingRate' => 1.0],
            ])->throw();

        $file = $path.'.mp3';
        Storage::disk(config('filesystems.media'))->put($file, base64_decode($response->json('audioContent')));

        // The API doesn't return a duration; estimate it at about 150 words a minute.
        $words = max(1, count(preg_split('/\s+/u', trim($text))));

        return ['path' => $file, 'seconds' => (int) max(1, round($words / 2.5))];
    }
}
