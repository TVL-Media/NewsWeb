<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Copies demo photos from storage/app/demo/{name}.jpg onto the media disk.
 * When a file is missing it draws a plain placeholder with GD instead, so seeding never
 * depends on the network. Drop real photos into storage/app/demo/ with the same names to replace them.
 */
class DemoMedia
{
    public static function put(string $name, string $targetDir, int $w = 1200, int $h = 800): string
    {
        $target = trim($targetDir, '/').'/'.$name.'.jpg';
        $source = storage_path('app/demo/'.$name.'.jpg');
        $disk = Storage::disk(config('filesystems.media'));

        $disk->put($target, is_file($source) ? file_get_contents($source) : self::placeholder($name, $w, $h));

        return $target;
    }

    private static function placeholder(string $name, int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        // Dark diagonal gradient seeded by the name, with a soft red glow.
        $seed = crc32($name);
        $base = [30 + $seed % 40, 30 + ($seed >> 8) % 40, 40 + ($seed >> 16) % 50];
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate($img, (int) ($base[0] + 60 * $t), (int) ($base[1] + 20 * $t), (int) ($base[2] + 10 * $t));
            imageline($img, 0, $y, $w, $y, $c);
        }
        $glow = imagecolorallocatealpha($img, 215, 20, 30, 90);
        imagefilledellipse($img, (int) ($w * 0.75), (int) ($h * 0.3), (int) ($w * 0.7), (int) ($h * 0.9), $glow);

        ob_start();
        imagejpeg($img, null, 82);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
