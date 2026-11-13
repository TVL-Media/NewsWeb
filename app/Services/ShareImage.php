<?php

namespace App\Services;

use App\Models\Story;
use App\Services\Concerns\DrawsText;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * Server-generated 1080×1350 share card (portrait, sized for WhatsApp): photo on top,
 * category tag, headline and the 60-word card text, NextGenNews logo and the story link.
 * Cached per story + language on the media disk; regenerated when the story changes.
 *
 * Hindi: GD has no complex-text shaping, so Devanagari conjuncts can render imperfectly.
 * Set IMAGE_DRIVER=imagick in production (ImageMagick built with libraqm) for correct Hindi.
 */
class ShareImage
{
    use DrawsText;

    private const W = 1080;
    private const H = 1350;

    public function urlFor(Story $story, string $lang): string
    {
        $story->loadMissing(['versions', 'category']);
        $version = $story->version($lang);
        $path = "shares/{$story->id}-{$version->language}-{$story->updated_at->timestamp}.jpg";
        $disk = Storage::disk(config('filesystems.media'));

        if (! $disk->exists($path)) {
            $disk->put($path, $this->render($story, $version->language));
        }

        return $disk->url($path);
    }

    public function render(Story $story, string $lang): string
    {
        $v = $story->version($lang);
        $hi = $v->language === 'hi';
        $fonts = resource_path('fonts');
        $bold = $hi ? "$fonts/NotoSansDevanagari-Bold.ttf" : "$fonts/Inter-Black.ttf";
        $body = $hi ? "$fonts/NotoSansDevanagari-Medium.ttf" : "$fonts/Inter-Regular.ttf";

        $manager = new ImageManager(config('services.image.driver') === 'imagick' ? new ImagickDriver : new GdDriver);
        $img = $manager->createImage(self::W, self::H)->fill('#FFFFFF');

        // Photo (top 600px)
        $photo = $story->photo_path ? Storage::disk(config('filesystems.media'))->get($story->photo_path) : null;
        if ($photo) {
            $img->insert($manager->decodeBinary($photo)->cover(self::W, 600), 0, 0);
        } else {
            $img->drawRectangle(fn ($r) => $r->at(0, 0)->size(self::W, 600)->background('#111111'));
        }

        // Red band with the logo mark and wordmark
        $img->drawRectangle(fn ($r) => $r->at(0, 560)->size(self::W, 80)->background('#D7141E'));
        $img->drawPolygon(function ($p) {
            // logo.svg path (28×24 viewBox), scaled ×2 and placed in the band
            foreach ([[2, 22], [8, 2], [13, 2], [18, 15], [22, 2], [27, 2], [21, 22], [16, 22], [11, 9], [7, 22]] as [$x, $y]) {
                $p->point(66 + $x * 2, 576 + $y * 2);
            }
            $p->background('#FFFFFF');
        });
        $img->text('NextGenNews', 136, 600, fn ($f) => $f->filename("$fonts/Inter-Black.ttf")->size(36)->color('#FFFFFF')->align('left', 'center'));

        $gd = config('services.image.driver') !== 'imagick';
        $prep = fn (string $t) => $hi && $gd ? $this->reorderMatras($t) : $t;

        // Tag, headline, card text — wrapped by measuring each word, drawn line by line.
        $y = 690;
        $tag = mb_strtoupper((string) $story->category?->name($v->language));
        $img->text($prep($tag), 70, $y, fn ($f) => $f->filename($hi ? $bold : "$fonts/Inter-Bold.ttf")->size(28)->color('#A50E16')->align('left', 'top'));
        $y += 62;

        foreach ($this->wrap($prep($v->headline), $bold, 50, 940) as $line) {
            $img->text($line, 70, $y, fn ($f) => $f->filename($bold)->size(50)->color('#111111')->align('left', 'top'));
            $y += $hi ? 78 : 64;
        }
        $y += 26;

        foreach ($this->wrap($prep($v->card_text), $body, 30, 940) as $line) {
            if ($y > self::H - 150) {
                break;
            }
            $img->text($line, 70, $y, fn ($f) => $f->filename($body)->size(30)->color('#3A3A3A')->align('left', 'top'));
            $y += $hi ? 56 : 46;
        }

        // Footer
        $img->drawRectangle(fn ($r) => $r->at(0, self::H - 90)->size(self::W, 90)->background('#F7F7F8'));
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'nextgennews.in';
        // The Devanagari font has no Latin letters, so the host name is always drawn in Inter.
        $lead = $hi ? $prep('पूरी ख़बर पढ़ें') : 'Read the full story';
        $leadFont = $hi ? "$fonts/NotoSansDevanagari-Medium.ttf" : "$fonts/Inter-Bold.ttf";
        $img->text($lead, 70, self::H - 45, fn ($f) => $f->filename($leadFont)->size(26)->color('#6B6B6B')->align('left', 'center'));
        $box = imagettfbbox(26 * 0.75, 0, $leadFont, $lead);
        $img->text('·  '.$host, 70 + ($box[2] - $box[0]) + 14, self::H - 45,
            fn ($f) => $f->filename("$fonts/Inter-Bold.ttf")->size(26)->color('#6B6B6B')->align('left', 'center'));

        return (string) $img->encodeUsingFormat(\Intervention\Image\Format::JPEG, quality: 85);
    }

