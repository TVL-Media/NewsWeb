<?php

namespace App\Services\Reels;

use App\Contracts\TextToSpeech;
use App\Models\Reel;
use App\Models\Story;
use App\Models\StoryVersion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Builds reel video files and stores them on the media disk (public locally, S3 + CloudFront in production).
 *
 * Auto reel timeline: headline → key point 1 → 2 → 3 → end card. Each segment is voiced with our TTS;
 * its on-screen text stays up for exactly as long as it is spoken (so the burned-in text doubles as
 * captions), over one continuous slow zoom/pan of the story photo. No music.
 */
class ReelBuilder
{
    private const GAP = 0.35; // breath after each spoken segment, seconds

    public function __construct(
        private readonly Ffmpeg $ffmpeg,
        private readonly ReelSlides $slides,
        private readonly TextToSpeech $tts,
    ) {}

    public static function contentHash(Story $story, StoryVersion $version): string
    {
        return md5(implode('|', [$version->language, $version->headline, json_encode($version->key_points), $story->photo_path]));
    }

    /** @return array{video_720_path: string, video_480_path: string, thumbnail_path: string, duration_seconds: int} */
    public function fromStory(Reel $reel, Story $story, StoryVersion $version): array
    {
        return $this->inWorkDir(function (string $dir) use ($reel, $story, $version) {
            $lang = $version->language;
            $hi = $lang === 'hi';
            $media = $this->disk();

            // 1. Segments and their voice-over
            $category = $story->category ? mb_strtoupper($story->category->name($lang)) : ($hi ? 'ख़बर' : 'NEWS');
            $points = array_values(array_filter((array) $version->key_points));
            $segments = [['kind' => 'headline', 'text' => rtrim($version->headline, '.।'), 'label' => $category]];
            foreach ($points as $i => $point) {
                $segments[] = ['kind' => 'point', 'text' => $point, 'label' => ($hi ? 'मुख्य बात ' : 'KEY POINT ').($i + 1).'/'.count($points)];
            }
            $end = $hi ? 'पूरी ख़बर NextGenNews पर पढ़ें।' : 'Read the full story on NextGenNews.';

            foreach ([...$segments, ['text' => $end]] as $i => $segment) {
                $voice[$i] = $this->voice($segment['text'], $lang, $dir, "seg{$i}");
            }
            $endVoice = array_pop($voice);

            // 2. Fit the 20–45 s window: drop trailing key points if too long, pad the end card if too short.
            $max = (float) min(config('reels.auto_max_seconds'), config('reels.max_seconds'));
            $min = (float) min(config('reels.auto_min_seconds'), $max);
            $endDur = $endVoice['seconds'] + self::GAP;
            $durations = array_map(fn ($v) => $v['seconds'] + self::GAP, $voice);
            while (array_sum($durations) + $endDur > $max && count($durations) > 2) {
                array_pop($durations);
                array_pop($voice);
                array_pop($segments);
            }
            $endDur += max(0, $min - (array_sum($durations) + $endDur));
            $total = min($max, array_sum($durations) + $endDur);

            // 3. Stills
            $photo = $story->photo_path && $media->exists($story->photo_path) ? $media->get($story->photo_path) : null;
            File::put("$dir/bg.jpg", $this->slides->background($photo));
            foreach ($segments as $i => $segment) {
                File::put("$dir/ov{$i}.png", $this->slides->overlay($segment['text'], $lang, $segment['kind'], $segment['label']));
            }
            $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'nextgennews.in';
            File::put("$dir/end.png", $this->slides->endCard($lang, $host));
            File::put("$dir/thumb.jpg", $this->slides->thumbnail($photo, File::get("$dir/ov0.png")));

            // 4. One FFmpeg pass: zoom/pan + overlays + end card + voice-over → 720p and 480p MP4s.
            $this->ffmpeg->run($this->storyArgs($dir, $segments, $durations, $voice, $endVoice, $endDur, $total));

            return $this->store($reel, $dir, $total);
        });
    }

