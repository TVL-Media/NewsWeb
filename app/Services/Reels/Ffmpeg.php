<?php

namespace App\Services\Reels;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/** Thin wrapper around the ffmpeg / ffprobe binaries (paths from config/reels.php). */
class Ffmpeg
{
    public function binary(): string
    {
        return (string) config('reels.ffmpeg');
    }

    public function probeBinary(): string
    {
        if ($probe = config('reels.ffprobe')) {
            return $probe;
        }

        // ffprobe ships next to ffmpeg in every distribution (…/bin/ffmpeg(.exe) → …/bin/ffprobe(.exe)).
        $bin = $this->binary();
        $dir = dirname($bin);
        $name = preg_replace('/ffmpeg(\.exe)?$/i', 'ffprobe$1', basename($bin));

        return $dir === '.' ? $name : $dir.DIRECTORY_SEPARATOR.$name;
    }

    public function available(): bool
    {
        try {
            return Process::timeout(15)->run([$this->binary(), '-version'])->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param  list<string>  $args  arguments after the binary name */
    public function run(array $args): void
    {
        $result = Process::timeout(config('reels.timeout'))->run([$this->binary(), '-hide_banner', '-loglevel', 'error', '-y', ...$args]);

        if (! $result->successful()) {
            throw new RuntimeException('FFmpeg failed: '.$this->lastLines($result->errorOutput() ?: $result->output()));
        }
    }

    /** Duration of a media file in seconds. */
    public function duration(string $file): float
    {
        $out = $this->probe(['-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $file]);
        $seconds = (float) trim($out);

        if ($seconds <= 0) {
            throw new RuntimeException('Could not read the duration of '.basename($file).'.');
        }

        return $seconds;
    }

    public function hasAudio(string $file): bool
    {
        return trim($this->probe(['-select_streams', 'a', '-show_entries', 'stream=index', '-of', 'csv=p=0', $file])) !== '';
    }

    private function probe(array $args): string
    {
        $result = Process::timeout(60)->run([$this->probeBinary(), '-v', 'error', ...$args]);

        if (! $result->successful()) {
            throw new RuntimeException('FFprobe failed: '.$this->lastLines($result->errorOutput()));
        }

        return $result->output();
    }

    private function lastLines(string $text, int $lines = 6): string
    {
        $all = array_filter(array_map('trim', preg_split('/\R/', trim($text))));

        return mb_strimwidth(implode(' | ', array_slice($all, -$lines)), 0, 1000, '…') ?: 'no output';
    }
}
