<?php

namespace App\Services\Reels;

use App\Services\Concerns\DrawsText;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Still images for an auto reel, drawn at 720×1280:
 *  - background: the story photo, cropped to 9:16 at 2× so FFmpeg can zoom/pan smoothly
 *  - one transparent overlay per spoken segment: logo, dark fade, red accent bar, big white text
 *  - the end card: "Read the full story on NextGenNews"
 * Text is drawn here (not with FFmpeg drawtext) so fonts behave the same on Windows and Linux.
 */
class ReelSlides
{
    use DrawsText;

    public const W = 720;
    public const H = 1280;

    // Phones taller than 9:16 crop ~6% off each side with object-fit: cover, so text stays inside this margin.
    private const SAFE_X = 58;

    // The player's like/comment/share column covers the right edge on phones; text stops before it.
    private const ACTIONS_W = 140;

    private const RED = '#D7141E';
    private const LOGO = [[2, 22], [8, 2], [13, 2], [18, 15], [22, 2], [27, 2], [21, 22], [16, 22], [11, 9], [7, 22]];

    private ImageManager $manager;

    private bool $gd;

    public function __construct()
    {
        $this->gd = config('services.image.driver') !== 'imagick';
        $this->manager = new ImageManager($this->gd ? new GdDriver : new ImagickDriver);
    }

    /** 1440×2560 JPEG of the photo (or a dark brand gradient when there is none). */
    public function background(?string $photo): string
    {
        $w = self::W * 2;
        $h = self::H * 2;

        if ($photo) {
            $img = $this->manager->decodeBinary($photo)->cover($w, $h);
        } else {
            $img = $this->manager->createImage($w, $h)->fill('#1A0204');
            foreach (range(12, 1) as $i) {
                $img->drawEllipse(fn ($e) => $e->at($w / 2, (int) ($h * 0.38))->size(220 * $i, 200 * $i)->background('rgba(199, 32, 43, 0.08)'));
            }
        }

        return (string) $img->encodeUsingFormat(Format::JPEG, quality: 88);
    }

    /**
     * Transparent PNG laid over the moving photo while one segment is spoken.
     *
     * @param  string  $kind  headline|point
     */
    public function overlay(string $text, string $lang, string $kind, string $label): string
    {
        $hi = $lang === 'hi';
        $fonts = resource_path('fonts');
        $img = $this->manager->createImage(self::W, self::H);

        // Fades: a light one under the logo, a deep one under the text.
        $this->fade($img, 0, 230, 0.55, 0.0);
        $this->fade($img, 380, self::H, 0.0, 0.9);

        $this->logo($img, self::SAFE_X, 52, 1.5, light: true);

        // Text block: label pill, then the text beside a red accent bar; bottom edge at ~y=990
        // so the player's own controls (Milestone 3) have the lower part of the frame.
        $font = $hi ? "$fonts/NotoSansDevanagari-Bold.ttf" : ($kind === 'headline' ? "$fonts/Inter-Black.ttf" : "$fonts/Inter-Bold.ttf");
        [$size, $lineHeight, $lines] = $this->fit($this->prep($text, $hi), $font, $kind === 'headline' ? 50 : 42, $hi, self::W - self::SAFE_X - 30 - self::ACTIONS_W, 8);

        $blockH = count($lines) * $lineHeight;
        $top = 990 - $blockH;

        $labelFont = $hi && preg_match('/\p{Devanagari}/u', $label) ? "$fonts/NotoSansDevanagari-Bold.ttf" : "$fonts/Inter-Bold.ttf";
        $this->pill($img, self::SAFE_X, $top - 74, $this->prep($label, $hi), $labelFont);

        $img->drawRectangle(fn ($r) => $r->at(self::SAFE_X, $top + 6)->size(8, $blockH - 14)->background(self::RED));

        $y = $top;
        foreach ($lines as $line) {
            $img->text($line, self::SAFE_X + 30, $y, fn ($f) => $f->filename($font)->size($size)->color('#FFFFFF')->align('left', 'top'));
            $y += $lineHeight;
        }

        return (string) $img->encodeUsingFormat(Format::PNG);
    }

