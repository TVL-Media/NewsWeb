<?php

namespace App\Jobs;

use App\Models\Story;
use App\Services\StoryAudio;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** On publish: Listen audio for both language versions (FakeTts locally, Google TTS in production). */
class GenerateAudio implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $storyId) {}

    public function handle(StoryAudio $audio): void
    {
        $story = Story::with('versions')->find($this->storyId);

        foreach ($story?->versions ?? [] as $version) {
            $audio->generate($version);
        }
    }
}