    /** Daily quiz result card (1080×1350): score, streak and date on the Morning 5 red gradient. */
    public function quizResultUrl(\App\Models\QuizAttempt $attempt, int $total, int $streak): string
    {
        $path = "shares/quiz-{$attempt->id}-{$attempt->score}-{$streak}.jpg";
        $disk = Storage::disk(config('filesystems.media'));

        if (! $disk->exists($path)) {
            $disk->put($path, $this->renderQuizResult($attempt->score, $total, $streak, $attempt->completed_at ?? now()));
        }

        return $disk->url($path);
    }

    public function renderQuizResult(int $score, int $total, int $streak, \DateTimeInterface $date): string
    {
        $fonts = resource_path('fonts');
        $black = "$fonts/Inter-Black.ttf";
        $bold = "$fonts/Inter-Bold.ttf";
        $manager = new ImageManager(config('services.image.driver') === 'imagick' ? new ImagickDriver : new GdDriver);
        $img = $manager->createImage(self::W, self::H)->fill('#8E0E16');

        // Radial-ish glow: concentric ellipses from the brand glow colour.
        foreach (range(10, 1) as $i) {
            $img->drawEllipse(fn ($e) => $e->at(540, 420)->size(140 * $i, 120 * $i)->background(sprintf('rgba(199, 32, 43, %.2f)', 0.10)));
        }

        // Tilted-tile stand-in: white rounded square with the red N
        $img->drawRectangle(fn ($r) => $r->at(430, 170)->size(220, 220)->background('#FFFFFF'));
        $img->drawPolygon(function ($p) {
            // logo.svg path (viewBox 28×24) at ×6, centred in the 220px tile
            foreach ([[2, 22], [8, 2], [13, 2], [18, 15], [22, 2], [27, 2], [21, 22], [16, 22], [11, 9], [7, 22]] as [$x, $y]) {
                $p->point(450 + $x * 6, 208 + $y * 6);
            }
            $p->background('#D7141E');
        });

        $center = fn (string $text, int $y, string $font, int $size, string $color) => $img->text($text, 540, $y,
            fn ($f) => $f->filename($font)->size($size)->color($color)->align('center', 'top'));

        $center('DAILY QUIZ', 470, $bold, 34, '#F4C6C9');
        $center("{$score}/{$total}", 530, $black, 200, '#FFFFFF');
        $center($score === $total ? 'Perfect score!' : ($score >= $total - 1 ? 'So close!' : 'Nice try!'), 790, $black, 56, '#FFFFFF');
        if ($streak > 0) {
            $center("{$streak}-day streak", 880, $bold, 40, '#F4C6C9');
        }
        $center($date->format('l, j F Y'), 960, $bold, 32, '#F4C6C9');

        $img->drawRectangle(fn ($r) => $r->at(0, self::H - 140)->size(self::W, 140)->background('#3A0508'));
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'nextgennews.in';
        $center("Can you beat me? Play at {$host}", self::H - 92, $bold, 32, '#FFFFFF');

        return (string) $img->encodeUsingFormat(\Intervention\Image\Format::JPEG, quality: 88);
    }
}