    /** @return array{video_720_path: string, video_480_path: string, thumbnail_path: string, duration_seconds: int} */
    public function fromUpload(Reel $reel): array
    {
        return $this->inWorkDir(function (string $dir) use ($reel) {
            $media = $this->disk();
            if (! $reel->source_path || ! $media->exists($reel->source_path)) {
                throw new RuntimeException('The uploaded video file is missing.');
            }

            $input = "$dir/input.".(pathinfo($reel->source_path, PATHINFO_EXTENSION) ?: 'mp4');
            $this->download($reel->source_path, $input);

            $duration = $this->ffmpeg->duration($input);
            $max = (int) config('reels.max_seconds');
            if ($duration > $max + 0.5) {
                throw new RuntimeException(sprintf('The video is %d seconds long; reels can be at most %d seconds.', round($duration), $max));
            }
            if ($duration < 3) {
                throw new RuntimeException('The video is shorter than 3 seconds.');
            }

            $fps = config('reels.fps');
            $args = ['-i', $input];
            $audio = '0:a:0';
            if (! $this->ffmpeg->hasAudio($input)) {
                $args = [...$args, '-f', 'lavfi', '-t', (string) $duration, '-i', 'anullsrc=r=44100:cl=stereo'];
                $audio = '1:a:0';
            }

            // Fill 9:16: the video fitted in the middle over a blurred, cropped copy of itself
            // (landscape agency footage keeps its full frame instead of being cropped hard).
            $filter = '[0:v]fps='.$fps.',split=2[s1][s2];'
                .'[s1]scale=720:1280:force_original_aspect_ratio=increase,crop=720:1280,boxblur=24:2[bg];'
                .'[s2]scale=720:1280:force_original_aspect_ratio=decrease[fg];'
                .'[bg][fg]overlay=(W-w)/2:(H-h)/2,setsar=1,format=yuv420p,split=2[v720][vx];'
                .'[vx]scale=480:854[v480]';

            $this->ffmpeg->run([...$args, '-filter_complex', $filter, ...$this->outputs($dir, $audio, $audio)]);
            $this->ffmpeg->run(['-ss', (string) min(1.0, $duration / 3), '-i', "$dir/720.mp4", '-frames:v', '1', '-q:v', '3', "$dir/thumb.jpg"]);

            return $this->store($reel, $dir, $this->ffmpeg->duration("$dir/720.mp4"));
        });
    }

    /** Remove files left over from a previous build (each build writes new names). */
    public function deleteFiles(array $paths): void
    {
        if ($paths = array_values(array_filter($paths))) {
            $this->disk()->delete($paths);
        }
    }

    private function storyArgs(string $dir, array $segments, array $durations, array $voice, array $endVoice, float $endDur, float $total): array
    {
        $fps = config('reels.fps');
        $content = array_sum($durations);
        $frames = max(1, (int) round($content * $fps));
        $count = count($segments);

        $args = ['-i', "$dir/bg.jpg"];
        foreach ($segments as $i => $_) {
            $args = [...$args, '-i', "$dir/ov{$i}.png"];
        }
        $args = [...$args, '-loop', '1', '-framerate', (string) $fps, '-t', sprintf('%.3f', $endDur), '-i', "$dir/end.png"];
        foreach ([...$voice, $endVoice] as $v) {
            $args = [...$args, '-i', $v['file']];
        }

        // Ken Burns: zoom 1.00 → 1.15 while drifting from the centre a little to the right.
        $f = sprintf("[0:v]zoompan=z='1+0.15*on/%d':x='(iw-iw/zoom)*(0.5+0.3*on/%d)':y='(ih-ih/zoom)*0.38':d=%d:s=720x1280:fps=%d,setsar=1[k0];",
            $frames, $frames, $frames, $fps);

        $t = 0.0;
        foreach ($durations as $i => $d) {
            $f .= sprintf("[k%d][%d:v]overlay=0:0:enable='gte(t,%.3f)*lt(t,%.3f)'[k%d];", $i, $i + 1, $t, $t + $d, $i + 1);
            $t += $d;
        }
        $f .= sprintf('[k%d]format=yuv420p[content];', $count);
        $f .= sprintf('[%d:v]scale=720:1280,setsar=1,fps=%d,format=yuv420p,fade=t=in:st=0:d=0.3[end];', $count + 1, $fps);
        $f .= '[content][end]concat=n=2:v=1:a=0,split=2[v720][vx];[vx]scale=480:854[v480];';

        // Each voice clip padded to its slot so sound and text stay in step.
        $firstAudio = $count + 2;
        $slots = [...$durations, $endDur];
        foreach ($slots as $i => $d) {
            $f .= sprintf('[%d:a]aresample=44100,aformat=channel_layouts=stereo,apad=whole_dur=%.3f[a%d];', $firstAudio + $i, $d, $i);
        }
        $f .= implode('', array_map(fn ($i) => "[a{$i}]", array_keys($slots))).'concat=n='.count($slots).':v=0:a=1,asplit=2[a720][a480]';

        return [...$args, '-filter_complex', $f, ...$this->outputs($dir, '[a720]', '[a480]', $total)];
    }

