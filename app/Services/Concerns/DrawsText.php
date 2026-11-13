<?php

namespace App\Services\Concerns;

/** Text helpers shared by the server-rendered images (share cards, reel slides). */
trait DrawsText
{
    /** Greedy word wrap using real glyph widths (GD's FreeType metrics). */
    private function wrap(string $text, string $font, int $size, int $width): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', trim($text)) as $word) {
            $try = $line === '' ? $word : "$line $word";
            $box = imagettfbbox($size * 0.75, 0, $font, $try); // points → px, matching Intervention's GD text size
            if ($line !== '' && ($box[2] - $box[0]) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function textWidth(string $text, string $font, int $size): int
    {
        $box = imagettfbbox($size * 0.75, 0, $font, $text);

        return $box[2] - $box[0];
    }

    /**
     * GD draws code points in logical order without Indic shaping, so the short-i sign (ि, U+093F)
     * would appear after its consonant. Move it before the consonant cluster it belongs to.
     */
    private function reorderMatras(string $text): string
    {
        $c = '[\x{0915}-\x{0939}\x{0958}-\x{095F}]\x{093C}?';

        return preg_replace("/((?:{$c}\x{094D})*{$c})\x{093F}/u", "\u{093F}$1", $text);
    }
}
