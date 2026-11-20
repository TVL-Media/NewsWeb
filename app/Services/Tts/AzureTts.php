<?php

namespace App\Services\Tts;

use App\Contracts\TextToSpeech;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Azure AI Speech text-to-speech (REST, resource key + region). Indian English and Hindi neural voices, MP3. */
class AzureTts implements TextToSpeech
{
    private const VOICES = [
        'en' => ['en-IN', 'en-IN-NeerjaNeural'],
        'hi' => ['hi-IN', 'hi-IN-SwaraNeural'],
    ];

    public function __construct(private readonly ?string $key, private readonly ?string $region) {}

    public function synthesize(string $text, string $language, string $path): array
    {
        if (! $this->key || ! $this->region) {
            throw new RuntimeException('The Azure Speech key or region is not set (Admin → Settings → Text-to-speech).');
        }

        [$lang, $voice] = self::VOICES[$language] ?? self::VOICES['en'];
        $ssml = sprintf('<speak version="1.0" xml:lang="%s"><voice name="%s">%s</voice></speak>',
            $lang, $voice, htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8'));

        $response = Http::timeout(30)->retry(2, 500)
            ->withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->key,
                'X-Microsoft-OutputFormat' => 'audio-24khz-48kbitrate-mono-mp3',
                'User-Agent' => 'news-tts',
            ])
            ->withBody($ssml, 'application/ssml+xml')
            ->post('https://'.$this->region.'.tts.speech.microsoft.com/cognitiveservices/v1')
            ->throw();

        $file = $path.'.mp3';
        Storage::disk(config('filesystems.media'))->put($file, $response->body());

        // The API doesn't return a duration; estimate it at about 150 words a minute.
        $words = max(1, count(preg_split('/\s+/u', trim($text))));

        return ['path' => $file, 'seconds' => (int) max(1, round($words / 2.5))];
    }
}