    /** Output options for both renditions (H.264 + AAC, +faststart). */
    private function outputs(string $dir, string $audio720, string $audio480, ?float $seconds = null): array
    {
        $args = [];
        foreach (config('reels.renditions') as $name => $r) {
            $audio = (string) $name === '480' ? $audio480 : $audio720; // numeric config keys arrive as ints
            $v = $r['video_kbps'];
            $args = [...$args,
                '-map', "[v{$name}]", '-map', $audio,
                ...($seconds ? ['-t', sprintf('%.3f', $seconds)] : []),
                '-c:v', 'libx264', '-preset', 'veryfast', '-profile:v', 'main', '-pix_fmt', 'yuv420p',
                '-b:v', "{$v}k", '-maxrate', (int) ($v * 1.25).'k', '-bufsize', ($v * 2).'k',
                '-r', (string) config('reels.fps'), '-g', (string) (config('reels.fps') * 2),
                '-c:a', 'aac', '-b:a', $r['audio_kbps'].'k', '-ar', '44100',
                '-movflags', '+faststart',
                "$dir/{$name}.mp4",
            ];
        }

        return $args;
    }

    /** TTS for one segment, copied to the work dir; returns the local file and its exact length. */
    private function voice(string $text, string $lang, string $dir, string $name): array
    {
        $remote = 'reels/tmp/'.basename($dir)."/{$name}";
        $audio = $this->tts->synthesize($text, $lang, $remote);

        $local = "$dir/{$name}.".pathinfo($audio['path'], PATHINFO_EXTENSION);
        $this->download($audio['path'], $local);
        $this->disk()->delete($audio['path']);

        return ['file' => $local, 'seconds' => $this->ffmpeg->duration($local)];
    }

    private function store(Reel $reel, string $dir, float $duration): array
    {
        // A fresh name per build so CloudFront never serves an old file after a re-generate.
        $prefix = $reel->mediaDir().'/'.Str::lower(Str::random(8));
        $media = $this->disk();

        foreach (['720.mp4' => 'video_720_path', '480.mp4' => 'video_480_path', 'thumb.jpg' => 'thumbnail_path'] as $file => $column) {
            if (! File::exists("$dir/$file") || File::size("$dir/$file") === 0) {
                throw new RuntimeException("FFmpeg did not produce {$file}.");
            }
            $stream = fopen("$dir/$file", 'r');
            $media->put($paths[$column] = "{$prefix}-{$file}", $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return [...$paths, 'duration_seconds' => (int) round($duration)];
    }

    private function download(string $path, string $local): void
    {
        $in = $this->disk()->readStream($path);
        $out = fopen($local, 'w');
        stream_copy_to_stream($in, $out);
        fclose($out);
        if (is_resource($in)) {
            fclose($in);
        }
    }

    private function inWorkDir(callable $callback): array
    {
        $dir = storage_path('app/reel-work/'.Str::uuid());
        File::ensureDirectoryExists($dir);

        try {
            return $callback($dir);
        } finally {
            File::deleteDirectory($dir);
            $this->disk()->deleteDirectory('reels/tmp/'.basename($dir));
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.media'));
    }
}