    /** Opaque closing card. */
    public function endCard(string $lang, string $host): string
    {
        $hi = $lang === 'hi';
        $fonts = resource_path('fonts');
        $img = $this->manager->createImage(self::W, self::H)->fill('#111111');

        foreach (range(30, 1) as $i) {
            $img->drawEllipse(fn ($e) => $e->at(self::W / 2, 520)->size(34 * $i, 34 * $i)->background('rgba(199, 32, 43, 0.022)'));
        }

        // White tile with the red N
        $img->drawRectangle(fn ($r) => $r->at(self::W / 2 - 80, 360)->size(160, 160)->background('#FFFFFF'));
        $img->drawPolygon(function ($p) {
            foreach (self::LOGO as [$x, $y]) {
                $p->point((int) (self::W / 2 - 70 + $x * 5), 380 + $y * 5);
            }
            $p->background(self::RED);
        });

        $center = fn (string $text, int $y, string $font, int $size, string $color) => $img->text($text, self::W / 2, $y,
            fn ($f) => $f->filename($font)->size($size)->color($color)->align('center', 'top'));

        if ($hi) {
            $center($this->prep('पूरी ख़बर पढ़ें', true), 590, "$fonts/NotoSansDevanagari-Bold.ttf", 46, '#F4C6C9');
        } else {
            $center('Read the full story on', 596, "$fonts/Inter-Bold.ttf", 38, '#F4C6C9');
        }
        $center('NextGenNews', 668, "$fonts/Inter-Black.ttf", 70, '#FFFFFF');
        $img->drawRectangle(fn ($r) => $r->at(self::W / 2 - 36, 776)->size(72, 8)->background(self::RED));
        $center($host, 812, "$fonts/Inter-Bold.ttf", 30, '#BDBDBD');

        return (string) $img->encodeUsingFormat(Format::PNG);
    }

    /** First frame as a JPEG thumbnail: photo cropped to 9:16 with the headline overlay on top. */
    public function thumbnail(?string $photo, string $overlayPng): string
    {
        $img = $photo
            ? $this->manager->decodeBinary($photo)->cover(self::W, self::H)
            : $this->manager->decodeBinary($this->background(null))->resize(self::W, self::H);

        $img->insert($this->manager->decodeBinary($overlayPng), 0, 0);

        return (string) $img->encodeUsingFormat(Format::JPEG, quality: 85);
    }

    /** Biggest font size (stepping down) at which the text fits in $maxLines. */
    private function fit(string $text, string $font, int $size, bool $hi, int $width, int $maxLines): array
    {
        for (; $size > 28; $size -= 4) {
            $lines = $this->wrap($text, $font, $size, $width);
            if (count($lines) <= $maxLines) {
                break;
            }
        }
        $lines = array_slice($lines, 0, $maxLines);

        return [$size, (int) round($size * ($hi ? 1.45 : 1.2)), $lines];
    }

    private function fade(ImageInterface $img, int $from, int $to, float $alphaFrom, float $alphaTo): void
    {
        $steps = (int) ceil(($to - $from) / 4);
        for ($i = 0; $i < $steps; $i++) {
            $t = $steps > 1 ? $i / ($steps - 1) : 1;
            $eased = $alphaFrom + ($alphaTo - $alphaFrom) * ($alphaTo > $alphaFrom ? $t * $t : 1 - (1 - $t) * (1 - $t));
            if ($eased <= 0.004) {
                continue;
            }
            $img->drawRectangle(fn ($r) => $r->at(0, $from + $i * 4)->size(self::W, 4)->background(sprintf('rgba(0, 0, 0, %.3f)', $eased)));
        }
    }

    private function logo(ImageInterface $img, int $x, int $y, float $scale, bool $light): void
    {
        $img->drawPolygon(function ($p) use ($x, $y, $scale) {
            foreach (self::LOGO as [$px, $py]) {
                $p->point((int) round($x + $px * $scale), (int) round($y + $py * $scale));
            }
            $p->background(self::RED);
        });
        $img->text('NextGenNews', (int) ($x + 30 * $scale + 8), (int) ($y + 12 * $scale), fn ($f) => $f
            ->filename(resource_path('fonts/Inter-Black.ttf'))->size(30)->color($light ? '#FFFFFF' : '#111111')->align('left', 'center'));
    }

    /** Red rounded label ("WORLD", "KEY POINT 1/3"). */
    private function pill(ImageInterface $img, int $x, int $y, string $text, string $font): void
    {
        $size = 24;
        $h = 46;
        $w = $this->textWidth($text, $font, $size) + 40;

        $img->drawRectangle(fn ($r) => $r->at($x + $h / 2, $y)->size($w - $h, $h)->background(self::RED));
        $img->drawEllipse(fn ($e) => $e->at($x + $h / 2, $y + $h / 2)->size($h, $h)->background(self::RED));
        $img->drawEllipse(fn ($e) => $e->at($x + $w - $h / 2, $y + $h / 2)->size($h, $h)->background(self::RED));
        $img->text($text, $x + 20, $y + $h / 2, fn ($f) => $f->filename($font)->size($size)->color('#FFFFFF')->align('left', 'center'));
    }

    private function prep(string $text, bool $hi): string
    {
        return $hi && $this->gd ? $this->reorderMatras($text) : $text;
    }
}
